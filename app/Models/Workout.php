<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workout extends Model
{
    use HasFactory, SoftDeletes;

    public const SECTIONS = ['warmup', 'physical', 'main'];

    public const SECTION_LABELS = [
        'warmup' => 'Calentamiento',
        'physical' => 'Bloque físico',
        'main' => 'Entreno principal',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'date',
        'status',
        'notes',
        'rating',
        'feedback_notes',
        'is_public',
    ];

    protected $casts = [
        'date' => 'date',
        'rating' => 'integer',
        'is_public' => 'boolean',
    ];

    protected $appends = ['sections_summary'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exercises()
    {
        return $this->belongsToMany(Exercise::class, 'exercise_workout')
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
            ->withTimestamps()
            ->orderByPivot('order');
    }

    public function warmupExercises()
    {
        return $this->exercises()->wherePivot('section', 'warmup');
    }

    public function physicalExercises()
    {
        return $this->exercises()->wherePivot('section', 'physical');
    }

    public function mainExercises()
    {
        return $this->exercises()->wherePivot('section', 'main');
    }

    public function exercisesInSection(string $section)
    {
        return $this->exercises->filter(
            fn (Exercise $exercise) => ($exercise->pivot->section ?? 'main') === $section
        )->values();
    }

    public function getSectionsSummaryAttribute(): array
    {
        // Si la relación no viene precargada se resuelve aquí para no devolver
        // un resumen engañoso (todo a cero) en los listados.
        $exercises = $this->relationLoaded('exercises')
            ? $this->exercises
            : $this->exercises()->get(['exercises.id', 'exercises.type']);

        return collect(self::SECTIONS)
            ->mapWithKeys(fn ($section) => [
                $section => $exercises->filter(
                    fn (Exercise $exercise) => ($exercise->pivot->section ?? 'main') === $section
                )->count(),
            ])
            ->all();
    }

    public function scopeVisibleTo($query, User $viewer)
    {
        return $query->where(function ($q) use ($viewer) {
            $q->where('user_id', $viewer->id)
                ->orWhere(function ($public) {
                    $public->where('is_public', true)
                        ->whereHas('user', fn ($user) => $user->where('is_private', false));
                });
        });
    }

    public function isVisibleTo(User $viewer): bool
    {
        if ($this->user_id === $viewer->id) {
            return true;
        }

        return $this->is_public && ! ($this->user?->is_private ?? true);
    }
}
