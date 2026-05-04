import socket
import threading
import tkinter as tk
from tkinter import scrolledtext
import pyautogui
import pyperclip
import time
import os
import sys

HOST = '0.0.0.0'
PORT = 8888

# Globale Variablen für die Chat-Koordinaten
chat_x = None
chat_y = None

def send_to_antigravity(text, log_callback):
    global chat_x, chat_y
    
    if chat_x is None or chat_y is None:
        log_callback("[FEHLER] Chat-Feld Koordinaten nicht kalibriert! Bitte erst auf 'Chat-Feld festlegen' klicken!")
        return

    # 1. Text in die Zwischenablage kopieren
    pyperclip.copy(text)
    
    # 2. Maus zur kalibrierten Position bewegen und klicken
    log_callback(f"[AKTION] Klicke in Eingabefeld bei x:{chat_x}, y:{chat_y}...")
    
    # Sichern der aktuellen Mausposition
    original_x, original_y = pyautogui.position()
    
    # Klick ins Chat-Feld (holt die IDE automatisch in den Vordergrund und fokussiert das Textfeld)
    pyautogui.click(chat_x, chat_y)
    time.sleep(0.3)
    
    # 3. Tasteneingaben simulieren (STRG+V und ENTER)
    log_callback("[AKTION] Füge Text ein...")
    pyautogui.hotkey('ctrl', 'v')
    time.sleep(0.2)
    pyautogui.press('enter')
    
    # Maus zurücksetzen
    pyautogui.moveTo(original_x, original_y)
    
    log_callback("[ERFOLG] Task an Antigravity gesendet!")

def start_tcp_server(log_callback):
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    try:
        server.bind((HOST, PORT))
        server.listen(1)
        log_callback(f"[SYSTEM] Bridge Server gestartet auf {HOST}:{PORT}")
        log_callback("[SYSTEM] Warte auf Signale von Funki (Laravel)...")
    except Exception as e:
        log_callback(f"[FATAL] Konnte Server nicht starten. Port belegt? Fehler: {e}")
        return
    
    while True:
        try:
            conn, addr = server.accept()
            data = conn.recv(8192).decode('utf-8')
            if data:
                log_callback("-" * 40)
                log_callback("[EMPFANGEN] Neuer Task von Laravel:")
                log_callback(data)
                threading.Thread(target=send_to_antigravity, args=(data, log_callback), daemon=True).start()
            conn.close()
        except Exception as e:
            log_callback(f"[FEHLER] Verbindungsproblem: {e}")

# --- GUI ---
class AntigravityBridgeGUI:
    def __init__(self, root):
        self.root = root
        self.root.title("Antigravity TCP Bridge 🌉")
        self.root.geometry("600x450")
        self.root.configure(bg="#0f172a")
        
        # Header
        header_frame = tk.Frame(root, bg="#1e293b", pady=15)
        header_frame.pack(fill=tk.X)
        
        lbl_title = tk.Label(header_frame, text="Antigravity Bridge", fg="#22d3ee", bg="#1e293b", font=("Segoe UI", 16, "bold"))
        lbl_title.pack()
        
        lbl_sub = tk.Label(header_frame, text="Verbindet dein lokales Agenten-System mit dieser IDE", fg="#94a3b8", bg="#1e293b", font=("Segoe UI", 10))
        lbl_sub.pack()

        # Calibration Controls
        calib_frame = tk.Frame(root, bg="#0f172a", pady=10)
        calib_frame.pack(fill=tk.X)
        
        self.btn_calib = tk.Button(calib_frame, text="🎯 1. Klick hier: Chat-Feld Position kalibrieren", 
                                   command=self.start_calibration, 
                                   bg="#3b82f6", fg="white", font=("Segoe UI", 10, "bold"), relief=tk.FLAT, padx=10, pady=5)
        self.btn_calib.pack()
        
        self.lbl_calib_info = tk.Label(calib_frame, text="Noch nicht kalibriert", fg="#ef4444", bg="#0f172a", font=("Segoe UI", 9))
        self.lbl_calib_info.pack(pady=5)

        # Log Area
        log_frame = tk.Frame(root, bg="#0f172a", padx=15, pady=5)
        log_frame.pack(fill=tk.BOTH, expand=True)
        
        self.log_text = scrolledtext.ScrolledText(log_frame, bg="#020617", fg="#10b981", font=("Consolas", 10), borderwidth=0, padx=10, pady=10)
        self.log_text.pack(fill=tk.BOTH, expand=True)
        
        # Status Footer
        footer_frame = tk.Frame(root, bg="#1e293b", pady=5)
        footer_frame.pack(fill=tk.X, side=tk.BOTTOM)
        
        self.lbl_status = tk.Label(footer_frame, text="🟢 ONLINE", fg="#10b981", bg="#1e293b", font=("Segoe UI", 9, "bold"))
        self.lbl_status.pack(side=tk.RIGHT, padx=15)

    def start_calibration(self):
        self.log_text.insert(tk.END, "\n[KALIBRIERUNG] Bewege deine Maus JETZT über das Eingabefeld in Antigravity...\n")
        self.log_text.insert(tk.END, "[KALIBRIERUNG] Position wird in 3 Sekunden gespeichert!\n")
        self.log_text.see(tk.END)
        self.btn_calib.config(text="⏳ Warte 3 Sekunden...", bg="#eab308")
        self.root.update()
        
        # In Hintergrund-Thread ausführen damit UI nicht einfriert
        threading.Thread(target=self._do_calibration, daemon=True).start()

    def _do_calibration(self):
        time.sleep(3)
        global chat_x, chat_y
        chat_x, chat_y = pyautogui.position()
        
        def update_ui():
            self.btn_calib.config(text="✅ Chat-Feld Position kalibriert!", bg="#10b981")
            self.lbl_calib_info.config(text=f"Position gespeichert: X={chat_x}, Y={chat_y}", fg="#10b981")
            self.log_text.insert(tk.END, f"[ERFOLG] Position gespeichert: X={chat_x}, Y={chat_y}\n")
            self.log_text.see(tk.END)
            
        self.root.after(0, update_ui)

    def log(self, msg):
        self.log_text.insert(tk.END, msg + "\n")
        self.log_text.see(tk.END)
        self.root.update()

def start_app():
    root = tk.Tk()
    app = AntigravityBridgeGUI(root)
    
    def log_thread_safe(msg):
        root.after(0, app.log, msg)
        
    server_thread = threading.Thread(target=start_tcp_server, args=(log_thread_safe,), daemon=True)
    server_thread.start()
    
    root.mainloop()

if __name__ == "__main__":
    start_app()
