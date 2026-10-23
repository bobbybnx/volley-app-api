<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminExerciseController extends Controller
{
    /**
     * Contadores para el panel de administración y el badge de navegación.
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'pending' => Exercise::where('is_validated', false)->count(),
            'validated' => Exercise::where('is_validated', true)->count(),
            'total' => Exercise::count(),
        ]);
    }

    /**
     * Revisión de ejercicios de la comunidad.
     *
     * `status`: pending (por defecto) | validated | all
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'pending')->toString();
        $query = Exercise::query()->with([
            'category',
            'methodology',
            'bodyAreas',
            'fundamentals',
            'user:id,name,username,display_name,profile_image_path',
        ]);

        if ($status === 'pending') {
            $query->where('is_validated', false);
        } elseif ($status === 'validated') {
            $query->where('is_validated', true);
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min(max($request->integer('per_page', 30), 1), 100);

        $exercises = $query->orderBy('is_validated')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($exercises);
    }

    /**
     * Aprueba o retira la validación de un ejercicio.
     */
    public function updateValidation(Request $request, Exercise $exercise): JsonResponse
    {
        $validated = $request->validate([
            'is_validated' => 'required|boolean',
        ]);

        $exercise->update(['is_validated' => $validated['is_validated']]);

        return response()->json([
            'message' => $exercise->is_validated
                ? 'Ejercicio validado y publicado en el catálogo global.'
                : 'Validación retirada: el ejercicio solo será visible para su autor.',
            'exercise' => $exercise->load([
                'category',
                'methodology',
                'bodyAreas',
                'fundamentals',
                'user:id,name,username,display_name,profile_image_path',
            ]),
        ]);
    }
}
