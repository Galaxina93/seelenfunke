<?php

namespace App\Models\Blog;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use SoftDeletes, HasUuids;

    protected $fillable = [
        'user_id', 'blog_category_id', 'title', 'slug', 'excerpt', 'content',
        'featured_image', 'status', 'published_at',
        'meta_title', 'meta_description', 'is_advertisement', 'contains_affiliate_links'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_advertisement' => 'boolean',
        'contains_affiliate_links' => 'boolean',
    ];

    // --- SCOPES ---

    /**
     * Zeigt nur Artikel, die "Published" sind UND deren Datum erreicht ist.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    // --- RELATIONSHIPS ---

    public function author()
    {
        // ÄNDERUNG: belongsTo(Admin::class, ...) statt User::class
        return $this->belongsTo(Admin::class, 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    // Hilfsfunktion für SEO Titel
    public function getSeoTitleAttribute(): string
    {
        return $this->meta_title ?: $this->title;
    }
}
