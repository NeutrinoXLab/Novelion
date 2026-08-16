<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image_path',
        'description',
        'is_active',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Folosim slug-ul în URL-uri.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Produsele din această categorie.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}