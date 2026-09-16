<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'is_admin',
    'account_deleted_at',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->is_admin === true;
    }

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if (
                (bool) $user->getRawOriginal('is_admin')
                && ! $user->is_admin
                && self::query()->where('is_admin', true)->count() <= 1
            ) {
                throw new \RuntimeException('Ultimul administrator nu poate deveni client.');
            }
        });

        static::deleting(function (User $user): void {
            if (self::query()->whereKey($user->id)->where('is_admin', true)->exists()) {
                throw new \RuntimeException('Conturile de administrator nu pot fi șterse.');
            }
            if ($user->orders()->exists() || $user->reviews()->exists()) {
                throw new \RuntimeException('Contul are evidențe contractuale; accesul trebuie eliminat prin anonimizare.');
            }
        });
    }

    /**
     * Produsele favorite.
     */
    public function wishlist(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'wishlists'
        )->withTimestamps();
    }

    /**
     * Comenzile utilizatorului.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Recenziile utilizatorului.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Conversia automată a atributelor.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'account_deleted_at' => 'datetime',
        ];
    }
}
