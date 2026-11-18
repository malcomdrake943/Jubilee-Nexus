<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StoreProduct extends Model
{
    protected $fillable = [
        'cj_pid',
        'cj_vid',
        'sku',
        'name',
        'slug',
        'category_name',
        'cj_category_id',
        'description',
        'image_url',
        'gallery_images',
        'variants',
        'cj_cost_price',
        'retail_price',
        'custom_shipping_price',
        'tax_percent',
        'custom_tax_amount',
        'weight_grams',
        'source_country_code',
        'stock',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'gallery_images'        => 'array',
        'variants'              => 'array',
        'cj_cost_price'         => 'decimal:2',
        'retail_price'          => 'decimal:2',
        'custom_shipping_price' => 'decimal:2',
        'tax_percent'           => 'decimal:2',
        'custom_tax_amount'     => 'decimal:2',
        'weight_grams'          => 'integer',
        'stock'                 => 'integer',
        'is_featured'           => 'boolean',
        'is_active'             => 'boolean',
    ];

    protected $appends = [
        'display_image_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $product) {
            if (blank($product->slug)) {
                $baseSlug = Str::slug($product->name) ?: 'product';
                $slug = $baseSlug;
                $counter = 2;

                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$counter}";
                    $counter++;
                }

                $product->slug = $slug;
            }
        });

        static::saving(function (self $product) {
            if ($product->isDirty('retail_price') && is_array($product->variants) && count($product->variants) > 0) {
                $originalVariants = $product->getOriginal('variants');
                $originalPrices = is_array($originalVariants)
                    ? array_map(fn ($v) => is_array($v) && isset($v['retail_price']) ? round((float) $v['retail_price'], 2) : null, array_values($originalVariants))
                    : [];
                $currentPrices = array_map(
                    fn ($v) => is_array($v) && isset($v['retail_price']) ? round((float) $v['retail_price'], 2) : null,
                    array_values($product->variants)
                );

                if ($originalPrices === $currentPrices) {
                    $oldRetail = round((float) $product->getOriginal('retail_price'), 2);
                    $newRetail = round((float) $product->retail_price, 2);
                    $singleVariant = count($product->variants) === 1;

                    $product->variants = collect($product->variants)
                        ->map(function ($variant) use ($oldRetail, $newRetail, $singleVariant) {
                            if (! is_array($variant)) {
                                return $variant;
                            }

                            $variantPrice = isset($variant['retail_price']) ? round((float) $variant['retail_price'], 2) : null;
                            if ($singleVariant || $variantPrice === null || abs($variantPrice - $oldRetail) < 0.01) {
                                $variant['retail_price'] = $newRetail;
                            }

                            return $variant;
                        })
                        ->all();
                }
            }
        });
    }

    public function getDisplayImageUrlAttribute(): ?string
    {
        if (blank($this->image_url)) {
            return null;
        }

        if (filter_var($this->image_url, FILTER_VALIDATE_URL) !== false || str_starts_with($this->image_url, 'data:')) {
            return $this->image_url;
        }

        $cleanPath = ltrim(str_replace('\\', '/', $this->image_url), '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, strlen('storage/'));
        }

        $baseUrl = app()->runningInConsole() ? '' : rtrim(request()->getBaseUrl(), '/');

        return $baseUrl . '/media?file=/' . $cleanPath;
    }

    public static function calculateRetailPrice(float $cost, ?float $markupPercent = null): float
    {
        $markup = $markupPercent ?? (float) (Setting::get('cj_markup_percent') ?? config('services.cjdropshipping.markup_percent', 25));

        return round(max(0.01, $cost * (1 + ($markup / 100))), 2);
    }

    public function calculateTaxAmount(float $subtotal, int $quantity = 1): float
    {
        if ($this->custom_tax_amount !== null) {
            return round(max(0, (float) $this->custom_tax_amount * max(1, $quantity)), 2);
        }

        if ($this->tax_percent !== null) {
            return round(max(0, $subtotal * ((float) $this->tax_percent / 100)), 2);
        }

        $globalPercent = (float) (Setting::get('store_tax_percent', '0') ?? 0);
        $globalFlat = (float) (Setting::get('store_tax_flat', '0') ?? 0);

        return round(max(0, ($subtotal * ($globalPercent / 100)) + $globalFlat), 2);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
