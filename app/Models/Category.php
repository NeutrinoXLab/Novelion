<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

    protected static function booted(): void
    {
        static::deleting(function (Category $category): void {
            if ($category->products()->exists()) {
                throw new \DomainException('Categoria nu poate fi ștearsă cât timp are produse asociate. Mută, realocă sau elimină mai întâi produsele.');
            }

            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }
        });
    }

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
