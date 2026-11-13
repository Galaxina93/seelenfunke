package de.meinseelenfunke.app.data.repository

import de.meinseelenfunke.app.data.api.FinanceGroup
import de.meinseelenfunke.app.data.api.FinanceKpis
import de.meinseelenfunke.app.data.api.FinanceSpecialIssue
import de.meinseelenfunke.app.data.api.UpdateVariableRequest
import de.meinseelenfunke.app.data.api.FixedItemRequest
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.AppLogger
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.toRequestBody

class FinanceRepository(private val serviceLocator: ServiceLocator) {

    suspend fun getKpis(): Result<FinanceKpis> {
        return try {
            val kpis = serviceLocator.getFinanceApi().getKpis()
            Result.success(kpis)
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "getKpis failed", e)
            Result.failure(e)
        }
    }

    suspend fun getCategories(): Result<List<String>> {
        return try {
            val categories = serviceLocator.getFinanceApi().getCategories()
            Result.success(categories)
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "getCategories failed", e)
            Result.failure(e)
        }
    }

    suspend fun getVariableItems(limit: Int = 50): Result<List<FinanceSpecialIssue>> {
        return try {
            val items = serviceLocator.getFinanceApi().getVariableItems(limit)
            Result.success(items)
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "getVariableItems failed", e)
            Result.failure(e)
        }
    }

    suspend fun getFixedGroups(): Result<List<FinanceGroup>> {
        return try {
            val groups = serviceLocator.getFinanceApi().getFixedGroups()
            Result.success(groups)
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "getFixedGroups failed", e)
            Result.failure(e)
        }
    }

    suspend fun deleteVariableItem(id: String): Result<Unit> {
        return try {
            val response = serviceLocator.getFinanceApi().deleteVariableItem(id)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Löschen fehlgeschlagen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "deleteVariableItem failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun quickEntry(
        title: String,
        amount: Double,
        category: String?,
        isBusiness: Boolean,
        date: String?,
        location: String?,
        fileBytes: ByteArray?,
        fileName: String?,
        mimeType: String?
    ): Result<Unit> {
        return try {
            val textType = "text/plain".toMediaTypeOrNull()
            val titleBody = title.toRequestBody(textType)
            val amountBody = amount.toString().toRequestBody(textType)
            val categoryBody = category?.toRequestBody(textType)
            val isBusinessBody = (if (isBusiness) "1" else "0").toRequestBody(textType)
            val dateBody = date?.toRequestBody(textType)
            val locationBody = location?.toRequestBody(textType)

            val filePart = if (fileBytes != null) {
                val resolvedMime = mimeType ?: "application/octet-stream"
                val resolvedName = fileName ?: "upload.bin"
                val mediaType = resolvedMime.toMediaTypeOrNull()
                val requestFile = fileBytes.toRequestBody(mediaType)
                MultipartBody.Part.createFormData("file", resolvedName, requestFile)
            } else {
                null
            }

            val response = serviceLocator.getFinanceApi().quickEntry(
                titleBody,
                amountBody,
                categoryBody,
                isBusinessBody,
                dateBody,
                locationBody,
                filePart
            )

            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Schnelleintrag fehlgeschlagen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "quickEntry failed", e)
            Result.failure(e)
        }
    }

    suspend fun scanReceipt(
        fileBytes: ByteArray,
        fileName: String,
        mimeType: String,
        autoSave: Boolean
    ): Result<de.meinseelenfunke.app.data.api.ReceiptAiScanResponse> {
        return try {
            val mediaType = mimeType.toMediaTypeOrNull() ?: "image/jpeg".toMediaTypeOrNull()
            val requestFile = fileBytes.toRequestBody(mediaType)
            val filePart = MultipartBody.Part.createFormData("file", fileName, requestFile)
            val autoSaveBody = autoSave.toString().toRequestBody("text/plain".toMediaTypeOrNull())

            val response = serviceLocator.getFinanceApi().scanReceipt(filePart, autoSaveBody)
            if (response.success) {
                Result.success(response)
            } else {
                val directFallback = tryDirectGeminiReceiptScan(fileBytes, mimeType)
                if (directFallback != null) {
                    Result.success(directFallback)
                } else {
                    Result.failure(Exception(response.message ?: "Beleg konnte nicht automatisch erkannt werden."))
                }
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "scanReceipt failed", e)
            val directFallback = tryDirectGeminiReceiptScan(fileBytes, mimeType)
            if (directFallback != null) {
                return Result.success(directFallback)
            }
            val friendlyMsg = when (e) {
                is retrofit2.HttpException -> {
                    val errBody = try { e.response()?.errorBody()?.string() } catch (_: Exception) { null }
                    val parsedMsg = try {
                        if (!errBody.isNullOrBlank()) org.json.JSONObject(errBody).optString("message") else null
                    } catch (_: Exception) { null }
                    if (!parsedMsg.isNullOrBlank()) parsedMsg else "Beleg-Scan fehlgeschlagen (HTTP ${e.code()})."
                }
                is java.net.SocketTimeoutException -> "Zeitüberschreitung beim Beleg-Scan. Bitte Verbindung prüfen."
                is java.net.ConnectException, is java.net.UnknownHostException -> "Server nicht erreichbar (${serviceLocator.getBaseUrl()}). Bitte WLAN oder Server-Einstellung prüfen."
                else -> e.localizedMessage ?: "Beleg-Scan fehlgeschlagen."
            }
            Result.failure(Exception(friendlyMsg))
        }
    }

    private suspend fun tryDirectGeminiReceiptScan(
        fileBytes: ByteArray,
        mimeType: String
    ): de.meinseelenfunke.app.data.api.ReceiptAiScanResponse? = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
        val apiKey = serviceLocator.getGeminiApiKey().trim()
        if (apiKey.isEmpty()) return@withContext null

        try {
            val b64 = android.util.Base64.encodeToString(fileBytes, android.util.Base64.NO_WRAP)
            val today = java.time.LocalDate.now().toString()
            val prompt = """
                Analysiere diesen Kassenbon / Beleg und antworte AUSSCHLIESSLICH im JSON-Format:
                {"merchant":"Händler","description":"Kurzbeschreibung","title":"Händler - Kurzbeschreibung","amount":0.0,"date":"$today","location":"Ort","category":"Werkzeug & Material","is_business":true,"tax_rate":19.0,"invoice_number":""}
            """.trimIndent()

            val payload = org.json.JSONObject().apply {
                put("contents", org.json.JSONArray().put(
                    org.json.JSONObject().put("parts", org.json.JSONArray().apply {
                        put(org.json.JSONObject().put("text", prompt))
                        put(org.json.JSONObject().put("inlineData", org.json.JSONObject().apply {
                            put("mimeType", if (mimeType.isBlank()) "image/jpeg" else mimeType)
                            put("data", b64)
                        }))
                    })
                ))
                put("generationConfig", org.json.JSONObject().apply {
                    put("temperature", 0.1)
                    put("responseMimeType", "application/json")
                    put("thinkingConfig", org.json.JSONObject().put("thinkingLevel", "LOW"))
                })
            }

            val client = okhttp3.OkHttpClient.Builder()
                .connectTimeout(8, java.util.concurrent.TimeUnit.SECONDS)
                .readTimeout(20, java.util.concurrent.TimeUnit.SECONDS)
                .build()

            val req = okhttp3.Request.Builder()
                .url("https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent")
                .addHeader("x-goog-api-key", apiKey)
                .post(payload.toString().toRequestBody("application/json".toMediaTypeOrNull()))
                .build()

            client.newCall(req).execute().use { resp ->
                if (!resp.isSuccessful) return@withContext null
                val bodyStr = resp.body?.string() ?: return@withContext null
                val root = org.json.JSONObject(bodyStr)
                val parts = root.optJSONArray("candidates")?.optJSONObject(0)
                    ?.optJSONObject("content")?.optJSONArray("parts") ?: return@withContext null

                var jsonText: String? = null
                for (i in 0 until parts.length()) {
                    val part = parts.optJSONObject(i) ?: continue
                    if (part.optBoolean("thought", false)) continue
                    val t = part.optString("text", "")
                    if (t.isNotBlank()) {
                        jsonText = t
                        break
                    }
                }
                if (jsonText.isNullOrBlank()) return@withContext null

                val cleaned = jsonText.replace(Regex("^```(?:json)?\\s*", RegexOption.IGNORE_CASE), "")
                    .replace(Regex("\\s*```$"), "")
                    .trim()
                val parsed = org.json.JSONObject(cleaned)
                val amountAbs = kotlin.math.abs(parsed.optDouble("amount", 0.0))
                val merchant = parsed.optString("merchant", "Beleg").trim()
                val desc = parsed.optString("description", "").trim()
                val rawTitle = parsed.optString("title", "").trim()
                val finalTitle = if (rawTitle.isNotBlank()) rawTitle else if (desc.isNotBlank()) "$merchant - $desc" else merchant

                val scanData = de.meinseelenfunke.app.data.api.ReceiptAiScanData(
                    title = finalTitle,
                    merchant = merchant,
                    description = desc,
                    amount_abs = amountAbs,
                    amount_formatted = String.format(java.util.Locale.US, "-%.2f", amountAbs),
                    date = parsed.optString("date", today).ifBlank { today },
                    location = parsed.optString("location", ""),
                    category = parsed.optString("category", "Sonstiges").ifBlank { "Sonstiges" },
                    is_business = parsed.optBoolean("is_business", true),
                    tax_rate = parsed.optDouble("tax_rate", 19.0),
                    invoice_number = parsed.optString("invoice_number", "")
                )

                de.meinseelenfunke.app.data.api.ReceiptAiScanResponse(
                    success = true,
                    auto_saved = false,
                    data = scanData,
                    entry = null,
                    message = "Beleg '$finalTitle' erkannt."
                )
            }
        } catch (_: Exception) {
            null
        }
    }

    suspend fun updateVariableItem(
        id: String,
        title: String,
        amount: Double,
        category: String,
        executionDate: String,
        isBusiness: Boolean,
        location: String?
    ): Result<Unit> {
        return try {
            val req = UpdateVariableRequest(
                title = title,
                amount = amount,
                category = category,
                execution_date = executionDate,
                is_business = isBusiness,
                location = location
            )
            val response = serviceLocator.getFinanceApi().updateVariableItem(id, req)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Update fehlgeschlagen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "updateVariableItem failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun createFixedItem(
        financeGroupId: String,
        name: String,
        amount: Double,
        intervalMonths: Int,
        firstPaymentDate: String,
        description: String?,
        isBusiness: Boolean
    ): Result<Unit> {
        return try {
            val req = FixedItemRequest(
                finance_group_id = financeGroupId,
                name = name,
                amount = amount,
                interval_months = intervalMonths,
                first_payment_date = firstPaymentDate,
                description = description,
                is_business = isBusiness
            )
            val response = serviceLocator.getFinanceApi().createFixedItem(req)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Speichern fehlgeschlagen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "createFixedItem failed", e)
            Result.failure(e)
        }
    }

    suspend fun updateFixedItem(
        id: String,
        financeGroupId: String,
        name: String,
        amount: Double,
        intervalMonths: Int,
        firstPaymentDate: String,
        description: String?,
        isBusiness: Boolean
    ): Result<Unit> {
        return try {
            val req = FixedItemRequest(
                finance_group_id = financeGroupId,
                name = name,
                amount = amount,
                interval_months = intervalMonths,
                first_payment_date = firstPaymentDate,
                description = description,
                is_business = isBusiness
            )
            val response = serviceLocator.getFinanceApi().updateFixedItem(id, req)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Update fehlgeschlagen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "updateFixedItem failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun deleteFixedItem(id: String): Result<Unit> {
        return try {
            val response = serviceLocator.getFinanceApi().deleteFixedItem(id)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Löschen fehlgeschlagen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "deleteFixedItem failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun sendToMiniPcTrichter(
        fileBytes: ByteArray,
        fileName: String,
        mimeType: String,
        customTitle: String?,
        source: String = "android"
    ): Result<String> {
        return try {
            val textType = "text/plain".toMediaTypeOrNull()
            val mediaType = mimeType.ifBlank { "application/pdf" }.toMediaTypeOrNull()
            val requestFile = fileBytes.toRequestBody(mediaType)
            val filePart = MultipartBody.Part.createFormData(
                "file",
                fileName.ifBlank { "scan_trichter.pdf" },
                requestFile
            )
            val titlePart = customTitle?.trim()?.takeIf { it.isNotEmpty() }?.toRequestBody(textType)
            val sourcePart = source.toRequestBody(textType)

            val response = serviceLocator.getFinanceApi().enqueueMiniPcTrichter(
                file = filePart,
                customTitle = titlePart,
                source = sourcePart
            )
            if (response.success) {
                Result.success(response.message ?: "Erfolgreich im Eingang-Trichter abgelegt.")
            } else {
                Result.failure(Exception(response.message ?: "Trichter-Übergabe vom Server abgelehnt."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "FinanceRepo", "sendToMiniPcTrichter failed", e)
            val friendlyMsg = when (e) {
                is retrofit2.HttpException -> {
                    val code = e.code()
                    val errBody = try { e.response()?.errorBody()?.string() } catch (_: Exception) { null }
                    val parsedMsg = try {
                        if (!errBody.isNullOrBlank()) org.json.JSONObject(errBody).optString("message") else null
                    } catch (_: Exception) { null }
                    when {
                        code == 401 || code == 403 -> "Sitzung abgelaufen (HTTP $code). Bitte in der App neu anmelden."
                        code == 404 -> "Trichter-Endpunkt auf ${serviceLocator.getBaseUrl()} nicht gefunden (HTTP 404). Bitte Server-Adresse in den Einstellungen prüfen."
                        code == 413 -> "Datei ist zu groß für den Upload (HTTP 413, max. 25 MB)."
                        !parsedMsg.isNullOrBlank() -> "$parsedMsg (HTTP $code)"
                        else -> "Trichter-Upload fehlgeschlagen (HTTP $code)."
                    }
                }
                is java.net.SocketTimeoutException -> "Zeitüberschreitung beim Senden in den Trichter. Bitte WLAN/Mobilfunk prüfen."
                is java.net.ConnectException, is java.net.UnknownHostException -> "Server nicht erreichbar (${serviceLocator.getBaseUrl()}). Bitte Internetverbindung prüfen."
                else -> e.localizedMessage ?: "Trichter-Upload fehlgeschlagen."
            }
            Result.failure(Exception(friendlyMsg))
        }
    }
}
