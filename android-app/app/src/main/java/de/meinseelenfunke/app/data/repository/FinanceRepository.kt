package de.meinseelenfunke.app.data.repository

import de.meinseelenfunke.app.data.api.FinanceGroup
import de.meinseelenfunke.app.data.api.FinanceKpis
import de.meinseelenfunke.app.data.api.FinanceSpecialIssue
import de.meinseelenfunke.app.data.api.UpdateVariableRequest
import de.meinseelenfunke.app.data.api.FixedItemRequest
import de.meinseelenfunke.app.di.ServiceLocator
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
            Result.failure(e)
        }
    }

    suspend fun getCategories(): Result<List<String>> {
        return try {
            val categories = serviceLocator.getFinanceApi().getCategories()
            Result.success(categories)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getVariableItems(limit: Int = 50): Result<List<FinanceSpecialIssue>> {
        return try {
            val items = serviceLocator.getFinanceApi().getVariableItems(limit)
            Result.success(items)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getFixedGroups(): Result<List<FinanceGroup>> {
        return try {
            val groups = serviceLocator.getFinanceApi().getFixedGroups()
            Result.success(groups)
        } catch (e: Exception) {
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

            android.util.Log.d("FinanceRepository", "quickEntry - title: $title, amount: $amount, category: $category, isBusiness: $isBusiness, date: $date, location: $location, fileBytes size: ${fileBytes?.size}, fileName: $fileName, mimeType: $mimeType")
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
            Result.failure(e)
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
            Result.failure(e)
        }
    }
}
