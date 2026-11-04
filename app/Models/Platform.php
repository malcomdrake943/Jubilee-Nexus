<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = [
        'name',
        'logo',
        'url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        if (blank($this->logo)) {
            return null;
        }

        if (filter_var($this->logo, FILTER_VALIDATE_URL) !== false || str_starts_with($this->logo, 'data:')) {
            return $this->logo;
        }

        $cleanPath = ltrim(str_replace('\\', '/', $this->logo), '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, strlen('storage/'));
        }

        $baseUrl = app()->runningInConsole() ? '' : rtrim(request()->getBaseUrl(), '/');

        return $baseUrl . '/media?file=/' . $cleanPath;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
