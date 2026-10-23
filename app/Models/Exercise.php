<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Exercise extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_WARMUP = 'warmup';
    public const TYPE_PHYSICAL = 'physical';
    public const TYPE_TECHNICAL = 'technical_tactical';

    public const TYPES = [
        self::TYPE_WARMUP,
        self::TYPE_PHYSICAL,
        self::TYPE_TECHNICAL,
    ];

    /** Bloque del entrenamiento al que pertenece cada tipo de ejercicio. */
    public const SECTION_BY_TYPE = [
        self::TYPE_WARMUP => 'warmup',
        self::TYPE_PHYSICAL => 'physical',
        self::TYPE_TECHNICAL => 'main',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'description',
        'image_path',
        'category_id',
        'measurement_mode',
        'default_reps',
        'default_time_seconds',
        'default_series',
        'methodology_id',
        'is_validated',
    ];

    protected $casts = [
        'default_reps' => 'integer',
        'default_time_seconds' => 'integer',
        'default_series' => 'integer',
        'is_validated' => 'boolean',
    ];

    /**
     * URL pública de la imagen. Se calcula siempre con Storage::url() para
     * que el frontend no tenga que construir rutas a mano.
     */
    protected $appends = ['image_url', 'section'];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->image_path) {
                return null;
            }

            return Storage::disk('public')->url($this->image_path);
        });
    }

    protected function section(): Attribute
    {
        return Attribute::get(fn () => self::SECTION_BY_TYPE[$this->type] ?? 'main');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function methodology()
    {
        return $this->belongsTo(Methodology::class);
    }

    public function bodyAreas()
    {
        return $this->belongsToMany(BodyArea::class, 'body_area_exercise');
    }

    public function fundamentals()
    {
        return $this->belongsToMany(Fundamental::class, 'exercise_fundamental');
    }

    public function workouts()
    {
        return $this->belongsToMany(Workout::class, 'exercise_workout')
            ->withPivot([
                'section',
                'order',
                'series',
                'reps',
                'time_seconds',
                'rest_seconds',
                'notes',
                'rating',
                'feedback_notes',
            ])
            ->withTimestamps();
    }

    public function scopeOfType($query, ?string $type)
    {
        return $type ? $query->where('type', $type) : $query;
    }

    public function scopeValidated($query)
    {
        return $query->where('is_validated', true);
    }

    /**
     * Catálogo visible: los ejercicios validados son globales y cada entrenador
     * ve además los suyos pendientes de validación.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        return $query->where(function ($query) use ($user) {
            $query->where('is_validated', true);

            if ($user) {
                $query->orWhere('user_id', $user->id);
            }
        });
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->is_validated) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $this->user_id === $user->id || $user->is_admin;
    }

    /**
     * Un ejercicio pendiente solo puede usarse en entrenamientos de su autor.
     * Los administradores tampoco pueden usar pendientes ajenos.
     */
    public function isUsableBy(?User $user): bool
    {
        return $this->is_validated || ($user !== null && $this->user_id === $user->id);
    }

    public function canBeDeletedBy(?User $user): bool
    {
        return $user !== null && ($user->is_admin || $this->user_id === $user->id);
    }

    public function getIsWarmupAttribute(): bool
    {
        return $this->type === self::TYPE_WARMUP;
    }
}
