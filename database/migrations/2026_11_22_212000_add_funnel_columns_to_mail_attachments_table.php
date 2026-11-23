<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mail_attachments')) {
            return;
        }

        Schema::table('mail_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('mail_attachments', 'funnel_type')) {
                $table->string('funnel_type', 32)->nullable()->index()->after('content_id');
            }
            if (!Schema::hasColumn('mail_attachments', 'funnel_status')) {
                $table->string('funnel_status', 32)->default('open')->index()->after('funnel_type');
            }
            if (!Schema::hasColumn('mail_attachments', 'accounting_issue_id')) {
                $table->uuid('accounting_issue_id')->nullable()->index()->after('funnel_status');
            }
            if (!Schema::hasColumn('mail_attachments', 'workspace_document_id')) {
                $table->unsignedBigInteger('workspace_document_id')->nullable()->index()->after('accounting_issue_id');
            }
            if (!Schema::hasColumn('mail_attachments', 'sha256_hash')) {
                $table->string('sha256_hash', 64)->nullable()->index()->after('workspace_document_id');
            }
            if (!Schema::hasColumn('mail_attachments', 'funnel_meta')) {
                $table->json('funnel_meta')->nullable()->after('sha256_hash');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mail_attachments')) {
            return;
        }

        Schema::table('mail_attachments', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('mail_attachments', 'funnel_type') ? 'funnel_type' : null,
                Schema::hasColumn('mail_attachments', 'funnel_status') ? 'funnel_status' : null,
                Schema::hasColumn('mail_attachments', 'accounting_issue_id') ? 'accounting_issue_id' : null,
                Schema::hasColumn('mail_attachments', 'workspace_document_id') ? 'workspace_document_id' : null,
                Schema::hasColumn('mail_attachments', 'sha256_hash') ? 'sha256_hash' : null,
                Schema::hasColumn('mail_attachments', 'funnel_meta') ? 'funnel_meta' : null,
            ]);
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
