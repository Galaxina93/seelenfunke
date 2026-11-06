# -*- coding: utf-8 -*-
"""
Funkenwerk Kamera Studio <-> Deep-Live-Cam Authentic Shared-Memory AI Bridge (v3)
=================================================================================
Integrates the original AI Face-Swapping, LAB Color Transfer & Landmark AR logic
from C:\\Users\\konta\\Documents\\Deep-Live-Cam directly into Funkenwerk Kamera Studio.

Key Fixes & Architecture (v3):
1. Single-Worker PID Lock in Shared Memory (offset 56): Prevents orphaned python.exe
   processes from competing on the shared memory buffer across restarts.
2. Direct Shared-Memory Source Face Path (offset 64..575): Reads the UTF-8 source
   portrait path directly from shared memory on every frame (plus fallback file),
   guaranteeing instant and reliable face switching whenever a preset or custom image
   is selected.
3. Dedicated Source Analyser (640x640, detection+recognition) + Live Detector (320x320):
   Never mutates det_model.input_size after prepare(), ensuring rock-solid 5-point
   ArcFace alignment on both high-res portraits and 16:9 webcam streams.
4. Every-Frame DirectML GPU InSwapper_128 + Original Deep-Live-Cam _fast_paste_back:
   Uses the exact inverse-affine corner bounding box and Gaussian-feathered elliptical
   mask (axes 0.44*128, blur 31x31 sigma 12) from Deep-Live-Cam's face_swapper.py.
"""

import os
import sys
import time
import mmap
import struct
import traceback
import cv2
import numpy as np

DLC_ROOT = r"C:\Users\konta\Documents\Deep-Live-Cam"
if DLC_ROOT not in sys.path:
    sys.path.insert(0, DLC_ROOT)

import onnxruntime as ort
import insightface
from insightface.app.common import Face

SHM_NAME = "FunkenwerkDeepFakeShm_v3"
MAX_W = 1920
MAX_H = 1080
FRAME_STRIDE = MAX_W * MAX_H * 4
HEADER_SIZE = 576  # 0..63 metadata + 64..575 UTF-8 source face path (512 bytes)
OUT_OFFSET = HEADER_SIZE + FRAME_STRIDE
TOTAL_SHM_SIZE = HEADER_SIZE + FRAME_STRIDE * 2

# Original Deep-Live-Cam soft elliptical alpha mask in 128x128 aligned-face space
# (matches _get_soft_alpha(128) in Deep-Live-Cam/modules/processors/frame/face_swapper.py)
SOFT_ALPHA_128 = np.zeros((128, 128), dtype=np.uint8)
cv2.ellipse(SOFT_ALPHA_128, (64, 64), (int(128 * 0.44), int(128 * 0.44)), 0, 0, 360, 255, -1)
SOFT_ALPHA_128 = cv2.GaussianBlur(SOFT_ALPHA_128, (31, 31), 12)

# Tight inner-lip mask in 128x128 aligned space (only active when user explicitly enables Mouth Mask)
INNER_MOUTH_MASK_128 = np.zeros((128, 128), dtype=np.float32)
cv2.ellipse(INNER_MOUTH_MASK_128, (64, 99), (13, 5), 0, 0, 360, 1.0, -1)
INNER_MOUTH_MASK_128 = cv2.GaussianBlur(INNER_MOUTH_MASK_128, (9, 9), 3.0)[:, :, np.newaxis]


def apply_dlc_lab_color_transfer_128(fake_128: np.ndarray, orig_128: np.ndarray) -> np.ndarray:
    """
    LAB color-space skin lighting adaptation based on Deep-Live-Cam's face_masking.py
    apply_color_transfer(), restricted to the inner cheek/nose region [34:94, 34:94]
    and blended gently (26%) so the source face identity and complexion remain intact.
    """
    try:
        src_f32 = fake_128.astype(np.float32) / 255.0
        tgt_f32 = orig_128.astype(np.float32) / 255.0

        src_lab = cv2.cvtColor(src_f32, cv2.COLOR_BGR2LAB)
        tgt_lab = cv2.cvtColor(tgt_f32, cv2.COLOR_BGR2LAB)

        src_roi = src_lab[34:94, 34:94]
        tgt_roi = tgt_lab[34:94, 34:94]

        s_mean, s_std = cv2.meanStdDev(src_roi)
        t_mean, t_std = cv2.meanStdDev(tgt_roi)

        s_mean = s_mean.reshape(1, 1, 3).astype(np.float32)
        s_std = np.maximum(s_std.reshape(1, 1, 3), 1e-4).astype(np.float32)
        t_mean = t_mean.reshape(1, 1, 3).astype(np.float32)
        t_std = t_std.reshape(1, 1, 3).astype(np.float32)

        scale = np.clip(t_std / s_std, 0.78, 1.25)
        res_lab = (src_lab - s_mean) * scale + t_mean
        res_bgr = cv2.cvtColor(res_lab, cv2.COLOR_LAB2BGR)
        transferred = np.clip(res_bgr * 255.0, 0, 255).astype(np.uint8)
        return cv2.addWeighted(transferred, 0.26, fake_128, 0.74, 0)
    except Exception:
        return fake_128


def enhance_face_128(fake_128: np.ndarray) -> np.ndarray:
    """Crisp detail enhancer on the 128x128 face crop."""
    try:
        blur = cv2.GaussianBlur(fake_128, (0, 0), 1.2)
        return cv2.addWeighted(fake_128, 1.38, blur, -0.38, 0)
    except Exception:
        return fake_128


def paste_swapped_face_to_overlay(
    overlay_bgra: np.ndarray,
    work_bgr: np.ndarray,
    bgr_fake: np.ndarray,
    M: np.ndarray,
    alpha_scale: float
):
    """
    Implements Deep-Live-Cam's _fast_paste_back (from face_swapper.py lines 450-510),
    computing the exact inverse affine bounding box from the 128x128 aligned corners,
    blending onto work_bgr (so multi-face and subsequent AR effects see the swapped face),
    and writing the feathered RGBA pixels into overlay_bgra.
    """
    h, w = work_bgr.shape[:2]
    face_h, face_w = 128, 128
    IM = cv2.invertAffineTransform(M)

    corners = np.array(
        [[0, 0], [face_w, 0], [face_w, face_h], [0, face_h]], dtype=np.float32
    )
    transformed = (IM[:, :2] @ corners.T).T + IM[:, 2]
    x1 = int(np.floor(transformed[:, 0].min()))
    x2 = int(np.ceil(transformed[:, 0].max()))
    y1 = int(np.floor(transformed[:, 1].min()))
    y2 = int(np.ceil(transformed[:, 1].max()))
    if x1 >= x2 or y1 >= y2:
        return None

    pad = 2
    y1p, y2p = max(0, y1 - pad), min(h, y2 + pad + 1)
    x1p, x2p = max(0, x1 - pad), min(w, x2 + pad + 1)
    crop_w, crop_h = x2p - x1p, y2p - y1p
    if crop_w <= 4 or crop_h <= 4:
        return None

    IM_crop = IM.copy()
    IM_crop[0, 2] -= x1p
    IM_crop[1, 2] -= y1p

    bgr_fake_crop = cv2.warpAffine(
        bgr_fake, IM_crop, (crop_w, crop_h), flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE
    )
    alpha_crop = cv2.warpAffine(
        SOFT_ALPHA_128, IM_crop, (crop_w, crop_h), flags=cv2.INTER_LINEAR, borderValue=0
    )
    if alpha_scale < 0.999:
        alpha_crop = (alpha_crop.astype(np.float32) * alpha_scale).astype(np.uint8)

    # Blend onto work_bgr in-place (just like Deep-Live-Cam _fast_paste_back)
    target_crop = work_bgr[y1p:y2p, x1p:x2p]
    alpha_3c = cv2.merge([alpha_crop, alpha_crop, alpha_crop])
    inv_alpha = 255 - alpha_3c
    a_fake = cv2.multiply(bgr_fake_crop, alpha_3c, scale=1.0 / 255.0)
    a_tgt = cv2.multiply(target_crop, inv_alpha, scale=1.0 / 255.0)
    blended_crop = cv2.add(a_fake, a_tgt)
    work_bgr[y1p:y2p, x1p:x2p] = blended_crop

    # Store the swapped RGB + feathered Alpha in overlay_bgra for C# high-res compositing
    overlay_bgra[y1p:y2p, x1p:x2p, :3] = bgr_fake_crop
    overlay_bgra[y1p:y2p, x1p:x2p, 3] = np.maximum(overlay_bgra[y1p:y2p, x1p:x2p, 3], alpha_crop)

    return (x1p, y1p, x2p, y2p)


def apply_ar_effect_rgba(overlay_bgra: np.ndarray, target_face, current_bgr: np.ndarray, ar_mode: int):
    """
    Draws real-time 5-point landmark AR effects into overlay_bgra using current_bgr
    (which already contains the swapped face if face-swapping is active!).
    """
    h, w = current_bgr.shape[:2]
    bbox = target_face.bbox.astype(np.int32)
    x1, y1, x2, y2 = bbox[0], bbox[1], bbox[2], bbox[3]
    kps = getattr(target_face, "kps", None)

    pad = int(max(x2 - x1, y2 - y1) * 0.35) + 16
    ax1, ay1 = max(0, x1 - pad), max(0, y1 - pad)
    ax2, ay2 = min(w, x2 + pad), min(h, y2 + pad)

    try:
        if ar_mode == 1 and kps is not None and len(kps) >= 2:
            # 1: Cyberpunk Neon Laser Visor
            le = kps[0].astype(np.float32)
            re = kps[1].astype(np.float32)
            center = ((le + re) * 0.5).astype(np.int32)
            dx, dy = re[0] - le[0], re[1] - le[1]
            dist = max(12.0, float(np.hypot(dx, dy)))
            angle = float(np.degrees(np.arctan2(dy, dx)))
            axes_outer = (int(dist * 1.18), int(dist * 0.38))
            axes_inner = (int(dist * 1.02), int(dist * 0.25))

            cv2.ellipse(overlay_bgra, tuple(center), axes_outer, angle, 0, 360, (255, 220, 0, 175), -1, cv2.LINE_AA)
            cv2.ellipse(overlay_bgra, tuple(center), axes_inner, angle, 0, 360, (255, 255, 190, 240), -1, cv2.LINE_AA)
            cv2.ellipse(overlay_bgra, tuple(center), axes_outer, angle, 0, 360, (180, 90, 255, 255), 2, cv2.LINE_AA)

        elif ar_mode == 2 and kps is not None and len(kps) >= 2:
            # 2: Anime / Cartoon Big Eyes (sampled from current_bgr so swapped face eyes are enlarged!)
            le = kps[0].astype(np.int32)
            re = kps[1].astype(np.int32)
            eye_dist = max(16.0, float(np.linalg.norm(re - le)))
            rad = int(eye_dist * 0.44)
            for ex, ey in (le, re):
                x0, y0 = max(0, ex - rad), max(0, ey - rad)
                x3, y3 = min(w, ex + rad), min(h, ey + rad)
                if x3 - x0 > 6 and y3 - y0 > 6:
                    sub = current_bgr[y0:y3, x0:x3]
                    sh, sw = sub.shape[:2]
                    grid_y, grid_x = np.mgrid[0:sh, 0:sw].astype(np.float32)
                    nx = (grid_x - sw * 0.5) / (sw * 0.5)
                    ny = (grid_y - sh * 0.5) / (sh * 0.5)
                    r = np.sqrt(nx * nx + ny * ny)
                    mask = r < 1.0
                    factor = np.where(mask, np.power(np.maximum(r, 1e-4), 0.55) / np.maximum(r, 1e-4), 1.0)
                    map_x = (sw * 0.5 + nx * factor * sw * 0.5).astype(np.float32)
                    map_y = (sh * 0.5 + ny * factor * sh * 0.5).astype(np.float32)
                    warped = cv2.remap(sub, map_x, map_y, cv2.INTER_LINEAR, borderMode=cv2.BORDER_REFLECT)
                    alpha_eye = np.clip((1.0 - r) * 2.5, 0.0, 1.0)
                    overlay_bgra[y0:y3, x0:x3, :3] = warped
                    overlay_bgra[y0:y3, x0:x3, 3] = np.maximum(overlay_bgra[y0:y3, x0:x3, 3], (alpha_eye * 255).astype(np.uint8))

        elif ar_mode == 3 and kps is not None and len(kps) >= 3:
            # 3: Alien / Giga-Morph
            nose = kps[2].astype(np.int32)
            fw = max(24, x2 - x1)
            fh = max(24, y2 - y1)
            rad = int(max(fw, fh) * 0.60)
            x0, y0 = max(0, nose[0] - rad), max(0, nose[1] - rad)
            x3, y3 = min(w, nose[0] + rad), min(h, nose[1] + rad)
            if x3 - x0 > 12 and y3 - y0 > 12:
                sub = current_bgr[y0:y3, x0:x3]
                sh, sw = sub.shape[:2]
                grid_y, grid_x = np.mgrid[0:sh, 0:sw].astype(np.float32)
                nx = (grid_x - sw * 0.5) / (sw * 0.5)
                ny = (grid_y - sh * 0.5) / (sh * 0.5)
                r = np.sqrt(nx * nx + ny * ny)
                mask = r < 1.0
                factor = np.where(mask, np.power(np.maximum(r, 1e-4), 1.55) / np.maximum(r, 1e-4), 1.0)
                map_x = (sw * 0.5 + nx * factor * sw * 0.5).astype(np.float32)
                map_y = (sh * 0.5 + ny * factor * sh * 0.5).astype(np.float32)
                warped = cv2.remap(sub, map_x, map_y, cv2.INTER_LINEAR, borderMode=cv2.BORDER_REFLECT)
                alpha_m = np.clip((1.0 - r) * 2.2, 0.0, 1.0)
                overlay_bgra[y0:y3, x0:x3, :3] = warped
                overlay_bgra[y0:y3, x0:x3, 3] = np.maximum(overlay_bgra[y0:y3, x0:x3, 3], (alpha_m * 255).astype(np.uint8))

        elif ar_mode == 4:
            # 4: Face Pixel Anonymizer
            fx1, fy1 = max(0, x1), max(0, y1)
            fx2, fy2 = min(w, x2), min(h, y2)
            if fx2 - fx1 > 12 and fy2 - fy1 > 12:
                sub = current_bgr[fy1:fy2, fx1:fx2]
                small = cv2.resize(sub, (max(6, (fx2 - fx1) // 14), max(6, (fy2 - fy1) // 14)), interpolation=cv2.INTER_LINEAR)
                mosaic = cv2.resize(small, (fx2 - fx1, fy2 - fy1), interpolation=cv2.INTER_NEAREST)
                overlay_bgra[fy1:fy2, fx1:fx2, :3] = mosaic
                overlay_bgra[fy1:fy2, fx1:fx2, 3] = 255

        elif ar_mode == 5:
            # 5: Terminator Cyborg Eye & HUD Target Lock
            if kps is not None and len(kps) >= 2:
                re = kps[1].astype(np.int32)
                cv2.circle(overlay_bgra, (re[0], re[1]), 16, (0, 20, 255, 160), -1, cv2.LINE_AA)
                cv2.circle(overlay_bgra, (re[0], re[1]), 8, (60, 120, 255, 230), -1, cv2.LINE_AA)
                cv2.circle(overlay_bgra, (re[0], re[1]), 4, (220, 245, 255, 255), -1, cv2.LINE_AA)
            cv2.rectangle(overlay_bgra, (max(0, x1), max(0, y1)), (min(w - 1, x2), min(h - 1, y2)), (20, 70, 255, 255), 2, cv2.LINE_AA)
            cv2.putText(overlay_bgra, "AI TARGET LOCKED", (max(4, x1), max(18, y1 - 6)), cv2.FONT_HERSHEY_SIMPLEX, 0.48, (30, 90, 255, 255), 1, cv2.LINE_AA)
    except Exception:
        pass

    return ax1, ay1, ax2, ax2 if False else ay2


def main():
    my_pid = os.getpid()
    config_dir = os.path.dirname(os.path.abspath(__file__))
    source_cfg_file = os.path.join(config_dir, "funkenwerk_dlc_source.txt")

    shm = mmap.mmap(-1, TOTAL_SHM_SIZE, tagname=SHM_NAME)

    # Register our PID in Shared Memory offset 56 so any older worker exits immediately
    shm.seek(56)
    shm.write(struct.pack("<i", my_pid))

    def set_status(status_code: int, infer_ms: int = 0, faces_cnt: int = 0, bbox=(0, 0, 0, 0)):
        shm.seek(28)
        shm.write(struct.pack("<iiiiiii", status_code, infer_ms, faces_cnt, bbox[0], bbox[1], bbox[2], bbox[3]))

    set_status(1, 0, 0)  # 1 = Loading models

    # 1. Source Portrait Analyser at full (640, 640) for crystal-clear 512-D ArcFace identity embeddings
    app_source = insightface.app.FaceAnalysis(
        name="buffalo_l",
        allowed_modules=["detection", "recognition"],
        providers=["CPUExecutionProvider"]
    )
    app_source.prepare(ctx_id=0, det_size=(640, 640))

    # 2. Live Webcam Face Detector at (320, 320) for accurate 5-point landmarks on 16:9 frames
    app_live = insightface.app.FaceAnalysis(
        name="buffalo_l",
        allowed_modules=["detection"],
        providers=["CPUExecutionProvider"]
    )
    app_live.prepare(ctx_id=0, det_size=(320, 320))
    det_live = app_live.models["detection"]

    # 3. Initialize Deep-Live-Cam's inswapper_128 model on DirectML GPU
    model_path = os.path.join(DLC_ROOT, "models", "inswapper_128_fp16.onnx")
    if not os.path.exists(model_path):
        model_path = os.path.join(DLC_ROOT, "models", "inswapper_128.onnx")

    avail = ort.get_available_providers()
    swap_providers = ["DmlExecutionProvider", "CPUExecutionProvider"] if "DmlExecutionProvider" in avail else ["CPUExecutionProvider"]
    swapper = insightface.model_zoo.get_model(model_path, providers=swap_providers)

    source_face = None
    loaded_source_path = ""
    source_embedding_cache = {}
    prev_kps = None

    def read_shm_source_path() -> str:
        try:
            shm.seek(64)
            raw = shm.read(512)
            nul = raw.find(b"\x00")
            if nul >= 0:
                raw = raw[:nul]
            path = raw.decode("utf-8", errors="ignore").lstrip("\ufeff").strip()
            if path and os.path.exists(path):
                return path
        except Exception:
            pass
        if os.path.exists(source_cfg_file):
            try:
                with open(source_cfg_file, "r", encoding="utf-8-sig") as f:
                    p = f.read().lstrip("\ufeff").strip()
                if p and os.path.exists(p):
                    return p
            except Exception:
                pass
        return ""

    def ensure_source_face_loaded():
        nonlocal source_face, loaded_source_path
        path = read_shm_source_path()
        if not path:
            # Fallback to default Elon Musk portrait in Deep-Live-Cam media folder
            default_musk = os.path.join(DLC_ROOT, "media", "Elon_Musk_-_54820081119_(cropped).jpg.webp")
            if os.path.exists(default_musk):
                path = default_musk
            else:
                default_one = os.path.join(DLC_ROOT, "media", "1.png")
                if os.path.exists(default_one):
                    path = default_one

        if not path or not os.path.exists(path):
            return

        norm_path = os.path.normcase(os.path.abspath(path))
        if norm_path == loaded_source_path and source_face is not None:
            return

        if norm_path in source_embedding_cache:
            source_face = source_embedding_cache[norm_path]
            loaded_source_path = norm_path
            return

        try:
            img = cv2.imdecode(np.fromfile(path, dtype=np.uint8), cv2.IMREAD_COLOR)
            if img is None:
                return
            faces = app_source.get(img)
            if not faces:
                # Try padded image if portrait is tightly cropped
                pad_img = cv2.copyMakeBorder(img, 64, 64, 64, 64, cv2.BORDER_CONSTANT, value=(128, 128, 128))
                faces = app_source.get(pad_img)
            if faces:
                # Choose the largest prominent face in the portrait
                faces = sorted(faces, key=lambda x: (x.bbox[2] - x.bbox[0]) * (x.bbox[3] - x.bbox[1]), reverse=True)
                best_face = faces[0]
                if hasattr(best_face, "normed_embedding") and best_face.normed_embedding is not None:
                    source_face = best_face
                    loaded_source_path = norm_path
                    source_embedding_cache[norm_path] = best_face
        except Exception:
            traceback.print_exc()

    ensure_source_face_loaded()
    set_status(2 if source_face is not None else 3, 0, 0)

    while True:
        shm.seek(0)
        hdr = shm.read(28)
        cmd, w, h, flags, opacity, ar_mode, src_ver = struct.unpack("<iiiiiii", hdr)

        # Single-instance check: exit immediately if a newer worker registered its PID
        shm.seek(56)
        active_pid = struct.unpack("<i", shm.read(4))[0]
        if active_pid != 0 and active_pid != my_pid:
            break

        if cmd == 99:
            break

        if cmd != 1:
            time.sleep(0.0015)
            continue

        # Always verify source face path from Shared Memory so preset changes take effect immediately
        ensure_source_face_loaded()

        if w <= 0 or h <= 0 or w > MAX_W or h > MAX_H:
            shm.seek(0)
            shm.write(struct.pack("<i", 2))
            continue

        t0 = time.perf_counter()
        try:
            byte_len = w * h * 4
            shm.seek(HEADER_SIZE)
            raw_bytes = shm.read(byte_len)
            frame_bgra = np.frombuffer(raw_bytes, dtype=np.uint8).reshape((h, w, 4))
            work_bgr = frame_bgra[:, :, :3].copy()

            many_faces = (flags & 32) != 0
            max_faces = 3 if many_faces else 1

            bboxes, kpss = det_live.detect(work_bgr, max_num=max_faces, metric="default")
            faces_count = 0 if bboxes is None else bboxes.shape[0]

            overlay_bgra = np.zeros((h, w, 4), dtype=np.uint8)
            union_bbox = [w, h, 0, 0]

            if faces_count > 0 and kpss is not None:
                areas = (bboxes[:, 2] - bboxes[:, 0]) * (bboxes[:, 3] - bboxes[:, 1])
                order = np.argsort(areas)[::-1]

                swap_enabled = (flags & 1) != 0
                mouth_mask = (flags & 2) != 0
                color_corr = (flags & 4) != 0
                enhancer = (flags & 16) != 0
                alpha_scale = float(np.clip(opacity / 100.0, 0.1, 1.0))

                for idx_pos, f_idx in enumerate(order):
                    bbox = bboxes[f_idx, 0:4]
                    kps = kpss[f_idx]

                    # Subtle temporal landmark smoothing to prevent sub-pixel jitter while keeping instant tracking
                    if idx_pos == 0:
                        if prev_kps is not None:
                            move_dist = float(np.mean(np.linalg.norm(kps - prev_kps, axis=1)))
                            if move_dist < 12.0:
                                kps = prev_kps * 0.22 + kps * 0.78
                        prev_kps = kps.copy()

                    tf = Face(bbox=bbox, kps=kps, det_score=float(bboxes[f_idx, 4]))

                    if swap_enabled and source_face is not None:
                        bgr_fake, M = swapper.get(work_bgr, tf, source_face, paste_back=False)

                        if bgr_fake is not None and isinstance(bgr_fake, np.ndarray):
                            if color_corr or mouth_mask:
                                orig_128 = cv2.warpAffine(work_bgr, M, (128, 128), flags=cv2.INTER_LINEAR, borderValue=0.0)
                                if color_corr:
                                    bgr_fake = apply_dlc_lab_color_transfer_128(bgr_fake, orig_128)
                                if mouth_mask:
                                    bgr_fake = (orig_128 * INNER_MOUTH_MASK_128 + bgr_fake * (1.0 - INNER_MOUTH_MASK_128)).astype(np.uint8)

                            if enhancer:
                                bgr_fake = enhance_face_128(bgr_fake)

                            pasted_box = paste_swapped_face_to_overlay(overlay_bgra, work_bgr, bgr_fake, M, alpha_scale)
                            if pasted_box is not None:
                                union_bbox[0] = min(union_bbox[0], pasted_box[0])
                                union_bbox[1] = min(union_bbox[1], pasted_box[1])
                                union_bbox[2] = max(union_bbox[2], pasted_box[2])
                                union_bbox[3] = max(union_bbox[3], pasted_box[3])

                    if ar_mode > 0:
                        ax1, ay1, ax2, ay2 = apply_ar_effect_rgba(overlay_bgra, tf, work_bgr, ar_mode)
                        union_bbox[0] = min(union_bbox[0], ax1)
                        union_bbox[1] = min(union_bbox[1], ay1)
                        union_bbox[2] = max(union_bbox[2], ax2)
                        union_bbox[3] = max(union_bbox[3], ay2)
            else:
                prev_kps = None

            if union_bbox[2] > union_bbox[0] and union_bbox[3] > union_bbox[1]:
                primary_bbox = (int(union_bbox[0]), int(union_bbox[1]), int(union_bbox[2]), int(union_bbox[3]))
                shm.seek(OUT_OFFSET)
                shm.write(overlay_bgra.tobytes())
            else:
                primary_bbox = (0, 0, 0, 0)

            dt_ms = int((time.perf_counter() - t0) * 1000.0)
            set_status(2 if (source_face is not None or ar_mode > 0) else 3, dt_ms, faces_count, primary_bbox)
        except Exception:
            traceback.print_exc()
            dt_ms = int((time.perf_counter() - t0) * 1000.0)
            set_status(-1, dt_ms, 0)

        # Signal frame processed (cmd = 2)
        shm.seek(0)
        shm.write(struct.pack("<i", 2))


if __name__ == "__main__":
    main()
