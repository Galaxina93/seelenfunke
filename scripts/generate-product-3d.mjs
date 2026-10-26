import fs from 'fs';
import path from 'path';
import sharp from 'sharp';

const PUBLIC_STORAGE = path.resolve(process.cwd(), 'storage/app/public/produkte/products');

const THEMES = [
    { key: 'standard',    tint: null },
    { key: 'winter',      tint: { r: 215, g: 230, b: 255, alpha: 0.08 } },
    { key: 'ostern',      tint: { r: 255, g: 245, b: 210, alpha: 0.08 } },
    { key: 'sommer',      tint: { r: 255, g: 242, b: 185, alpha: 0.08 } },
    { key: 'herbst',      tint: { r: 255, g: 215, b: 175, alpha: 0.09 } },
    { key: 'hochzeit',    tint: { r: 255, g: 235, b: 240, alpha: 0.08 } },
    { key: 'geburtstag',  tint: { r: 255, g: 240, b: 195, alpha: 0.08 } },
    { key: 'muttertag',   tint: { r: 255, g: 225, b: 235, alpha: 0.08 } },
    { key: 'vatertag',    tint: { r: 215, g: 235, b: 250, alpha: 0.08 } },
];

/**
 * 1. DER SEELEN-TALISMAN (800x800)
 * Exakte Rekonstruktion des Original-Produkts (Temu Gold-Edelstahl-Anhänger):
 * - Gesamthöhe exakt y = 40 .. 760 (720 px = 90% der 800x800 Bühne, symmetrisch um y = 400)
 * - Oben: Großer flacher Schlüsselring (Split-Ring, Außen-Ø 288 px: y = 40 .. 328, x = 256 .. 544, Innen-Ø 226 px)
 * - Mitte: Kleiner goldener Biegering (Jump-Ring) durch das obere Stanzloch (cx=400, cy=353, r=16)
 * - Unten: Massiver, hochglanzpolierter Gold-Edelstahl-Anhänger mit:
 *   - Geraden vertikalen Seitenkanten links (x = 271) und rechts (x = 529) von y = 378 bis y = 704
 *   - Geschwungenem Kreisbogen oben (von y = 378 hoch bis Scheitelpunkt y = 322)
 *   - Geschwungenem Kreisbogen unten (von y = 704 hinab bis Scheitelpunkt y = 760)
 */
function createTalismanSvg() {
    return `<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
        <defs>
            <!-- Hochglanz-Goldverlauf der polierten Edelstahl-Platte (wie im Originalfoto) -->
            <linearGradient id="goldPlateFace" x1="12%" y1="8%" x2="88%" y2="92%">
                <stop offset="0%" stop-color="#FAF0AE"/>
                <stop offset="22%" stop-color="#F5DF7E"/>
                <stop offset="48%" stop-color="#ECCD62"/>
                <stop offset="72%" stop-color="#F8E896"/>
                <stop offset="90%" stop-color="#DFB949"/>
                <stop offset="100%" stop-color="#C89F32"/>
            </linearGradient>

            <!-- Sanfter Lichtreflex diagonal über die polierte Goldfläche -->
            <linearGradient id="goldSpecular" x1="0%" y1="0%" x2="100%" y2="65%">
                <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.32"/>
                <stop offset="35%" stop-color="#FFF9D2" stop-opacity="0.12"/>
                <stop offset="65%" stop-color="#D6AD3F" stop-opacity="0.0"/>
                <stop offset="100%" stop-color="#9A7218" stop-opacity="0.14"/>
            </linearGradient>

            <!-- 3D-Metallkante (links & unten sichtbare Materialstärke wie beim Original) -->
            <linearGradient id="goldEdgeBevel" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#8B6918"/>
                <stop offset="30%" stop-color="#FFF3A8"/>
                <stop offset="55%" stop-color="#B88E28"/>
                <stop offset="85%" stop-color="#755510"/>
                <stop offset="100%" stop-color="#D4AF37"/>
            </linearGradient>

            <!-- Großer flacher Schlüsselring (Split-Ring) Goldverlauf -->
            <linearGradient id="goldKeyring" x1="15%" y1="10%" x2="85%" y2="90%">
                <stop offset="0%" stop-color="#FDF4B8"/>
                <stop offset="25%" stop-color="#EDD06A"/>
                <stop offset="50%" stop-color="#C99E30"/>
                <stop offset="75%" stop-color="#F8E690"/>
                <stop offset="100%" stop-color="#A87E1E"/>
            </linearGradient>

            <!-- Innenkante des Schlüsselrings -->
            <linearGradient id="goldKeyringInner" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#7A5912"/>
                <stop offset="40%" stop-color="#F7E48E"/>
                <stop offset="70%" stop-color="#9C741A"/>
                <stop offset="100%" stop-color="#FCEEA8"/>
            </linearGradient>
        </defs>

        <!-- 1. GROSSER FLACHER GOLD-SCHLÜSSELRING (y = 40 .. 328, cx = 400, cy = 184, R_out = 144, R_in = 113) -->
        <!-- Äußerer dunkler Metallrand für realistische Tiefe -->
        <path d="M 400 40
                 A 144 144 0 1 0 400 328
                 A 144 144 0 1 0 400 40 Z
                 M 400 71
                 A 113 113 0 1 1 400 297
                 A 113 113 0 1 1 400 71 Z"
              fill="url(#goldKeyring)"
              fill-rule="evenodd"
              stroke="#9E781E"
              stroke-width="2"/>

        <!-- Doppel-Windung / Flachprofil-Spaltlinie des Schlüsselrings (wie im Originalfoto oben & unten sichtbar) -->
        <circle cx="400" cy="184" r="139" fill="none" stroke="#FFF8C8" stroke-width="2" opacity="0.75"/>
        <circle cx="400" cy="184" r="117" fill="none" stroke="url(#goldKeyringInner)" stroke-width="3.5" opacity="0.9"/>
        <path d="M 320 68 A 140 140 0 0 1 510 105" fill="none" stroke="#5A3E08" stroke-width="2.2" opacity="0.75"/>
        <path d="M 335 294 A 132 132 0 0 0 475 292" fill="none" stroke="#5A3E08" stroke-width="2.2" opacity="0.7"/>

        <!-- 2. MASSIVE GOLD-EDELSTAHL-PLATTE MIT STANZLOCH (y = 322 .. 760, x = 271 .. 529) -->
        <!-- 3D-Seitenwand / Materialstärke (leicht links & unten versetzt wie im Originalbild) -->
        <path d="M 267 379
                 A 176 176 0 0 1 527 379
                 L 527 705
                 A 176 176 0 0 1 267 705
                 Z
                 M 400 336
                 A 16.5 16.5 0 1 0 400 369
                 A 16.5 16.5 0 1 0 400 336 Z"
              fill="url(#goldEdgeBevel)"
              fill-rule="evenodd"/>

        <!-- Vorderseite der polierten Gold-Platte (gerade Seiten x=271..529, Kreisbögen oben y=322 & unten y=760) -->
        <path d="M 271 378
                 A 176 176 0 0 1 529 378
                 L 529 704
                 A 176 176 0 0 1 271 704
                 Z
                 M 400 336
                 A 16.5 16.5 0 1 0 400 369
                 A 16.5 16.5 0 1 0 400 336 Z"
              fill="url(#goldPlateFace)"
              fill-rule="evenodd"
              stroke="#D2AC3E"
              stroke-width="1.5"/>

        <!-- Spekular-Glanzschicht auf der Gold-Platte -->
        <path d="M 271 378
                 A 176 176 0 0 1 529 378
                 L 529 704
                 A 176 176 0 0 1 271 704
                 Z
                 M 400 336
                 A 16.5 16.5 0 1 0 400 369
                 A 16.5 16.5 0 1 0 400 336 Z"
              fill="url(#goldSpecular)"
              fill-rule="evenodd"/>

        <!-- Feine Lichtkante innen entlang des Rands & Stanzlochs -->
        <path d="M 273 379 A 174 174 0 0 1 527 379 L 527 703 A 174 174 0 0 1 273 703 Z"
              fill="none" stroke="#FFF9CA" stroke-width="1.5" opacity="0.65"/>
        <circle cx="400" cy="352.5" r="17" fill="none" stroke="#8C6816" stroke-width="2" opacity="0.75"/>

        <!-- 3. KLEINER GOLDENER BIEGERING / VERBINDUNGSRING (verbindet Stanzloch y=352 mit Schlüsselring y=312) -->
        <!-- Hinterer Teil des kleinen Rings (verschwindet hinter dem großen Ring) -->
        <path d="M 412 325 C 424 312, 424 295, 408 292"
              fill="none" stroke="#8B6816" stroke-width="9" stroke-linecap="round"/>
        <path d="M 412 325 C 424 312, 424 295, 408 292"
              fill="none" stroke="#E5C158" stroke-width="6" stroke-linecap="round"/>

        <!-- Vorderer Bogen des kleinen Biegerings (greift von vorn durch das Stanzloch über den unteren Schlüsselring) -->
        <path d="M 404 353 C 384 351, 376 324, 388 302 C 394 292, 405 290, 412 296"
              fill="none" stroke="#6B4E0B" stroke-width="11" stroke-linecap="round"/>
        <path d="M 404 353 C 384 351, 376 324, 388 302 C 394 292, 405 290, 412 296"
              fill="none" stroke="url(#goldKeyring)" stroke-width="8" stroke-linecap="round"/>
        <path d="M 402 351 C 386 349, 379 324, 390 304"
              fill="none" stroke="#FFF9D2" stroke-width="2.2" stroke-linecap="round"/>
    </svg>`;
}

/**
 * 2. DIE SEELEN-PFOTE (800x800)
 * Exakte 1:1 Rekonstruktion des Original-Temu-Produkts (goods_id=601099514279197):
 * - Massiver, hochglanzpolierter SILBER-Edelstahl-Knochenanhänger (ohne Schwarz-Inlay, ohne Goldrand)
 * - Integrierte runde Aufhängungs-Öse oben in der Mitte (cx=400, cy=268, R_out=43, Stanzloch cx=400, cy=270, r=21)
 * - Charakteristischer ausgestanzter Pfotenabdruck (Durchbruch) unten rechts im Knochenflügel (Hauptballen + 4 Zehen)
 * - Symmetrisch zentriert um (400, 400): Breite x = 80 .. 720 (640 px = 80%), Höhe y = 225 .. 575 (350 px)
 */
function getPfoteOuterContourPath() {
    return `
        M 357 290
        C 360 250, 377 225, 400 225
        C 423 225, 440 250, 443 290
        L 530 290
        C 572 290, 592 225, 646 225
        C 696 225, 720 268, 720 318
        C 720 362, 696 385, 684 400
        C 696 415, 720 438, 720 482
        C 720 532, 696 575, 646 575
        C 592 575, 572 510, 530 510
        L 270 510
        C 228 510, 208 575, 154 575
        C 104 575, 80 532, 80 482
        C 80 438, 104 415, 116 400
        C 104 385, 80 362, 80 318
        C 80 268, 104 225, 154 225
        C 208 225, 228 290, 270 290
        Z
    `;
}

function getPfoteHolesPath() {
    // 1. Oberes Stanzloch in der integrierten Öse (cx=400, cy=270, r=21)
    const topHole = `M 421 270 A 21 21 0 1 0 379 270 A 21 21 0 1 0 421 270 Z`;

    // 2. Ausgestanzter Pfotenabdruck unten rechts (wie im Originalfoto)
    // Hauptballen (abgerundetes Dreieck/Herz bei cx=612, cy=486)
    const mainPad = `M 594 468 C 608 461, 629 466, 637 477 C 643 485, 623 506, 605 512 C 594 514, 589 485, 594 468 Z`;

    // 4 ovale Zehenballen als geschlossene Bézier-Ovale
    const ovalPath = (cx, cy, rx, ry, deg) => {
        const rad = (deg * Math.PI) / 180;
        const cos = Math.cos(rad), sin = Math.sin(rad);
        const pt = (x, y) => `${(cx + x * cos - y * sin).toFixed(1)} ${(cy + x * sin + y * cos).toFixed(1)}`;
        const k = 0.552;
        return [
            `M ${pt(rx, 0)}`,
            `C ${pt(rx, ry * k)}, ${pt(rx * k, ry)}, ${pt(0, ry)}`,
            `C ${pt(-rx * k, ry)}, ${pt(-rx, ry * k)}, ${pt(-rx, 0)}`,
            `C ${pt(-rx, -ry * k)}, ${pt(-rx * k, -ry)}, ${pt(0, -ry)}`,
            `C ${pt(rx * k, -ry)}, ${pt(rx, -ry * k)}, ${pt(rx, 0)}`,
            `Z`
        ].join(' ');
    };

    const toe1 = ovalPath(573, 493, 10.5, 7.8, -28);
    const toe2 = ovalPath(572, 466, 11.0, 8.2, -32);
    const toe3 = ovalPath(592, 446, 11.0, 8.2, -36);
    const toe4 = ovalPath(619, 448, 10.5, 7.8, -40);

    return `${topHole} ${mainPad} ${toe1} ${toe2} ${toe3} ${toe4}`;
}

function createPfoteSvg() {
    const outerBone = getPfoteOuterContourPath();
    const holes = getPfoteHolesPath();

    return `<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
        <defs>
            <!-- Hochglanzpolierter Silber-Edelstahl-Verlauf (wie im Original-Händlerbild) -->
            <linearGradient id="silverPlateFace" x1="10%" y1="8%" x2="90%" y2="92%">
                <stop offset="0%" stop-color="#FFFFFF"/>
                <stop offset="22%" stop-color="#F4F6F9"/>
                <stop offset="48%" stop-color="#E4E9F0"/>
                <stop offset="72%" stop-color="#F8FAFC"/>
                <stop offset="100%" stop-color="#CBD5E1"/>
            </linearGradient>

            <linearGradient id="silverBevelDark" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#475569"/>
                <stop offset="50%" stop-color="#1E293B"/>
                <stop offset="100%" stop-color="#64748B"/>
            </linearGradient>

            <filter id="pfoteDropShadow" x="-8%" y="-8%" width="116%" height="116%">
                <feDropShadow dx="0" dy="5" stdDeviation="6" flood-color="#0F172A" flood-opacity="0.32"/>
            </filter>
        </defs>

        <!-- 1. 3D-Edelstahl-Kantenfase (leicht nach oben/außen versetzt wie im Originalfoto) -->
        <g filter="url(#pfoteDropShadow)">
            <path fill-rule="evenodd"
                  d="${outerBone} ${holes}"
                  transform="translate(0, -5)"
                  fill="url(#silverBevelDark)"
                  stroke="#1E293B"
                  stroke-width="3"
                  stroke-linejoin="round"/>
        </g>

        <!-- 2. Hauptplatte: Spiegelpolierter Silber-Edelstahl mit echtem Stanzloch & Pfoten-Durchbruch -->
        <path fill-rule="evenodd"
              d="${outerBone} ${holes}"
              fill="url(#silverPlateFace)"
              stroke="#64748B"
              stroke-width="2.2"
              stroke-linejoin="round"/>

        <!-- 3. Feine Glanzkante entlang der Innenkontur -->
        <path d="${outerBone}"
              fill="none"
              stroke="#FFFFFF"
              stroke-width="1.8"
              opacity="0.85"/>
    </svg>`;
}

/**
 * Erzeugt ein echtes 3D-Extrusions-Mesh direkt aus einer 2D-Konturliste in 800x800 Pixelkoordinaten.
 * Jedes Vertex (px, py) erhält exakt:
 *   X = (px - 400) / 360
 *   Y = (400 - py) / 360
 *   u = px / 800
 *   v = 1.0 - (py / 800)  (für glTF Top-Left UV Konvention bzw. v = py / 800)
 * Dadurch ist das 3D-Modell zu 100,0% deckungsgleich mit dem 800x800 2D-Overlay-Bild!
 */
function extrudePixelContour({
    outerPts,     // Array von [px, py] im Uhrzeigersinn / gegen Uhrzeigersinn
    holePts = null, // Optionales Array von [px, py] für ein Innenloch (z.B. Schlüsselring)
    centerPx = [400, 400], // Falls kein Loch: Zentrum für Triangle-Fan auf Vorder-/Rückseite
    zHalf = 0.032,
    positions,
    normals,
    uvs,
    indices,
}) {
    const toX = (px) => (px - 400) / 360;
    const toY = (py) => (400 - py) / 360;
    const toU = (px) => px / 800;
    const toV = (py) => py / 800;

    const n = outerPts.length;

    if (!holePts) {
        // 1. Vorderseite (+zHalf) als Fan um centerPx (oder Ear-Clipping / konvexes Fan)
        const fCenterIdx = positions.length / 3;
        positions.push(toX(centerPx[0]), toY(centerPx[1]), zHalf);
        normals.push(0, 0, 1);
        uvs.push(toU(centerPx[0]), toV(centerPx[1]));

        const fRingStart = positions.length / 3;
        for (let i = 0; i < n; i++) {
            const [px, py] = outerPts[i];
            positions.push(toX(px), toY(py), zHalf);
            normals.push(0, 0, 1);
            uvs.push(toU(px), toV(py));
        }
        for (let i = 0; i < n; i++) {
            const next = (i + 1) % n;
            indices.push(fCenterIdx, fRingStart + i, fRingStart + next);
        }

        // 2. Rückseite (-zHalf)
        const bCenterIdx = positions.length / 3;
        positions.push(toX(centerPx[0]), toY(centerPx[1]), -zHalf);
        normals.push(0, 0, -1);
        uvs.push(toU(centerPx[0]), toV(centerPx[1]));

        const bRingStart = positions.length / 3;
        for (let i = 0; i < n; i++) {
            const [px, py] = outerPts[i];
            positions.push(toX(px), toY(py), -zHalf);
            normals.push(0, 0, -1);
            uvs.push(toU(px), toV(py));
        }
        for (let i = 0; i < n; i++) {
            const next = (i + 1) % n;
            indices.push(bCenterIdx, bRingStart + next, bRingStart + i);
        }
    } else {
        // Ring-Topologie (outerPts + holePts gleicher Länge n)
        // 1. Vorderseite (+zHalf)
        const fStart = positions.length / 3;
        for (let i = 0; i < n; i++) {
            const [ox, oy] = outerPts[i];
            const [hx, hy] = holePts[i];
            positions.push(toX(ox), toY(oy), zHalf);
            normals.push(0, 0, 1);
            uvs.push(toU(ox), toV(oy));

            positions.push(toX(hx), toY(hy), zHalf);
            normals.push(0, 0, 1);
            uvs.push(toU(hx), toV(hy));
        }
        for (let i = 0; i < n; i++) {
            const next = (i + 1) % n;
            const o0 = fStart + i * 2;
            const h0 = fStart + i * 2 + 1;
            const o1 = fStart + next * 2;
            const h1 = fStart + next * 2 + 1;
            indices.push(o0, h0, o1, h0, h1, o1);
        }

        // 2. Rückseite (-zHalf)
        const bStart = positions.length / 3;
        for (let i = 0; i < n; i++) {
            const [ox, oy] = outerPts[i];
            const [hx, hy] = holePts[i];
            positions.push(toX(ox), toY(oy), -zHalf);
            normals.push(0, 0, -1);
            uvs.push(toU(ox), toV(oy));

            positions.push(toX(hx), toY(hy), -zHalf);
            normals.push(0, 0, -1);
            uvs.push(toU(hx), toV(hy));
        }
        for (let i = 0; i < n; i++) {
            const next = (i + 1) % n;
            const o0 = bStart + i * 2;
            const h0 = bStart + i * 2 + 1;
            const o1 = bStart + next * 2;
            const h1 = bStart + next * 2 + 1;
            indices.push(o0, o1, h0, h0, o1, h1);
        }

        // Innenwand des Lochs
        const hwStart = positions.length / 3;
        for (let i = 0; i < n; i++) {
            const next = (i + 1) % n;
            const [x0, y0] = holePts[i];
            const [x1, y1] = holePts[next];
            const dx = toX(x1) - toX(x0);
            const dy = toY(y1) - toY(y0);
            const len = Math.hypot(dx, dy) || 1;
            const nx = -dy / len;
            const ny = dx / len;

            const vBase = positions.length / 3;
            positions.push(toX(x0), toY(y0), zHalf, toX(x1), toY(y1), zHalf, toX(x1), toY(y1), -zHalf, toX(x0), toY(y0), -zHalf);
            for (let k = 0; k < 4; k++) normals.push(nx, ny, 0);
            uvs.push(toU(x0), toV(y0), toU(x1), toV(y1), toU(x1), toV(y1), toU(x0), toV(y0));
            indices.push(vBase, vBase + 1, vBase + 2, vBase, vBase + 2, vBase + 3);
        }
    }

    // 3. Außenwand (Seitenkante in Z)
    for (let i = 0; i < n; i++) {
        const next = (i + 1) % n;
        const [x0, y0] = outerPts[i];
        const [x1, y1] = outerPts[next];
        const dx = toX(x1) - toX(x0);
        const dy = toY(y1) - toY(y0);
        const len = Math.hypot(dx, dy) || 1;
        const nx = dy / len;
        const ny = -dx / len;

        const vBase = positions.length / 3;
        positions.push(
            toX(x0), toY(y0), zHalf,
            toX(x0), toY(y0), -zHalf,
            toX(x1), toY(y1), -zHalf,
            toX(x1), toY(y1), zHalf
        );
        for (let k = 0; k < 4; k++) normals.push(nx, ny, 0);
        uvs.push(toU(x0), toV(y0), toU(x0), toV(y0), toU(x1), toV(y1), toU(x1), toV(y1));
        indices.push(vBase, vBase + 1, vBase + 2, vBase, vBase + 2, vBase + 3);
    }
}

/**
 * Erzeugt die exakt zum 2D-Overlay (y = 40..760) passende 3D-Kontur der Gold-Platte (mit echtem Stanzloch!),
 * des großen flachen Schlüsselrings und des kleinen Biegerings.
 */
function buildTalisman3DMesh() {
    const positions = [];
    const normals = [];
    const uvs = [];
    const indices = [];

    // 1. Gold-Platte (gerade Seiten x=271..529, Kreisbögen oben y=322..378 und unten y=704..760)
    // Wir bauen die Platte als Ring um das Stanzloch (cx=400, cy=352.5, r=16.5), damit das Stanzloch auch in 3D echt durchbrochen ist!
    const plateOuter = [];
    const stepsArc = 32;
    const stepsSide = 16;

    // Oben: Kreisbogen von (271, 378) über (400, 322) nach (529, 378)
    // Kreis-Mittelpunkt des oberen Bogens (Radius R = 176): cy_top = 322 + 176 = 498
    const R_arc = 176;
    const cyTop = 498;
    const aTopStart = Math.atan2(378 - cyTop, 271 - 400); // ~ -2.35 rad
    const aTopEnd = Math.atan2(378 - cyTop, 529 - 400);   // ~ -0.79 rad
    for (let i = 0; i <= stepsArc; i++) {
        const a = aTopStart + (aTopEnd - aTopStart) * (i / stepsArc);
        plateOuter.push([400 + R_arc * Math.cos(a), cyTop + R_arc * Math.sin(a)]);
    }

    // Rechte gerade Kante von (529, 378) nach (529, 704)
    for (let i = 1; i < stepsSide; i++) {
        const t = i / stepsSide;
        plateOuter.push([529, 378 + (704 - 378) * t]);
    }

    // Unten: Kreisbogen von (529, 704) über (400, 760) nach (271, 704)
    // Kreis-Mittelpunkt des unteren Bogens (Radius R = 176): cy_bot = 760 - 176 = 584
    const cyBot = 584;
    const aBotStart = Math.atan2(704 - cyBot, 529 - 400); // ~ +0.79 rad
    const aBotEnd = Math.atan2(704 - cyBot, 271 - 400);   // ~ +2.35 rad
    for (let i = 0; i <= stepsArc; i++) {
        const a = aBotStart + (aBotEnd - aBotStart) * (i / stepsArc);
        plateOuter.push([400 + R_arc * Math.cos(a), cyBot + R_arc * Math.sin(a)]);
    }

    // Linke gerade Kante von (271, 704) nach (271, 378)
    for (let i = 1; i < stepsSide; i++) {
        const t = i / stepsSide;
        plateOuter.push([271, 704 - (704 - 378) * t]);
    }

    // Passendes Innenloch (Stanzloch bei cx=400, cy=352.5, r=16.5) mit gleicher Punktanzahl
    const holePts = plateOuter.map(([ox, oy]) => {
        const ang = Math.atan2(oy - 352.5, ox - 400);
        return [400 + 16.5 * Math.cos(ang), 352.5 + 16.5 * Math.sin(ang)];
    });

    extrudePixelContour({
        outerPts: plateOuter,
        holePts,
        zHalf: 0.032,
        positions,
        normals,
        uvs,
        indices,
    });

    // 2. Großer flacher Schlüsselring oben (cx=400, cy=184, R_out=144, R_in=113 -> y=40..328)
    const ringSteps = 72;
    const ringOuter = [];
    const ringInner = [];
    for (let i = 0; i < ringSteps; i++) {
        const a = (i / ringSteps) * Math.PI * 2;
        ringOuter.push([400 + 144 * Math.cos(a), 184 + 144 * Math.sin(a)]);
        ringInner.push([400 + 113 * Math.cos(a), 184 + 113 * Math.sin(a)]);
    }
    extrudePixelContour({
        outerPts: ringOuter,
        holePts: ringInner,
        zHalf: 0.030,
        positions,
        normals,
        uvs,
        indices,
    });

    // 3. Kleiner Verbindungsring / Biegering (cx=400, cy=322, Rx=20, Ry=32)
    const jumpOuter = [];
    const jumpInner = [];
    const jumpSteps = 36;
    for (let i = 0; i < jumpSteps; i++) {
        const a = (i / jumpSteps) * Math.PI * 2;
        jumpOuter.push([400 + 21 * Math.cos(a), 322 + 32 * Math.sin(a)]);
        jumpInner.push([400 + 11 * Math.cos(a), 322 + 22 * Math.sin(a)]);
    }
    extrudePixelContour({
        outerPts: jumpOuter,
        holePts: jumpInner,
        zHalf: 0.031,
        positions,
        normals,
        uvs,
        indices,
    });

    return { positions, normals, uvs, indices };
}

/**
 * Erzeugt das exakt zum 2D-Overlay (800x800) deckungsgleiche 3D-Mesh für Die Seelen-Pfote
 * (Silber-Edelstahl-Knochen mit integrierter Öse oben und ausgestanztem Pfotenabdruck unten rechts).
 */
function buildPfote3DMesh() {
    const positions = [];
    const normals = [];
    const uvs = [];
    const indices = [];

    const bonePts = [];
    const sampleCubic = (p0, p1, p2, p3, steps = 14) => {
        for (let i = 1; i <= steps; i++) {
            const t = i / steps;
            const mt = 1 - t;
            const x = mt*mt*mt*p0[0] + 3*mt*mt*t*p1[0] + 3*mt*t*t*p2[0] + t*t*t*p3[0];
            const y = mt*mt*mt*p0[1] + 3*mt*mt*t*p1[1] + 3*mt*t*t*p2[1] + t*t*t*p3[1];
            bonePts.push([x, y]);
        }
    };

    bonePts.push([357, 290]);
    sampleCubic([357, 290], [360, 250], [377, 225], [400, 225], 10);
    sampleCubic([400, 225], [423, 225], [440, 250], [443, 290], 10);
    bonePts.push([530, 290]);
    sampleCubic([530, 290], [572, 290], [592, 225], [646, 225]);
    sampleCubic([646, 225], [696, 225], [720, 268], [720, 318]);
    sampleCubic([720, 318], [720, 362], [696, 385], [684, 400]);
    sampleCubic([684, 400], [696, 415], [720, 438], [720, 482]);
    sampleCubic([720, 482], [720, 532], [696, 575], [646, 575]);
    sampleCubic([646, 575], [592, 575], [572, 510], [530, 510]);
    bonePts.push([270, 510]);
    sampleCubic([270, 510], [228, 510], [208, 575], [154, 575]);
    sampleCubic([154, 575], [104, 575], [80, 532], [80, 482]);
    sampleCubic([80, 482], [80, 438], [104, 415], [116, 400]);
    sampleCubic([116, 400], [104, 385], [80, 362], [80, 318]);
    sampleCubic([80, 318], [80, 268], [104, 225], [154, 225]);
    sampleCubic([154, 225], [208, 225], [228, 290], [270, 290]);

    extrudePixelContour({
        outerPts: bonePts,
        centerPx: [400, 400],
        zHalf: 0.032,
        positions,
        normals,
        uvs,
        indices,
    });

    // 3D-Innenwände für das obere Ösen-Loch und die 5 Pfoten-Durchbrüche
    const addHoleWallRing = (pts, zHalf = 0.032) => {
        const toX = (px) => (px - 400) / 360;
        const toY = (py) => (400 - py) / 360;
        const toU = (px) => px / 800;
        const toV = (py) => py / 800;
        const m = pts.length;
        for (let i = 0; i < m; i++) {
            const next = (i + 1) % m;
            const [x0, y0] = pts[i];
            const [x1, y1] = pts[next];
            const dx = toX(x1) - toX(x0);
            const dy = toY(y1) - toY(y0);
            const len = Math.hypot(dx, dy) || 1;
            const nx = -dy / len;
            const ny = dx / len;
            const vBase = positions.length / 3;
            positions.push(
                toX(x0), toY(y0), zHalf,
                toX(x1), toY(y1), zHalf,
                toX(x1), toY(y1), -zHalf,
                toX(x0), toY(y0), -zHalf
            );
            for (let k = 0; k < 4; k++) normals.push(nx, ny, 0);
            // UVs leicht nach außen auf den metallischen Rand legen
            uvs.push(toU(400), toV(400), toU(400), toV(400), toU(400), toV(400), toU(400), toV(400));
            indices.push(vBase, vBase + 1, vBase + 2, vBase, vBase + 2, vBase + 3);
        }
    };

    const makeOvalPts = (cx, cy, rx, ry, deg, steps = 20) => {
        const rad = (deg * Math.PI) / 180;
        const cos = Math.cos(rad), sin = Math.sin(rad);
        const arr = [];
        for (let i = 0; i < steps; i++) {
            const a = (i / steps) * Math.PI * 2;
            const lx = rx * Math.cos(a);
            const ly = ry * Math.sin(a);
            arr.push([cx + lx * cos - ly * sin, cy + lx * sin + ly * cos]);
        }
        return arr;
    };

    addHoleWallRing(makeOvalPts(400, 270, 21, 21, 0, 28));
    addHoleWallRing(makeOvalPts(612, 488, 20, 17, -35, 24));
    addHoleWallRing(makeOvalPts(573, 493, 10.5, 7.8, -28, 16));
    addHoleWallRing(makeOvalPts(572, 466, 11.0, 8.2, -32, 16));
    addHoleWallRing(makeOvalPts(592, 446, 11.0, 8.2, -36, 16));
    addHoleWallRing(makeOvalPts(619, 448, 10.5, 7.8, -40, 16));

    return { positions, normals, uvs, indices };
}

/**
 * Baut ein binäres glTF 2.0 (.glb) mit eingebetteter PNG-Textur, sodass applyMaterial()
 * in Three.js (oldMap = child.material.map) auf dem gesamten Modell die Original-Goldtextur anzeigt!
 */
function buildSingleTexturedGlb(meshData, pngBuffer, meshName = 'ProductMesh') {
    const posArr = new Float32Array(meshData.positions);
    const normArr = new Float32Array(meshData.normals);
    const uvArr = new Float32Array(meshData.uvs);
    const idxArr = new Uint32Array(meshData.indices);

    let minX = Infinity, minY = Infinity, minZ = Infinity;
    let maxX = -Infinity, maxY = -Infinity, maxZ = -Infinity;
    for (let i = 0; i < posArr.length; i += 3) {
        const x = posArr[i], y = posArr[i + 1], z = posArr[i + 2];
        if (x < minX) minX = x; if (x > maxX) maxX = x;
        if (y < minY) minY = y; if (y > maxY) maxY = y;
        if (z < minZ) minZ = z; if (z > maxZ) maxZ = z;
    }

    const pad4 = (buf) => {
        const rem = buf.length % 4;
        return rem === 0 ? buf : Buffer.concat([buf, Buffer.alloc(4 - rem)]);
    };

    const bPos = pad4(Buffer.from(posArr.buffer));
    const bNorm = pad4(Buffer.from(normArr.buffer));
    const bUv = pad4(Buffer.from(uvArr.buffer));
    const bIdx = pad4(Buffer.from(idxArr.buffer));
    const bImg = pad4(pngBuffer);

    let offset = 0;
    const bvPos = { buffer: 0, byteOffset: offset, byteLength: posArr.byteLength, target: 34962 }; offset += bPos.length;
    const bvNorm = { buffer: 0, byteOffset: offset, byteLength: normArr.byteLength, target: 34962 }; offset += bNorm.length;
    const bvUv = { buffer: 0, byteOffset: offset, byteLength: uvArr.byteLength, target: 34962 }; offset += bUv.length;
    const bvIdx = { buffer: 0, byteOffset: offset, byteLength: idxArr.byteLength, target: 34963 }; offset += bIdx.length;
    const bvImg = { buffer: 0, byteOffset: offset, byteLength: pngBuffer.length }; offset += bImg.length;

    const binBuffer = Buffer.concat([bPos, bNorm, bUv, bIdx, bImg]);

    const gltf = {
        asset: { version: '2.0', generator: 'Seelenfunke-3D-Generator-v2' },
        scene: 0,
        scenes: [{ nodes: [0] }],
        nodes: [{ name: meshName, mesh: 0 }],
        meshes: [{
            name: meshName,
            primitives: [{
                attributes: { POSITION: 0, NORMAL: 1, TEXCOORD_0: 2 },
                indices: 3,
                material: 0,
            }],
        }],
        materials: [{
            name: 'PolishedGoldMetal',
            doubleSided: true,
            pbrMetallicRoughness: {
                baseColorFactor: [1.0, 1.0, 1.0, 1.0],
                baseColorTexture: { index: 0 },
                metallicFactor: 0.85,
                roughnessFactor: 0.2,
            },
        }],
        textures: [{ source: 0, sampler: 0 }],
        samplers: [{ magFilter: 9729, minFilter: 9729, wrapS: 33071, wrapT: 33071 }],
        images: [{ bufferView: 4, mimeType: 'image/png' }],
        accessors: [
            { bufferView: 0, componentType: 5126, count: posArr.length / 3, type: 'VEC3', min: [minX, minY, minZ], max: [maxX, maxY, maxZ] },
            { bufferView: 1, componentType: 5126, count: normArr.length / 3, type: 'VEC3' },
            { bufferView: 2, componentType: 5126, count: uvArr.length / 2, type: 'VEC2' },
            { bufferView: 3, componentType: 5125, count: idxArr.length, type: 'SCALAR' },
        ],
        bufferViews: [bvPos, bvNorm, bvUv, bvIdx, bvImg],
        buffers: [{ byteLength: binBuffer.length }],
    };

    let jsonBuf = Buffer.from(JSON.stringify(gltf), 'utf8');
    const jsonRem = jsonBuf.length % 4;
    if (jsonRem !== 0) {
        jsonBuf = Buffer.concat([jsonBuf, Buffer.alloc(4 - jsonRem, 0x20)]);
    }

    const totalLen = 12 + 8 + jsonBuf.length + 8 + binBuffer.length;
    const header = Buffer.alloc(12);
    header.write('glTF', 0, 4, 'ascii');
    header.writeUInt32LE(2, 4);
    header.writeUInt32LE(totalLen, 8);

    const jsonHeader = Buffer.alloc(8);
    jsonHeader.writeUInt32LE(jsonBuf.length, 0);
    jsonHeader.write('JSON', 4, 4, 'ascii');

    const binHeader = Buffer.alloc(8);
    binHeader.writeUInt32LE(binBuffer.length, 0);
    binHeader.write('BIN\0', 4, 4, 'ascii');

    return Buffer.concat([header, jsonHeader, jsonBuf, binHeader, binBuffer]);
}

async function generateOverlaysForProduct(slug, svgString) {
    const dir = path.join(PUBLIC_STORAGE, slug);
    fs.mkdirSync(dir, { recursive: true });

    const basePngBuffer = await sharp(Buffer.from(svgString))
        .resize(800, 800)
        .png({ compressionLevel: 9 })
        .toBuffer();

    fs.writeFileSync(path.join(dir, 'overlay.png'), basePngBuffer);

    for (const theme of THEMES) {
        const outPath = path.join(dir, `overlay_${theme.key}.png`);
        if (!theme.tint) {
            fs.writeFileSync(outPath, basePngBuffer);
        } else {
            const tintSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800">
                <rect width="800" height="800" fill="rgb(${theme.tint.r},${theme.tint.g},${theme.tint.b})" fill-opacity="${theme.tint.alpha}"/>
            </svg>`;
            const tinted = await sharp(basePngBuffer)
                .composite([{ input: Buffer.from(tintSvg), blend: 'atop' }])
                .png({ compressionLevel: 9 })
                .toBuffer();
            fs.writeFileSync(outPath, tinted);
        }
    }

    const refBg = path.join(PUBLIC_STORAGE, 'seelen-anhaenger', 'header_bg.png');
    const targetBg = path.join(dir, 'header_bg.png');
    if (fs.existsSync(refBg) && !fs.existsSync(targetBg)) {
        fs.copyFileSync(refBg, targetBg);
    }

    return basePngBuffer;
}

/**
 * Erzeugt ein hochauflösendes (1024x1024) Technisches Maßblatt-Produktbild ({slug}-dimensions.jpg)
 * auf Basis des echten Produkt-Overlays mit präzisen Bemaßungslinien, Pfeilen und Spezifikationstabelle.
 */
async function generateDimensionSheet({
    slug,
    outFileName,
    title,
    subtitle,
    horizArrows = [], // [{ x1, x2, y, label, dashedYFrom }]
    vertArrows = [],  // [{ y1, y2, x, label, dashedXFrom }]
    callouts = [],    // [{ x, y, tx, ty, label }]
    specs = [],       // [{ label, value }] (4 Spalten unten)
}) {
    const prodDir = path.join(PUBLIC_STORAGE, slug);
    const overlayPath = path.join(prodDir, 'overlay.png');
    if (!fs.existsSync(overlayPath)) {
        return;
    }

    const resizedOverlay = await sharp(overlayPath)
        .resize(560, 560, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
        .png()
        .toBuffer();

    const escXml = (str) => String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    const drawHorizArrow = ({ x1, x2, y, label, dashedYFrom }) => {
        const midX = (x1 + x2) / 2;
        const badgeW = Math.max(150, label.length * 9.5 + 28);
        const helpers = dashedYFrom !== undefined ? `
            <line x1="${x1}" y1="${dashedYFrom}" x2="${x1}" y2="${y + (y > dashedYFrom ? 10 : -10)}" stroke="#C5A059" stroke-width="1.5" stroke-dasharray="5,5" opacity="0.75"/>
            <line x1="${x2}" y1="${dashedYFrom}" x2="${x2}" y2="${y + (y > dashedYFrom ? 10 : -10)}" stroke="#C5A059" stroke-width="1.5" stroke-dasharray="5,5" opacity="0.75"/>
        ` : '';
        return `
            ${helpers}
            <line x1="${x1}" y1="${y - 9}" x2="${x1}" y2="${y + 9}" stroke="#E5C158" stroke-width="2.5"/>
            <line x1="${x2}" y1="${y - 9}" x2="${x2}" y2="${y + 9}" stroke="#E5C158" stroke-width="2.5"/>
            <line x1="${x1 + 4}" y1="${y}" x2="${x2 - 4}" y2="${y}" stroke="#E5C158" stroke-width="2.2"/>
            <polygon points="${x1},${y} ${x1 + 12},${y - 5} ${x1 + 12},${y + 5}" fill="#E5C158"/>
            <polygon points="${x2},${y} ${x2 - 12},${y - 5} ${x2 - 12},${y + 5}" fill="#E5C158"/>
            <rect x="${midX - badgeW / 2}" y="${y - 16}" width="${badgeW}" height="32" rx="8" fill="#171411" stroke="#C5A059" stroke-width="1.8"/>
            <text x="${midX}" y="${y + 5}" text-anchor="middle" fill="#F8E7B0" font-family="sans-serif" font-size="15" font-weight="bold">${escXml(label)}</text>
        `;
    };

    const drawVertArrow = ({ y1, y2, x, label, dashedXFrom }) => {
        const midY = (y1 + y2) / 2;
        const badgeW = Math.max(155, label.length * 9.2 + 26);
        const helpers = dashedXFrom !== undefined ? `
            <line x1="${dashedXFrom}" y1="${y1}" x2="${x + (x > dashedXFrom ? 10 : -10)}" y2="${y1}" stroke="#C5A059" stroke-width="1.5" stroke-dasharray="5,5" opacity="0.75"/>
            <line x1="${dashedXFrom}" y1="${y2}" x2="${x + (x > dashedXFrom ? 10 : -10)}" y2="${y2}" stroke="#C5A059" stroke-width="1.5" stroke-dasharray="5,5" opacity="0.75"/>
        ` : '';
        return `
            ${helpers}
            <line x1="${x - 9}" y1="${y1}" x2="${x + 9}" y2="${y1}" stroke="#E5C158" stroke-width="2.5"/>
            <line x1="${x - 9}" y1="${y2}" x2="${x + 9}" y2="${y2}" stroke="#E5C158" stroke-width="2.5"/>
            <line x1="${x}" y1="${y1 + 4}" x2="${x}" y2="${y2 - 4}" stroke="#E5C158" stroke-width="2.2"/>
            <polygon points="${x},${y1} ${x - 5},${y1 + 12} ${x + 5},${y1 + 12}" fill="#E5C158"/>
            <polygon points="${x},${y2} ${x - 5},${y2 - 12} ${x + 5},${y2 - 12}" fill="#E5C158"/>
            <g transform="translate(${x}, ${midY})">
                <rect x="${-badgeW / 2}" y="-16" width="${badgeW}" height="32" rx="8" fill="#171411" stroke="#C5A059" stroke-width="1.8"/>
                <text x="0" y="5" text-anchor="middle" fill="#F8E7B0" font-family="sans-serif" font-size="14.5" font-weight="bold">${escXml(label)}</text>
            </g>
        `;
    };

    const drawCallout = ({ x, y, tx, ty, label }) => {
        const badgeW = Math.max(170, label.length * 9.2 + 28);
        return `
            <line x1="${x}" y1="${y}" x2="${tx}" y2="${ty}" stroke="#E5C158" stroke-width="2" stroke-dasharray="4,4"/>
            <circle cx="${x}" cy="${y}" r="5" fill="#E5C158" stroke="#171411" stroke-width="2"/>
            <rect x="${tx - badgeW / 2}" y="${ty - 16}" width="${badgeW}" height="32" rx="8" fill="#171411" stroke="#C5A059" stroke-width="1.8"/>
            <text x="${tx}" y="${ty + 5}" text-anchor="middle" fill="#F8E7B0" font-family="sans-serif" font-size="14.5" font-weight="bold">${escXml(label)}</text>
        `;
    };

    const specBoxes = specs.slice(0, 4).map((s, idx) => {
        const bx = 56 + idx * 232;
        return `
            <rect x="${bx}" y="845" width="216" height="112" rx="12" fill="#1E1A16" stroke="#C5A059" stroke-opacity="0.45" stroke-width="1.5"/>
            <text x="${bx + 108}" y="882" text-anchor="middle" fill="#A39582" font-family="sans-serif" font-size="13" font-weight="bold" letter-spacing="1">${escXml(s.label.toUpperCase())}</text>
            <text x="${bx + 108}" y="918" text-anchor="middle" fill="#FFFFFF" font-family="sans-serif" font-size="18" font-weight="bold">${escXml(s.value)}</text>
            <text x="${bx + 108}" y="942" text-anchor="middle" fill="#C5A059" font-family="sans-serif" font-size="12.5">${escXml(s.sub || '')}</text>
        `;
    }).join('\n');

    const bgSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024">
        <defs>
            <radialGradient id="bgGlow" cx="50%" cy="45%" r="60%">
                <stop offset="0%" stop-color="#2A231C"/>
                <stop offset="65%" stop-color="#15120F"/>
                <stop offset="100%" stop-color="#0D0B09"/>
            </radialGradient>
            <pattern id="techGrid" width="40" height="40" patternUnits="userSpaceOnUse">
                <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#C5A059" stroke-width="0.6" stroke-opacity="0.10"/>
            </pattern>
        </defs>
        <rect width="1024" height="1024" fill="url(#bgGlow)"/>
        <rect width="1024" height="1024" fill="url(#techGrid)"/>
        <rect x="28" y="28" width="968" height="968" rx="22" fill="none" stroke="#C5A059" stroke-opacity="0.45" stroke-width="2"/>

        <!-- Header -->
        <text x="512" y="78" text-anchor="middle" fill="#C5A059" font-family="sans-serif" font-size="14" font-weight="bold" letter-spacing="3">MEIN-SEELENFUNKE MANUFAKTUR • TECHNISCHES MAßBLATT</text>
        <text x="512" y="118" text-anchor="middle" fill="#FFFFFF" font-family="serif" font-size="32" font-weight="bold">${escXml(title)}</text>
        <text x="512" y="148" text-anchor="middle" fill="#B8AC9C" font-family="sans-serif" font-size="15">${escXml(subtitle)}</text>

        <!-- Spezifikations-Kacheln unten -->
        ${specBoxes}
    </svg>`;

    const overlayAnnotationsSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024">
        ${horizArrows.map(drawHorizArrow).join('\n')}
        ${vertArrows.map(drawVertArrow).join('\n')}
        ${callouts.map(drawCallout).join('\n')}
    </svg>`;

    const finalJpg = await sharp(Buffer.from(bgSvg))
        .composite([
            { input: resizedOverlay, left: 232, top: 185 },
            { input: Buffer.from(overlayAnnotationsSvg), left: 0, top: 0 },
        ])
        .jpeg({ quality: 92 })
        .toBuffer();

    fs.writeFileSync(path.join(prodDir, outFileName), finalJpg);
}

async function generateAllDimensionSheets() {
    // 1. Der Seelen Kristall
    await generateDimensionSheet({
        slug: 'seelen-kristall',
        outFileName: 'seelen-kristall-dimensions.jpg',
        title: 'Der Seelen Kristall – Maße & Details',
        subtitle: 'Optisches K9-Kristallglas mit geschliffener Facettenkante & Tiefen-Lasergravur',
        horizArrows: [
            { x1: 288, x2: 736, y: 745, dashedYFrom: 680, label: 'Breite: 180 mm' },
        ],
        vertArrows: [
            { y1: 241, y2: 689, x: 835, dashedXFrom: 736, label: 'Höhe: 200 mm' },
        ],
        callouts: [
            { x: 512, y: 315, tx: 220, ty: 225, label: 'Gravurfläche: Ø 150 mm' },
        ],
        specs: [
            { label: 'Abmessungen', value: '180 × 200 mm', sub: 'Facetten-Achteck / Kreis' },
            { label: 'Materialstärke', value: '40 mm (Massiv)', sub: 'Freistehend ohne Sockel' },
            { label: 'Gewicht', value: 'ca. 920 g', sub: 'Schwere Manufaktur-Qualität' },
            { label: 'Material', value: 'K9 Kristallglas', sub: 'Hochreiner Facettenschliff' },
        ],
    });

    // 2. Der Seelenanhänger
    await generateDimensionSheet({
        slug: 'seelen-anhaenger',
        outFileName: 'seelen-anhaenger-dimensions.jpg',
        title: 'Der Seelenanhänger – Maße & Details',
        subtitle: 'Herz-Schlüsselanhänger aus eloxiertem Aluminium mit Hochglanz-Metallrahmen',
        horizArrows: [
            { x1: 305, x2: 719, y: 745, dashedYFrom: 675, label: 'Herz-Breite: 45 mm' },
        ],
        vertArrows: [
            { y1: 235, y2: 690, x: 835, dashedXFrom: 719, label: 'Gesamthöhe: 40 mm' },
        ],
        callouts: [
            { x: 512, y: 520, tx: 215, ty: 650, label: 'Gravurfeld: 36 × 32 mm' },
        ],
        specs: [
            { label: 'Abmessungen', value: '45 × 40 mm', sub: 'inkl. massivem Spaltring' },
            { label: 'Materialstärke', value: '2,3 mm', sub: 'Formstabil & leicht' },
            { label: 'Gewicht', value: 'ca. 19 g', sub: 'Angenehm am Schlüsselbund' },
            { label: 'Gravur', value: 'Beidseitig', sub: 'Vorder- & Rückseite inklusive' },
        ],
    });

    // 3. Der Feierabend-Funke (Weizenglas)
    await generateDimensionSheet({
        slug: 'weizenglas-personalisiert',
        outFileName: 'weizenglas-dimensions.jpg',
        title: 'Der Feierabend-Funke – Maße & Details',
        subtitle: 'Bayerisches 0,5L Kristall-Weizenglas mit umlaufender 360°-Lasergravur',
        horizArrows: [
            { x1: 385, x2: 639, y: 205, dashedYFrom: 245, label: 'Kelch-Ø oben: 74 mm' },
        ],
        vertArrows: [
            { y1: 235, y2: 710, x: 815, dashedXFrom: 639, label: 'Gesamthöhe: 218 mm' },
        ],
        callouts: [
            { x: 512, y: 455, tx: 220, ty: 455, label: 'Gravurzone: 148 mm Höhe' },
        ],
        specs: [
            { label: 'Abmessungen', value: '218 × Ø 74 mm', sub: 'Klassische Weizen-Kontur' },
            { label: 'Füllvolumen', value: '0,5 Liter', sub: 'Randvoll 580 ml' },
            { label: 'Gewicht', value: 'ca. 450 g', sub: 'Starker Manufaktur-Eisboden' },
            { label: 'Veredelung', value: 'Spülmaschinenfest', sub: 'Dauerhafte 360°-Lasergravur' },
        ],
    });

    // 4. Der Seelen-Talisman (Massiv-Gold Dog-Tag)
    await generateDimensionSheet({
        slug: 'seelen-talisman',
        outFileName: 'seelen-talisman-dimensions.jpg',
        title: 'Der Seelen-Talisman – Maße & Details',
        subtitle: 'Massiver, spiegelpolierter Edelstahl-Schlüsselanhänger in edlem Gold-Finish',
        horizArrows: [
            { x1: 411, x2: 613, y: 185, dashedYFrom: 250, label: 'Flachring: Ø 32 mm' },
            { x1: 422, x2: 602, y: 760, dashedYFrom: 705, label: 'Plaketten-Breite: 28 mm' },
        ],
        vertArrows: [
            { y1: 410, y2: 717, x: 255, dashedXFrom: 422, label: 'Plaketten-Höhe: 50 mm' },
            { y1: 213, y2: 717, x: 785, dashedXFrom: 613, label: 'Gesamtlänge: 78 mm' },
        ],
        callouts: [],
        specs: [
            { label: 'Plaketten-Maß', value: '50 × 28 mm', sub: 'Gewölbte Dog-Tag Form' },
            { label: 'Materialstärke', value: '2,2 mm (Massiv)', sub: 'Rostfreier Edelstahl' },
            { label: 'Gewicht', value: 'ca. 24 g', sub: 'Spürbar wertige Haptik' },
            { label: 'Veredelung', value: 'Beidseitig', sub: 'Dunkle Faserlaser-Anlassgravur' },
        ],
    });

    // 5. Die Seelen-Pfote (Silber-Edelstahl Knochenmarke mit Pfoten-Durchbruch)
    await generateDimensionSheet({
        slug: 'seelen-pfote-hundemarke',
        outFileName: 'seelen-pfote-dimensions.jpg',
        title: 'Die Seelen-Pfote – Maße & Details',
        subtitle: 'Hochglanzpolierte Edelstahl-Hundemarke mit ausgestanztem Pfotenabdruck',
        horizArrows: [
            { x1: 288, x2: 736, y: 650, dashedYFrom: 575, label: 'Gesamtbreite: 40 mm' },
        ],
        vertArrows: [
            { y1: 342, y2: 588, x: 845, dashedXFrom: 736, label: 'Gesamthöhe: 26 mm' },
        ],
        callouts: [
            { x: 512, y: 374, tx: 310, ty: 255, label: 'Aufhängungs-Öse: Ø 5,0 mm' },
            { x: 660, y: 525, tx: 710, ty: 255, label: 'Echter Pfoten-Durchbruch' },
        ],
        specs: [
            { label: 'Knochen-Maß', value: '40 × 26 mm', sub: 'Ideal für kleine & große Hunde' },
            { label: 'Materialstärke', value: '1,8 mm', sub: 'Massiver V2A-Edelstahl' },
            { label: 'Gewicht', value: 'ca. 16 g', sub: 'Rostfrei, wasser- & kratzfest' },
            { label: 'Gravur', value: 'Beidseitig', sub: 'Name vorn + Notfall-Nr. hinten' },
        ],
    });
}

async function main() {
    const talismanSvg = createTalismanSvg();
    const talismanPng = await generateOverlaysForProduct('seelen-talisman', talismanSvg);
    const talismanMesh = buildTalisman3DMesh();
    const talismanGlb = buildSingleTexturedGlb(talismanMesh, talismanPng, 'SeelenTalisman');
    fs.writeFileSync(path.join(PUBLIC_STORAGE, 'seelen-talisman', 'seelen_talisman_3d.glb'), talismanGlb);

    const pfoteSvg = createPfoteSvg();
    const pfotePng = await generateOverlaysForProduct('seelen-pfote-hundemarke', pfoteSvg);
    const pfoteMesh = buildPfote3DMesh();
    const pfoteGlb = buildSingleTexturedGlb(pfoteMesh, pfotePng, 'SeelenPfote');
    fs.writeFileSync(path.join(PUBLIC_STORAGE, 'seelen-pfote-hundemarke', 'seelen_pfote_3d.glb'), pfoteGlb);

    await generateAllDimensionSheets();

    console.log(JSON.stringify({
        success: true,
        talisman_glb_bytes: talismanGlb.length,
        pfote_glb_bytes: pfoteGlb.length,
    }));
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
