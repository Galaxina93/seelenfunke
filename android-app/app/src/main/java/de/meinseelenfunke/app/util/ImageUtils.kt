package de.meinseelenfunke.app.util

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Color
import android.graphics.Matrix
import android.graphics.Paint
import android.graphics.RectF
import android.graphics.pdf.PdfDocument
import android.media.ExifInterface
import android.net.Uri
import java.io.ByteArrayOutputStream

object ImageUtils {
    fun compressImageUri(context: Context, uri: Uri, maxDimension: Int = 1600, quality: Int = 80): ByteArray? {
        return try {
            val contentResolver = context.contentResolver

            // 0. Read EXIF rotation so portrait receipt photos are never sent sideways
            val rotationDegrees = try {
                contentResolver.openInputStream(uri)?.use { input ->
                    val exif = ExifInterface(input)
                    when (exif.getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL)) {
                        ExifInterface.ORIENTATION_ROTATE_90 -> 90f
                        ExifInterface.ORIENTATION_ROTATE_180 -> 180f
                        ExifInterface.ORIENTATION_ROTATE_270 -> 270f
                        else -> 0f
                    }
                } ?: 0f
            } catch (_: Exception) {
                0f
            }
            
            // 1. Decode bounds to check size
            val options = BitmapFactory.Options().apply {
                inJustDecodeBounds = true
            }
            contentResolver.openInputStream(uri)?.use { input ->
                BitmapFactory.decodeStream(input, null, options)
            }
            
            val width = options.outWidth
            val height = options.outHeight
            if (width <= 0 || height <= 0) return null

            // 2. Calculate sample size to downsample
            var sampleSize = 1
            while (width / sampleSize > maxDimension * 2 || height / sampleSize > maxDimension * 2) {
                sampleSize *= 2
            }

            // 3. Decode bitmap with inSampleSize
            val decodeOptions = BitmapFactory.Options().apply {
                inSampleSize = sampleSize
            }
            val decodedBitmap = contentResolver.openInputStream(uri)?.use { input ->
                BitmapFactory.decodeStream(input, null, decodeOptions)
            } ?: return null

            // 4. Apply EXIF rotation if needed
            val orientedBitmap = if (rotationDegrees != 0f) {
                val matrix = Matrix().apply { postRotate(rotationDegrees) }
                val rotated = Bitmap.createBitmap(
                    decodedBitmap, 0, 0, decodedBitmap.width, decodedBitmap.height, matrix, true
                )
                if (rotated != decodedBitmap) {
                    decodedBitmap.recycle()
                }
                rotated
            } else {
                decodedBitmap
            }

            // 5. Fine-scale if it exceeds max dimension
            val currentWidth = orientedBitmap.width
            val currentHeight = orientedBitmap.height
            val scale = Math.min(
                maxDimension.toFloat() / currentWidth,
                maxDimension.toFloat() / currentHeight
            )
            
            val resizedBitmap = if (scale < 1f) {
                val targetWidth = Math.max(1, (currentWidth * scale).toInt())
                val targetHeight = Math.max(1, (currentHeight * scale).toInt())
                Bitmap.createScaledBitmap(orientedBitmap, targetWidth, targetHeight, true)
            } else {
                orientedBitmap
            }

            // 6. Compress
            val outputStream = ByteArrayOutputStream()
            resizedBitmap.compress(Bitmap.CompressFormat.JPEG, quality, outputStream)
            
            if (resizedBitmap != orientedBitmap) {
                resizedBitmap.recycle()
            }
            orientedBitmap.recycle()
            
            outputStream.toByteArray()
        } catch (e: Exception) {
            e.printStackTrace()
            null
        }
    }

    /**
     * Compiles 1..N compressed JPEG page byte arrays into a single A4-scaled PDF document
     * (~150 DPI: 1240x1754 portrait / 1754x1240 landscape) decoding one page at a time to keep RAM tiny.
     */
    fun buildPdfFromJpegPages(jpegPages: List<ByteArray>): ByteArray? {
        if (jpegPages.isEmpty()) return null
        val pdfDoc = PdfDocument()
        return try {
            val bitmapPaint = Paint(Paint.FILTER_BITMAP_FLAG or Paint.DITHER_FLAG)
            var addedPages = 0

            jpegPages.forEachIndexed { idx, bytes ->
                val bmp = BitmapFactory.decodeByteArray(bytes, 0, bytes.size) ?: return@forEachIndexed
                try {
                    val isLandscape = bmp.width > bmp.height
                    val pageWidth = if (isLandscape) 1754 else 1240
                    val pageHeight = if (isLandscape) 1240 else 1754
                    val margin = 24f

                    val pageInfo = PdfDocument.PageInfo.Builder(pageWidth, pageHeight, idx + 1).create()
                    val page = pdfDoc.startPage(pageInfo)
                    val canvas = page.canvas
                    canvas.drawColor(Color.WHITE)

                    val availW = pageWidth - margin * 2f
                    val availH = pageHeight - margin * 2f
                    val scale = kotlin.math.min(availW / bmp.width.toFloat(), availH / bmp.height.toFloat())
                    val drawW = bmp.width * scale
                    val drawH = bmp.height * scale
                    val left = (pageWidth - drawW) / 2f
                    val top = (pageHeight - drawH) / 2f

                    canvas.drawBitmap(bmp, null, RectF(left, top, left + drawW, top + drawH), bitmapPaint)
                    pdfDoc.finishPage(page)
                    addedPages++
                } finally {
                    bmp.recycle()
                }
            }

            if (addedPages == 0) return null
            val out = ByteArrayOutputStream()
            pdfDoc.writeTo(out)
            out.toByteArray()
        } catch (e: Exception) {
            e.printStackTrace()
            null
        } finally {
            try { pdfDoc.close() } catch (_: Exception) {}
        }
    }
}
