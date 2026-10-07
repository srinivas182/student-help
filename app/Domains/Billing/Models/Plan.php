<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'description', 'price_cents', 'interval', 'features',
        'monthly_request_quota', 'monthly_ai_quota', 'is_public', 'position',
    ];

    protected function casts(): array
    {
        return ['features' => 'array', 'is_public' => 'boolean'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)->orderBy('position');
    }

    public function isFree(): bool
    {
        return $this->price_cents === 0;
    }

    public function priceRands(): string
    {
        return 'R'.number_format($this->price_cents / 100, 2);
    }
}
