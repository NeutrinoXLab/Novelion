<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'ean',
        'supplier_reference',
        'purchase_price',
        'selling_price',
        'sale_price',
        'vat_rate',
        'stock_quantity',
        'low_stock_threshold',

        // Transport și dimensiuni
        'weight',
        'length',
        'width',
        'height',

        'short_description',
        'description',
        'is_active',
        'is_featured',
        'is_new',
        'is_on_sale',
        'manufacturer_name',
        'manufacturer_contact',
        'model_identifier',
        'eu_responsible_person_name',
        'eu_responsible_person_contact',
        'warnings',
        'safety_instructions',
        'commercial_warranty',
        'requires_eu_responsible_person',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'sale_price' => 'decimal:2',

        // Transport și dimensiuni
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',

        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'is_on_sale' => 'boolean',
        'requires_eu_responsible_person' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (Product $product): void {
            $product->priceHistory()->create(['price' => $product->sale_price ?: $product->selling_price, 'effective_at' => $product->created_at ?? now()]);
        });

        static::updated(function (Product $product): void {
            if ($product->wasChanged(['selling_price', 'sale_price'])) {
                $previousPrice = $product->getOriginal('sale_price') ?: $product->getOriginal('selling_price');
                $currentPrice = $product->sale_price ?: $product->selling_price;
                if ((float) $previousPrice !== (float) $currentPrice) {
                    $product->priceHistory()->create(['price' => $currentPrice, 'effective_at' => now()]);
                }
            }
        });
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    public function referencePrice(): ?float
    {
        if (! $this->sale_price) {
            return null;
        }
        $latest = $this->priceHistory()->latest('id')->first();
        if (! $latest || (float) $latest->price !== (float) $this->sale_price) {
            return null;
        }
        $start = $latest->effective_at->copy()->subDays(30);
        $prices = $this->priceHistory()->where('id', '<', $latest->id)->where('effective_at', '>=', $start)->pluck('price');
        $previous = $this->priceHistory()->where('id', '<', $latest->id)->where('effective_at', '<', $start)->latest('effective_at')->first();
        if ($previous) {
            $prices->push($previous->price);
        }

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    /**
     * Categoria produsului.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Brandul produsului.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Toate imaginile produsului.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order');
    }

    /**
     * Imaginea principală.
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_primary', true);
    }

    /**
     * Calea imaginii principale.
     */
    public function getPrimaryImagePathAttribute(): ?string
    {
        return $this->primaryImage?->image_path;
    }

    /**
     * Utilizatorii care au produsul la favorite.
     */
    public function wishlistedBy(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'wishlists'
        )->withTimestamps();
    }

    /**
     * Recenziile produsului.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)
            ->where('is_approved', true)
            ->latest();
    }

    /**
     * Ratingul mediu.
     */
    public function getAverageRatingAttribute(): float
    {
        return round($this->reviews()->avg('rating') ?? 0, 1);
    }

    /**
     * Numărul recenziilor.
     */
    public function getReviewsCountAttribute(): int
    {
        return $this->reviews()->count();
    }

    /**
     * Volumul produsului în centimetri cubi.
     */
    public function getVolumeAttribute(): float
    {
        if (
            ! $this->length ||
            ! $this->width ||
            ! $this->height
        ) {
            return 0;
        }

        return round(
            $this->length *
            $this->width *
            $this->height,
            2
        );
    }
}
