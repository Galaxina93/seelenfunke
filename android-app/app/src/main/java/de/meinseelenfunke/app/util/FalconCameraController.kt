package de.meinseelenfunke.app.util

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.ImageFormat
import android.graphics.Matrix
import android.graphics.Rect
import android.graphics.SurfaceTexture
import android.graphics.YuvImage
import android.hardware.camera2.CameraCaptureSession
import android.hardware.camera2.CameraCharacteristics
import android.hardware.camera2.CameraDevice
import android.hardware.camera2.CameraManager
import android.hardware.camera2.CameraMetadata
import android.hardware.camera2.CaptureRequest
import android.media.ExifInterface
import android.media.Image
import android.media.ImageReader
import android.os.Handler
import android.os.HandlerThread
import android.util.Size
import android.view.Surface
import android.view.WindowManager
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.core.content.ContextCompat
import de.meinseelenfunke.app.R
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull
import java.io.ByteArrayInputStream
import java.io.ByteArrayOutputStream
import java.text.SimpleDateFormat
import java.util.Base64
import java.util.Date
import java.util.Locale
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.roundToInt

data class FalconCapturedPhoto(
    val base64Jpeg: String,
    val mimeType: String = "image/jpeg",
    val fileName: String,
    val previewBitmap: ImageBitmap,
    val width: Int,
    val height: Int,
    val capturedAtMs: Long = System.currentTimeMillis(),
    val sessionId: String? = null,
    val attachedToInbox: Boolean = false
) {
    fun toPreparedAgAttachment(): PreparedAgAttachment {
        return PreparedAgAttachment(
            base64Jpeg = base64Jpeg,
            mimeType = mimeType,
            fileName = fileName,
            previewBitmap = previewBitmap,
            width = width,
            height = height
        )
    }
}

data class PendingCameraAction(
    val action: String,
    val lens: String? = null
)

object FalconCameraController {
    const val VISION_MAX_EDGE_PX = 640
    const val VISION_JPEG_QUALITY = 70
    const val VISION_MAX_BYTES = 58_000
    const val PHOTO_MAX_EDGE_PX = 1440
    const val PHOTO_JPEG_QUALITY = 84

    private val controllerScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val cameraMutex = Mutex()

    private var cameraThread: HandlerThread? = null
    private var cameraHandler: Handler? = null

    private var cameraDevice: CameraDevice? = null
    private var captureSession: CameraCaptureSession? = null
    private var liveYuvReader: ImageReader? = null
    private var stillJpegReader: ImageReader? = null
    private var warmUpSurfaceTexture: SurfaceTexture? = null
    private var warmUpSurface: Surface? = null

    private var activeSensorOrientation: Int = 90
    private var activeLensFacing: Int = CameraCharacteristics.LENS_FACING_BACK
    @Volatile
    private var lastPreviewProcessMs: Long = 0L
    @Volatile
    private var latestVisionJpegBase64: String? = null
    @Volatile
    private var pendingStillCapture: CompletableDeferred<ByteArray?>? = null

    private val _isVisionActive = MutableStateFlow(false)
    val isVisionActive: StateFlow<Boolean> = _isVisionActive.asStateFlow()

    private val _activeLens = MutableStateFlow("back")
    val activeLens: StateFlow<String> = _activeLens.asStateFlow()

    private val _previewBitmap = MutableStateFlow<ImageBitmap?>(null)
    val previewBitmap: StateFlow<ImageBitmap?> = _previewBitmap.asStateFlow()

    private val _isCapturingPhoto = MutableStateFlow(false)
    val isCapturingPhoto: StateFlow<Boolean> = _isCapturingPhoto.asStateFlow()

    private val _lastCapturedPhoto = MutableStateFlow<FalconCapturedPhoto?>(null)
    val lastCapturedPhoto: StateFlow<FalconCapturedPhoto?> = _lastCapturedPhoto.asStateFlow()

    private val _permissionRequestEvents = MutableSharedFlow<PendingCameraAction>(extraBufferCapacity = 2)
    val permissionRequestEvents: SharedFlow<PendingCameraAction> = _permissionRequestEvents.asSharedFlow()

    fun hasCameraPermission(context: Context): Boolean {
        return try {
            ContextCompat.checkSelfPermission(
                context.applicationContext,
                Manifest.permission.CAMERA
            ) == PackageManager.PERMISSION_GRANTED
        } catch (_: Throwable) {
            false
        }
    }

    fun requestPermissionForAction(action: String, lens: String? = null) {
        _permissionRequestEvents.tryEmit(PendingCameraAction(action = action, lens = lens))
    }

    fun getLatestVisionFrameBase64(): String? {
        return if (_isVisionActive.value) latestVisionJpegBase64 else null
    }

    fun clearLastCapturedPhoto() {
        _lastCapturedPhoto.value = null
    }

    fun markLastPhotoAttached() {
        _lastCapturedPhoto.value = _lastCapturedPhoto.value?.copy(attachedToInbox = true)
    }

    internal fun setLastCapturedPhotoForTest(photo: FalconCapturedPhoto?) {
        _lastCapturedPhoto.value = photo
    }

    private fun ensureCameraHandler(): Handler {
        var thread = cameraThread
        if (thread == null || !thread.isAlive) {
            thread = HandlerThread("FalconCameraBg").apply { start() }
            cameraThread = thread
            cameraHandler = Handler(thread.looper)
        }
        return cameraHandler!!
    }

    fun startVisionAsync(context: Context, lens: String = _activeLens.value) {
        val appCtx = context.applicationContext
        controllerScope.launch {
            startVision(appCtx, lens)
        }
    }

    fun toggleVisionAsync(context: Context) {
        if (_isVisionActive.value) {
            stopCamera()
        } else {
            startVisionAsync(context, _activeLens.value)
        }
    }

    fun switchCameraAsync(context: Context, explicitLens: String? = null) {
        val appCtx = context.applicationContext
        val target = explicitLens?.trim()?.lowercase(Locale.ROOT)?.takeIf { it == "front" || it == "back" }
            ?: if (_activeLens.value == "back") "front" else "back"
        controllerScope.launch {
            _activeLens.value = target
            if (_isVisionActive.value) {
                startVision(appCtx, target)
            }
        }
    }

    suspend fun startVision(context: Context, lens: String = "back"): Boolean = withContext(Dispatchers.IO) {
        val appCtx = context.applicationContext
        if (!hasCameraPermission(appCtx)) {
            requestPermissionForAction("enable_vision", lens)
            return@withContext false
        }

        val cleanLens = if (lens.trim().equals("front", ignoreCase = true)) "front" else "back"
        cameraMutex.withLock {
            stopCameraUnlocked()
            val opened = openCameraPipelineUnlocked(
                appCtx = appCtx,
                lens = cleanLens,
                includeLiveYuv = true
            )
            _activeLens.value = cleanLens
            _isVisionActive.value = opened
            opened
        }
    }

    fun stopCamera() {
        controllerScope.launch {
            cameraMutex.withLock {
                stopCameraUnlocked()
            }
        }
    }

    suspend fun captureHighResPhoto(
        context: Context,
        lens: String? = null,
        sessionId: String? = null
    ): FalconCapturedPhoto? = withContext(Dispatchers.IO) {
        val appCtx = context.applicationContext
        val desiredLens = lens?.trim()?.lowercase(Locale.ROOT)?.takeIf { it == "front" || it == "back" }
            ?: _activeLens.value
        if (!hasCameraPermission(appCtx)) {
            requestPermissionForAction("take_photo", desiredLens)
            return@withContext null
        }

        _isCapturingPhoto.value = true
        try {
            cameraMutex.withLock {
                val wasVisionRunning = _isVisionActive.value && cameraDevice != null && captureSession != null
                val sameLens = _activeLens.value == desiredLens

                if (!wasVisionRunning || !sameLens) {
                    stopCameraUnlocked()
                    val opened = openCameraPipelineUnlocked(
                        appCtx = appCtx,
                        lens = desiredLens,
                        includeLiveYuv = wasVisionRunning
                    )
                    if (!opened) {
                        stopCameraUnlocked()
                        return@withLock null
                    }
                    // Allow ~620 ms for Auto-Exposure & Auto-Focus convergence
                    delay(620L)
                }

                val rawJpegBytes = triggerStillCaptureUnlocked(appCtx)
                val photo = if (rawJpegBytes != null && rawJpegBytes.isNotEmpty()) {
                    decodeRotateAndCompressHighRes(
                        appCtx = appCtx,
                        rawJpeg = rawJpegBytes,
                        sessionId = sessionId
                    )
                } else {
                    // Fallback to latest live vision JPEG if vendor HAL skipped still callback
                    latestVisionJpegBase64?.let { b64 ->
                        val fallbackBytes = Base64.getDecoder().decode(b64)
                        decodeRotateAndCompressHighRes(
                            appCtx = appCtx,
                            rawJpeg = fallbackBytes,
                            sessionId = sessionId
                        )
                    }
                }

                if (!wasVisionRunning) {
                    stopCameraUnlocked()
                } else {
                    _activeLens.value = desiredLens
                    _isVisionActive.value = true
                }

                if (photo != null) {
                    _lastCapturedPhoto.value = photo
                    try {
                        SoundManager.playSound(R.raw.click_file_in_project_brain)
                    } catch (_: Throwable) {
                    }
                }
                photo
            }
        } catch (_: Throwable) {
            null
        } finally {
            _isCapturingPhoto.value = false
        }
    }

    @SuppressLint("MissingPermission")
    private suspend fun openCameraPipelineUnlocked(
        appCtx: Context,
        lens: String,
        includeLiveYuv: Boolean
    ): Boolean {
        return try {
            val manager = appCtx.getSystemService(Context.CAMERA_SERVICE) as? CameraManager ?: return false
            val targetFacing = if (lens == "front") {
                CameraCharacteristics.LENS_FACING_FRONT
            } else {
                CameraCharacteristics.LENS_FACING_BACK
            }

            val cameraId = manager.cameraIdList.firstOrNull { id ->
                manager.getCameraCharacteristics(id).get(CameraCharacteristics.LENS_FACING) == targetFacing
            } ?: manager.cameraIdList.firstOrNull() ?: return false

            val chars = manager.getCameraCharacteristics(cameraId)
            activeSensorOrientation = chars.get(CameraCharacteristics.SENSOR_ORIENTATION) ?: 90
            activeLensFacing = chars.get(CameraCharacteristics.LENS_FACING) ?: targetFacing

            val map = chars.get(CameraCharacteristics.SCALER_STREAM_CONFIGURATION_MAP) ?: return false
            val handler = ensureCameraHandler()

            val surfaces = mutableListOf<Surface>()

            if (includeLiveYuv) {
                val yuvChoices = map.getOutputSizes(ImageFormat.YUV_420_888)?.toList().orEmpty()
                val yuvSize = chooseOptimalSize(yuvChoices, 640, 480)
                val reader = ImageReader.newInstance(yuvSize.width, yuvSize.height, ImageFormat.YUV_420_888, 3)
                reader.setOnImageAvailableListener({ ir ->
                    val img = try { ir.acquireLatestImage() } catch (_: Throwable) { null } ?: return@setOnImageAvailableListener
                    try {
                        val now = System.currentTimeMillis()
                        if (now - lastPreviewProcessMs >= 135L) {
                            lastPreviewProcessMs = now
                            processLiveYuvFrame(appCtx, img)
                        }
                    } catch (_: Throwable) {
                    } finally {
                        try { img.close() } catch (_: Throwable) { }
                    }
                }, handler)
                liveYuvReader = reader
                surfaces.add(reader.surface)
            } else {
                val st = SurfaceTexture(11).apply {
                    setDefaultBufferSize(640, 480)
                }
                val surf = Surface(st)
                warmUpSurfaceTexture = st
                warmUpSurface = surf
                surfaces.add(surf)
            }

            val jpegChoices = map.getOutputSizes(ImageFormat.JPEG)?.toList().orEmpty()
            val stillSize = chooseOptimalSize(jpegChoices, 1600, 1200)
            val sReader = ImageReader.newInstance(stillSize.width, stillSize.height, ImageFormat.JPEG, 2)
            sReader.setOnImageAvailableListener({ ir ->
                val img = try { ir.acquireLatestImage() } catch (_: Throwable) { null } ?: return@setOnImageAvailableListener
                try {
                    val buf = img.planes.firstOrNull()?.buffer
                    if (buf != null) {
                        val bytes = ByteArray(buf.remaining())
                        buf.get(bytes)
                        pendingStillCapture?.complete(bytes)
                    } else {
                        pendingStillCapture?.complete(null)
                    }
                } catch (_: Throwable) {
                    pendingStillCapture?.complete(null)
                } finally {
                    try { img.close() } catch (_: Throwable) { }
                }
            }, handler)
            stillJpegReader = sReader
            surfaces.add(sReader.surface)

            val openedDevice = CompletableDeferred<CameraDevice?>()
            manager.openCamera(cameraId, object : CameraDevice.StateCallback() {
                override fun onOpened(camera: CameraDevice) {
                    if (!openedDevice.isCompleted) openedDevice.complete(camera)
                }

                override fun onDisconnected(camera: CameraDevice) {
                    try { camera.close() } catch (_: Throwable) { }
                    if (!openedDevice.isCompleted) {
                        openedDevice.complete(null)
                    } else {
                        _isVisionActive.value = false
                    }
                }

                override fun onError(camera: CameraDevice, error: Int) {
                    try { camera.close() } catch (_: Throwable) { }
                    if (!openedDevice.isCompleted) {
                        openedDevice.complete(null)
                    } else {
                        _isVisionActive.value = false
                    }
                }
            }, handler)

            val device = withTimeoutOrNull(3000L) { openedDevice.await() } ?: return false
            cameraDevice = device

            val sessionDeferred = CompletableDeferred<CameraCaptureSession?>()
            @Suppress("DEPRECATION")
            device.createCaptureSession(surfaces, object : CameraCaptureSession.StateCallback() {
                override fun onConfigured(session: CameraCaptureSession) {
                    if (!sessionDeferred.isCompleted) sessionDeferred.complete(session)
                }

                override fun onConfigureFailed(session: CameraCaptureSession) {
                    if (!sessionDeferred.isCompleted) sessionDeferred.complete(null)
                }
            }, handler)

            val session = withTimeoutOrNull(3000L) { sessionDeferred.await() } ?: return false
            captureSession = session

            val previewTarget = liveYuvReader?.surface ?: warmUpSurface ?: return false
            val previewReq = device.createCaptureRequest(CameraDevice.TEMPLATE_PREVIEW).apply {
                addTarget(previewTarget)
                set(CaptureRequest.CONTROL_AF_MODE, CaptureRequest.CONTROL_AF_MODE_CONTINUOUS_PICTURE)
                set(CaptureRequest.CONTROL_AE_MODE, CaptureRequest.CONTROL_AE_MODE_ON)
            }
            session.setRepeatingRequest(previewReq.build(), null, handler)
            true
        } catch (_: Throwable) {
            false
        }
    }

    private suspend fun triggerStillCaptureUnlocked(appCtx: Context): ByteArray? {
        val dev = cameraDevice ?: return null
        val sess = captureSession ?: return null
        val sReader = stillJpegReader ?: return null
        val handler = ensureCameraHandler()

        val deferred = CompletableDeferred<ByteArray?>()
        pendingStillCapture = deferred
        return try {
            val req = dev.createCaptureRequest(CameraDevice.TEMPLATE_STILL_CAPTURE).apply {
                addTarget(sReader.surface)
                set(CaptureRequest.CONTROL_AF_MODE, CaptureRequest.CONTROL_AF_MODE_CONTINUOUS_PICTURE)
                set(CaptureRequest.CONTROL_AE_MODE, CaptureRequest.CONTROL_AE_MODE_ON)
                set(CaptureRequest.JPEG_QUALITY, 90.toByte())
                set(CaptureRequest.JPEG_ORIENTATION, computeRotationDegrees(appCtx))
            }
            sess.capture(req.build(), null, handler)
            withTimeoutOrNull(2500L) { deferred.await() }
        } catch (_: Throwable) {
            null
        } finally {
            pendingStillCapture = null
        }
    }

    private fun stopCameraUnlocked() {
        _isVisionActive.value = false
        latestVisionJpegBase64 = null
        _previewBitmap.value = null
        pendingStillCapture?.complete(null)
        pendingStillCapture = null

        try { captureSession?.stopRepeating() } catch (_: Throwable) { }
        try { captureSession?.close() } catch (_: Throwable) { }
        captureSession = null

        try { cameraDevice?.close() } catch (_: Throwable) { }
        cameraDevice = null

        try { liveYuvReader?.close() } catch (_: Throwable) { }
        liveYuvReader = null

        try { stillJpegReader?.close() } catch (_: Throwable) { }
        stillJpegReader = null

        try { warmUpSurface?.release() } catch (_: Throwable) { }
        warmUpSurface = null

        try { warmUpSurfaceTexture?.release() } catch (_: Throwable) { }
        warmUpSurfaceTexture = null
    }

    private fun processLiveYuvFrame(appCtx: Context, image: Image) {
        val nv21 = yuv420888ToNv21(image) ?: return
        val yuvImg = YuvImage(nv21, ImageFormat.NV21, image.width, image.height, null)
        val rawJpeg = ByteArrayOutputStream().use { bos ->
            yuvImg.compressToJpeg(Rect(0, 0, image.width, image.height), 82, bos)
            bos.toByteArray()
        }
        val decoded = BitmapFactory.decodeByteArray(rawJpeg, 0, rawJpeg.size) ?: return
        val rotDeg = computeRotationDegrees(appCtx)
        val isFront = activeLensFacing == CameraCharacteristics.LENS_FACING_FRONT

        val longest = max(decoded.width, decoded.height)
        val scale = if (longest > VISION_MAX_EDGE_PX) VISION_MAX_EDGE_PX.toFloat() / longest.toFloat() else 1f

        val wsMatrix = Matrix().apply {
            if (scale < 1f) postScale(scale, scale)
            if (rotDeg != 0) postRotate(rotDeg.toFloat())
        }
        val wsBitmap = Bitmap.createBitmap(decoded, 0, 0, decoded.width, decoded.height, wsMatrix, true)

        var wsJpeg = ByteArrayOutputStream().use { out ->
            wsBitmap.compress(Bitmap.CompressFormat.JPEG, VISION_JPEG_QUALITY, out)
            out.toByteArray()
        }
        if (wsJpeg.size > VISION_MAX_BYTES) {
            wsJpeg = ByteArrayOutputStream().use { out ->
                wsBitmap.compress(Bitmap.CompressFormat.JPEG, 50, out)
                out.toByteArray()
            }
        }
        if (wsJpeg.size <= VISION_MAX_BYTES) {
            latestVisionJpegBase64 = Base64.getEncoder().encodeToString(wsJpeg)
        }

        val pipBitmap = if (isFront) {
            val mirrorMatrix = Matrix().apply { postScale(-1f, 1f) }
            Bitmap.createBitmap(wsBitmap, 0, 0, wsBitmap.width, wsBitmap.height, mirrorMatrix, true)
        } else {
            wsBitmap
        }
        _previewBitmap.value = pipBitmap.asImageBitmap()
        if (decoded !== wsBitmap && decoded !== pipBitmap) {
            decoded.recycle()
        }
    }

    private fun decodeRotateAndCompressHighRes(
        appCtx: Context,
        rawJpeg: ByteArray,
        sessionId: String?
    ): FalconCapturedPhoto? {
        val exifDeg = try {
            val exif = ExifInterface(ByteArrayInputStream(rawJpeg))
            when (exif.getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_UNDEFINED)) {
                ExifInterface.ORIENTATION_ROTATE_90 -> 90
                ExifInterface.ORIENTATION_ROTATE_180 -> 180
                ExifInterface.ORIENTATION_ROTATE_270 -> 270
                ExifInterface.ORIENTATION_NORMAL -> 0
                else -> -1
            }
        } catch (_: Throwable) {
            -1
        }
        val rotation = if (exifDeg >= 0) exifDeg else computeRotationDegrees(appCtx)

        val decoded = BitmapFactory.decodeByteArray(rawJpeg, 0, rawJpeg.size) ?: return null
        val rotated = if (rotation != 0) {
            val m = Matrix().apply { postRotate(rotation.toFloat()) }
            Bitmap.createBitmap(decoded, 0, 0, decoded.width, decoded.height, m, true).also {
                if (it !== decoded) decoded.recycle()
            }
        } else {
            decoded
        }

        val longest = max(rotated.width, rotated.height)
        val scaled = if (longest > PHOTO_MAX_EDGE_PX) {
            val r = PHOTO_MAX_EDGE_PX.toFloat() / longest.toFloat()
            val tw = max(1, (rotated.width * r).roundToInt())
            val th = max(1, (rotated.height * r).roundToInt())
            Bitmap.createScaledBitmap(rotated, tw, th, true).also {
                if (it !== rotated) rotated.recycle()
            }
        } else {
            rotated
        }

        val outBytes = ByteArrayOutputStream().use { out ->
            scaled.compress(Bitmap.CompressFormat.JPEG, PHOTO_JPEG_QUALITY, out)
            out.toByteArray()
        }
        val base64 = Base64.getEncoder().encodeToString(outBytes)
        val preview = BitmapFactory.decodeByteArray(outBytes, 0, outBytes.size)?.asImageBitmap()
            ?: scaled.asImageBitmap()
        val stamp = SimpleDateFormat("yyyyMMdd_HHmmss", Locale.US).format(Date())

        return FalconCapturedPhoto(
            base64Jpeg = base64,
            mimeType = "image/jpeg",
            fileName = "falcon_$stamp.jpg",
            previewBitmap = preview,
            width = scaled.width,
            height = scaled.height,
            capturedAtMs = System.currentTimeMillis(),
            sessionId = sessionId,
            attachedToInbox = false
        )
    }

    private fun computeRotationDegrees(context: Context): Int {
        val displayRotation = try {
            val wm = context.getSystemService(Context.WINDOW_SERVICE) as? WindowManager
            @Suppress("DEPRECATION")
            wm?.defaultDisplay?.rotation ?: Surface.ROTATION_0
        } catch (_: Throwable) {
            Surface.ROTATION_0
        }
        val deviceDegrees = when (displayRotation) {
            Surface.ROTATION_0 -> 0
            Surface.ROTATION_90 -> 90
            Surface.ROTATION_180 -> 180
            Surface.ROTATION_270 -> 270
            else -> 0
        }
        return if (activeLensFacing == CameraCharacteristics.LENS_FACING_FRONT) {
            val total = (activeSensorOrientation + deviceDegrees) % 360
            (360 - total) % 360
        } else {
            (activeSensorOrientation - deviceDegrees + 360) % 360
        }
    }

    private fun chooseOptimalSize(choices: List<Size>, targetW: Int, targetH: Int): Size {
        if (choices.isEmpty()) return Size(targetW, targetH)
        return choices.minByOrNull { s ->
            abs(s.width - targetW) + abs(s.height - targetH)
        } ?: choices.first()
    }

    private fun yuv420888ToNv21(image: Image): ByteArray? {
        return try {
            val width = image.width
            val height = image.height
            val yPlane = image.planes[0]
            val uPlane = image.planes[1]
            val vPlane = image.planes[2]

            val yBuffer = yPlane.buffer
            val uBuffer = uPlane.buffer
            val vBuffer = vPlane.buffer
            yBuffer.rewind()
            uBuffer.rewind()
            vBuffer.rewind()

            val nv21 = ByteArray(width * height + (width * height) / 2)
            var pos = 0

            val yRowStride = yPlane.rowStride
            val yPixelStride = yPlane.pixelStride
            for (row in 0 until height) {
                val rowStart = row * yRowStride
                if (yPixelStride == 1) {
                    yBuffer.position(rowStart)
                    yBuffer.get(nv21, pos, width)
                    pos += width
                } else {
                    for (col in 0 until width) {
                        nv21[pos++] = yBuffer.get(rowStart + col * yPixelStride)
                    }
                }
            }

            val uvHeight = height / 2
            val uvWidth = width / 2
            val vRowStride = vPlane.rowStride
            val vPixelStride = vPlane.pixelStride
            val uRowStride = uPlane.rowStride
            val uPixelStride = uPlane.pixelStride

            for (row in 0 until uvHeight) {
                val vRowStart = row * vRowStride
                val uRowStart = row * uRowStride
                for (col in 0 until uvWidth) {
                    nv21[pos++] = vBuffer.get(vRowStart + col * vPixelStride)
                    nv21[pos++] = uBuffer.get(uRowStart + col * uPixelStride)
                }
            }
            nv21
        } catch (_: Throwable) {
            null
        }
    }
}
