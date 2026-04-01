<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('order_orders')->cascadeOnDelete();
            
            $table->string('tracking_number'); // e.g. DHL piececode
            $table->string('shipping_label_path')->nullable(); // stored PDF label
            $table->string('carrier')->default('DHL'); // In case other carriers are added later
            $table->string('status')->default('shipped'); // status of this specific package (e.g., shipped, transit, delivered)
            
            $table->timestamps();
        });

        // Migrate Old Data
        $orders = DB::table('order_orders')->whereNotNull('tracking_number')->get();
        foreach ($orders as $order) {
            DB::table('order_shipments')->insert([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'shipping_label_path' => $order->shipping_label_path,
                'carrier' => 'DHL',
                'status' => 'shipped',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Drop old columns from order_orders
        Schema::table('order_orders', function (Blueprint $table) {
            $table->dropColumn(['tracking_number', 'shipping_label_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add columns
        Schema::table('order_orders', function (Blueprint $table) {
            $table->string('tracking_number')->nullable();
            $table->string('shipping_label_path')->nullable();
        });

        // Migrate Data back to first shipment
        $shipments = DB::table('order_shipments')->groupBy('order_id')->get();
        foreach ($shipments as $shipment) {
            DB::table('order_orders')
                ->where('id', $shipment->order_id)
                ->update([
                    'tracking_number' => $shipment->tracking_number,
                    'shipping_label_path' => $shipment->shipping_label_path,
                ]);
        }

        // Drop new table
        Schema::dropIfExists('order_shipments');
    }
};
