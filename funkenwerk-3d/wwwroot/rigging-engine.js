import * as THREE from './vendor/build/three.module.min.js';
import { mergeGeometries } from './vendor/jsm/utils/BufferGeometryUtils.js';
import { GLTFExporter } from './vendor/jsm/exporters/GLTFExporter.js';

function clamp(v, lo, hi) {
    return Math.max(lo, Math.min(hi, v));
}

function smoothstep(e0, e1, v) {
    const t = clamp((v - e0) / Math.max(0.0001, e1 - e0), 0, 1);
    return t * t * (3.0 - 2.0 * t);
}

/**
 * Strips optional flat pedestal/plinth sub-meshes or bottom disk and normalizes geometry so feet rest at Y = 0.000
 * and total height is ~1.62 m (standard game character scale).
 */
export function prepareCharacterGeometryAndMaterial(rootObj, options = {}) {
    const stripPedestal = options.stripPedestal !== false;
    const variant = options.variant || 'original'; // original | funki_male | funkira_female

    rootObj.updateMatrixWorld(true);
    const sourceMeshes = [];
    rootObj.traverse(node => {
        if (node.isMesh && node.geometry && node.geometry.attributes.position) {
            sourceMeshes.push(node);
        }
    });

    if (sourceMeshes.length === 0) {
        throw new Error('Keine 3D-Mesh-Geometrie zum Riggen gefunden.');
    }

    const bakedParts = [];
    let primaryMaterial = null;

    for (const m of sourceMeshes) {
        let g = m.geometry.clone();
        g.applyMatrix4(m.matrixWorld);

        // Strip existing skin attributes if re-rigging
        if (g.attributes.skinIndex) g.deleteAttribute('skinIndex');
        if (g.attributes.skinWeight) g.deleteAttribute('skinWeight');

        if (!g.attributes.normal) {
            g.computeVertexNormals();
        }

        // Convert indexed geometry ONLY if needed to unify attributes across multi-mesh models
        if (sourceMeshes.length > 1 && g.index) {
            g = g.toNonIndexed();
        }

        if (!primaryMaterial && m.material) {
            const rawMat = Array.isArray(m.material) ? m.material[0] : m.material;
            primaryMaterial = rawMat ? rawMat.clone() : null;
        }
        bakedParts.push(g);
    }

    let merged;
    if (bakedParts.length === 1) {
        merged = bakedParts[0];
    } else {
        // Ensure compatible attribute sets before merging
        const commonAttrs = ['position', 'normal'];
        const allHaveUv = bakedParts.every(p => !!p.attributes.uv);
        if (allHaveUv) commonAttrs.push('uv');

        const cleaned = bakedParts.map(p => {
            for (const key of Object.keys(p.attributes)) {
                if (!commonAttrs.includes(key)) p.deleteAttribute(key);
            }
            return p;
        });
        merged = mergeGeometries(cleaned, false);
        cleaned.forEach(c => c.dispose());
    }

    merged.computeBoundingBox();
    // Auto-align Tripo / sideways-authored characters (shoulder span along Z > X) to standard glTF +Z forward!
    {
        const preBox = merged.boundingBox;
        const preW = Math.max(1e-5, preBox.max.x - preBox.min.x);
        const preD = Math.max(1e-5, preBox.max.z - preBox.min.z);
        if (options.orientForward !== false && preD > preW * 1.15) {
            merged.rotateY(-Math.PI * 0.5);
            merged.computeBoundingBox();
        }
    }
    const rawBox = merged.boundingBox.clone();
    const rawW = Math.max(1e-5, rawBox.max.x - rawBox.min.x);
    const rawH = Math.max(1e-5, rawBox.max.y - rawBox.min.y);
    const rawD = Math.max(1e-5, rawBox.max.z - rawBox.min.z);
    const cx = (rawBox.min.x + rawBox.max.x) * 0.5;
    const cz = (rawBox.min.z + rawBox.max.z) * 0.5;

    // Optional pedestal rim filter (if wide flat disk sits below y < 0.06 * rawH)
    if (stripPedestal) {
        const pos = merged.attributes.position;
        const yCut = rawBox.min.y + rawH * 0.055;
        let lowWideVerts = 0;
        let midVertsMaxR = 0;
        for (let i = 0; i < pos.count; i++) {
            const vx = pos.getX(i) - cx;
            const vy = pos.getY(i);
            const vz = pos.getZ(i) - cz;
            const r = Math.hypot(vx, vz);
            if (vy > rawBox.min.y + rawH * 0.12 && vy < rawBox.min.y + rawH * 0.45) {
                if (r > midVertsMaxR) midVertsMaxR = r;
            }
        }
        if (midVertsMaxR > 0) {
            for (let i = 0; i < pos.count; i++) {
                const vx = pos.getX(i) - cx;
                const vy = pos.getY(i);
                const vz = pos.getZ(i) - cz;
                if (vy <= yCut && Math.hypot(vx, vz) > midVertsMaxR * 1.28) {
                    lowWideVerts++;
                }
            }
            if (lowWideVerts > 24 && lowWideVerts < pos.count * 0.35) {
                merged = filterOutPedestalTriangles(merged, cx, cz, yCut, midVertsMaxR * 1.22);
                merged.computeBoundingBox();
            }
        }
    }

    const box2 = merged.boundingBox;
    const w2 = Math.max(1e-5, box2.max.x - box2.min.x);
    const h2 = Math.max(1e-5, box2.max.y - box2.min.y);
    const d2 = Math.max(1e-5, box2.max.z - box2.min.z);
    const centerX = (box2.min.x + box2.max.x) * 0.5;
    const centerZ = (box2.min.z + box2.max.z) * 0.5;
    const floorY = box2.min.y;

    const posAttr = merged.attributes.position;
    let centerCrownMaxY = -Infinity;
    for (let i = 0; i < posAttr.count; i++) {
        const dx = Math.abs(posAttr.getX(i) - centerX);
        const dz = posAttr.getZ(i) - centerZ;
        if (dx <= w2 * 0.14 && dz >= -d2 * 0.08) {
            const vy = posAttr.getY(i);
            if (vy > centerCrownMaxY) centerCrownMaxY = vy;
        }
    }
    const centerCrownH = isFinite(centerCrownMaxY) ? Math.max(1e-5, centerCrownMaxY - floorY) : h2;
    const hasTallBackProps = centerCrownH < h2 * 0.92 && centerCrownH > h2 * 0.45;

    const targetH = 1.62;
    const scaleFactor = hasTallBackProps ? (1.44 / centerCrownH) : (targetH / h2);

    for (let i = 0; i < posAttr.count; i++) {
        let nx = (posAttr.getX(i) - centerX) * scaleFactor;
        let ny = (posAttr.getY(i) - floorY) * scaleFactor;
        let nz = (posAttr.getZ(i) - centerZ) * scaleFactor;

        // Subtle feminine silhouette sculpt for Funkira (waist & shoulder contour below neck u < 0.53; 0% head/face distortion!)
        if (variant === 'funkira_female') {
            const u = clamp(ny / targetH, 0, 1);
            if (u >= 0.24 && u <= 0.52) {
                const waistBell = Math.sin(((u - 0.24) / 0.28) * Math.PI);
                const taper = 1.0 - 0.105 * waistBell;
                nx *= taper;
                nz *= (1.0 - 0.06 * waistBell);
            }
        }

        posAttr.setXYZ(i, nx, ny, nz);
    }
    posAttr.needsUpdate = true;

    if (!merged.attributes.normal) {
        merged.computeVertexNormals();
    } else if (variant === 'funkira_female') {
        merged.normalizeNormals();
    }
    merged.computeBoundingBox();

    let finalMat = primaryMaterial || new THREE.MeshStandardMaterial({
        color: 0xd8dee9,
        metalness: 0.22,
        roughness: 0.48
    });

    if (variant === 'funki_male') {
        finalMat.name = 'Funki_SchwarzGold_PBR';
        if ('roughness' in finalMat) finalMat.roughness = Math.min(finalMat.roughness ?? 0.6, 0.52);
        if ('metalness' in finalMat) finalMat.metalness = Math.max(finalMat.metalness ?? 0.1, 0.24);
    } else if (variant === 'funkira_female') {
        finalMat.name = 'Funkira_RoseGold_PBR';
        if (finalMat.color) finalMat.color.setRGB(1.0, 0.89, 0.86);
        if ('roughness' in finalMat) finalMat.roughness = Math.min(finalMat.roughness ?? 0.6, 0.42);
        if ('metalness' in finalMat) finalMat.metalness = Math.max(finalMat.metalness ?? 0.1, 0.30);
    }

    const finalBox = merged.boundingBox;
    const widthRatio = (finalBox.max.x - finalBox.min.x) / Math.max(0.01, targetH);
    return {
        geometry: merged,
        material: finalMat,
        variant,
        height: targetH,
        widthRatio,
        originalSizeMeters: { x: rawW, y: rawH, z: rawD }
    };
}

function filterOutPedestalTriangles(geo, cx, cz, yCut, maxBodyR) {
    const nonIdx = geo.index ? geo.toNonIndexed() : geo;
    const pos = nonIdx.attributes.position;
    const norm = nonIdx.attributes.normal;
    const uv = nonIdx.attributes.uv;

    const keepTris = [];
    const triCount = Math.floor(pos.count / 3);
    for (let t = 0; t < triCount; t++) {
        const i0 = t * 3, i1 = t * 3 + 1, i2 = t * 3 + 2;
        const y0 = pos.getY(i0), y1 = pos.getY(i1), y2 = pos.getY(i2);
        const r0 = Math.hypot(pos.getX(i0) - cx, pos.getZ(i0) - cz);
        const r1 = Math.hypot(pos.getX(i1) - cx, pos.getZ(i1) - cz);
        const r2 = Math.hypot(pos.getX(i2) - cx, pos.getZ(i2) - cz);

        const isPedestalRim = (y0 <= yCut && y1 <= yCut && y2 <= yCut) &&
                              (Math.max(r0, r1, r2) > maxBodyR);
        if (!isPedestalRim) {
            keepTris.push(i0, i1, i2);
        }
    }

    if (keepTris.length < 36) return geo;

    const out = new THREE.BufferGeometry();
    const newPos = new Float32Array(keepTris.length * 3);
    const newNorm = norm ? new Float32Array(keepTris.length * 3) : null;
    const newUv = uv ? new Float32Array(keepTris.length * 2) : null;

    for (let k = 0; k < keepTris.length; k++) {
        const src = keepTris[k];
        newPos[k * 3] = pos.getX(src);
        newPos[k * 3 + 1] = pos.getY(src);
        newPos[k * 3 + 2] = pos.getZ(src);
        if (newNorm && norm) {
            newNorm[k * 3] = norm.getX(src);
            newNorm[k * 3 + 1] = norm.getY(src);
            newNorm[k * 3 + 2] = norm.getZ(src);
        }
        if (newUv && uv) {
            newUv[k * 2] = uv.getX(src);
            newUv[k * 2 + 1] = uv.getY(src);
        }
    }

    out.setAttribute('position', new THREE.BufferAttribute(newPos, 3));
    if (newNorm) out.setAttribute('normal', new THREE.BufferAttribute(newNorm, 3));
    if (newUv) out.setAttribute('uv', new THREE.BufferAttribute(newUv, 2));
    return out;
}

function createFunkiraDiademMesh(headTopRelY, headFrontZ, headHalfWidth) {
    const crownRadius = clamp(headHalfWidth * 0.56, 0.095, 0.165);
    const crownBaseY = headTopRelY - 0.052;
    const zFront = Math.max(0.08, headFrontZ * 0.56);

    const tiaraParts = [];
    const arc = new THREE.TorusGeometry(crownRadius, 0.013, 12, 32, Math.PI);
    arc.rotateX(Math.PI * 0.36);
    arc.translate(0, crownBaseY, zFront * 0.55);
    tiaraParts.push(arc.index ? arc.toNonIndexed() : arc);

    const star = new THREE.OctahedronGeometry(0.038, 0);
    star.scale(0.85, 1.45, 0.45);
    star.translate(0, crownBaseY + 0.068, zFront * 0.92);
    tiaraParts.push(star.index ? star.toNonIndexed() : star);

    for (const side of [-1, 1]) {
        const wing = new THREE.ConeGeometry(0.018, 0.068, 8);
        wing.rotateZ(side * -0.42);
        wing.translate(side * (crownRadius * 0.58), crownBaseY + 0.044, zFront * 0.78);
        tiaraParts.push(wing.index ? wing.toNonIndexed() : wing);
    }

    const mergedTiara = mergeGeometries(tiaraParts, false);
    tiaraParts.forEach(p => p.dispose());

    const goldMat = new THREE.MeshStandardMaterial({
        name: 'Seelenfunke_14K_Gold_Diadem',
        color: 0xC5A059,
        metalness: 0.76,
        roughness: 0.28
    });

    const diademMesh = new THREE.Mesh(mergedTiara, goldMat);
    diademMesh.name = 'FunkiraStarDiadem';
    return diademMesh;
}

/**
 * Default Joint Landmarks (normalized to height H = 1.62m) for Chibi/Funki vs Standard Humanoid.
 */
export function computeDefaultJointLandmarks(profile, H, widthRatio) {
    const isChibi = profile === 'funki_chibi' || (profile === 'auto' && widthRatio >= 0.48);

    if (isChibi) {
        return {
            profileResolved: 'funki_chibi',
            pelvisY: 0.358 * H,
            spineY: 0.438 * H,
            chestY: 0.512 * H,
            neckY: 0.580 * H,
            shoulderX: 0.240,
            shoulderY: 0.506 * H,
            elbowX: 0.270,
            elbowY: 0.407 * H,
            wristX: 0.282,
            wristY: 0.321 * H,
            hipX: 0.140,
            hipY: 0.346 * H,
            kneeX: 0.140,
            kneeY: 0.179 * H,
            ankleX: 0.140,
            ankleY: 0.025 * H
        };
    }

    return {
        profileResolved: 'humanoid',
        pelvisY: 0.515 * H,
        spineY: 0.620 * H,
        chestY: 0.735 * H,
        neckY: 0.845 * H,
        shoulderX: 0.195,
        shoulderY: 0.790 * H,
        elbowX: 0.300,
        elbowY: 0.635 * H,
        wristX: 0.380,
        wristY: 0.495 * H,
        hipX: 0.105,
        hipY: 0.495 * H,
        kneeX: 0.108,
        kneeY: 0.265 * H,
        ankleX: 0.108,
        ankleY: 0.045 * H
    };
}

/**
 * Builds the 22-bone Skeleton + SkinnedMesh + 4 Equipment Sockets + 8 Skeletal AnimationClips.
 */
export function buildRiggedCharacterFromLandmarks(prepared, landmarks) {
    const H = prepared.height || 1.62;
    const geo = prepared.geometry.clone();
    const posAttr = geo.attributes.position;
    const vCount = posAttr.count;

    const neckNorm = clamp(landmarks.neckY / H, 0.42, 0.90);
    const pelvisNorm = clamp(landmarks.pelvisY / H, 0.25, neckNorm - 0.08);
    const shoulderLatNorm = clamp((landmarks.shoulderX * 0.72) / H, 0.08, 0.30);

    const skinIndices = new Uint16Array(vCount * 4);
    const skinWeights = new Float32Array(vCount * 4);
    const wBuf = new Float32Array(22);

    // 22-Bone Canonical Seelenfunke Order (100% compatible with seelenfunke-louvre-showroom-3d.js!)
    // 0: root, 1: pelvis, 2: spine, 3: chest, 4: head,
    // 5: shoulderL, 6: foreArmL, 7: handL, 8: fingersL,
    // 9: shoulderR, 10: foreArmR, 11: handR, 12: fingersR,
    // 13: hipL, 14: kneeL, 15: footL, 16: hipR, 17: kneeR, 18: footR,
    // 19: capeUpper, 20: capeMid, 21: capeLower
    for (let vi = 0; vi < vCount; vi++) {
        const vx = posAttr.getX(vi);
        const vy = posAttr.getY(vi);
        const vz = posAttr.getZ(vi);
        const u = clamp(vy / H, 0, 1);
        const ax = Math.abs(vx) / H;

        wBuf.fill(0);

        const armTopNorm = neckNorm - 0.045;
        const armBotNorm = Math.max(0.24, pelvisNorm - 0.08);

        // 1. Arm / Hand / Fingers zone
        let wArmTotal = 0;
        if (u >= armBotNorm && u <= armTopNorm && ax > shoulderLatNorm) {
            const armEnterY = smoothstep(armBotNorm, armBotNorm + 0.06, u);
            const armExitY = 1.0 - smoothstep(armTopNorm - 0.05, armTopNorm, u);
            const armLat = smoothstep(shoulderLatNorm, shoulderLatNorm + 0.055, ax);
            const backWingGuard = (vz < -0.062 * H && u > armTopNorm - 0.08)
                ? (1.0 - smoothstep(0.062 * H, 0.130 * H, -vz))
                : 1.0;
            wArmTotal = armEnterY * armExitY * armLat * backWingGuard;

            if (wArmTotal > 0.0001) {
                const distalAmt = clamp(
                    Math.max(smoothstep(shoulderLatNorm + 0.05, shoulderLatNorm + 0.18, ax), 1.0 - smoothstep(armBotNorm + 0.04, armTopNorm - 0.03, u)),
                    0,
                    1
                );
                const fingerFrac = smoothstep(0.76, 0.94, distalAmt);
                const handFrac = (1.0 - fingerFrac) * smoothstep(0.54, 0.76, distalAmt);
                const foreFrac = (1.0 - fingerFrac - handFrac) * smoothstep(0.24, 0.54, distalAmt);
                const shFrac = Math.max(0, 1.0 - fingerFrac - handFrac - foreFrac);

                if (vx < 0) {
                    wBuf[5] += wArmTotal * shFrac;
                    wBuf[6] += wArmTotal * foreFrac;
                    wBuf[7] += wArmTotal * handFrac;
                    wBuf[8] += wArmTotal * fingerFrac;
                } else {
                    wBuf[9] += wArmTotal * shFrac;
                    wBuf[10] += wArmTotal * foreFrac;
                    wBuf[11] += wArmTotal * handFrac;
                    wBuf[12] += wArmTotal * fingerFrac;
                }
            }
        }

        // 2. Cape zone (back side below neck)
        let wCapeTotal = 0;
        if (vz < -0.072 * H && u >= 0.08 && u <= armTopNorm && ax <= 0.24) {
            const capeDepth = smoothstep(0.072 * H, 0.135 * H, -vz);
            const capeYMask = smoothstep(0.08, 0.15, u) * (1.0 - smoothstep(armTopNorm - 0.07, armTopNorm, u));
            const capeLatMask = 1.0 - smoothstep(0.19, 0.24, ax);
            wCapeTotal = capeDepth * capeYMask * capeLatMask * (1.0 - wArmTotal) * 0.90;
            if (wCapeTotal > 0.0001) {
                const lowerFrac = 1.0 - smoothstep(0.18, 0.31, u);
                const midFrac = (1.0 - lowerFrac) * (1.0 - smoothstep(0.34, 0.45, u));
                const upperFrac = Math.max(0, 1.0 - lowerFrac - midFrac);
                wBuf[19] += wCapeTotal * upperFrac;
                wBuf[20] += wCapeTotal * midFrac;
                wBuf[21] += wCapeTotal * lowerFrac;
            }
        }

        // 3. Left / Right Legs with strict center-seam isolation
        let wLegTotal = 0;
        const legTopNorm = pelvisNorm + 0.04;
        if (u < legTopNorm) {
            const legY = 1.0 - smoothstep(legTopNorm - 0.08, legTopNorm, u);
            const seamPelvis = 1.0 - smoothstep(0.016, 0.068, ax);
            wLegTotal = legY * (1.0 - seamPelvis) * Math.max(0, 1.0 - wArmTotal - wCapeTotal);

            if (wLegTotal > 0.0001) {
                const ankleNorm = clamp(landmarks.ankleY / H, 0.02, 0.12);
                const kneeNorm = clamp(landmarks.kneeY / H, ankleNorm + 0.06, pelvisNorm - 0.05);
                const fFrac = 1.0 - smoothstep(ankleNorm, ankleNorm + 0.06, u);
                const kFrac = (1.0 - fFrac) * (1.0 - smoothstep(kneeNorm - 0.02, kneeNorm + 0.07, u));
                const hFrac = Math.max(0, 1.0 - fFrac - kFrac);

                if (vx < 0) {
                    wBuf[13] += wLegTotal * hFrac;
                    wBuf[14] += wLegTotal * kFrac;
                    wBuf[15] += wLegTotal * fFrac;
                } else {
                    wBuf[16] += wLegTotal * hFrac;
                    wBuf[17] += wLegTotal * kFrac;
                    wBuf[18] += wLegTotal * fFrac;
                }
            }
        }

        // 4. Rigid Head Zone (0% facial distortion; back-mounted swords/spears at vz < -0.07*H stay anchored to chest!)
        let wHead = 0;
        if (u > neckNorm - 0.06) {
            const headY = u >= neckNorm ? 1.0 : smoothstep(neckNorm - 0.06, neckNorm, u);
            const backPropStayOnChest = vz < -0.065 * H ? smoothstep(-0.125 * H, -0.065 * H, vz) : 1.0;
            wHead = headY * backPropStayOnChest * Math.max(0, 1.0 - wArmTotal - wCapeTotal);
            wBuf[4] += wHead;
        }

        // 5. Core Torso (pelvis -> spine -> chest)
        const coreRem = Math.max(0, 1.0 - wLegTotal - wArmTotal - wCapeTotal - wHead);
        if (coreRem > 0.0001) {
            const spineNorm = clamp(landmarks.spineY / H, pelvisNorm + 0.03, neckNorm - 0.06);
            const chestNorm = clamp(landmarks.chestY / H, spineNorm + 0.03, neckNorm - 0.02);
            const toSpine = smoothstep(pelvisNorm, spineNorm, u);
            const toChest = smoothstep(spineNorm, chestNorm, u);
            wBuf[1] += coreRem * (1.0 - toSpine);
            wBuf[2] += coreRem * toSpine * (1.0 - toChest);
            wBuf[3] += coreRem * toSpine * toChest;
        }

        // Select Top 4 weights & normalize to 1.0000
        const topIdx = [1, 0, 0, 0];
        const topVal = [0, 0, 0, 0];
        for (let bi = 0; bi < 22; bi++) {
            const w = wBuf[bi];
            if (w <= topVal[3]) continue;
            if (w > topVal[0]) {
                topVal[3] = topVal[2]; topIdx[3] = topIdx[2];
                topVal[2] = topVal[1]; topIdx[2] = topIdx[1];
                topVal[1] = topVal[0]; topIdx[1] = topIdx[0];
                topVal[0] = w; topIdx[0] = bi;
            } else if (w > topVal[1]) {
                topVal[3] = topVal[2]; topIdx[3] = topIdx[2];
                topVal[2] = topVal[1]; topIdx[2] = topIdx[1];
                topVal[1] = w; topIdx[1] = bi;
            } else if (w > topVal[2]) {
                topVal[3] = topVal[2]; topIdx[3] = topIdx[2];
                topVal[2] = w; topIdx[2] = bi;
            } else {
                topVal[3] = w; topIdx[3] = bi;
            }
        }

        const sumW = topVal[0] + topVal[1] + topVal[2] + topVal[3];
        const b4 = vi * 4;
        if (sumW > 1e-5) {
            const inv = 1.0 / sumW;
            const nw0 = topVal[0] * inv;
            const nw1 = topVal[1] * inv;
            const nw2 = topVal[2] * inv;
            const nw3 = Math.max(0, 1.0 - (nw0 + nw1 + nw2));
            skinIndices[b4] = topIdx[0];
            skinIndices[b4 + 1] = topIdx[1];
            skinIndices[b4 + 2] = topIdx[2];
            skinIndices[b4 + 3] = topIdx[3];
            skinWeights[b4] = nw0;
            skinWeights[b4 + 1] = nw1;
            skinWeights[b4 + 2] = nw2;
            skinWeights[b4 + 3] = nw3;
        } else {
            skinIndices[b4] = 1;
            skinWeights[b4] = 1.0;
        }
    }

    geo.setAttribute('skinIndex', new THREE.Uint16BufferAttribute(skinIndices, 4));
    geo.setAttribute('skinWeight', new THREE.Float32BufferAttribute(skinWeights, 4));

    // Create Bone Hierarchy
    const bRoot = new THREE.Bone(); bRoot.name = 'root'; bRoot.position.set(0, 0, 0);
    const bPelvis = new THREE.Bone(); bPelvis.name = 'pelvis'; bPelvis.position.set(0, landmarks.pelvisY, 0);
    const bSpine = new THREE.Bone(); bSpine.name = 'spine'; bSpine.position.set(0, landmarks.spineY - landmarks.pelvisY, 0);
    const bChest = new THREE.Bone(); bChest.name = 'chest'; bChest.position.set(0, landmarks.chestY - landmarks.spineY, 0);
    const bHead = new THREE.Bone(); bHead.name = 'head'; bHead.position.set(0, landmarks.neckY - landmarks.chestY, 0);

    const shRelY = landmarks.shoulderY - landmarks.chestY;
    const elRelX = landmarks.elbowX - landmarks.shoulderX;
    const elRelY = landmarks.elbowY - landmarks.shoulderY;
    const wrRelX = landmarks.wristX - landmarks.elbowX;
    const wrRelY = landmarks.wristY - landmarks.elbowY;

    const bShoulderL = new THREE.Bone(); bShoulderL.name = 'shoulderL'; bShoulderL.position.set(-landmarks.shoulderX, shRelY, -0.01);
    const bForeArmL = new THREE.Bone(); bForeArmL.name = 'foreArmL'; bForeArmL.position.set(-elRelX, elRelY, 0);
    const bHandL = new THREE.Bone(); bHandL.name = 'handL'; bHandL.position.set(-wrRelX, wrRelY, 0);
    const bFingersL = new THREE.Bone(); bFingersL.name = 'fingersL'; bFingersL.position.set(0, -0.05, 0.004);

    const bShoulderR = new THREE.Bone(); bShoulderR.name = 'shoulderR'; bShoulderR.position.set(landmarks.shoulderX, shRelY, -0.01);
    const bForeArmR = new THREE.Bone(); bForeArmR.name = 'foreArmR'; bForeArmR.position.set(elRelX, elRelY, 0);
    const bHandR = new THREE.Bone(); bHandR.name = 'handR'; bHandR.position.set(wrRelX, wrRelY, 0);
    const bFingersR = new THREE.Bone(); bFingersR.name = 'fingersR'; bFingersR.position.set(0, -0.05, 0.004);

    const hipRelY = landmarks.hipY - landmarks.pelvisY;
    const kneeRelY = landmarks.kneeY - landmarks.hipY;
    const footRelY = landmarks.ankleY - landmarks.kneeY;

    const bHipL = new THREE.Bone(); bHipL.name = 'hipL'; bHipL.position.set(-landmarks.hipX, hipRelY, 0);
    const bKneeL = new THREE.Bone(); bKneeL.name = 'kneeL'; bKneeL.position.set(0, kneeRelY, 0);
    const bFootL = new THREE.Bone(); bFootL.name = 'footL'; bFootL.position.set(0, footRelY, 0.025);

    const bHipR = new THREE.Bone(); bHipR.name = 'hipR'; bHipR.position.set(landmarks.hipX, hipRelY, 0);
    const bKneeR = new THREE.Bone(); bKneeR.name = 'kneeR'; bKneeR.position.set(0, kneeRelY, 0);
    const bFootR = new THREE.Bone(); bFootR.name = 'footR'; bFootR.position.set(0, footRelY, 0.025);

    const bCapeUpper = new THREE.Bone(); bCapeUpper.name = 'capeUpper'; bCapeUpper.position.set(0, 0.01, -0.095);
    const bCapeMid = new THREE.Bone(); bCapeMid.name = 'capeMid'; bCapeMid.position.set(0, -0.24, -0.02);
    const bCapeLower = new THREE.Bone(); bCapeLower.name = 'capeLower'; bCapeLower.position.set(0, -0.26, -0.02);

    bRoot.add(bPelvis);
    bPelvis.add(bSpine, bHipL, bHipR);
    bSpine.add(bChest);
    bChest.add(bHead, bShoulderL, bShoulderR, bCapeUpper);
    bShoulderL.add(bForeArmL);
    bForeArmL.add(bHandL);
    bHandL.add(bFingersL);
    bShoulderR.add(bForeArmR);
    bForeArmR.add(bHandR);
    bHandR.add(bFingersR);
    bHipL.add(bKneeL);
    bKneeL.add(bFootL);
    bHipR.add(bKneeR);
    bKneeR.add(bFootR);
    bCapeUpper.add(bCapeMid);
    bCapeMid.add(bCapeLower);

    // MMO Equipment Attachment Sockets
    const rightWeaponSocket = new THREE.Group();
    rightWeaponSocket.name = 'rightHandWeaponSocket';
    bHandL.add(rightWeaponSocket);

    const leftShieldSocket = new THREE.Group();
    leftShieldSocket.name = 'leftHandShieldSocket';
    bHandR.add(leftShieldSocket);

    const headSocket = new THREE.Group();
    headSocket.name = 'headSocket';
    bHead.add(headSocket);

    if (prepared.variant === 'funkira_female') {
        geo.computeBoundingBox();
        const bb = geo.boundingBox;
        let helmetMaxY = -Infinity;
        let foreheadMaxZ = 0.08;
        for (let vi = 0; vi < vCount; vi++) {
            const vx = posAttr.getX(vi);
            const vy = posAttr.getY(vi);
            const vz = posAttr.getZ(vi);
            if (Math.abs(vx) < 0.09 && vz > -0.03 && vy > helmetMaxY) {
                helmetMaxY = vy;
            }
            if (vy > landmarks.neckY + 0.05 && vy < landmarks.neckY + 0.42 && Math.abs(vx) < 0.14 && vz > foreheadMaxZ) {
                foreheadMaxZ = vz;
            }
        }
        if (!isFinite(helmetMaxY)) helmetMaxY = bb.max.y * 0.88;
        const headTopRelY = Math.max(0.18, helmetMaxY - landmarks.neckY - 0.035);
        const headFrontZ = Math.max(0.08, foreheadMaxZ);
        const headHalfWidth = Math.max(0.12, (bb.max.x - bb.min.x) * 0.34);
        headSocket.add(createFunkiraDiademMesh(headTopRelY, headFrontZ, headHalfWidth));
    }

    const backCapeSocket = new THREE.Group();
    backCapeSocket.name = 'backCapeSocket';
    bChest.add(backCapeSocket);

    const bones = [
        bRoot, bPelvis, bSpine, bChest, bHead,
        bShoulderL, bForeArmL, bHandL, bFingersL,
        bShoulderR, bForeArmR, bHandR, bFingersR,
        bHipL, bKneeL, bFootL, bHipR, bKneeR, bFootR,
        bCapeUpper, bCapeMid, bCapeLower
    ];

    const mat = prepared.material.clone();
    mat.skinning = true;
    mat.needsUpdate = true;

    const skinnedMesh = new THREE.SkinnedMesh(geo, mat);
    skinnedMesh.name = 'CharacterSkinnedMesh';
    skinnedMesh.frustumCulled = false;

    const exportGroup = new THREE.Group();
    exportGroup.name = 'CharacterArmatureRoot';
    exportGroup.add(bRoot);
    exportGroup.add(skinnedMesh);
    exportGroup.updateMatrixWorld(true);

    const skeleton = new THREE.Skeleton(bones);
    skinnedMesh.bind(skeleton);

    const clips = buildSkeletalAnimationSuite(landmarks.pelvisY);
    return {
        exportGroup,
        skinnedMesh,
        skeleton,
        bones,
        clips
    };
}

function quatTrack(boneName, times, eulerSamplesXYZ) {
    const values = new Float32Array(times.length * 4);
    const e = new THREE.Euler(0, 0, 0, 'XYZ');
    const q = new THREE.Quaternion();
    for (let i = 0; i < times.length; i++) {
        const s = eulerSamplesXYZ[i];
        e.set(s[0], s[1], s[2], 'XYZ');
        q.setFromEuler(e);
        values[i * 4] = q.x;
        values[i * 4 + 1] = q.y;
        values[i * 4 + 2] = q.z;
        values[i * 4 + 3] = q.w;
    }
    return new THREE.QuaternionKeyframeTrack(`${boneName}.quaternion`, times, values);
}

function posTrack(boneName, times, vecSamplesXYZ) {
    const values = new Float32Array(times.length * 3);
    for (let i = 0; i < times.length; i++) {
        values[i * 3] = vecSamplesXYZ[i][0];
        values[i * 3 + 1] = vecSamplesXYZ[i][1];
        values[i * 3 + 2] = vecSamplesXYZ[i][2];
    }
    return new THREE.VectorKeyframeTrack(`${boneName}.position`, times, values);
}

/**
 * Generates 8 smooth, loop-seamless multi-bone AnimationClips embedded directly into the exported .glb.
 */
export function buildSkeletalAnimationSuite(pelvisRestY) {
    const py = pelvisRestY;

    // 1. Idle (2.4s)
    const tIdle = [0, 0.6, 1.2, 1.8, 2.4];
    const idleClip = new THREE.AnimationClip('Idle', 2.4, [
        posTrack('pelvis', tIdle, [[0, py, 0], [0.008, py + 0.012, 0], [0, py, 0], [-0.008, py + 0.012, 0], [0, py, 0]]),
        quatTrack('chest', tIdle, [[0, 0, 0], [-0.03, 0.025, 0], [0, 0, 0], [-0.03, -0.025, 0], [0, 0, 0]]),
        quatTrack('head', tIdle, [[0, 0, 0], [0.025, -0.04, 0.015], [0, 0, 0], [0.02, 0.04, -0.015], [0, 0, 0]]),
        quatTrack('shoulderL', tIdle, [[0, 0, 0.05], [0.03, 0, 0.08], [0, 0, 0.05], [-0.03, 0, 0.08], [0, 0, 0.05]]),
        quatTrack('shoulderR', tIdle, [[0, 0, -0.05], [-0.03, 0, -0.08], [0, 0, -0.05], [0.03, 0, -0.08], [0, 0, -0.05]]),
        quatTrack('capeUpper', tIdle, [[0.06, 0, 0], [0.09, 0.02, 0], [0.06, 0, 0], [0.09, -0.02, 0], [0.06, 0, 0]]),
        quatTrack('capeMid', tIdle, [[0.05, 0, 0], [0.08, 0.03, 0], [0.05, 0, 0], [0.08, -0.03, 0], [0.05, 0, 0]])
    ]);

    // 2. Walk (0.96s)
    const tWalk = [0, 0.24, 0.48, 0.72, 0.96];
    const walkClip = new THREE.AnimationClip('Walk', 0.96, [
        posTrack('pelvis', tWalk, [[0, py + 0.018, 0], [0, py - 0.014, 0], [0, py + 0.018, 0], [0, py - 0.014, 0], [0, py + 0.018, 0]]),
        quatTrack('pelvis', tWalk, [[0.04, -0.06, 0], [0.04, 0, 0.025], [0.04, 0.06, 0], [0.04, 0, -0.025], [0.04, -0.06, 0]]),
        quatTrack('chest', tWalk, [[0.05, 0.07, 0], [0.05, 0, 0], [0.05, -0.07, 0], [0.05, 0, 0], [0.05, 0.07, 0]]),
        quatTrack('hipL', tWalk, [[-0.46, 0, 0], [0.06, 0, 0], [0.44, 0, 0], [-0.10, 0, 0], [-0.46, 0, 0]]),
        quatTrack('kneeL', tWalk, [[0.14, 0, 0], [0.18, 0, 0], [0.22, 0, 0], [0.62, 0, 0], [0.14, 0, 0]]),
        quatTrack('footL', tWalk, [[0.16, 0, 0], [0, 0, 0], [-0.20, 0, 0], [0.12, 0, 0], [0.16, 0, 0]]),
        quatTrack('hipR', tWalk, [[0.44, 0, 0], [-0.10, 0, 0], [-0.46, 0, 0], [0.06, 0, 0], [0.44, 0, 0]]),
        quatTrack('kneeR', tWalk, [[0.22, 0, 0], [0.62, 0, 0], [0.14, 0, 0], [0.18, 0, 0], [0.22, 0, 0]]),
        quatTrack('footR', tWalk, [[-0.20, 0, 0], [0.12, 0, 0], [0.16, 0, 0], [0, 0, 0], [-0.20, 0, 0]]),
        quatTrack('shoulderL', tWalk, [[0.36, 0, 0.06], [0, 0, 0.06], [-0.38, 0, 0.06], [0, 0, 0.06], [0.36, 0, 0.06]]),
        quatTrack('foreArmL', tWalk, [[-0.22, 0, 0], [-0.30, 0, 0], [-0.42, 0, 0], [-0.28, 0, 0], [-0.22, 0, 0]]),
        quatTrack('shoulderR', tWalk, [[-0.38, 0, -0.06], [0, 0, -0.06], [0.36, 0, -0.06], [0, 0, -0.06], [-0.38, 0, -0.06]]),
        quatTrack('foreArmR', tWalk, [[-0.42, 0, 0], [-0.28, 0, 0], [-0.22, 0, 0], [-0.30, 0, 0], [-0.42, 0, 0]]),
        quatTrack('capeUpper', tWalk, [[0.18, 0, 0], [0.24, 0.04, 0], [0.18, 0, 0], [0.24, -0.04, 0], [0.18, 0, 0]]),
        quatTrack('capeMid', tWalk, [[0.14, 0, 0], [0.22, 0.05, 0], [0.14, 0, 0], [0.22, -0.05, 0], [0.14, 0, 0]])
    ]);

    // 3. Sprint (0.64s)
    const tRun = [0, 0.16, 0.32, 0.48, 0.64];
    const sprintClip = new THREE.AnimationClip('Sprint', 0.64, [
        posTrack('pelvis', tRun, [[0, py + 0.032, 0], [0, py - 0.022, 0], [0, py + 0.032, 0], [0, py - 0.022, 0], [0, py + 0.032, 0]]),
        quatTrack('pelvis', tRun, [[0.14, -0.09, 0], [0.14, 0, 0.03], [0.14, 0.09, 0], [0.14, 0, -0.03], [0.14, -0.09, 0]]),
        quatTrack('chest', tRun, [[0.12, 0.11, 0], [0.12, 0, 0], [0.12, -0.11, 0], [0.12, 0, 0], [0.12, 0.11, 0]]),
        quatTrack('head', tRun, [[-0.14, 0, 0], [-0.12, 0, 0], [-0.14, 0, 0], [-0.12, 0, 0], [-0.14, 0, 0]]),
        quatTrack('hipL', tRun, [[-0.72, 0, 0], [0.10, 0, 0], [0.66, 0, 0], [-0.18, 0, 0], [-0.72, 0, 0]]),
        quatTrack('kneeL', tRun, [[0.24, 0, 0], [0.28, 0, 0], [0.35, 0, 0], [1.05, 0, 0], [0.24, 0, 0]]),
        quatTrack('hipR', tRun, [[0.66, 0, 0], [-0.18, 0, 0], [-0.72, 0, 0], [0.10, 0, 0], [0.66, 0, 0]]),
        quatTrack('kneeR', tRun, [[0.35, 0, 0], [1.05, 0, 0], [0.24, 0, 0], [0.28, 0, 0], [0.35, 0, 0]]),
        quatTrack('shoulderL', tRun, [[0.62, 0, 0.08], [0, 0, 0.08], [-0.68, 0, 0.08], [0, 0, 0.08], [0.62, 0, 0.08]]),
        quatTrack('foreArmL', tRun, [[-0.65, 0, 0], [-0.78, 0, 0], [-0.88, 0, 0], [-0.72, 0, 0], [-0.65, 0, 0]]),
        quatTrack('shoulderR', tRun, [[-0.68, 0, -0.08], [0, 0, -0.08], [0.62, 0, -0.08], [0, 0, -0.08], [-0.68, 0, -0.08]]),
        quatTrack('foreArmR', tRun, [[-0.88, 0, 0], [-0.72, 0, 0], [-0.65, 0, 0], [-0.78, 0, 0], [-0.88, 0, 0]]),
        quatTrack('capeUpper', tRun, [[0.42, 0, 0], [0.50, 0.06, 0], [0.42, 0, 0], [0.50, -0.06, 0], [0.42, 0, 0]]),
        quatTrack('capeMid', tRun, [[0.32, 0, 0], [0.42, 0.08, 0], [0.32, 0, 0], [0.42, -0.08, 0], [0.32, 0, 0]])
    ]);

    // 4. Jump (0.90s)
    const tJump = [0, 0.18, 0.48, 0.75, 0.90];
    const jumpClip = new THREE.AnimationClip('Jump', 0.90, [
        posTrack('pelvis', tJump, [[0, py, 0], [0, py - 0.05, 0], [0, py + 0.14, 0], [0, py + 0.04, 0], [0, py, 0]]),
        quatTrack('hipL', tJump, [[0, 0, 0], [-0.35, 0, 0], [-0.55, 0, 0.06], [-0.22, 0, 0], [0, 0, 0]]),
        quatTrack('kneeL', tJump, [[0, 0, 0], [0.65, 0, 0], [0.85, 0, 0], [0.35, 0, 0], [0, 0, 0]]),
        quatTrack('hipR', tJump, [[0, 0, 0], [-0.35, 0, 0], [-0.32, 0, -0.06], [-0.22, 0, 0], [0, 0, 0]]),
        quatTrack('kneeR', tJump, [[0, 0, 0], [0.65, 0, 0], [0.52, 0, 0], [0.35, 0, 0], [0, 0, 0]]),
        quatTrack('shoulderL', tJump, [[0, 0, 0.06], [0.32, 0, 0.12], [-0.65, 0, 0.35], [-0.25, 0, 0.18], [0, 0, 0.06]]),
        quatTrack('shoulderR', tJump, [[0, 0, -0.06], [0.32, 0, -0.12], [-0.65, 0, -0.35], [-0.25, 0, -0.18], [0, 0, -0.06]]),
        quatTrack('capeUpper', tJump, [[0.08, 0, 0], [0.15, 0, 0], [0.52, 0, 0], [0.35, 0, 0], [0.08, 0, 0]])
    ]);

    // 5. WallSalto (0.72s 360-degree somersault)
    const tSalto = [0, 0.18, 0.36, 0.54, 0.72];
    const saltoClip = new THREE.AnimationClip('WallSalto', 0.72, [
        posTrack('pelvis', tSalto, [[0, py, 0], [0, py + 0.12, 0], [0, py + 0.18, 0], [0, py + 0.10, 0], [0, py, 0]]),
        quatTrack('pelvis', tSalto, [[0, 0, 0], [-Math.PI * 0.5, 0, 0], [-Math.PI, 0, 0], [-Math.PI * 1.5, 0, 0], [-Math.PI * 2.0, 0, 0]]),
        quatTrack('hipL', tSalto, [[0, 0, 0], [-0.75, 0, 0], [-0.92, 0, 0], [-0.65, 0, 0], [0, 0, 0]]),
        quatTrack('kneeL', tSalto, [[0, 0, 0], [1.10, 0, 0], [1.28, 0, 0], [0.85, 0, 0], [0, 0, 0]]),
        quatTrack('hipR', tSalto, [[0, 0, 0], [-0.75, 0, 0], [-0.92, 0, 0], [-0.65, 0, 0], [0, 0, 0]]),
        quatTrack('kneeR', tSalto, [[0, 0, 0], [1.10, 0, 0], [1.28, 0, 0], [0.85, 0, 0], [0, 0, 0]]),
        quatTrack('shoulderL', tSalto, [[0, 0, 0.08], [-0.55, 0, 0.22], [-0.70, 0, 0.25], [-0.40, 0, 0.16], [0, 0, 0.08]]),
        quatTrack('shoulderR', tSalto, [[0, 0, -0.08], [-0.55, 0, -0.22], [-0.70, 0, -0.25], [-0.40, 0, -0.16], [0, 0, -0.08]])
    ]);

    // 6. Wave (1.80s)
    const tWave = [0, 0.35, 0.70, 1.05, 1.40, 1.80];
    const waveClip = new THREE.AnimationClip('Wave', 1.80, [
        quatTrack('head', tWave, [[0, 0, 0], [0, 0, 0.10], [0, 0, 0.08], [0, 0, 0.12], [0, 0, 0.08], [0, 0, 0]]),
        quatTrack('shoulderR', tWave, [[0, 0, -0.05], [-0.25, 0, -1.85], [-0.25, 0, -1.65], [-0.25, 0, -1.95], [-0.25, 0, -1.65], [0, 0, -0.05]]),
        quatTrack('foreArmR', tWave, [[0, 0, 0], [-0.35, 0, -0.45], [-0.35, 0, 0.25], [-0.35, 0, -0.45], [-0.35, 0, 0.25], [0, 0, 0]])
    ]);

    // 7. Cheer (1.60s)
    const tCheer = [0, 0.35, 0.80, 1.20, 1.60];
    const cheerClip = new THREE.AnimationClip('Cheer', 1.60, [
        posTrack('pelvis', tCheer, [[0, py, 0], [0, py + 0.06, 0], [0, py + 0.01, 0], [0, py + 0.07, 0], [0, py, 0]]),
        quatTrack('shoulderL', tCheer, [[0, 0, 0.05], [-0.20, 0, 2.05], [-0.20, 0, 1.80], [-0.20, 0, 2.10], [0, 0, 0.05]]),
        quatTrack('shoulderR', tCheer, [[0, 0, -0.05], [-0.20, 0, -2.05], [-0.20, 0, -1.80], [-0.20, 0, -2.10], [0, 0, -0.05]]),
        quatTrack('head', tCheer, [[0, 0, 0], [-0.14, 0, 0], [-0.06, 0, 0], [-0.15, 0, 0], [0, 0, 0]])
    ]);

    // 8. Dance (2.00s)
    const tDance = [0, 0.50, 1.00, 1.50, 2.00];
    const danceClip = new THREE.AnimationClip('Dance', 2.00, [
        posTrack('pelvis', tDance, [[0, py, 0], [0.045, py - 0.025, 0], [0, py + 0.03, 0], [-0.045, py - 0.025, 0], [0, py, 0]]),
        quatTrack('pelvis', tDance, [[0, 0, 0], [0, 0.16, -0.08], [0, 0, 0], [0, -0.16, 0.08], [0, 0, 0]]),
        quatTrack('chest', tDance, [[0, 0, 0], [0, -0.18, 0.09], [0, 0, 0], [0, 0.18, -0.09], [0, 0, 0]]),
        quatTrack('shoulderL', tDance, [[0, 0, 0.10], [-0.45, 0, 1.25], [0.20, 0, 0.45], [-0.35, 0, 0.85], [0, 0, 0.10]]),
        quatTrack('shoulderR', tDance, [[0, 0, -0.10], [0.20, 0, -0.45], [-0.45, 0, -1.25], [-0.35, 0, -0.85], [0, 0, -0.10]]),
        quatTrack('kneeL', tDance, [[0, 0, 0], [0.35, 0, 0], [0.05, 0, 0], [0.18, 0, 0], [0, 0, 0]]),
        quatTrack('kneeR', tDance, [[0, 0, 0], [0.18, 0, 0], [0.05, 0, 0], [0.35, 0, 0], [0, 0, 0]])
    ]);

    return [idleClip, walkClip, sprintClip, jumpClip, saltoClip, waveClip, cheerClip, danceClip];
}

function arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    const chunkSize = 0x8000;
    let binary = '';
    for (let i = 0; i < bytes.length; i += chunkSize) {
        const sub = bytes.subarray(i, Math.min(i + chunkSize, bytes.length));
        binary += String.fromCharCode.apply(null, sub);
    }
    return btoa(binary);
}

/**
 * Extracts an active rig state if a loaded .glb/.fbx already contains a SkinnedMesh + Skeleton.
 */
export function extractExistingRigState(rootObj, animClips = []) {
    let foundSkinned = null;
    rootObj.traverse(node => {
        if (!foundSkinned && node.isSkinnedMesh && node.skeleton && Array.isArray(node.skeleton.bones) && node.skeleton.bones.length > 0) {
            foundSkinned = node;
        }
    });
    if (!foundSkinned) return null;

    return {
        exportGroup: rootObj,
        skinnedMesh: foundSkinned,
        skeleton: foundSkinned.skeleton,
        bones: foundSkinned.skeleton.bones,
        clips: Array.isArray(animClips) ? animClips : []
    };
}

/**
 * Exports the rigged SkinnedMesh + Skeleton + AnimationClips to binary .glb bytes (Base64 string for C# bridge).
 */
export async function exportRiggedCharacterToGlbBase64Async(rigState, options = {}) {
    if (!rigState || !rigState.exportGroup) {
        throw new Error('Kein geriggtes Exoskelett zum Exportieren aktiv.');
    }

    const keepCurrentPose = !!options.keepCurrentPose;
    const prevParent = rigState.exportGroup.parent;
    const prevPos = rigState.exportGroup.position.clone();
    const prevQuat = rigState.exportGroup.quaternion.clone();
    const prevScale = rigState.exportGroup.scale.clone();

    try {
        if (prevParent) {
            prevParent.remove(rigState.exportGroup);
        }
        if (!keepCurrentPose && rigState.skeleton) {
            rigState.skeleton.pose();
        }
        rigState.exportGroup.position.set(0, 0, 0);
        rigState.exportGroup.quaternion.identity();
        rigState.exportGroup.scale.set(1, 1, 1);
        rigState.exportGroup.updateMatrixWorld(true);

        const exporter = new GLTFExporter();
        const arrayBuffer = await exporter.parseAsync(rigState.exportGroup, {
            binary: true,
            trs: true,
            animations: rigState.clips || [],
            onlyVisible: true,
            maxTextureSize: 2048
        });

        return arrayBufferToBase64(arrayBuffer);
    } finally {
        rigState.exportGroup.position.copy(prevPos);
        rigState.exportGroup.quaternion.copy(prevQuat);
        rigState.exportGroup.scale.copy(prevScale);
        if (prevParent) {
            prevParent.add(rigState.exportGroup);
            prevParent.updateMatrixWorld(true);
        }
    }
}
