<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutController extends Controller
{
    private const PIVOT_NUMERIC_RULES = [
        'series' => 'nullable|integer|min:1',
        'reps' => 'nullable|integer|min:1',
        'time_seconds' => 'nullable|integer|min:1',
        'rest_seconds' => 'nullable|integer|min:0',
        'notes' => 'nullable|string',
        'rating' => 'nullable|integer|min:1|max:5',
        'feedback_notes' => 'nullable|string',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Workout::where('user_id', $request->user()->id)
            ->with([
                'user:id,name,username,display_name,profile_image_path',
                'exercises:id,title,type,status',
            ])
            ->withCount('exercises');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $workouts = $query->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($workouts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
            'status' => 'nullable|in:planned,completed',
            'notes' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'feedback_notes' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
            'exercises' => 'present|array|min:1',
            'exercises.*.exercise_id' => 'required|exists:exercises,id',
            'exercises.*.section' => 'required|in:warmup,physical,main',
            'exercises.*.order' => 'nullable|integer|min:0',
        ], self::PIVOT_NUMERIC_RULES));

        $this->assertSectionsAreValid($validated['exercises'], $request->user());

        $workout = Workout::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'date' => $validated['date'] ?? null,
            'status' => $validated['status'] ?? 'planned',
            'notes' => $validated['notes'] ?? null,
            'rating' => $validated['rating'] ?? null,
            'feedback_notes' => $validated['feedback_notes'] ?? null,
            'is_public' => $validated['is_public'] ?? true,
        ]);

        $this->attachExercises($workout, $validated['exercises']);

        return response()->json($this->loadWorkout($request, $workout), 201);
    }

    public function show(Request $request, Workout $workout): JsonResponse
    {
        $workout->loadMissing('user');

        if (! $workout->isVisibleTo($request->user())) {
            return response()->json([
                'message' => 'Este entrenamiento es privado o pertenece a un perfil privado.',
            ], 403);
        }

        return response()->json($this->loadWorkout($request, $workout));
    }

    public function update(Request $request, Workout $workout): JsonResponse
    {
        if (! $this->isOwner($request, $workout)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
            'status' => 'nullable|in:planned,completed',
            'notes' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'feedback_notes' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
        ]);

        $workout->update($validated);

        if ($request->has('exercises')) {
            $exerciseData = $request->validate(array_merge([
                'exercises' => 'present|array|min:1',
                'exercises.*.exercise_id' => 'required|exists:exercises,id',
                'exercises.*.section' => 'required|in:warmup,physical,main',
                'exercises.*.order' => 'nullable|integer|min:0',
            ], self::PIVOT_NUMERIC_RULES));

            $this->assertSectionsAreValid($exerciseData['exercises'], $request->user());
            $this->replaceExercises($workout, $exerciseData['exercises']);
        }

        return response()->json($this->loadWorkout($request, $workout));
    }

    public function destroy(Request $request, Workout $workout): JsonResponse
    {
        if (! $this->isOwner($request, $workout)) {
            return $this->forbidden();
        }

        $workout->delete();

        return response()->json(['message' => 'Entrenamiento eliminado correctamente']);
    }

    /**
     * Reemplaza los tres bloques del entrenamiento de una sola vez.
     */
    public function syncExercises(Request $request, Workout $workout): JsonResponse
    {
        if (! $this->isOwner($request, $workout)) {
            return $this->forbidden();
        }

        $validated = $request->validate(array_merge([
            'exercises' => 'present|array|min:1',
            'exercises.*.exercise_id' => 'required|exists:exercises,id',
            'exercises.*.section' => 'required|in:warmup,physical,main',
            'exercises.*.order' => 'nullable|integer|min:0',
        ], self::PIVOT_NUMERIC_RULES));

        $this->assertSectionsAreValid($validated['exercises'], $request->user());
        $this->replaceExercises($workout, $validated['exercises']);

        return response()->json($this->loadWorkout($request, $workout));
    }

    public function addExercise(Request $request, Workout $workout): JsonResponse
    {
        if (! $this->isOwner($request, $workout)) {
            return $this->forbidden();
        }

        $validated = $request->validate(array_merge([
            'exercise_id' => 'required|exists:exercises,id',
            'section' => 'required|in:warmup,physical,main',
            'order' => 'nullable|integer|min:0',
        ], self::PIVOT_NUMERIC_RULES));

        $exercise = Exercise::findOrFail($validated['exercise_id']);

        if (! $exercise->isUsableBy($request->user())) {
            return response()->json([
                'message' => 'Este ejercicio todavía no está validado y solo puede usarlo su creador.',
            ], 403);
        }

        $this->assertExerciseFitsSection($exercise, $validated['section'], 0);

        $order = $validated['order'] ?? null;

        if ($order === null) {
            $max = $workout->exercises()
                ->wherePivot('section', $validated['section'])
                ->max('exercise_workout.order');
            $order = $max === null ? 0 : (int) $max + 1;
        }

        $workout->exercises()->attach($exercise->id, [
            'section' => $validated['section'],
            'order' => $order,
            'series' => $validated['series'] ?? null,
            'reps' => $validated['reps'] ?? null,
            'time_seconds' => $validated['time_seconds'] ?? null,
            'rest_seconds' => $validated['rest_seconds'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'rating' => $validated['rating'] ?? null,
            'feedback_notes' => $validated['feedback_notes'] ?? null,
        ]);

        return response()->json($this->loadWorkout($request, $workout));
    }

    /**
     * Cargas (series/reps/tiempo/descanso/notas) del ejercicio en la sesión.
     */
    public function updateExercise(Request $request, Workout $workout, int $exerciseId): JsonResponse
    {
        if (! $this->isOwner($request, $workout)) {
            return $this->forbidden();
        }

        $validated = $request->validate(array_merge(
            ['section' => 'sometimes|required|in:warmup,physical,main'],
            self::PIVOT_NUMERIC_RULES
        ));

        if (isset($validated['section'])) {
            $exercise = Exercise::findOrFail($exerciseId);
            $this->assertExerciseFitsSection($exercise, $validated['section'], 0);
        }

        $payload = collect($validated)
            ->filter(fn ($value, $key) => $request->has($key))
            ->all();

        $workout->exercises()->updateExistingPivot($exerciseId, $payload);

        return response()->json($this->loadWorkout($request, $workout));
    }

    /**
     * Feedback concreto de un ejercicio dentro de un entrenamiento.
     */
    public function updateExerciseFeedback(Request $request, Workout $workout, int $exerciseId): JsonResponse
    {
        if (! $this->isOwner($request, $workout)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'feedback_notes' => 'nullable|string|max:2000',
        ]);

        $exists = $workout->exercises()->where('exercises.id', $exerciseId)->exists();
        if (! $exists) {
            return response()->json(['message' => 'Ese ejercicio no forma parte del entrenamiento.'], 404);
        }

        $payload = collect($validated)
            ->filter(fn ($value, $key) => $request->has($key))
            ->all();

        $workout->exercises()->updateExistingPivot($exerciseId, $payload);

        return response()->json($this->loadWorkout($request, $workout));
    }

    /**
     * Feedback global del entrenamiento.
     */
    public function updateFeedback(Request $request, Workout $workout): JsonResponse
    {
        if (! $this->isOwner($request, $workout)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'feedback_notes' => 'nullable|string|max:2000',
        ]);

        $workout->update($validated);

        return response()->json($this->loadWorkout($request, $workout));
    }

    public function updateExerciseOrder(Request $request, Workout $workout, int $exerciseId): JsonResponse
    {
        if (! $this->isOwner($request, $workout)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'order' => 'required|integer|min:0',
            'section' => 'sometimes|required|in:warmup,physical,main',
        ]);

        $workout->exercises()->updateExistingPivot(
            $exerciseId,
            array_filter($validated, fn ($value) => $value !== null)
        );

        return response()->json($this->loadWorkout($request, $workout));
    }

    public function removeExercise(Request $request, Workout $workout, int $exerciseId): JsonResponse
    {
        if (! $this->isOwner($request, $workout)) {
            return $this->forbidden();
        }

        $workout->exercises()->detach($exerciseId);

        return response()->json($this->loadWorkout($request, $workout));
    }

    private function attachExercises(Workout $workout, array $exercises): void
    {
        foreach ($this->groupedBySection($exercises) as $section => $rows) {
            foreach ($rows as $index => $row) {
                $workout->exercises()->attach($row['exercise_id'], [
                    'section' => $section,
                    'order' => $index,
                    'series' => $row['series'] ?? null,
                    'reps' => $row['reps'] ?? null,
                    'time_seconds' => $row['time_seconds'] ?? null,
                    'rest_seconds' => $row['rest_seconds'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'rating' => $row['rating'] ?? null,
                    'feedback_notes' => $row['feedback_notes'] ?? null,
                ]);
            }
        }
    }

    private function replaceExercises(Workout $workout, array $exercises): void
    {
        DB::transaction(function () use ($workout, $exercises) {
            $workout->exercises()->detach();
            $this->attachExercises($workout, $exercises);
        });
    }

    private function groupedBySection(array $exercises): array
    {
        $grouped = ['warmup' => [], 'physical' => [], 'main' => []];

        foreach ($exercises as $row) {
            $grouped[$row['section']][] = $row;
        }

        return $grouped;
    }

    /**
     * Valida que existan los 3 bloques, que cada ejercicio esté en el suyo y
     * que el entrenador pueda usar cada ejercicio (validado o propio).
     */
    private function assertSectionsAreValid(array $exercises, ?User $user): void
    {
        $grouped = $this->groupedBySection($exercises);

        $labels = [
            'warmup' => 'calentamiento',
            'physical' => 'físico',
            'main' => 'entreno principal',
        ];

        foreach ($labels as $section => $label) {
            if (count($grouped[$section]) === 0) {
                throw ValidationException::withMessages([
                    'exercises' => ["El bloque de {$label} debe tener al menos un ejercicio."],
                ]);
            }
        }

        $types = Exercise::whereIn('id', collect($exercises)->pluck('exercise_id')->unique())
            ->get(['id', 'title', 'type', 'status', 'user_id'])
            ->keyBy('id');

        foreach ($exercises as $index => $row) {
            $exercise = $types->get($row['exercise_id']);

            if (! $exercise) {
                continue; // la regla "exists" ya devuelve el error correspondiente
            }

            if (! $exercise->isUsableBy($user)) {
                throw ValidationException::withMessages([
                    "exercises.{$index}.exercise_id" => [
                        "\"{$exercise->title}\" todavía no está validado y solo puede usarlo su creador.",
                    ],
                ]);
            }

            $this->assertExerciseFitsSection($exercise, $row['section'], $index);
        }
    }

    private function assertExerciseFitsSection(Exercise $exercise, string $section, int $index): void
    {
        $expected = Exercise::SECTION_BY_TYPE[$exercise->type] ?? 'main';

        if ($expected !== $section) {
            $sectionLabel = Workout::SECTION_LABELS[$section] ?? $section;
            $expectedLabel = Workout::SECTION_LABELS[$expected] ?? $expected;

            throw ValidationException::withMessages([
                "exercises.{$index}.section" => [
                    "\"{$exercise->title}\" es del bloque {$expectedLabel} y no puede ir en {$sectionLabel}.",
                ],
            ]);
        }
    }

    private function loadWorkout(Request $request, Workout $workout): Workout
    {
        $isOwner = $this->isOwner($request, $workout);

        $workout->load([
            'user:id,name,username,display_name,is_private,profile_image_path',
            'exercises' => function ($query) use ($isOwner) {
                // Los ejercicios pendientes nunca se muestran a terceros,
                // aunque formen parte de un entrenamiento publicado.
                if (! $isOwner) {
                    $query->where('exercises.status', \App\Models\Exercise::STATUS_APPROVED);
                }

                $query->with(['category', 'methodology', 'bodyAreas', 'fundamentals']);
            },
        ]);

        $workout->setAttribute('is_owner', $isOwner);
        $workout->setAttribute('can_edit', $isOwner);

        return $workout;
    }

    private function isOwner(Request $request, Workout $workout): bool
    {
        return $workout->user_id === $request->user()->id;
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'No autorizado'], 403);
    }
}
