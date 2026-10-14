<?php

namespace App\Models\Marketing;

use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingBlogPost extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'marketing_blog_posts';

    protected $fillable = [
        'user_id', 'author_name', 'blog_category_id', 'title', 'slug', 'excerpt', 'content',
        'featured_image', 'header_image', 'status', 'published_at',
        'meta_title', 'meta_description', 'is_advertisement', 'contains_affiliate_links'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_advertisement' => 'boolean',
        'contains_affiliate_links' => 'boolean',
    ];

    /**
     * Zeigt nur Artikel, die "published" sind.
     * In Produktion nur Artikel, deren Datum bereits erreicht ist (geplante Beiträge).
     * Im lokalen Modus oder für eingeloggte Admins können geplante Beiträge bereits begutachtet werden.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');

        if (!app()->isLocal() && !auth('admin')->check()) {
            $query->where('published_at', '<=', now());
        }
    }

    // --- RELATIONSHIPS ---

    public function author()
    {
        // ÄNDERUNG: belongsTo(Admin::class, ...)
        return $this->belongsTo(Admin::class, 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(MarketingBlogCategory::class, 'blog_category_id');
    }

    // Hilfsfunktion für SEO Titel
    public function getSeoTitleAttribute(): string
    {
        return $this->meta_title ?: $this->title;
    }

    // Gibt den konkreten Namen des Autors zurück
    public function getDisplayAuthorAttribute(): string
    {
        if ($this->author_name) {
            return $this->author_name;
        }
        return $this->author ? ($this->author->first_name . ' ' . $this->author->last_name) : 'Mein-Seelenfunke';
    }
}
