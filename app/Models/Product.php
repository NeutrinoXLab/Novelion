<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    ];

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