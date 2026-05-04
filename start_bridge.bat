@echo off
cd /d "%~dp0"
title Antigravity TCP Bridge
echo =========================================
echo Startet Antigravity TCP Bridge (Python)
echo =========================================
echo.
echo Pruefe Voraussetzungen (pyautogui, pyperclip, pygetwindow)...
pip install pyautogui pyperclip pygetwindow > nul 2>&1

echo Starte Bridge...
python antigravity_bridge.py

pause
