package de.meinseelenfunke.app.util

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import java.io.ByteArrayOutputStream

object ImageUtils {
    fun compressImageUri(context: Context, uri: Uri, maxDimension: Int = 1920, quality: Int = 80): ByteArray? {
        return try {
            val contentResolver = context.contentResolver
            
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
            while (width / sampleSize > maxDimension || height / sampleSize > maxDimension) {
                sampleSize *= 2
            }

            // 3. Decode bitmap with inSampleSize
            val decodeOptions = BitmapFactory.Options().apply {
                inSampleSize = sampleSize
            }
            val originalBitmap = contentResolver.openInputStream(uri)?.use { input ->
                BitmapFactory.decodeStream(input, null, decodeOptions)
            } ?: return null

            // 4. Fine-scale if it exceeds max dimension
            val currentWidth = originalBitmap.width
            val currentHeight = originalBitmap.height
            val scale = Math.min(
                maxDimension.toFloat() / currentWidth,
                maxDimension.toFloat() / currentHeight
            )
            
            val resizedBitmap = if (scale < 1f) {
                val targetWidth = (currentWidth * scale).toInt()
                val targetHeight = (currentHeight * scale).toInt()
                Bitmap.createScaledBitmap(originalBitmap, targetWidth, targetHeight, true)
            } else {
                originalBitmap
            }

            // 5. Compress
            val outputStream = ByteArrayOutputStream()
            resizedBitmap.compress(Bitmap.CompressFormat.JPEG, quality, outputStream)
            
            if (resizedBitmap != originalBitmap) {
                resizedBitmap.recycle()
            }
            originalBitmap.recycle()
            
            outputStream.toByteArray()
        } catch (e: Exception) {
            e.printStackTrace()
            null
        }
    }
}
