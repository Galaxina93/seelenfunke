<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\Mail\MailFolder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

Route::prefix('funki/emails')->group(function () {

    // 1. Accounts & Folders
    Route::get('/accounts', function () {
        $accounts = MailAccount::all();
        $result = [];

        $baseFolders = [
            'INBOX' => 'Posteingang',
            'Sent' => 'Gesendet',
            'Drafts' => 'Entwürfe',
            'Junk' => 'Spam',
            'Trash' => 'Papierkorb',
            'Archive' => 'Archiv',
        ];

        foreach ($accounts as $acc) {
            $accFolders = $baseFolders;
            $customFolders = MailFolder::where('mail_account_id', $acc->id)->pluck('name')->toArray();
            foreach ($customFolders as $cf) {
                $accFolders[$cf] = $cf;
            }

            $folderCounts = [];
            $totalUnread = 0;
            $query = MailMessage::where('mail_account_id', $acc->id);

            foreach ($accFolders as $key => $label) {
                $qc = clone $query;
                if ($key === 'Archive') {
                    $count = $qc->where('is_archived', true)->where('is_read', false)->count();
                } else {
                    $count = $qc->where('folder', $key)->where('is_archived', false)->where('is_read', false)->count();
                }
                $folderCounts[$key] = $count;
                $totalUnread += $count;
            }

            $result[] = [
                'id' => $acc->id,
                'name' => $acc->name,
                'email' => $acc->email,
                'is_default' => $acc->is_default,
                'folders' => $accFolders,
                'counts' => $folderCounts,
                'total_unread' => $totalUnread
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    });

    // 2. Mails List (with filters)
    Route::get('/messages', function (Request $request) {
        $query = MailMessage::query();

        if ($request->has('account_id')) {
            $query->where('mail_account_id', $request->account_id);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', $search)
                  ->orWhere('from_email', 'like', $search)
                  ->orWhere('from_name', 'like', $search);
            });
        } elseif ($request->has('folder')) {
            if ($request->folder === 'Archive') {
                $query->where('is_archived', true);
            } else {
                $query->where('folder', $request->folder)->where('is_archived', false);
            }
        }

        if ($request->has('unread') && $request->unread == '1') {
            $query->where('is_read', false);
        }

        $messages = $query->orderBy('received_at', 'desc')->paginate(50);
        
        return response()->json(['success' => true, 'data' => $messages]);
    });

    // 3. Single Mail Details
    Route::get('/messages/{id}', function ($id) {
        $msg = MailMessage::with('attachments')->findOrFail($id);
        
        // Auto mark as read when fetching details
        if (!$msg->is_read) {
            $msg->update(['is_read' => true]);
        }

        return response()->json(['success' => true, 'data' => $msg]);
    });

    // 4. Send Mail
    Route::post('/send', function (Request $request) {
        $data = $request->validate([
            'account_id' => 'required',
            'to' => 'required|email',
            'subject' => 'required|string',
            'body' => 'required|string',
        ]);

        $account = MailAccount::findOrFail($data['account_id']);

        if (!$account->smtp_host) {
            return response()->json(['error' => 'SMTP Konfiguration fehlt'], 400);
        }

        try {
            Config::set('mail.mailers.dynamic', [
                'transport' => 'smtp',
                'host' => trim($account->smtp_host),
                'port' => trim($account->smtp_port),
                'encryption' => trim($account->smtp_encryption),
                'username' => trim($account->smtp_username ?: $account->email),
                'password' => trim($account->password),
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN'),
            ]);

            app('mail.manager')->purge('dynamic');

            // TODO: In testing environment it falls back to Mail::to, in prod to Mail::mailer('dynamic')
            $signature = "<br><br>" . $account->signature;
            $bodyHtml = $data['body'] . $signature;
            $signatureHtml = null;

            if (app()->environment('testing')) {
                Mail::to($data['to'])->send(
                    new \App\Mail\CrmOutgoingMailToCustomer(
                        $data['subject'], $bodyHtml, $signatureHtml, $account->email, $account->name, []
                    )
                );
            } else {
                Mail::mailer('dynamic')->to($data['to'])->send(
                    new \App\Mail\CrmOutgoingMailToCustomer(
                        $data['subject'], $bodyHtml, $signatureHtml, $account->email, $account->name, []
                    )
                );
            }

            // Save in Sent
            MailMessage::create([
                'mail_account_id' => $account->id,
                'message_id' => 'sent-' . uniqid(),
                'folder' => 'Sent',
                'subject' => $data['subject'],
                'from_name' => $account->name,
                'from_email' => $account->email,
                'to_email' => $data['to'],
                'body_plain' => $data['body'],
                'body_html' => $bodyHtml,
                'is_read' => true,
                'has_attachments' => false,
                'received_at' => now()
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('API SMTP Sende-Fehler: ' . $e->getMessage());
            return response()->json(['error' => 'Sende-Fehler: ' . $e->getMessage()], 500);
        }
    });

    // 5. Move Message
    Route::put('/messages/{id}/move', function (Request $request, $id) {
        $data = $request->validate(['folder' => 'required|string']);
        $msg = MailMessage::findOrFail($id);
        $msg->update(['folder' => $data['folder'], 'is_archived' => ($data['folder'] === 'Archive')]);
        return response()->json(['success' => true]);
    });

    // 6. Toggle Read Status
    Route::put('/messages/{id}/read', function (Request $request, $id) {
        $data = $request->validate(['is_read' => 'required|boolean']);
        $msg = MailMessage::findOrFail($id);
        $msg->update(['is_read' => $data['is_read']]);
        return response()->json(['success' => true]);
    });

    // 7. Delete (Trash)
    Route::delete('/messages/{id}', function ($id) {
        $msg = MailMessage::findOrFail($id);
        if ($msg->folder === 'Trash') {
            $msg->delete();
        } else {
            $msg->update(['folder' => 'Trash']);
        }
        return response()->json(['success' => true]);
    });
});
