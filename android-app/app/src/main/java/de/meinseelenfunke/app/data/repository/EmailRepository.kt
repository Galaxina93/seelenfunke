package de.meinseelenfunke.app.data.repository

import android.content.Context
import android.net.Uri
import de.meinseelenfunke.app.data.api.EmailAccount
import de.meinseelenfunke.app.data.api.EmailMessage
import de.meinseelenfunke.app.data.api.EmailAttachment
import de.meinseelenfunke.app.data.api.SendEmailRequest
import de.meinseelenfunke.app.di.ServiceLocator
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody

import de.meinseelenfunke.app.data.api.ManagementContact

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
            Result.failure(e)
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
                smtpHost, smtpPort, smtpEncryption, smtpUsername, signature, isDefault, isCommercial
            )
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte E-Mail-Konto nicht speichern."))
            }
        } catch (e: Exception) {
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
            Result.failure(e)
        }
    }

    suspend fun getMessages(
        folder: String?,
        accountId: String?,
        search: String?,
        unreadOnly: Boolean?
    ): Result<List<EmailMessage>> {
        return try {
            val response = serviceLocator.getEmailApi().getMessages(folder, accountId, search, unreadOnly)
            if (response.success) {
                Result.success(response.data.data)
            } else {
                Result.failure(Exception("Konnte E-Mails nicht laden."))
            }
        } catch (e: Exception) {
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
            Result.failure(e)
        }
    }

    suspend fun sendEmail(
        from: String,
        to: String,
        subject: String,
        body: String,
        attachments: List<EmailAttachment>? = null
    ): Result<Unit> {
        return try {
            val request = SendEmailRequest(
                from = from,
                to = to,
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
            Result.failure(e)
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
            context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
        } catch (e: Exception) {
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
            Result.failure(e)
        }
    }
}
