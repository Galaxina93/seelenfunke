#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Funkenwerk OS Linux - Local Zero-Cloud Sense Daemon (127.0.0.1:17701)
1. 100% Offline German Wake-Word Engine (Vosk + PipeWire parec, 0 Cloud Tokens)
2. 100% Offline Biometric Owner Guard (OpenCV YuNet + SFace ONNX, RAM-only, 0 Cloud Upload)
"""

import base64
import json
import math
import os
import re
import subprocess
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

os.environ.setdefault("OMP_NUM_THREADS", "2")
os.environ.setdefault("ORT_INTRA_OP_NUM_THREADS", "2")
os.environ.setdefault("ORT_INTER_OP_NUM_THREADS", "1")

HOME_DIR = os.path.expanduser("~")
SENSE_BASE = os.environ.get("FW_SENSE_DIR", os.path.join(HOME_DIR, ".local", "share", "funkenwerk-sense"))
SITE_PACKAGES = os.path.join(SENSE_BASE, "site-packages")
MODELS_DIR = os.path.join(SENSE_BASE, "models")

if os.path.isdir(SITE_PACKAGES) and SITE_PACKAGES not in sys.path:
    sys.path.insert(0, SITE_PACKAGES)

VOSK_AVAILABLE = False
CV2_AVAILABLE = False

try:
    import numpy as np
    import cv2
    CV2_AVAILABLE = True
except Exception as e:
    sys.stderr.write(f"[fw-sense] OpenCV import note: {e}\n")

try:
    import vosk
    vosk.SetLogLevel(-1)
    VOSK_AVAILABLE = True
except Exception as e:
    sys.stderr.write(f"[fw-sense] Vosk import note: {e}\n")

OCR_AVAILABLE = False
PDFIUM_AVAILABLE = False

try:
    from rapidocr_onnxruntime import RapidOCR
    OCR_AVAILABLE = True
except Exception as e:
    sys.stderr.write(f"[fw-sense] RapidOCR import note: {e}\n")

try:
    import pypdfium2 as pdfium
    PDFIUM_AVAILABLE = True
except Exception as e:
    sys.stderr.write(f"[fw-sense] pypdfium2 import note: {e}\n")


class LocalSenseEngine:
    DIRECT_WAKE_PHRASES = (
        "funkira",
        "funk ihre",
        "funk ihrer",
        "funkt ihre",
        "funkt ihrer",
        "funken ihre",
        "funken ihrer",
        "funk kira",
        "funkt kira",
        "funken kira",
        "von kira",
        "vom kira",
        "funk ira",
        "funk tiere",
        "funky",
        "funki",
        "hey kira",
        "hallo kira",
        "hi kira",
        "komm her",
        "komm mal her",
        "aufwachen",
    )
    WAKE_REGEX = re.compile(
        r"\b("
        r"funk\w*\s*(ihr\w*|kir\w*|ir\w*|tier\w*|klar\w*|chiar\w*|mir\w*|hier)"
        r"|vo[nm]\s+kir\w*"
        r"|funkir\w*"
        r"|(hey|hallo|hi|na)\s+(kir\w*|funk\w*)"
        r")\b"
    )

    def __init__(self):
        self.lock = threading.Lock()
        self.face_lock = threading.Lock()
        self.ocr_lock = threading.Lock()
        self.vosk_model = None
        self.detector = None
        self.recognizer = None
        self.ocr_engine = None

        self.want_listening = False
        self.is_listening = False
        self.wake_triggered = False
        self.wake_phrase = ""
        self.wake_ts = 0.0
        self.last_trigger_ts = 0.0
        self.parec_proc = None
        self.active_mic_source = ""
        self.last_heard = ""

        self._init_models()
        self.worker = threading.Thread(target=self._audio_loop, daemon=True)
        self.worker.start()

    def _init_models(self):
        vosk_dir = os.path.join(MODELS_DIR, "vosk-model-small-de-0.15")
        if VOSK_AVAILABLE and os.path.isdir(vosk_dir):
            try:
                self.vosk_model = vosk.Model(vosk_dir)
            except Exception as e:
                sys.stderr.write(f"[fw-sense] Vosk model load error: {e}\n")

        yunet_path = os.path.join(MODELS_DIR, "face_detection_yunet_2023mar.onnx")
        sface_path = os.path.join(MODELS_DIR, "face_recognition_sface_2021dec.onnx")
        if CV2_AVAILABLE and os.path.isfile(yunet_path) and os.path.isfile(sface_path):
            try:
                self.detector = cv2.FaceDetectorYN.create(yunet_path, "", (320, 320), 0.72, 0.3, 5000)
                self.recognizer = cv2.FaceRecognizerSF.create(sface_path, "")
            except Exception as e:
                sys.stderr.write(f"[fw-sense] YuNet/SFace init error: {e}\n")

    def set_wake_listening(self, active: bool):
        with self.lock:
            self.want_listening = bool(active)
            if active:
                self.wake_triggered = False
                self.wake_phrase = ""
            else:
                self._stop_parec_locked()

    def _stop_parec_locked(self):
        proc = self.parec_proc
        self.parec_proc = None
        self.is_listening = False
        if proc is not None:
            try:
                proc.terminate()
                proc.wait(timeout=0.5)
            except Exception:
                try:
                    proc.kill()
                except Exception:
                    pass

    def poll_wake(self) -> dict:
        with self.lock:
            trig = self.wake_triggered
            phrase = self.wake_phrase
            ts = self.wake_ts
            if trig:
                self.wake_triggered = False
                self.wake_phrase = ""
            return {
                "ok": True,
                "voskReady": self.vosk_model is not None,
                "listening": self.is_listening,
                "wantListening": self.want_listening,
                "micSource": self.active_mic_source,
                "lastHeard": self.last_heard,
                "triggered": trig,
                "phrase": phrase,
                "timestamp": ts
            }

    def _find_preferred_mic_source(self) -> str:
        try:
            res = subprocess.run(
                ["pactl", "list", "short", "sources"],
                capture_output=True,
                text=True,
                timeout=1.5
            )
            if res.returncode != 0 or not res.stdout:
                return ""
            inputs = []
            for line in res.stdout.splitlines():
                parts = line.strip().split()
                if len(parts) < 2:
                    continue
                src_name = parts[1]
                if ".monitor" in src_name.lower():
                    continue
                inputs.append(src_name)
            for s in inputs:
                low = s.lower()
                if "c920" in low or "webcam" in low or "logitech" in low:
                    return s
            for s in inputs:
                if "usb" in s.lower():
                    return s
            return inputs[0] if inputs else ""
        except Exception:
            return ""

    def _check_text_for_wake(self, text: str) -> str:
        raw = (text or "").strip().lower()
        if not raw or raw == "[unk]":
            return ""
        cleaned = re.sub(r"[^a-zäöüß\s]", " ", raw)
        cleaned = re.sub(r"\s+", " ", cleaned).strip()
        if not cleaned:
            return ""
        with self.lock:
            self.last_heard = cleaned[:80]

        for tok in self.DIRECT_WAKE_PHRASES:
            if tok in cleaned:
                return cleaned
        if self.WAKE_REGEX.search(cleaned):
            return cleaned
        words = cleaned.split()
        if len(words) <= 3 and ("kira" in words or "kiara" in words or "chiara" in words):
            return cleaned
        return ""

    def _audio_loop(self):
        while True:
            if self.vosk_model is None:
                time.sleep(1.0)
                continue

            with self.lock:
                should_listen = self.want_listening

            if not should_listen:
                time.sleep(0.18)
                continue

            if (time.time() - self.last_trigger_ts) < 2.2:
                time.sleep(0.25)
                continue

            # Unconstrained German recognizer so OOV names like 'Funkira' are phonetically decoded instead of stripped to [unk]!
            rec = vosk.KaldiRecognizer(self.vosk_model, 16000)
            rec.SetWords(False)

            proc = None
            try:
                mic_src = self._find_preferred_mic_source()
                if os.path.isfile("/usr/bin/parec"):
                    cmd = ["/usr/bin/parec", "--rate=16000", "--channels=1", "--format=s16le", "--latency-msec=80"]
                    if mic_src:
                        cmd.append(f"--device={mic_src}")
                else:
                    cmd = ["pw-cat", "-r", "--rate", "16000", "--channels", "1", "--format", "s16"]
                    if mic_src:
                        cmd.extend(["--target", mic_src])
                    cmd.append("-")

                proc = subprocess.Popen(
                    cmd,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.DEVNULL,
                    bufsize=3200
                )
                with self.lock:
                    if not self.want_listening:
                        proc.terminate()
                        continue
                    self.parec_proc = proc
                    self.active_mic_source = mic_src or "default"
                    self.is_listening = True

                while True:
                    with self.lock:
                        if not self.want_listening or self.parec_proc is not proc:
                            break
                    chunk = proc.stdout.read(3200) if proc.stdout else b""
                    if not chunk:
                        time.sleep(0.05)
                        break

                    matched = ""
                    if rec.AcceptWaveform(chunk):
                        res = json.loads(rec.Result() or "{}")
                        matched = self._check_text_for_wake(res.get("text", ""))
                    else:
                        pres = json.loads(rec.PartialResult() or "{}")
                        matched = self._check_text_for_wake(pres.get("partial", ""))

                    if matched:
                        now_ts = time.time()
                        with self.lock:
                            self.wake_triggered = True
                            self.wake_phrase = matched
                            self.wake_ts = now_ts
                            self.last_trigger_ts = now_ts
                            self.want_listening = False
                            self._stop_parec_locked()
                        break
            except Exception as e:
                sys.stderr.write(f"[fw-sense] parec loop error: {e}\n")
                time.sleep(0.5)
            finally:
                with self.lock:
                    if self.parec_proc is proc:
                        self._stop_parec_locked()
                if proc is not None:
                    try:
                        proc.terminate()
                    except Exception:
                        pass

    def analyze_face(self, jpeg_b64: str, owner_embedding: list | None, include_embedding: bool, known_companions: list | None = None) -> dict:
        if not CV2_AVAILABLE or self.detector is None or self.recognizer is None:
            return {"ok": False, "faceReady": False, "faceDetected": False, "identity": "unavailable"}

        if not jpeg_b64:
            return {"ok": False, "faceReady": True, "faceDetected": False, "identity": "none"}

        if "," in jpeg_b64:
            jpeg_b64 = jpeg_b64.split(",", 1)[1]

        try:
            raw = base64.b64decode(jpeg_b64)
            arr = np.frombuffer(raw, dtype=np.uint8)
            img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
            if img is None or img.shape[0] < 32 or img.shape[1] < 32:
                return {"ok": False, "faceReady": True, "faceDetected": False, "identity": "none"}

            h0, w0 = img.shape[:2]
            if w0 > 640:
                scale = 640.0 / float(w0)
                img = cv2.resize(img, (640, max(32, int(round(h0 * scale)))), interpolation=cv2.INTER_AREA)

            h, w = img.shape[:2]
            extracted_faces = []
            with self.face_lock:
                self.detector.setInputSize((w, h))
                _, faces = self.detector.detect(img)

                if faces is None or len(faces) == 0:
                    return {"ok": True, "faceReady": True, "faceDetected": False, "faceCount": 0, "identity": "none"}

                sorted_faces = sorted(faces, key=lambda f: float(f[2]) * float(f[3]), reverse=True)
                for idx, f in enumerate(sorted_faces[:5]):
                    fx, fy, fw, fh = float(f[0]), float(f[1]), float(f[2]), float(f[3])
                    det_score = float(f[-1])
                    rel_w = fw / float(w)
                    if idx > 0 and (rel_w < 0.044 or det_score < 0.68):
                        continue
                    cx = max(0.0, min(1.0, (fx + fw * 0.5) / float(w)))
                    cy = max(0.0, min(1.0, (fy + fh * 0.45) / float(h)))
                    mirror_x = 1.0 - cx
                    side = "links" if mirror_x < 0.40 else ("rechts" if mirror_x > 0.60 else "mitte")

                    aligned = self.recognizer.alignCrop(img, f)
                    feat = self.recognizer.feature(aligned).flatten().astype(np.float32)
                    norm = float(np.linalg.norm(feat))
                    if norm > 1e-6:
                        feat = feat / norm
                    extracted_faces.append({
                        "cx": cx,
                        "cy": cy,
                        "relWidth": rel_w,
                        "score": det_score,
                        "side": side,
                        "feat": feat
                    })

            if not extracted_faces:
                return {"ok": True, "faceReady": True, "faceDetected": False, "faceCount": 0, "identity": "none"}

            primary = extracted_faces[0]
            ref_owner = None
            if owner_embedding and len(owner_embedding) == len(primary["feat"]):
                cand_ref = np.array(owner_embedding, dtype=np.float32)
                rn = float(np.linalg.norm(cand_ref))
                if rn > 1e-6:
                    ref_owner = cand_ref / rn

            comp_refs = []
            if isinstance(known_companions, list):
                for item in known_companions[:25]:
                    if not isinstance(item, dict):
                        continue
                    vec = item.get("vector")
                    if isinstance(vec, list) and len(vec) == len(primary["feat"]):
                        cv = np.array(vec, dtype=np.float32)
                        cn = float(np.linalg.norm(cv))
                        if cn > 1e-6:
                            comp_refs.append({
                                "contactId": str(item.get("contactId", "")),
                                "name": str(item.get("name", "")).strip(),
                                "relationship": str(item.get("relationship", "")).strip(),
                                "vec": cv / cn
                            })

            owner_face_idx = -1
            best_owner_cos = -1.0
            if ref_owner is not None:
                for i, fd in enumerate(extracted_faces):
                    cval = float(np.dot(fd["feat"], ref_owner))
                    fd["ownerCos"] = cval
                    if cval > best_owner_cos:
                        best_owner_cos = cval
                        if cval >= 0.363:
                            owner_face_idx = i
            else:
                best_owner_cos = 0.0

            owner_present = owner_face_idx >= 0
            recognized_companions = []
            unknown_sides = []
            largest_unknown_feat = None

            for i, fd in enumerate(extracted_faces):
                if i == owner_face_idx:
                    continue
                matched_comp = None
                best_comp_cos = -1.0
                for cref in comp_refs:
                    cc = float(np.dot(fd["feat"], cref["vec"]))
                    if cc >= 0.375 and cc > best_comp_cos:
                        best_comp_cos = cc
                        matched_comp = cref
                if matched_comp is not None:
                    recognized_companions.append({
                        "contactId": matched_comp["contactId"],
                        "name": matched_comp["name"],
                        "relationship": matched_comp["relationship"],
                        "side": fd["side"],
                        "cosine": round(best_comp_cos, 4)
                    })
                else:
                    unknown_sides.append(fd["side"])
                    if largest_unknown_feat is None:
                        largest_unknown_feat = fd["feat"]

            companions_count = len(recognized_companions) + len(unknown_sides)

            if ref_owner is None:
                identity = "unenrolled"
            elif owner_present and companions_count > 0:
                identity = "alina_with_companion"
            elif owner_present:
                identity = "alina"
            elif best_owner_cos >= 0.275 or primary["relWidth"] < 0.065 or primary["score"] < 0.80:
                identity = "uncertain"
            else:
                identity = "visitor"

            focus_face = extracted_faces[owner_face_idx] if owner_present else primary
            out = {
                "ok": True,
                "faceReady": True,
                "faceDetected": True,
                "faceCount": len(extracted_faces),
                "ownerPresent": owner_present,
                "companionsCount": companions_count,
                "recognizedCompanions": recognized_companions,
                "unknownCompanionsCount": len(unknown_sides),
                "unknownCompanionSides": unknown_sides,
                "cx": round(focus_face["cx"], 4),
                "cy": round(focus_face["cy"], 4),
                "relWidth": round(focus_face["relWidth"], 4),
                "score": round(focus_face["score"], 4),
                "cosine": round(max(0.0, best_owner_cos), 4),
                "identity": identity
            }
            if include_embedding:
                out["embedding"] = [round(float(v), 6) for v in primary["feat"].tolist()]
            if owner_present and largest_unknown_feat is not None:
                out["companionEmbedding"] = [round(float(v), 6) for v in largest_unknown_feat.tolist()]
            return out
        except Exception as e:
            return {"ok": False, "faceReady": True, "faceDetected": False, "identity": "error", "message": str(e)}

    def analyze_voice(self, pcm16_b64: str, owner_voice_embedding: list | None, include_embedding: bool) -> dict:
        if not CV2_AVAILABLE or not pcm16_b64:
            return {"ok": False, "voiced": False, "identity": "unavailable"}
        try:
            raw = base64.b64decode(pcm16_b64)
            usable = len(raw) - (len(raw) % 2)
            if usable < 6400:
                return {"ok": True, "voiced": False, "identity": "too_short"}

            samples = np.frombuffer(raw[:usable], dtype=np.int16).astype(np.float32) / 32768.0
            sr = 16000
            win = 400  # 25 ms
            hop = 160  # 10 ms
            if len(samples) < win + hop * 12:
                return {"ok": True, "voiced": False, "identity": "too_short"}

            hann = np.hanning(win).astype(np.float32)
            freqs = np.fft.rfftfreq(512, d=1.0 / sr)
            band_edges = np.geomspace(85.0, 7400.0, 33)

            voiced_specs = []
            f0_vals = []
            centroids = []
            min_lag = int(sr / 360.0)
            max_lag = int(sr / 75.0)

            for start in range(0, len(samples) - win, hop):
                frame = samples[start:start + win]
                rms = float(np.sqrt(np.mean(frame * frame)))
                if rms < 0.014:
                    continue
                wframe = frame * hann
                mag = np.abs(np.fft.rfft(wframe, n=512)) + 1e-7

                sub = np.zeros(32, dtype=np.float32)
                for b in range(32):
                    mask = (freqs >= band_edges[b]) & (freqs < band_edges[b + 1])
                    if np.any(mask):
                        sub[b] = float(np.log(np.mean(mag[mask]) + 1e-7))
                voiced_specs.append(sub)

                denom = float(np.sum(mag))
                if denom > 1e-5:
                    centroids.append(float(np.sum(freqs * mag) / denom) / 4000.0)

                ac = np.correlate(wframe, wframe, mode="full")[win - 1:]
                if len(ac) > max_lag and ac[0] > 1e-5:
                    seg = ac[min_lag:max_lag]
                    pk_idx = int(np.argmax(seg))
                    if seg[pk_idx] / ac[0] >= 0.28:
                        lag = min_lag + pk_idx
                        f0_vals.append((float(sr) / float(lag)) / 300.0)

            if len(voiced_specs) < 14:
                return {"ok": True, "voiced": False, "identity": "silence"}

            mat = np.stack(voiced_specs, axis=0)
            # Cepstral mean normalization across utterance for mic-distance invariance
            cmn = mat - np.mean(mat, axis=1, keepdims=True)
            mean_32 = np.mean(cmn, axis=0)
            std_12 = np.std(cmn[:, :12], axis=0)

            f0_arr = np.array(f0_vals, dtype=np.float32) if f0_vals else np.array([0.62], dtype=np.float32)
            cent_arr = np.array(centroids, dtype=np.float32) if centroids else np.array([0.45], dtype=np.float32)
            prosody_4 = np.array([
                float(np.mean(f0_arr)),
                float(np.median(f0_arr)),
                float(np.std(f0_arr)),
                float(np.mean(cent_arr))
            ], dtype=np.float32)

            vec48 = np.concatenate([mean_32, std_12, prosody_4]).astype(np.float32)
            vnorm = float(np.linalg.norm(vec48))
            if vnorm > 1e-6:
                vec48 = vec48 / vnorm

            cosine = 0.0
            identity = "unenrolled"
            if owner_voice_embedding and len(owner_voice_embedding) == 48:
                ref = np.array(owner_voice_embedding, dtype=np.float32)
                rn = float(np.linalg.norm(ref))
                if rn > 1e-6:
                    ref = ref / rn
                cosine = float(np.dot(vec48, ref))
                if cosine >= 0.83:
                    identity = "alina"
                elif cosine < 0.68:
                    identity = "other_speaker"
                else:
                    identity = "uncertain"

            out = {
                "ok": True,
                "voiced": True,
                "voicedFrames": len(voiced_specs),
                "cosine": round(cosine, 4),
                "identity": identity
            }
            if include_embedding:
                out["embedding"] = [round(float(v), 6) for v in vec48.tolist()]
            return out
        except Exception as e:
            return {"ok": False, "voiced": False, "identity": "error", "message": str(e)}

    def _ensure_ocr_locked(self):
        if self.ocr_engine is None and OCR_AVAILABLE:
            try:
                self.ocr_engine = RapidOCR()
            except Exception as e:
                sys.stderr.write(f"[fw-sense] RapidOCR init error: {e}\n")

    def extract_document_ocr(self, file_path: str = "", image_b64: str = "", max_pages: int = 5) -> dict:
        if not CV2_AVAILABLE:
            return {"ok": False, "ocrReady": False, "text": "", "message": "OpenCV unavailable"}

        safe_max_pages = max(1, min(12, int(max_pages or 5)))
        collected_pages = []
        used_ocr = False
        page_count = 0

        try:
            if file_path:
                real_root = os.path.realpath(os.path.join(HOME_DIR, "Meine_Daten"))
                real_target = os.path.realpath(file_path)
                if not (real_target == real_root or real_target.startswith(real_root + os.sep)):
                    return {"ok": False, "text": "", "message": "Path outside Meine_Daten blocked"}
                if not os.path.isfile(real_target):
                    return {"ok": False, "text": "", "message": "File not found"}

                ext = os.path.splitext(real_target)[1].lower()
                if ext == ".pdf" and PDFIUM_AVAILABLE:
                    pdf = pdfium.PdfDocument(real_target)
                    page_count = len(pdf)
                    limit = min(page_count, safe_max_pages)
                    for idx in range(limit):
                        page = pdf[idx]
                        txt_page = page.get_textpage()
                        embedded = (txt_page.get_text_range() or "").strip()
                        if len(embedded) >= 32:
                            collected_pages.append(embedded)
                        elif OCR_AVAILABLE:
                            bitmap = page.render(scale=2.0)
                            pil_img = bitmap.to_pil()
                            rgb = np.array(pil_img)
                            bgr = cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR)
                            ocr_txt = self._run_ocr_on_bgr(bgr)
                            if ocr_txt:
                                used_ocr = True
                                collected_pages.append(ocr_txt)
                    pdf.close()
                elif ext in (".jpg", ".jpeg", ".png", ".webp", ".bmp", ".tif", ".tiff") and OCR_AVAILABLE:
                    page_count = 1
                    raw_bytes = np.fromfile(real_target, dtype=np.uint8)
                    img = cv2.imdecode(raw_bytes, cv2.IMREAD_COLOR)
                    if img is not None:
                        ocr_txt = self._run_ocr_on_bgr(img)
                        if ocr_txt:
                            used_ocr = True
                            collected_pages.append(ocr_txt)
            elif image_b64 and OCR_AVAILABLE:
                if "," in image_b64:
                    image_b64 = image_b64.split(",", 1)[1]
                raw = base64.b64decode(image_b64)
                arr = np.frombuffer(raw, dtype=np.uint8)
                img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
                if img is not None:
                    page_count = 1
                    ocr_txt = self._run_ocr_on_bgr(img)
                    if ocr_txt:
                        used_ocr = True
                        collected_pages.append(ocr_txt)

            full_text = "\n\n".join(p for p in collected_pages if p).strip()
            return {
                "ok": True,
                "ocrReady": OCR_AVAILABLE,
                "pdfReady": PDFIUM_AVAILABLE,
                "ocrUsed": used_ocr,
                "pageCount": page_count,
                "text": full_text
            }
        except Exception as e:
            return {"ok": False, "ocrReady": OCR_AVAILABLE, "text": "", "message": str(e)}

    def _run_ocr_on_bgr(self, img_bgr) -> str:
        h, w = img_bgr.shape[:2]
        if max(h, w) > 2000:
            scale = 2000.0 / float(max(h, w))
            img_bgr = cv2.resize(img_bgr, (max(32, int(w * scale)), max(32, int(h * scale))), interpolation=cv2.INTER_AREA)
        with self.ocr_lock:
            self._ensure_ocr_locked()
            if self.ocr_engine is None:
                return ""
            result, _ = self.ocr_engine(img_bgr)
        if not result:
            return ""
        lines = []
        for entry in result:
            if isinstance(entry, (list, tuple)) and len(entry) >= 2:
                txt = str(entry[1]).strip()
                if txt:
                    lines.append(txt)
        return "\n".join(lines)


ENGINE = LocalSenseEngine()


class SenseHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        return

    def _send_json(self, code: int, payload: dict):
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path.startswith("/health"):
            self._send_json(200, {
                "ok": True,
                "voskReady": ENGINE.vosk_model is not None,
                "faceReady": ENGINE.detector is not None and ENGINE.recognizer is not None,
                "ocrReady": OCR_AVAILABLE,
                "pdfReady": PDFIUM_AVAILABLE,
                "listening": ENGINE.is_listening
            })
            return
        if self.path.startswith("/wake-poll"):
            self._send_json(200, ENGINE.poll_wake())
            return
        self._send_json(404, {"ok": False})

    def do_POST(self):
        length = int(self.headers.get("Content-Length", "0") or "0")
        if length > 6 * 1024 * 1024:
            self._send_json(413, {"ok": False, "message": "Payload too large"})
            return
        raw = self.rfile.read(length) if length > 0 else b"{}"
        try:
            data = json.loads(raw.decode("utf-8"))
        except Exception:
            data = {}

        if self.path.startswith("/wake-control"):
            ENGINE.set_wake_listening(bool(data.get("active", False)))
            self._send_json(200, ENGINE.poll_wake())
            return

        if self.path.startswith("/face-analyze"):
            res = ENGINE.analyze_face(
                str(data.get("jpegBase64", "")),
                data.get("ownerEmbedding"),
                bool(data.get("includeEmbedding", False)),
                data.get("knownCompanions")
            )
            self._send_json(200, res)
            return

        if self.path.startswith("/voice-analyze"):
            res = ENGINE.analyze_voice(
                str(data.get("pcm16Base64", "")),
                data.get("ownerVoiceEmbedding"),
                bool(data.get("includeEmbedding", False))
            )
            self._send_json(200, res)
            return

        if self.path.startswith("/ocr"):
            res = ENGINE.extract_document_ocr(
                str(data.get("filePath", "")),
                str(data.get("imageBase64", "")),
                int(data.get("maxPages", 5) or 5)
            )
            self._send_json(200, res)
            return

        self._send_json(404, {"ok": False})


def main():
    port = int(os.environ.get("FW_SENSE_PORT", "17701"))
    srv = ThreadingHTTPServer(("127.0.0.1", port), SenseHandler)
    sys.stdout.write(
        f"[fw-sense] Ready on 127.0.0.1:{port} "
        f"(vosk={ENGINE.vosk_model is not None}, face={ENGINE.detector is not None})\n"
    )
    sys.stdout.flush()
    srv.serve_forever()


if __name__ == "__main__":
    main()
