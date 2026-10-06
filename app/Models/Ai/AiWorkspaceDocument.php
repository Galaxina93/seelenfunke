<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiWorkspaceDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sha256',
        'filename',
        'file_path',
        'file_type',
        'file_size',
        'title',
        'category',
        'tags',
        'purpose',
        'summary',
        'key_facts',
        'extracted_date',
        'content_preview',
        'full_text',
    ];

    protected $casts = [
        'tags' => 'array',
        'key_facts' => 'array',
        'extracted_date' => 'date',
        'file_size' => 'integer',
    ];

    /**
     * Get the authenticated download / preview URL for the workspace file.
     */
    public function getUrlAttribute(): string
    {
        return route('admin.ai.workspace.file', ['path' => $this->file_path]);
    }

    /**
     * Scope a query to search documents by keyword in title, purpose, summary, or content.
     */
    public function scopeSearch($query, string $term)
    {
        $term = trim($term);
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('filename', 'like', "%{$term}%")
              ->orWhere('purpose', 'like', "%{$term}%")
              ->orWhere('summary', 'like', "%{$term}%")
              ->orWhere('category', 'like', "%{$term}%")
              ->orWhere('full_text', 'like', "%{$term}%");
        });
    }

    /**
     * Find a document by relative path or exact filename.
     */
    public static function findByPathOrName(string $identifier): ?self
    {
        $clean = trim(str_replace('\\', '/', $identifier));
        $base = basename($clean);

        return self::where('file_path', $clean)
            ->orWhere('file_path', 'agenten/workspace/' . ltrim($clean, '/'))
            ->orWhere('filename', $base)
            ->first();
    }
}
