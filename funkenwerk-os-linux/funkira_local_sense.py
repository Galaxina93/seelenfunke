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
import subprocess
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

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
    WAKE_WORDS = [
        "funkira",
        "hey funkira",
        "hallo funkira",
        "hi funkira",
        "komm her",
        "komm mal her",
        "alina hier",
        "aufwachen",
        "[unk]"
    ]
    TRIGGER_TOKENS = ("funkira", "komm her", "komm mal her", "aufwachen")

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
        self.parec_proc = None

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
                "triggered": trig,
                "phrase": phrase,
                "timestamp": ts
            }

    def _check_text_for_wake(self, text: str) -> str:
        t = (text or "").strip().lower()
        if not t or t == "[unk]":
            return ""
        for tok in self.TRIGGER_TOKENS:
            if tok in t:
                return t
        return ""

    def _audio_loop(self):
        grammar = json.dumps(self.WAKE_WORDS, ensure_ascii=False)
        while True:
            if self.vosk_model is None:
                time.sleep(1.0)
                continue

            with self.lock:
                should_listen = self.want_listening

            if not should_listen:
                time.sleep(0.18)
                continue

            rec = vosk.KaldiRecognizer(self.vosk_model, 16000, grammar)
            rec.SetWords(False)

            proc = None
            try:
                cmd = ["/usr/bin/parec", "--rate=16000", "--channels=1", "--format=s16le", "--latency-msec=80"]
                if not os.path.isfile("/usr/bin/parec"):
                    cmd = ["pw-cat", "-r", "--rate", "16000", "--channels", "1", "--format", "s16", "-"]
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
                        with self.lock:
                            self.wake_triggered = True
                            self.wake_phrase = matched
                            self.wake_ts = time.time()
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

    def analyze_face(self, jpeg_b64: str, owner_embedding: list | None, include_embedding: bool) -> dict:
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
            scale = 1.0
            if w0 > 640:
                scale = 640.0 / float(w0)
                img = cv2.resize(img, (640, max(32, int(round(h0 * scale)))), interpolation=cv2.INTER_AREA)

            h, w = img.shape[:2]
            with self.face_lock:
                self.detector.setInputSize((w, h))
                _, faces = self.detector.detect(img)

                if faces is None or len(faces) == 0:
                    return {"ok": True, "faceReady": True, "faceDetected": False, "identity": "none"}

                # Select largest prominent face in front of monitor
                best = max(faces, key=lambda f: float(f[2]) * float(f[3]))
                fx, fy, fw, fh = float(best[0]), float(best[1]), float(best[2]), float(best[3])
                det_score = float(best[-1])
                cx = max(0.0, min(1.0, (fx + fw * 0.5) / float(w)))
                cy = max(0.0, min(1.0, (fy + fh * 0.45) / float(h)))
                rel_w = fw / float(w)

                aligned = self.recognizer.alignCrop(img, best)
                feat = self.recognizer.feature(aligned).flatten().astype(np.float32)
                norm = float(np.linalg.norm(feat))
                if norm > 1e-6:
                    feat = feat / norm

            cosine = 0.0
            identity = "unenrolled"
            if owner_embedding and len(owner_embedding) == len(feat):
                ref = np.array(owner_embedding, dtype=np.float32)
                ref_norm = float(np.linalg.norm(ref))
                if ref_norm > 1e-6:
                    ref = ref / ref_norm
                cosine = float(np.dot(feat, ref))
                # Official OpenCV SFace cosine threshold is 0.363
                if cosine >= 0.363:
                    identity = "alina"
                elif cosine >= 0.275 or rel_w < 0.065 or det_score < 0.80:
                    identity = "uncertain"
                else:
                    identity = "visitor"

            out = {
                "ok": True,
                "faceReady": True,
                "faceDetected": True,
                "faceCount": int(len(faces)),
                "cx": round(cx, 4),
                "cy": round(cy, 4),
                "relWidth": round(rel_w, 4),
                "score": round(det_score, 4),
                "cosine": round(cosine, 4),
                "identity": identity
            }
            if include_embedding:
                out["embedding"] = [round(float(v), 6) for v in feat.tolist()]
            return out
        except Exception as e:
            return {"ok": False, "faceReady": True, "faceDetected": False, "identity": "error", "message": str(e)}

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
