<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('quote_number')->unique(); // z.B. AN-2024-001

            // NEU: Für den öffentlichen Link
            $table->string('token')->unique()->nullable();
            $table->timestamp('expires_at')->nullable();

            // Kontaktdaten (Snapshot)
            $table->string('email');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company')->nullable();
            $table->string('phone')->nullable(); // Wichtig für Rückfragen

            // Status
            $table->enum('status', ['open', 'replied', 'converted', 'rejected'])->default('open');

            // Verknüpfung (falls bereits Kunde, sonst null)
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Wenn umgewandelt, hier die Order ID speichern
            $table->foreignUuid('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();

            // Summen (Cent)
            $table->integer('net_total');
            $table->integer('tax_total');
            $table->integer('gross_total');

            $table->integer('shipping_price')->default(0);

            // Flags
            $table->boolean('is_express')->default(false);
            $table->date('deadline')->nullable();

            $table->text('admin_notes')->nullable(); // Interne Notizen
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
