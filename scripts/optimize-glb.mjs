import fs from 'fs';
import { NodeIO } from '@gltf-transform/core';
import { ALL_EXTENSIONS } from '@gltf-transform/extensions';
import { dedup, weld, simplify, prune } from '@gltf-transform/functions';
import { MeshoptSimplifier } from 'meshoptimizer';
import sharp from 'sharp';

/**
 * Strips proprietary/non-standard extensions (like FB_ngon_encoding from Tripo AI)
 * from a GLB buffer so @gltf-transform/core can parse any generator's output cleanly.
 */
function sanitizeGlbBuffer(buffer) {
    const magic = buffer.toString('ascii', 0, 4);
    if (magic !== 'glTF') {
        throw new Error('Ungültige GLB-Datei (Magic Header fehlt).');
    }
    const version = buffer.readUInt32LE(4);
    const jsonChunkLen = buffer.readUInt32LE(12);
    const jsonChunkType = buffer.toString('ascii', 16, 20);
    if (jsonChunkType !== 'JSON') {
        return new Uint8Array(buffer);
    }

    const jsonStr = buffer.toString('utf8', 20, 20 + jsonChunkLen);
    const json = JSON.parse(jsonStr);

    const unsupportedExts = ['FB_ngon_encoding'];
    let modified = false;

    if (Array.isArray(json.extensionsUsed)) {
        const before = json.extensionsUsed.length;
        json.extensionsUsed = json.extensionsUsed.filter(e => !unsupportedExts.includes(e));
        if (json.extensionsUsed.length !== before) modified = true;
    }
    if (Array.isArray(json.extensionsRequired)) {
        const before = json.extensionsRequired.length;
        json.extensionsRequired = json.extensionsRequired.filter(e => !unsupportedExts.includes(e));
        if (json.extensionsRequired.length !== before) modified = true;
    }
    if (Array.isArray(json.meshes)) {
        for (const m of json.meshes) {
            if (m.extensions) {
                for (const ext of unsupportedExts) {
                    if (ext in m.extensions) {
                        delete m.extensions[ext];
                        modified = true;
                    }
                }
            }
            if (Array.isArray(m.primitives)) {
                for (const p of m.primitives) {
                    if (p.extensions) {
                        for (const ext of unsupportedExts) {
                            if (ext in p.extensions) {
                                delete p.extensions[ext];
                                modified = true;
                            }
                        }
                    }
                }
            }
        }
    }

    if (!modified) {
        return new Uint8Array(buffer);
    }

    let newJsonBuf = Buffer.from(JSON.stringify(json), 'utf8');
    const pad = (4 - (newJsonBuf.length % 4)) % 4;
    if (pad > 0) {
        newJsonBuf = Buffer.concat([newJsonBuf, Buffer.alloc(pad, 0x20)]);
    }

    const restBuf = buffer.subarray(20 + jsonChunkLen);
    const totalLen = 12 + 8 + newJsonBuf.length + restBuf.length;
    const out = Buffer.alloc(totalLen);
    out.write('glTF', 0, 4, 'ascii');
    out.writeUInt32LE(version, 4);
    out.writeUInt32LE(totalLen, 8);
    out.writeUInt32LE(newJsonBuf.length, 12);
    out.write('JSON', 16, 4, 'ascii');
    newJsonBuf.copy(out, 20);
    restBuf.copy(out, 20 + newJsonBuf.length);

    return new Uint8Array(out);
}

function countStats(doc) {
    let vertices = 0;
    let triangles = 0;
    for (const mesh of doc.getRoot().listMeshes()) {
        for (const prim of mesh.listPrimitives()) {
            const pos = prim.getAttribute('POSITION');
            if (pos) vertices += pos.getCount();
            const ind = prim.getIndices();
            if (ind) triangles += Math.round(ind.getCount() / 3);
            else if (pos) triangles += Math.round(pos.getCount() / 3);
        }
    }
    return { vertices, triangles };
}

async function run() {
    const args = process.argv.slice(2);
    const inputPath = args[0];
    const outputPath = args[1] || inputPath;
    const targetMaxTris = parseInt(args[2] || '55000', 10);
    const maxTexSize = parseInt(args[3] || '2048', 10);

    if (!inputPath || !fs.existsSync(inputPath)) {
        console.error(JSON.stringify({ success: false, error: 'Eingabedatei nicht gefunden: ' + inputPath }));
        process.exit(1);
    }

    await MeshoptSimplifier.ready;

    const rawBuf = fs.readFileSync(inputPath);
    const originalSizeBytes = rawBuf.length;
    const sanitized = sanitizeGlbBuffer(rawBuf);

    const io = new NodeIO().registerExtensions(ALL_EXTENSIONS);
    const doc = await io.readBinary(sanitized);

    const beforeStats = countStats(doc);

    // 1. Optimize oversized embedded textures (> maxTexSize or bulky PNGs)
    for (const tex of doc.getRoot().listTextures()) {
        const img = tex.getImage();
        if (!img) continue;
        const mime = tex.getMimeType();
        try {
            const meta = await sharp(Buffer.from(img)).metadata();
            const needsResize = (meta.width && meta.width > maxTexSize) || (meta.height && meta.height > maxTexSize);
            const isBulkyPng = mime === 'image/png' && img.byteLength > 500 * 1024;

            if (needsResize || isBulkyPng) {
                let pipeline = sharp(Buffer.from(img));
                if (needsResize) {
                    pipeline = pipeline.resize({
                        width: maxTexSize,
                        height: maxTexSize,
                        fit: 'inside',
                        withoutEnlargement: true,
                    });
                }
                if (meta.hasAlpha) {
                    const outBuf = await pipeline.png({ compressionLevel: 9, palette: true }).toBuffer();
                    if (outBuf.length < img.byteLength) {
                        tex.setImage(new Uint8Array(outBuf));
                        tex.setMimeType('image/png');
                    }
                } else {
                    const outBuf = await pipeline.jpeg({ quality: 88, mozjpeg: true }).toBuffer();
                    if (outBuf.length < img.byteLength) {
                        tex.setImage(new Uint8Array(outBuf));
                        tex.setMimeType('image/jpeg');
                    }
                }
            }
        } catch (_) {
            // Keep original texture if sharp cannot process it
        }
    }

    // 2. Deduplicate and weld vertices
    await doc.transform(
        dedup(),
        weld({ tolerance: 0.0001 })
    );

    const afterWeldStats = countStats(doc);

    // 3. Simplify mesh if triangle count exceeds targetMaxTris
    if (afterWeldStats.triangles > targetMaxTris) {
        const ratio = Math.max(0.02, Math.min(1.0, targetMaxTris / afterWeldStats.triangles));
        await doc.transform(
            simplify({
                simplifier: MeshoptSimplifier,
                ratio: ratio,
                error: 0.01,
                lockBorder: true,
            })
        );
    }

    // 4. Prune unused resources
    await doc.transform(prune());

    const afterStats = countStats(doc);

    // 5. Stamp optimization metadata in asset.extras
    const asset = doc.getRoot().getAsset();
    asset.extras = {
        ...(asset.extras || {}),
        seelenfunke_optimized: true,
        optimized_at: new Date().toISOString(),
        original_size_bytes: originalSizeBytes,
        original_triangles: beforeStats.triangles,
        original_vertices: beforeStats.vertices,
        optimized_triangles: afterStats.triangles,
        optimized_vertices: afterStats.vertices,
    };

    const outBinary = await io.writeBinary(doc);
    fs.writeFileSync(outputPath, Buffer.from(outBinary));

    const newSizeBytes = outBinary.byteLength;
    const savedPercent = originalSizeBytes > 0
        ? Math.round((1 - newSizeBytes / originalSizeBytes) * 1000) / 10
        : 0;

    console.log(JSON.stringify({
        success: true,
        input: inputPath,
        output: outputPath,
        original_size_bytes: originalSizeBytes,
        original_size_mb: Math.round((originalSizeBytes / 1024 / 1024) * 100) / 100,
        new_size_bytes: newSizeBytes,
        new_size_mb: Math.round((newSizeBytes / 1024 / 1024) * 100) / 100,
        saved_percent: savedPercent,
        original_vertices: beforeStats.vertices,
        original_triangles: beforeStats.triangles,
        new_vertices: afterStats.vertices,
        new_triangles: afterStats.triangles,
    }));
}

run().catch((err) => {
    console.error(JSON.stringify({ success: false, error: err.message || String(err) }));
    process.exit(1);
});
