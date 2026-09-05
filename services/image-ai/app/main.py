from __future__ import annotations

import io
import os
import tempfile
from dataclasses import dataclass
from typing import Callable

import anyio
from fastapi import FastAPI, File, Request, UploadFile
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse, Response
from PIL import Image, ImageEnhance, ImageFilter, ImageOps, UnidentifiedImageError
from rembg import remove

MAX_BYTES = int(os.getenv("IMAGE_AI_MAX_BYTES", str(10 * 1024 * 1024)))
MAX_PIXELS = int(os.getenv("IMAGE_AI_MAX_PIXELS", "40000000"))
TIMEOUT_SECONDS = float(os.getenv("IMAGE_AI_REQUEST_TIMEOUT", "120"))
ALLOWED_TYPES = {"image/jpeg", "image/png", "image/webp"}

app = FastAPI(title="ToolJoy Local Image AI", version="1.0.0")


@dataclass
class ApiError(Exception):
    status: int
    code: str
    message: str


@app.exception_handler(ApiError)
async def api_error_handler(_: Request, error: ApiError) -> JSONResponse:
    return JSONResponse(status_code=error.status, content={"error": {"code": error.code, "message": error.message}})


@app.exception_handler(RequestValidationError)
async def validation_handler(_: Request, __: RequestValidationError) -> JSONResponse:
    return JSONResponse(status_code=422, content={"error": {"code": "invalid_request", "message": "A supported image file is required."}})


@app.exception_handler(Exception)
async def unexpected_handler(_: Request, __: Exception) -> JSONResponse:
    return JSONResponse(status_code=500, content={"error": {"code": "processing_failed", "message": "The image could not be processed."}})


@app.get("/health")
async def health() -> dict[str, object]:
    return {"status": "ok", "enhancer": "pillow", "background_removal": True}


@app.post("/v1/remove-background")
async def remove_background(file: UploadFile = File(...)) -> Response:
    image = await validated_image(file)

    def process() -> bytes:
        with tempfile.TemporaryDirectory(prefix="tooljoy-"):
            output = remove(image)
            result = Image.open(io.BytesIO(output) if isinstance(output, bytes) else output)
            buffer = io.BytesIO()
            result.save(buffer, "PNG", optimize=True)
            return buffer.getvalue()

    return await timed_image(process, "image/png")


@app.post("/v1/enhance")
async def enhance(file: UploadFile = File(...)) -> Response:
    data = await validated_image(file)

    def process() -> bytes:
        with Image.open(io.BytesIO(data)) as source:
            image = ImageOps.exif_transpose(source).convert("RGB")
            # Lightweight fallback that works everywhere. It avoids model downloads.
            image = image.resize((image.width * 2, image.height * 2), Image.Resampling.LANCZOS)
            image = ImageEnhance.Contrast(image).enhance(1.06)
            image = ImageEnhance.Color(image).enhance(1.04)
            image = image.filter(ImageFilter.UnsharpMask(radius=1.2, percent=115, threshold=3))
            buffer = io.BytesIO()
            image.save(buffer, "PNG", optimize=True)
            return buffer.getvalue()

    return await timed_image(process, "image/png")


async def validated_image(file: UploadFile) -> bytes:
    if file.content_type not in ALLOWED_TYPES:
        raise ApiError(415, "unsupported_type", "Only JPG, PNG, and WebP images are supported.")
    data = await file.read(MAX_BYTES + 1)
    await file.close()
    if len(data) > MAX_BYTES:
        raise ApiError(413, "file_too_large", "The image exceeds the configured upload limit.")
    try:
        with Image.open(io.BytesIO(data)) as image:
            image.verify()
        with Image.open(io.BytesIO(data)) as image:
            if image.width * image.height > MAX_PIXELS:
                raise ApiError(413, "too_many_pixels", "The image dimensions are too large.")
            detected = Image.MIME.get(image.format)
            if detected not in ALLOWED_TYPES:
                raise ApiError(415, "invalid_image", "The file contents do not match a supported image type.")
    except (UnidentifiedImageError, OSError):
        raise ApiError(422, "invalid_image", "The uploaded file is not a valid image.")
    return data


async def timed_image(callback: Callable[[], bytes], media_type: str) -> Response:
    try:
        with anyio.fail_after(TIMEOUT_SECONDS):
            output = await anyio.to_thread.run_sync(callback, abandon_on_cancel=True)
    except TimeoutError:
        raise ApiError(504, "processing_timeout", "Image processing exceeded the configured timeout.")
    return Response(content=output, media_type=media_type, headers={"Cache-Control": "no-store"})
