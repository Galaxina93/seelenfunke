# WSL Integrity Rule

All tools accessing the file system under Windows must format WSL paths as Windows UNC:
- Path format: `\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\...`
- Do not use forward slashes at the start of UNC paths (`//wsl.localhost/...`).
- Format chat links as `[Label](file://wsl.localhost/Ubuntu/home/...)`.
