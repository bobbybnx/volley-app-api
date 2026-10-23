<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExerciseController extends Controller
{
    private const IMAGE_RULES = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120';

    public function index(Request $request): JsonResponse
    {
        // Los ejercicios validados son globales. Cada entrenador ve además sus
        // propios ejercicios pendientes de validación.
        $query = Exercise::with([
            'category',
            'methodology',
            'bodyAreas',
            'fundamentals',
            'user:id,name,username,display_name,profile_image_path',
        ])->visibleTo($request->user());

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('section')) {
            $types = match ($request->string('section')->toString()) {
                'warmup' => [Exercise::TYPE_WARMUP],
                'physical' => [Exercise::TYPE_PHYSICAL],
                'main' => [Exercise::TYPE_TECHNICAL],
                default => Exercise::TYPES,
            };
            $query->whereIn('type', $types);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('methodology_id')) {
            $query->where('methodology_id', $request->integer('methodology_id'));
        }

        if ($request->filled('body_area')) {
            $query->whereHas('bodyAreas', fn ($q) => $q->where('body_areas.id', $request->integer('body_area')));
        }

        if ($request->filled('fundamental')) {
            $query->whereHas('fundamentals', fn ($q) => $q->where('fundamentals.id', $request->integer('fundamental')));
        }

        if ($request->boolean('mine')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $exercises = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(function (Exercise $exercise) use ($request) {
                $exercise->setAttribute('can_edit', $exercise->user_id === $request->user()->id);
                $exercise->setAttribute('can_delete', $exercise->canBeDeletedBy($request->user()));

                return $exercise;
            });

        return response()->json($exercises);
    }

    public function store(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $validated = $request->validate($this->rulesFor($type, false));

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('exercises', 'public')
            : null;

        $exercise = Exercise::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'type' => $type,
            'description' => $validated['description'],
            'image_path' => $imagePath,
            'category_id' => $type === Exercise::TYPE_TECHNICAL ? null : ($validated['category_id'] ?? null),
            'measurement_mode' => $type === Exercise::TYPE_TECHNICAL ? null : ($validated['measurement_mode'] ?? null),
            'default_reps' => $type === Exercise::TYPE_TECHNICAL ? null : ($validated['default_reps'] ?? null),
            'default_time_seconds' => $type === Exercise::TYPE_TECHNICAL ? null : ($validated['default_time_seconds'] ?? null),
            'default_series' => $type === Exercise::TYPE_TECHNICAL ? null : ($validated['default_series'] ?? null),
            'methodology_id' => $type === Exercise::TYPE_TECHNICAL ? ($validated['methodology_id'] ?? null) : null,
            // Los administradores publican directamente; el resto queda pendiente.
            'is_validated' => $request->user()->is_admin,
        ]);

        if (! empty($validated['body_areas']) && $type !== Exercise::TYPE_TECHNICAL) {
            $exercise->bodyAreas()->sync($validated['body_areas']);
        }

        if (! empty($validated['fundamentals']) && $type === Exercise::TYPE_TECHNICAL) {
            $exercise->fundamentals()->sync($validated['fundamentals']);
        }

        return response()->json($this->loadExercise($request, $exercise), 201);
    }

    public function show(Request $request, Exercise $exercise): JsonResponse
    {
        if (! $exercise->isVisibleTo($request->user())) {
            return response()->json([
                'message' => 'Este ejercicio todavía no ha sido validado por un administrador.',
            ], 403);
        }

        return response()->json($this->loadExercise($request, $exercise));
    }

    public function update(Request $request, Exercise $exercise): JsonResponse
    {
        if ($exercise->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Solo el entrenador que creó el ejercicio puede editarlo.'], 403);
        }

        $type = $request->input('type', $exercise->type);
        $validated = $request->validate($this->rulesFor($type, ! $request->has('type')));

        $attributes = Arr::only($validated, [
            'title',
            'description',
            'category_id',
            'measurement_mode',
            'default_reps',
            'default_time_seconds',
            'default_series',
            'methodology_id',
        ]);
        $attributes['type'] = $type;

        if ($request->hasFile('image')) {
            if ($exercise->image_path) {
                Storage::disk('public')->delete($exercise->image_path);
            }
            $attributes['image_path'] = $request->file('image')->store('exercises', 'public');
        } elseif ($request->boolean('remove_image') && $exercise->image_path) {
            Storage::disk('public')->delete($exercise->image_path);
            $attributes['image_path'] = null;
        }

        $exercise->update($attributes);

        $section = Exercise::SECTION_BY_TYPE[$exercise->type] ?? 'main';

        if ($section === 'main') {
            // Un ejercicio técnico-táctico no guarda datos de carga física.
            $exercise->update([
                'category_id' => null,
                'measurement_mode' => null,
                'default_reps' => null,
                'default_time_seconds' => null,
                'default_series' => null,
            ]);
            $exercise->bodyAreas()->sync([]);
        } else {
            $exercise->update(['methodology_id' => null]);
            $exercise->fundamentals()->sync([]);
        }

        if ($request->has('body_areas') && $section !== 'main') {
            $exercise->bodyAreas()->sync($validated['body_areas'] ?? []);
        }

        if ($request->has('fundamentals') && $section === 'main') {
            $exercise->fundamentals()->sync($validated['fundamentals'] ?? []);
        }

        return response()->json($this->loadExercise($request, $exercise));
    }

    public function destroy(Request $request, Exercise $exercise): JsonResponse
    {
        if (! $exercise->canBeDeletedBy($request->user())) {
            return response()->json(['message' => 'Solo el creador o un administrador puede eliminar este ejercicio.'], 403);
        }

        if ($exercise->image_path) {
            Storage::disk('public')->delete($exercise->image_path);
        }

        $exercise->delete();

        return response()->json(['message' => 'Ejercicio eliminado correctamente']);
    }

    public function uploadImage(Request $request, Exercise $exercise): JsonResponse
    {
        if ($exercise->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Solo el entrenador que creó el ejercicio puede cambiar la imagen.'], 403);
        }

        $request->validate(['image' => self::IMAGE_RULES]);

        if ($exercise->image_path) {
            Storage::disk('public')->delete($exercise->image_path);
        }

        $exercise->update(['image_path' => $request->file('image')->store('exercises', 'public')]);
        $exercise->refresh();

        return response()->json([
            'message' => 'Imagen subida correctamente',
            'image_path' => $exercise->image_path,
            'image_url' => $exercise->image_url,
            'exercise' => $exercise,
        ]);
    }

    private function loadExercise(Request $request, Exercise $exercise): Exercise
    {
        $exercise->load([
            'category',
            'methodology',
            'bodyAreas',
            'fundamentals',
            'user:id,name,username,display_name,profile_image_path',
        ]);

        $exercise->setAttribute('can_edit', $exercise->user_id === $request->user()->id);
        $exercise->setAttribute('can_delete', $exercise->canBeDeletedBy($request->user()));

        return $exercise;
    }

    /**
     * Reglas de validación según el tipo de ejercicio.
     */
    private function rulesFor(?string $type, bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        $rules = [
            'title' => [...$required, 'string', 'max:255'],
            'type' => [...$required, 'in:warmup,physical,technical_tactical'],
            'description' => [...$required, 'string'],
            'image' => self::IMAGE_RULES,
            'remove_image' => 'sometimes|boolean',
            'category_id' => 'nullable|exists:categories,id',
            'measurement_mode' => 'nullable|in:time,repetitions',
            'default_reps' => 'nullable|integer|min:1',
            'default_time_seconds' => 'nullable|integer|min:1',
            'default_series' => 'nullable|integer|min:1',
            'methodology_id' => 'nullable|exists:methodologies,id',
            'body_areas' => 'nullable|array',
            'body_areas.*' => 'exists:body_areas,id',
            'fundamentals' => 'nullable|array',
            'fundamentals.*' => 'exists:fundamentals,id',
        ];

        if ($type === Exercise::TYPE_PHYSICAL) {
            $rules['category_id'] = [($partial ? 'sometimes|' : '') . 'required', 'exists:categories,id'];
            $rules['measurement_mode'] = [($partial ? 'sometimes|' : '') . 'required', 'in:time,repetitions'];
            $rules['default_series'] = [($partial ? 'sometimes|' : '') . 'required', 'integer', 'min:1'];
            $rules['default_reps'] = 'nullable|required_if:measurement_mode,repetitions|integer|min:1';
            $rules['default_time_seconds'] = 'nullable|required_if:measurement_mode,time|integer|min:1';
            $rules['body_areas'] = ($partial ? 'sometimes|' : '') . 'required|array|min:1';
        }

        if ($type === Exercise::TYPE_TECHNICAL) {
            $rules['methodology_id'] = [($partial ? 'sometimes|' : '') . 'required', 'exists:methodologies,id'];
            $rules['fundamentals'] = ($partial ? 'sometimes|' : '') . 'required|array|min:1';
        }

        if ($type === null) {
            throw ValidationException::withMessages(['type' => ['El tipo de ejercicio es obligatorio.']]);
        }

        return $rules;
    }
}
