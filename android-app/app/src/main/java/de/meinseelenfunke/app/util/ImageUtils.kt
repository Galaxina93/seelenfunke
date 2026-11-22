package de.meinseelenfunke.app.util

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import android.media.ExifInterface
import android.net.Uri
import java.io.ByteArrayOutputStream
import java.util.Locale

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

    private data class JpegStreamSpec(
        val jpegBytes: ByteArray,
        val width: Int,
        val height: Int,
        val colorSpace: String
    )

    private fun parseJpegSofHeader(bytes: ByteArray): JpegStreamSpec? {
        if (bytes.size < 16) return null
        if ((bytes[0].toInt() and 0xFF) != 0xFF || (bytes[1].toInt() and 0xFF) != 0xD8) return null

        var i = 2
        while (i + 8 < bytes.size) {
            if ((bytes[i].toInt() and 0xFF) != 0xFF) {
                i++
                continue
            }
            var marker = bytes[i + 1].toInt() and 0xFF
            while (marker == 0xFF && i + 2 < bytes.size) {
                i++
                marker = bytes[i + 1].toInt() and 0xFF
            }
            if (marker == 0xD9 || marker == 0xDA) break
            if (marker == 0x00 || marker in 0xD0..0xD8 || marker == 0x01) {
                i += 2
                continue
            }
            if (i + 3 >= bytes.size) break
            val segLen = ((bytes[i + 2].toInt() and 0xFF) shl 8) or (bytes[i + 3].toInt() and 0xFF)
            if (segLen < 2 || i + 2 + segLen > bytes.size) break

            // Baseline (SOF0 = 0xC0), Extended Sequential (SOF1 = 0xC1), Progressive (SOF2 = 0xC2)
            if (marker == 0xC0 || marker == 0xC1 || marker == 0xC2) {
                if (i + 9 >= bytes.size) return null
                val precision = bytes[i + 4].toInt() and 0xFF
                val height = ((bytes[i + 5].toInt() and 0xFF) shl 8) or (bytes[i + 6].toInt() and 0xFF)
                val width = ((bytes[i + 7].toInt() and 0xFF) shl 8) or (bytes[i + 8].toInt() and 0xFF)
                val components = bytes[i + 9].toInt() and 0xFF
                if (precision != 8 || width <= 0 || height <= 0) return null
                val cs = when (components) {
                    1 -> "/DeviceGray"
                    3 -> "/DeviceRGB"
                    else -> return null
                }
                return JpegStreamSpec(bytes, width, height, cs)
            }
            i += 2 + segLen
        }
        return null
    }

    private fun normalizeToEmbeddableJpeg(rawPageBytes: ByteArray): JpegStreamSpec? {
        parseJpegSofHeader(rawPageBytes)?.let { return it }

        val bmp = BitmapFactory.decodeByteArray(rawPageBytes, 0, rawPageBytes.size) ?: return null
        return try {
            val out = ByteArrayOutputStream()
            bmp.compress(Bitmap.CompressFormat.JPEG, 82, out)
            val reencoded = out.toByteArray()
            parseJpegSofHeader(reencoded)
        } finally {
            bmp.recycle()
        }
    }

    /**
     * Compiles 1..N JPEG page byte arrays directly into a compact PDF 1.4 document using native
     * `/Filter /DCTDecode` image streams (~250 KB/page instead of ~4 MB uncompressed Canvas bitmaps)
     * with zero full-page Bitmap decoding when input pages are standard RGB/Grayscale JPEGs.
     */
    fun buildPdfFromJpegPages(jpegPages: List<ByteArray>): ByteArray? {
        if (jpegPages.isEmpty()) return null
        return try {
            val specs = jpegPages.mapNotNull { normalizeToEmbeddableJpeg(it) }
            if (specs.isEmpty()) return null

            val pageCount = specs.size
            val totalObjects = 2 + pageCount * 3
            val offsets = IntArray(totalObjects + 1)
            val out = ByteArrayOutputStream(specs.sumOf { it.jpegBytes.size + 1024 } + 2048)

            fun writeAscii(text: String) {
                out.write(text.toByteArray(Charsets.ISO_8859_1))
            }

            // PDF 1.4 Header + binary comment marker
            writeAscii("%PDF-1.4\n%\u00E2\u00E3\u00CF\u00D3\n")

            // 1 0 obj: Catalog
            offsets[1] = out.size()
            writeAscii("1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n")

            // 2 0 obj: Pages root
            val kidsRefs = (0 until pageCount).joinToString(" ") { idx -> "${3 + idx * 3} 0 R" }
            offsets[2] = out.size()
            writeAscii("2 0 obj\n<< /Type /Pages /Kids [ $kidsRefs ] /Count $pageCount >>\nendobj\n")

            specs.forEachIndexed { idx, spec ->
                val pageObj = 3 + idx * 3
                val contentObj = 4 + idx * 3
                val imgObj = 5 + idx * 3

                val isLandscape = spec.width > spec.height
                val pageW = if (isLandscape) 841.89f else 595.28f
                val pageH = if (isLandscape) 595.28f else 841.89f
                val margin = 14f

                val availW = pageW - margin * 2f
                val availH = pageH - margin * 2f
                val scale = kotlin.math.min(availW / spec.width.toFloat(), availH / spec.height.toFloat())
                val drawW = spec.width * scale
                val drawH = spec.height * scale
                val left = (pageW - drawW) / 2f
                val bottom = (pageH - drawH) / 2f

                val contentCmd = String.format(
                    Locale.US,
                    "q %.2f 0 0 %.2f %.2f %.2f cm /Im0 Do Q",
                    drawW,
                    drawH,
                    left,
                    bottom
                )
                val contentBytes = contentCmd.toByteArray(Charsets.ISO_8859_1)

                // Page object
                offsets[pageObj] = out.size()
                writeAscii(
                    String.format(
                        Locale.US,
                        "%d 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /ProcSet [/PDF /ImageC /ImageB] /XObject << /Im0 %d 0 R >> >> /Contents %d 0 R >>\nendobj\n",
                        pageObj,
                        pageW,
                        pageH,
                        imgObj,
                        contentObj
                    )
                )

                // Content stream object
                offsets[contentObj] = out.size()
                writeAscii("$contentObj 0 obj\n<< /Length ${contentBytes.size} >>\nstream\n")
                out.write(contentBytes)
                writeAscii("\nendstream\nendobj\n")

                // Native DCTDecode JPEG XObject
                offsets[imgObj] = out.size()
                writeAscii(
                    "$imgObj 0 obj\n<< /Type /XObject /Subtype /Image /Width ${spec.width} /Height ${spec.height} " +
                        "/ColorSpace ${spec.colorSpace} /BitsPerComponent 8 /Filter /DCTDecode /Length ${spec.jpegBytes.size} >>\nstream\n"
                )
                out.write(spec.jpegBytes)
                writeAscii("\nendstream\nendobj\n")
            }

            val xrefOffset = out.size()
            writeAscii("xref\n0 ${totalObjects + 1}\n")
            writeAscii("0000000000 65535 f \n")
            for (objId in 1..totalObjects) {
                writeAscii(String.format(Locale.US, "%010d 00000 n \n", offsets[objId]))
            }
            writeAscii("trailer\n<< /Size ${totalObjects + 1} /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF\n")

            out.toByteArray()
        } catch (e: Exception) {
            e.printStackTrace()
            null
        }
    }
}
