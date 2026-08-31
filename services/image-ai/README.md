# Local image AI service

This private FastAPI service provides background removal with `rembg` and a reliable Pillow-based 2× enhancement fallback. It downloads the free U²-Net model on the first background-removal request. Keep it on localhost unless you add network authentication and TLS.

## Windows (PowerShell)

```powershell
cd services/image-ai
py -3.11 -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8090
```

Python 3.10–3.12 is recommended. If PowerShell blocks activation, run `.venv\Scripts\python -m pip install -r requirements.txt` and `.venv\Scripts\python -m uvicorn app.main:app --host 127.0.0.1 --port 8090` directly.

## Linux

```bash
cd services/image-ai
python3 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8090
```

On Debian/Ubuntu you may first need `sudo apt install python3-venv libgl1`. The first `rembg` call needs internet access only to fetch its free model; subsequent processing is local.

## Optional Real-ESRGAN

The shipped enhancer deliberately uses Pillow so the app always works without GPU packages. To enable Real-ESRGAN later, install a PyTorch build matching your CPU/CUDA environment plus `realesrgan`, add a model adapter in `enhance()`, and expose the active backend from `/health`. Do not install an arbitrary CUDA wheel without checking the official PyTorch compatibility matrix.

Limits and timeout are configurable through the variables in `.env.example`. Errors use `{ "error": { "code": "...", "message": "..." } }`; successful processing returns image bytes.
