<?php

namespace Shazzoo\ContentStudio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Shazzoo\ContentStudio\ContentStudio;

class Article extends Model
{
    protected $table = 'content_studio_articles';

    protected $guarded = ['id'];

    protected $casts = [
        'cta' => 'array',
        'cluster' => 'array',
        'generated_at' => 'datetime',
        'planned_at' => 'datetime',
        'published_confirmed_at' => 'datetime',
    ];

    public function relatedHubArticles(): HasMany
    {
        return $this->hasMany(self::class, 'hub_content_id', 'content_studio_article_id')
            ->where('locale', $this->locale);
    }

    /** Articles that can be shown: in the given locale and with a slug. */
    public function scopeVisible(Builder $query, string $locale): void
    {
        $query->where('locale', $locale)
            ->whereNotNull('slug')
            ->where('slug', '!=', '');
    }

    public function isHub(): bool
    {
        return $this->content_type === 'hub';
    }

    public function url(): string
    {
        return ContentStudio::url($this);
    }

    /** The same article in the other languages of the Engine project. */
    public function translations(): HasMany
    {
        return $this->hasMany(self::class, 'content_studio_article_id', 'content_studio_article_id')
            ->whereKeyNot($this->getKey())
            ->whereNotNull('slug')
            ->where('slug', '!=', '');
    }

    public function imageUrl(): ?string
    {
        if (! filled($this->featured_image_url)) {
            return null;
        }

        return Storage::disk('public')->url($this->featured_image_url);
    }

    /** Excerpt from the Engine, or else a shortened version of the body. */
    public function excerpt(int $limit = 160): string
    {
        return (string) ($this->excerpt ?: Str::limit(strip_tags((string) $this->body_html), $limit));
    }

    public function readTime(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body_html)) / 200));
    }

    public function formattedDate(): ?string
    {
        return $this->generated_at?->locale(app()->getLocale())->isoFormat('LL');
    }
}
