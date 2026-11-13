package de.meinseelenfunke.app.data.repository

import android.content.Context
import android.net.Uri
import de.meinseelenfunke.app.data.api.EmailAccount
import de.meinseelenfunke.app.data.api.EmailAccountResponse
import de.meinseelenfunke.app.data.api.EmailMessage
import de.meinseelenfunke.app.data.api.EmailAttachment
import de.meinseelenfunke.app.data.api.EmailRecipientDto
import de.meinseelenfunke.app.data.api.ManagementContact
import de.meinseelenfunke.app.data.api.ResolveMessageRequest
import de.meinseelenfunke.app.data.api.SaveDraftRequest
import de.meinseelenfunke.app.data.api.SendEmailRequest
import de.meinseelenfunke.app.data.api.SenderRuleRequest
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.AppLogger
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.File
import java.util.concurrent.TimeUnit

class EmailRepository(private val serviceLocator: ServiceLocator) {

    suspend fun getAccounts(): Result<List<EmailAccount>> {
        return try {
            val response = serviceLocator.getEmailApi().getAccounts()
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte E-Mail-Konten nicht laden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "getAccounts failed", e)
            Result.failure(e)
        }
    }

    suspend fun getAccountsResponse(): Result<EmailAccountResponse> {
        return try {
            val response = serviceLocator.getEmailApi().getAccounts()
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception("Konnte E-Mail-Konten nicht laden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "getAccountsResponse failed", e)
            Result.failure(e)
        }
    }

    suspend fun getRecipients(): Result<List<EmailRecipientDto>> {
        return try {
            val response = serviceLocator.getEmailApi().getRecipients()
            if (response.success) {
                Result.success(response.data ?: emptyList())
            } else {
                Result.success(emptyList())
            }
        } catch (e: Exception) {
            Result.success(emptyList())
        }
    }

    suspend fun saveAccount(
        id: String?,
        name: String,
        email: String,
        password: String?,
        imapHost: String,
        imapPort: Int,
        imapEncryption: String?,
        imapUsername: String?,
        smtpHost: String,
        smtpPort: Int,
        smtpEncryption: String?,
        smtpUsername: String?,
        signature: String?,
        isDefault: Boolean,
        isCommercial: Boolean
    ): Result<EmailAccount> {
        return try {
            val response = serviceLocator.getEmailApi().saveAccount(
                id, name, email, password, imapHost, imapPort, imapEncryption, imapUsername,
                smtpHost, smtpPort, smtpEncryption, smtpUsername, signature,
                if (isDefault) 1 else 0,
                if (isCommercial) 1 else 0
            )
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte E-Mail-Konto nicht speichern."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "saveAccount failed: email=$email", e)
            Result.failure(e)
        }
    }

    suspend fun deleteAccount(id: String): Result<Unit> {
        return try {
            val response = serviceLocator.getEmailApi().deleteAccount(id)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception("Konnte E-Mail-Konto nicht löschen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "deleteAccount failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun getContacts(): Result<List<ManagementContact>> {
        return try {
            val response = serviceLocator.getEmailApi().getContacts()
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Kontakte nicht laden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "getContacts failed", e)
            Result.failure(e)
        }
    }

    suspend fun getMessages(
        folder: String?,
        accountId: String?,
        search: String?,
        unreadOnly: Boolean?,
        global: Int? = 1,
        priorityFilter: String? = null,
        categoryFilter: String? = null
    ): Result<List<EmailMessage>> {
        return try {
            val unreadFlag = if (unreadOnly == true) 1 else null
            val response = serviceLocator.getEmailApi().getMessages(
                folder = folder,
                accountId = accountId,
                search = search,
                unread = unreadFlag,
                global = global,
                priorityFilter = priorityFilter,
                categoryFilter = categoryFilter
            )
            if (response.success) {
                Result.success(response.data.data)
            } else {
                Result.failure(Exception("Konnte E-Mails nicht laden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "getMessages failed: folder=$folder, accountId=$accountId, search=$search", e)
            Result.failure(e)
        }
    }

    suspend fun getMessageDetails(id: String): Result<EmailMessage> {
        return try {
            val response = serviceLocator.getEmailApi().getMessageDetails(id)
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte E-Mail-Details nicht laden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "getMessageDetails failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun sendEmail(
        accountId: String?,
        from: String,
        to: String,
        subject: String,
        body: String,
        attachments: List<EmailAttachment>? = null,
        draftId: String? = null,
        cc: String? = null,
        bcc: String? = null
    ): Result<Unit> {
        return try {
            val request = SendEmailRequest(
                draftId = draftId,
                accountId = accountId,
                from = from,
                to = to,
                cc = cc,
                bcc = bcc,
                subject = subject,
                body = body,
                attachments = attachments
            )
            val response = serviceLocator.getEmailApi().sendEmail(request)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Konnte E-Mail nicht senden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "sendEmail failed: from=$from, to=$to, subject=$subject", e)
            Result.failure(e)
        }
    }

    suspend fun saveDraft(
        draftId: String?,
        accountId: String,
        to: String,
        cc: String,
        bcc: String,
        subject: String,
        body: String
    ): Result<EmailMessage> {
        return try {
            val request = SaveDraftRequest(
                draftId = draftId,
                accountId = accountId,
                to = to,
                cc = cc,
                bcc = bcc,
                subject = subject,
                body = body
            )
            val response = serviceLocator.getEmailApi().saveDraft(request)
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Entwurf konnte nicht gespeichert werden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "saveDraft failed", e)
            Result.failure(e)
        }
    }

    suspend fun syncEmails(): Result<String> {
        return try {
            val response = serviceLocator.getEmailApi().syncEmails()
            if (response.success) {
                Result.success(response.message ?: "Postfächer synchronisiert.")
            } else {
                Result.failure(Exception(response.message ?: "Synchronisation fehlgeschlagen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "syncEmails failed", e)
            Result.failure(e)
        }
    }

    suspend fun aiSortEmails(): Result<String> {
        return try {
            val response = serviceLocator.getEmailApi().aiSortEmails()
            if (response.success) {
                Result.success(response.message ?: "E-Mails durch den KI-Trichter verarbeitet.")
            } else {
                Result.failure(Exception(response.message ?: "KI-Sortierung fehlgeschlagen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "aiSortEmails failed", e)
            Result.failure(e)
        }
    }

    suspend fun resolveMessageImportance(id: String, important: Boolean): Result<String> {
        return try {
            val response = serviceLocator.getEmailApi().resolveMessage(id, ResolveMessageRequest(important = important))
            if (response.success) {
                Result.success(response.message ?: if (important) "Als Wichtig markiert." else "Aufgabe als erledigt markiert.")
            } else {
                Result.failure(Exception(response.message ?: "Status konnte nicht geändert werden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "resolveMessageImportance failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun createTaskFromMail(id: String): Result<String> {
        return try {
            val response = serviceLocator.getEmailApi().createTaskFromMail(id)
            if (response.success) {
                Result.success(response.message ?: "Aufgabe in 'E-Mail Aufgaben' angelegt.")
            } else {
                Result.failure(Exception(response.message ?: "Aufgabe konnte nicht erstellt werden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "createTaskFromMail failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun createContactFromMail(id: String): Result<String> {
        return try {
            val response = serviceLocator.getEmailApi().createContactFromMail(id)
            if (response.success) {
                Result.success(response.message ?: "Absender im Adressbuch gespeichert.")
            } else {
                Result.failure(Exception(response.message ?: "Kontakt konnte nicht gespeichert werden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "createContactFromMail failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun createSenderRule(id: String, targetFolder: String): Result<String> {
        return try {
            val response = serviceLocator.getEmailApi().createSenderRule(id, SenderRuleRequest(targetFolder = targetFolder))
            if (response.success) {
                Result.success(response.message ?: "Automatische Regel für Absender aktiviert.")
            } else {
                Result.failure(Exception(response.message ?: "Regel konnte nicht gespeichert werden."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "createSenderRule failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun downloadAttachmentToFile(context: Context, attachmentId: Int, filename: String): Result<File> {
        return withContext(Dispatchers.IO) {
            try {
                val baseUrl = serviceLocator.getBaseUrl().let { if (it.endsWith("/")) it else "$it/" }
                if (!serviceLocator.isAllowedBaseUrl(baseUrl)) {
                    return@withContext Result.failure(Exception("Nicht vertrauenswürdiger Server-Endpunkt."))
                }
                val token = serviceLocator.getAuthToken()
                val url = "${baseUrl}funki/emails/attachments/$attachmentId"

                val client = OkHttpClient.Builder()
                    .connectTimeout(30, TimeUnit.SECONDS)
                    .readTimeout(60, TimeUnit.SECONDS)
                    .followRedirects(false)
                    .followSslRedirects(false)
                    .build()

                val requestBuilder = Request.Builder().url(url)
                if (!token.isNullOrBlank()) {
                    requestBuilder.addHeader("Authorization", "Bearer $token")
                }

                val response = client.newCall(requestBuilder.build()).execute()
                response.use { resp ->
                    if (!resp.isSuccessful) {
                        return@withContext Result.failure(Exception("Download fehlgeschlagen (HTTP ${resp.code})."))
                    }

                    val body = resp.body
                        ?: return@withContext Result.failure(Exception("Leere Datei vom Server erhalten."))

                    val maxAttachmentBytes = 25L * 1024L * 1024L
                    if (body.contentLength() > maxAttachmentBytes) {
                        return@withContext Result.failure(Exception("Anhang ist größer als 25 MB."))
                    }

                    val safeName = filename
                        .replace("..", "_")
                        .replace(Regex("[^a-zA-Z0-9._\\-äöüÄÖÜß ()]"), "_")
                        .trim()
                        .trimStart('.')
                        .take(120)
                        .ifBlank { "Anhang_$attachmentId.dat" }

                    val dir = File(context.cacheDir, "mail_attachments").apply { mkdirs() }
                    val targetFile = File(dir, safeName)
                    if (!targetFile.canonicalPath.startsWith(dir.canonicalPath + File.separator)) {
                        return@withContext Result.failure(SecurityException("Ungültiger Dateipfad."))
                    }

                    body.byteStream().use { input ->
                        targetFile.outputStream().use { output ->
                            val buf = ByteArray(8192)
                            var total = 0L
                            while (true) {
                                val read = input.read(buf)
                                if (read == -1) break
                                total += read
                                if (total > maxAttachmentBytes) {
                                    targetFile.delete()
                                    return@withContext Result.failure(Exception("Anhang überschreitet das Limit von 25 MB."))
                                }
                                output.write(buf, 0, read)
                            }
                        }
                    }
                    Result.success(targetFile)
                }
            } catch (e: Exception) {
                AppLogger.error(context, "EmailRepo", "downloadAttachmentToFile failed: id=$attachmentId", e)
                Result.failure(e)
            }
        }
    }

    suspend fun uploadAttachment(context: Context, uri: Uri): Result<EmailAttachment> {
        return try {
            val mimeType = getMimeType(context, uri)
            val fileName = getFileName(context, uri)
            
            val isImage = mimeType.startsWith("image/", ignoreCase = true)
            val bytes = if (isImage) {
                de.meinseelenfunke.app.util.ImageUtils.compressImageUri(context, uri) ?: readUriBytes(context, uri)
            } else {
                readUriBytes(context, uri)
            } ?: return Result.failure(Exception("Konnte Datei nicht lesen."))

            val mediaType = mimeType.toMediaTypeOrNull()
            val requestFile = bytes.toRequestBody(mediaType)
            val body = MultipartBody.Part.createFormData("file", fileName, requestFile)
            
            val response = serviceLocator.getEmailApi().uploadAttachment(body)
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Upload der Datei $fileName fehlgeschlagen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "uploadAttachment failed: uri=$uri", e)
            Result.failure(e)
        }
    }

    private fun getFileName(context: Context, uri: Uri): String {
        var name = ""
        val cursor = context.contentResolver.query(uri, null, null, null, null)
        cursor?.use {
            if (it.moveToFirst()) {
                val nameIndex = it.getColumnIndex(android.provider.OpenableColumns.DISPLAY_NAME)
                if (nameIndex != -1) {
                    name = it.getString(nameIndex)
                }
            }
        }
        if (name.isEmpty()) {
            name = uri.lastPathSegment ?: "file"
        }
        return name
    }

    private fun getMimeType(context: Context, uri: Uri): String {
        return context.contentResolver.getType(uri) ?: "application/octet-stream"
    }

    private fun readUriBytes(context: Context, uri: Uri): ByteArray? {
        return try {
            val maxUploadBytes = 25 * 1024 * 1024
            context.contentResolver.openInputStream(uri)?.use { stream ->
                val out = java.io.ByteArrayOutputStream()
                val buf = ByteArray(8192)
                var total = 0
                while (true) {
                    val read = stream.read(buf)
                    if (read == -1) break
                    total += read
                    if (total > maxUploadBytes) {
                        throw IllegalArgumentException("Anhang ist größer als 25 MB.")
                    }
                    out.write(buf, 0, read)
                }
                out.toByteArray()
            }
        } catch (e: Exception) {
            AppLogger.error(context, "EmailRepo", "readUriBytes failed", e)
            null
        }
    }

    suspend fun moveMessage(id: String, folder: String): Result<Unit> {
        return try {
            val response = serviceLocator.getEmailApi().moveMessage(id, folder)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Konnte E-Mail nicht verschieben."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "moveMessage failed: id=$id, folder=$folder", e)
            Result.failure(e)
        }
    }

    suspend fun markMessageRead(id: String, isRead: Boolean): Result<Unit> {
        return try {
            val response = serviceLocator.getEmailApi().markMessageRead(id, isRead)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Konnte E-Mail-Status nicht aktualisieren."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "markMessageRead failed: id=$id, isRead=$isRead", e)
            Result.failure(e)
        }
    }

    suspend fun deleteMessage(id: String): Result<Unit> {
        return try {
            val response = serviceLocator.getEmailApi().deleteMessage(id)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Konnte E-Mail nicht löschen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "deleteMessage failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun createFolder(accountId: String, name: String): Result<Unit> {
        return try {
            val response = serviceLocator.getEmailApi().createFolder(accountId, name)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Konnte Ordner nicht erstellen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "createFolder failed: accountId=$accountId, name=$name", e)
            Result.failure(e)
        }
    }

    suspend fun deleteFolder(accountId: String, name: String): Result<Unit> {
        return try {
            val response = serviceLocator.getEmailApi().deleteFolder(accountId, name)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Konnte Ordner nicht löschen."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "EmailRepo", "deleteFolder failed: accountId=$accountId, name=$name", e)
            Result.failure(e)
        }
    }
}


