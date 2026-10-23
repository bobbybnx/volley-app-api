<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'display_name',
        'email',
        'password',
        'bio',
        'is_private',
        'theme',
        'profile_image_path',
        'banner_image_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['initials', 'profile_image_url', 'banner_image_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_private' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * URL pública de la foto de perfil.
     */
    protected function profileImageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->profile_image_path
            ? Storage::disk('public')->url($this->profile_image_path)
            : null);
    }

    /**
     * URL pública del banner del perfil.
     */
    protected function bannerImageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->banner_image_path
            ? Storage::disk('public')->url($this->banner_image_path)
            : null);
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class);
    }

    public function workouts()
    {
        return $this->hasMany(Workout::class);
    }

    public function publicWorkouts()
    {
        return $this->workouts()->where('is_public', true);
    }

    public function getInitialsAttribute(): string
    {
        $source = $this->display_name ?: $this->name ?: $this->username ?: '?';

        return Str::of($source)
            ->squish()
            ->explode(' ')
            ->take(2)
            ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    public function isPublicProfile(): bool
    {
        return ! $this->is_private;
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public static function generateUsername(string $email, ?string $fallback = null): string
    {
        $base = Str::slug($fallback ?: Str::before($email, '@'), '.');
        $base = $base !== '' ? substr($base, 0, 40) : 'entrenador';
        $candidate = $base;
        $suffix = 1;

        while (static::where('username', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
