<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingBlogCategory extends Model
{
    use HasUuids;

    protected $table = 'marketing_blog_categories';

    protected $fillable = ['name', 'slug'];

    /**
     * Beziehung zu den Blog-Posts.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(MarketingBlogPost::class, 'blog_category_id');
    }
}
