<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // z.B. "Standard DE"
            $table->decimal('rate', 5, 2); // z.B. 19.00
            $table->string('country_code', 2)->default('DE'); // ISO Code
            $table->string('tax_class')->default('standard'); // Verknüpfung zum Produkt
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        DB::table('tax_rates')->insert([
            ['name' => 'Standard DE', 'rate' => 19.00, 'tax_class' => 'standard', 'is_default' => true],
            ['name' => 'Ermäßigt DE', 'rate' => 7.00, 'tax_class' => 'reduced', 'is_default' => false],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
