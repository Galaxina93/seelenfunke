package de.meinseelenfunke.app.ui.screens

import android.content.Context
import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.data.api.EmailAccount
import de.meinseelenfunke.app.data.api.EmailMessage
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class EmailUiState {
    object Loading : EmailUiState()
    object Success : EmailUiState()
    data class Error(val message: String) : EmailUiState()
}

class EmailViewModel : ViewModel() {

    private val repository = ServiceLocator.emailRepository

    private val _uiState = MutableStateFlow<EmailUiState>(EmailUiState.Loading)
    val uiState: StateFlow<EmailUiState> = _uiState.asStateFlow()

    private val _accounts = MutableStateFlow<List<EmailAccount>>(emptyList())
    val accounts: StateFlow<List<EmailAccount>> = _accounts.asStateFlow()

    private val _messages = MutableStateFlow<List<EmailMessage>>(emptyList())
    val messages: StateFlow<List<EmailMessage>> = _messages.asStateFlow()

    private val _selectedAccount = MutableStateFlow<EmailAccount?>(null)
    val selectedAccount: StateFlow<EmailAccount?> = _selectedAccount.asStateFlow()

    private val _selectedFolder = MutableStateFlow<String>("INBOX")
    val selectedFolder: StateFlow<String> = _selectedFolder.asStateFlow()

    private val _selectedMessage = MutableStateFlow<EmailMessage?>(null)
    val selectedMessage: StateFlow<EmailMessage?> = _selectedMessage.asStateFlow()

    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    private val _unreadOnly = MutableStateFlow(false)
    val unreadOnly: StateFlow<Boolean> = _unreadOnly.asStateFlow()

    private val _isSending = MutableStateFlow(false)
    val isSending: StateFlow<Boolean> = _isSending.asStateFlow()

    private val _composeAttachments = MutableStateFlow<List<Uri>>(emptyList())
    val composeAttachments: StateFlow<List<Uri>> = _composeAttachments.asStateFlow()

    fun addComposeAttachment(uri: Uri) {
        val current = _composeAttachments.value.toMutableList()
        if (!current.contains(uri)) {
            current.add(uri)
            _composeAttachments.value = current
        }
    }

    fun removeComposeAttachment(uri: Uri) {
        val current = _composeAttachments.value.toMutableList()
        current.remove(uri)
        _composeAttachments.value = current
    }

    fun clearComposeAttachments() {
        _composeAttachments.value = emptyList()
    }

    private val webSocketListener: (String, String) -> Unit = { event, _ ->
        if (event == "new_email") {
            loadAccountsAndMessages(showLoading = false)
        }
    }

    init {
        loadAccountsAndMessages()
        de.meinseelenfunke.app.data.EmailWebSocketClient.addListener(webSocketListener)
    }

    override fun onCleared() {
        super.onCleared()
        de.meinseelenfunke.app.data.EmailWebSocketClient.removeListener(webSocketListener)
    }

    fun loadAccountsAndMessages(showLoading: Boolean = true) {
        viewModelScope.launch {
            if (showLoading) {
                _uiState.value = EmailUiState.Loading
            }
            repository.getAccounts().onSuccess { accountsList ->
                _accounts.value = accountsList
                if (_selectedAccount.value == null && accountsList.isNotEmpty()) {
                    _selectedAccount.value = accountsList.first()
                }
                loadMessages(showLoading = false)
            }.onFailure { exception ->
                _uiState.value = EmailUiState.Error(exception.message ?: "Fehler beim Laden der E-Mail-Konten.")
            }
        }
    }

    fun loadMessages(showLoading: Boolean = false) {
        viewModelScope.launch {
            if (showLoading) {
                _uiState.value = EmailUiState.Loading
            }
            val accountId = _selectedAccount.value?.id
            repository.getMessages(
                folder = _selectedFolder.value,
                accountId = accountId,
                search = _searchQuery.value.ifBlank { null },
                unreadOnly = if (_unreadOnly.value) true else null
            ).onSuccess { messagesList ->
                _messages.value = messagesList
                _uiState.value = EmailUiState.Success
            }.onFailure { exception ->
                _uiState.value = EmailUiState.Error(exception.message ?: "Fehler beim Laden der E-Mails.")
            }
        }
    }

    fun selectAccount(account: EmailAccount) {
        _selectedAccount.value = account
        _selectedMessage.value = null
        loadMessages(showLoading = true)
    }

    fun selectFolder(folder: String) {
        _selectedFolder.value = folder
        _selectedMessage.value = null
        loadMessages(showLoading = true)
    }

    fun selectMessage(message: EmailMessage) {
        viewModelScope.launch {
            _selectedMessage.value = message
            if (!message.isRead) {
                repository.markMessageRead(message.id, true).onSuccess {
                    // Update read status locally in the list
                    _messages.value = _messages.value.map { msg ->
                        if (msg.id == message.id) msg.copy(isRead = true) else msg
                    }
                    // Fetch fresh unread count
                    repository.getAccounts().onSuccess { accountsList ->
                        _accounts.value = accountsList
                    }
                }
            }
            // Fetch detailed email body
            repository.getMessageDetails(message.id).onSuccess { detailedMsg ->
                _selectedMessage.value = detailedMsg
            }
        }
    }

    fun toggleMessageRead(message: EmailMessage) {
        viewModelScope.launch {
            val newReadStatus = !message.isRead
            repository.markMessageRead(message.id, newReadStatus).onSuccess {
                _messages.value = _messages.value.map { msg ->
                    if (msg.id == message.id) msg.copy(isRead = newReadStatus) else msg
                }
                if (_selectedMessage.value?.id == message.id) {
                    _selectedMessage.value = _selectedMessage.value?.copy(isRead = newReadStatus)
                }
                // Fetch fresh unread count
                repository.getAccounts().onSuccess { accountsList ->
                    _accounts.value = accountsList
                }
            }
        }
    }

    fun setSearchQuery(query: String) {
        _searchQuery.value = query
        loadMessages(showLoading = false)
    }

    fun setUnreadOnly(unread: Boolean) {
        _unreadOnly.value = unread
        loadMessages(showLoading = true)
    }

    fun deleteMessage(message: EmailMessage) {
        viewModelScope.launch {
            repository.deleteMessage(message.id).onSuccess {
                _messages.value = _messages.value.filter { it.id != message.id }
                if (_selectedMessage.value?.id == message.id) {
                    _selectedMessage.value = null
                }
                loadAccountsAndMessages(showLoading = false)
            }
        }
    }

    fun moveMessage(message: EmailMessage, folder: String) {
        viewModelScope.launch {
            repository.moveMessage(message.id, folder).onSuccess {
                _messages.value = _messages.value.filter { it.id != message.id }
                if (_selectedMessage.value?.id == message.id) {
                    _selectedMessage.value = null
                }
                loadAccountsAndMessages(showLoading = false)
            }
        }
    }

    fun saveAccount(
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
        isCommercial: Boolean,
        onComplete: (Boolean) -> Unit
    ) {
        viewModelScope.launch {
            repository.saveAccount(
                id, name, email, password, imapHost, imapPort, imapEncryption, imapUsername,
                smtpHost, smtpPort, smtpEncryption, smtpUsername, signature, isDefault, isCommercial
            ).onSuccess {
                loadAccountsAndMessages(showLoading = false)
                onComplete(true)
            }.onFailure {
                onComplete(false)
            }
        }
    }

    fun deleteAccount(id: String, onComplete: (Boolean) -> Unit) {
        viewModelScope.launch {
            repository.deleteAccount(id).onSuccess {
                val updatedAccounts = _accounts.value.filter { it.id != id }
                _accounts.value = updatedAccounts
                if (_selectedAccount.value?.id == id) {
                    _selectedAccount.value = updatedAccounts.firstOrNull()
                }
                loadAccountsAndMessages(showLoading = false)
                onComplete(true)
            }.onFailure {
                onComplete(false)
            }
        }
    }

    fun sendEmail(
        context: Context,
        to: String,
        subject: String,
        body: String,
        onComplete: (Boolean) -> Unit
    ) {
        val fromAccount = _selectedAccount.value ?: return
        _isSending.value = true
        viewModelScope.launch {
            try {
                val uploadedAttachments = mutableListOf<de.meinseelenfunke.app.data.api.EmailAttachment>()
                val uris = _composeAttachments.value
                
                for (uri in uris) {
                    val uploadResult = repository.uploadAttachment(context, uri)
                    if (uploadResult.isSuccess) {
                        uploadedAttachments.add(uploadResult.getOrThrow())
                    } else {
                        _isSending.value = false
                        onComplete(false)
                        return@launch
                    }
                }
                
                repository.sendEmail(
                    from = fromAccount.email,
                    to = to,
                    subject = subject,
                    body = body,
                    attachments = if (uploadedAttachments.isEmpty()) null else uploadedAttachments
                ).onSuccess {
                    _isSending.value = false
                    clearComposeAttachments()
                    onComplete(true)
                    loadMessages(showLoading = false)
                }.onFailure {
                    _isSending.value = false
                    onComplete(false)
                }
            } catch (e: Exception) {
                _isSending.value = false
                onComplete(false)
            }
        }
    }
}
