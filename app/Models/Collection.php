<?php // app/Models/Collection.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $fillable = ['title', 'synopsis'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class)->orderBy('order');
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(Article::class)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('order');
    }

    public function effectiveDate(): ?\Carbon\Carbon
    {
        return $this->publishedArticles()->max('published_at')
            ? \Carbon\Carbon::parse($this->publishedArticles()->max('published_at'))
            : null;
    }
}
