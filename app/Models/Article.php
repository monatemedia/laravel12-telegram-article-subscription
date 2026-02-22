<?php // app/Models/Article.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'collection_id',
        'title',
        'slug',
        'body',
        'synopsis',
        'status',
        'published_at',
        'order',
        'pdf_path',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function analyticsEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public static function generateSlug(string $title, ?int $exceptId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $count = 1;

        while (
            static::where('slug', $slug)
                ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeStandalone($query)
    {
        return $query->whereNull('collection_id');
    }
}
