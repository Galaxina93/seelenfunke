<?php

// database/migrations/xxxx_xx_xx_create_blog_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kategorien
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Artikel
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade'); // Author
            $table->foreignUuid('blog_category_id')->nullable()->constrained('blog_categories')->nullOnDelete();

            // Inhalt
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable(); // Für Meta Desc & Vorschau
            $table->longText('content');
            $table->string('featured_image')->nullable();

            // Status & Zeitsteuerung
            $table->enum('status', ['draft', 'scheduled', 'published'])->default('draft');
            $table->dateTime('published_at')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();

            // Rechtliches / Compliance
            $table->boolean('is_advertisement')->default(false)->comment('Kennzeichnung als Werbung/Anzeige');
            $table->boolean('contains_affiliate_links')->default(false);

            $table->softDeletes();
            $table->timestamps();

            // Index für Performance
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
