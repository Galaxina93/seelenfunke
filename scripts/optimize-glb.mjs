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

/**
 * Computes smooth area-weighted vertex normals across indexed & UV-seam vertices
 * WITHOUT unwelding triangles (keeps vertex count 4-5x smaller than flat normals!).
 */
function computeSmoothIndexedNormals(doc) {
    const root = doc.getRoot();
    const defaultBuf = root.listBuffers()[0] || doc.createBuffer();

    for (const mesh of root.listMeshes()) {
        for (const prim of mesh.listPrimitives()) {
            if (prim.getAttribute('NORMAL')) continue;
            const posAcc = prim.getAttribute('POSITION');
            if (!posAcc) continue;

            const vCount = posAcc.getCount();
            const posArr = posAcc.getArray();
            if (!posArr || vCount === 0) continue;

            const indAcc = prim.getIndices();
            const indArr = indAcc ? indAcc.getArray() : null;
            const triCount = indArr ? Math.floor(indArr.length / 3) : Math.floor(vCount / 3);

            // Accumulate area-weighted normals grouped by quantized 3D position so UV seams stay seamless!
            const posAccum = new Map();
            const vKeys = new Array(vCount);

            for (let v = 0; v < vCount; v++) {
                const px = posArr[v * 3];
                const py = posArr[v * 3 + 1];
                const pz = posArr[v * 3 + 2];
                const key = `${Math.round(px * 10000)},${Math.round(py * 10000)},${Math.round(pz * 10000)}`;
                vKeys[v] = key;
                if (!posAccum.has(key)) {
                    posAccum.set(key, [0, 0, 0]);
                }
            }

            for (let t = 0; t < triCount; t++) {
                const i0 = indArr ? indArr[t * 3] : t * 3;
                const i1 = indArr ? indArr[t * 3 + 1] : t * 3 + 1;
                const i2 = indArr ? indArr[t * 3 + 2] : t * 3 + 2;

                const ax = posArr[i0 * 3], ay = posArr[i0 * 3 + 1], az = posArr[i0 * 3 + 2];
                const bx = posArr[i1 * 3], by = posArr[i1 * 3 + 1], bz = posArr[i1 * 3 + 2];
                const cx = posArr[i2 * 3], cy = posArr[i2 * 3 + 1], cz = posArr[i2 * 3 + 2];

                const e1x = bx - ax, e1y = by - ay, e1z = bz - az;
                const e2x = cx - ax, e2y = cy - ay, e2z = cz - az;

                const nx = e1y * e2z - e1z * e2y;
                const ny = e1z * e2x - e1x * e2z;
                const nz = e1x * e2y - e1y * e2x;

                const a0 = posAccum.get(vKeys[i0]);
                const a1 = posAccum.get(vKeys[i1]);
                const a2 = posAccum.get(vKeys[i2]);
                a0[0] += nx; a0[1] += ny; a0[2] += nz;
                a1[0] += nx; a1[1] += ny; a1[2] += nz;
                a2[0] += nx; a2[1] += ny; a2[2] += nz;
            }

            const normArr = new Float32Array(vCount * 3);
            for (let v = 0; v < vCount; v++) {
                const acc = posAccum.get(vKeys[v]);
                const len = Math.hypot(acc[0], acc[1], acc[2]);
                if (len > 1e-10) {
                    normArr[v * 3] = acc[0] / len;
                    normArr[v * 3 + 1] = acc[1] / len;
                    normArr[v * 3 + 2] = acc[2] / len;
                } else {
                    normArr[v * 3 + 1] = 1.0;
                }
            }

            const normAcc = doc.createAccessor()
                .setType('VEC3')
                .setArray(normArr)
                .setBuffer(defaultBuf);
            prim.setAttribute('NORMAL', normAcc);
        }
    }
}

function countStats(doc) {
    let vertices = 0;
    let triangles = 0;
    let missingNormals = false;
    for (const mesh of doc.getRoot().listMeshes()) {
        for (const prim of mesh.listPrimitives()) {
            const pos = prim.getAttribute('POSITION');
            if (pos) vertices += pos.getCount();
            if (!prim.getAttribute('NORMAL')) missingNormals = true;
            const ind = prim.getIndices();
            if (ind) triangles += Math.round(ind.getCount() / 3);
            else if (pos) triangles += Math.round(pos.getCount() / 3);
        }
    }
    return { vertices, triangles, missingNormals };
}

async function run() {
    const args = process.argv.slice(2);
    const inputPath = args[0];
    const outputPath = args[1] || inputPath;
    const targetMaxTris = parseInt(args[2] || '55000', 10);
    const maxTexSize = parseInt(args[3] || '2048', 10);
    const maxAuxTexSize = parseInt(args[4] || String(maxTexSize <= 1024 ? 512 : Math.min(maxTexSize, 1024)), 10);

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

    const auxTextures = new Set();
    const baseTextures = new Set();
    for (const mat of doc.getRoot().listMaterials()) {
        const baseTex = mat.getBaseColorTexture?.() || null;
        const emissiveTex = mat.getEmissiveTexture?.() || null;
        const normalTex = mat.getNormalTexture?.() || null;
        const mrTex = mat.getMetallicRoughnessTexture?.() || null;
        const occTex = mat.getOcclusionTexture?.() || null;

        if (baseTex) baseTextures.add(baseTex);
        if (emissiveTex) baseTextures.add(emissiveTex);
        if (normalTex) auxTextures.add(normalTex);
        if (mrTex) auxTextures.add(mrTex);
        if (occTex) auxTextures.add(occTex);
    }

    // 1. Optimize embedded textures (role-aware sizing + mozjpeg recompression when > 350 KB)
    for (const tex of doc.getRoot().listTextures()) {
        const img = tex.getImage();
        if (!img) continue;
        const mime = tex.getMimeType();
        const isAuxOnly = auxTextures.has(tex) && !baseTextures.has(tex);
        const targetDim = isAuxOnly ? maxAuxTexSize : maxTexSize;
        const jpegQuality = isAuxOnly ? 80 : 84;

        try {
            const meta = await sharp(Buffer.from(img)).metadata();
            const needsResize = (meta.width && meta.width > targetDim) || (meta.height && meta.height > targetDim);
            const isBulkyPng = mime === 'image/png' && img.byteLength > 350 * 1024;
            const isBulkyJpeg = mime === 'image/jpeg' && img.byteLength > 350 * 1024;

            if (needsResize || isBulkyPng || isBulkyJpeg) {
                let pipeline = sharp(Buffer.from(img));
                if (needsResize) {
                    pipeline = pipeline.resize({
                        width: targetDim,
                        height: targetDim,
                        fit: 'inside',
                        withoutEnlargement: true,
                    });
                }
                if (meta.hasAlpha && !isAuxOnly) {
                    const outBuf = await pipeline.png({ compressionLevel: 9, palette: true }).toBuffer();
                    if (outBuf.length < img.byteLength || needsResize) {
                        tex.setImage(new Uint8Array(outBuf));
                        tex.setMimeType('image/png');
                    }
                } else {
                    const outBuf = await pipeline.jpeg({ quality: jpegQuality, mozjpeg: true }).toBuffer();
                    if (outBuf.length < img.byteLength || needsResize) {
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
        const ratio = Math.max(0.01, Math.min(1.0, targetMaxTris / afterWeldStats.triangles));
        await doc.transform(
            simplify({
                simplifier: MeshoptSimplifier,
                ratio: ratio,
                error: 0.008,
                lockBorder: true,
            })
        );
    }

    // 4. Compute smooth indexed vertex normals without splitting shared vertices
    computeSmoothIndexedNormals(doc);

    // 5. Prune unused resources
    await doc.transform(prune());

    const afterStats = countStats(doc);

    // 6. Stamp optimization & copyright metadata in asset / asset.extras
    const asset = doc.getRoot().getAsset();
    asset.copyright = '© Mein-Seelenfunke (Inhaberin: Alina Steinhauer, Gifhorn) - Alle Rechte vorbehalten (§§ 2, 10, 69a UrhG). Unautorisierte Vervielfältigung oder Nutzung untersagt.';
    asset.extras = {
        ...(asset.extras || {}),
        seelenfunke_optimized: true,
        rights_holder: 'Mein-Seelenfunke (Alina Steinhauer)',
        protected_under: 'UrhG §§ 2, 10, 69a & UWG § 4 Nr. 3',
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
        has_normals: !afterStats.missingNormals,
    }));
}

run().catch((err) => {
    console.error(JSON.stringify({ success: false, error: err.message || String(err) }));
    process.exit(1);
});
