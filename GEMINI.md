# Seelenfunke Project Rules & Path Standards

## 1. WSL & Windows Path Integrity (CRITICAL - BLACK SCREEN PREVENTION)

This project resides in WSL (`\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke`).
When operating from the Windows Antigravity IDE:

1. **Tool File Arguments (view_file, replace_file_content, write_to_file, etc.)**:
   - ✅ **ALWAYS USE WINDOWS UNC**: `\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\...`
   - ❌ **NEVER USE FORWARD-SLASH UNC**: `//wsl.localhost/...`
   - ❌ **NEVER PASS RELATIVE OR LINUX ROOT PATHS DIRECTLY TO WINDOWS TOOLS**: `/home/ubuntuxina/...`

2. **Clickable Links in Chat**:
   - ✅ `[Dateiname](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/...)`
   - ❌ `[Dateiname](file:////wsl.localhost/...)` (FATAL: crashes Antigravity UI)

3. **Bash / CLI Execution**:
   - When running Artisan, PHPUnit, Node or Docker in this project, run via WSL:
     `wsl -d Ubuntu bash -c "cd /home/ubuntuxina/meine-projekte/seelenfunke && ..."`
