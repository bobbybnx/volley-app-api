<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminExerciseController extends Controller
{
    /**
     * Contadores para el panel de administración y el badge de navegación.
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'pending' => Exercise::where('status', Exercise::STATUS_PENDING)->count(),
            'validated' => Exercise::where('status', Exercise::STATUS_APPROVED)->count(),
            'approved' => Exercise::where('status', Exercise::STATUS_APPROVED)->count(),
            'rejected' => Exercise::where('status', Exercise::STATUS_REJECTED)->count(),
            'total' => Exercise::count(),
        ]);
    }

    /**
     * Revisión de ejercicios de la comunidad.
     *
     * `status`: pending (por defecto) | approved/validated | rejected | all
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'pending')->toString();
        if ($status === 'validated') {
            $status = 'approved';
        }

        $query = Exercise::query()->with([
            'category',
            'methodology',
            'bodyAreas',
            'fundamentals',
            'user:id,name,username,display_name,profile_image_path',
        ]);

        if (in_array($status, [Exercise::STATUS_PENDING, Exercise::STATUS_APPROVED, Exercise::STATUS_REJECTED], true)) {
            $query->where('status', $status);
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min(max($request->integer('per_page', 30), 1), 100);

        $exercises = $query->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($exercises);
    }

    /**
     * Aprueba, rechaza o retira la validación de un ejercicio.
     */
    public function updateValidation(Request $request, Exercise $exercise): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([Exercise::STATUS_PENDING, Exercise::STATUS_APPROVED, Exercise::STATUS_REJECTED])],
            'is_validated' => 'nullable|boolean',
        ]);

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $exercise->update(['status' => $validated['status']]);
        } elseif (array_key_exists('is_validated', $validated)) {
            $exercise->update([
                'status' => $validated['is_validated'] ? Exercise::STATUS_APPROVED : Exercise::STATUS_PENDING,
            ]);
        } else {
            return response()->json(['message' => 'Debes indicar un estado válido.'], 422);
        }

        $exercise->refresh();

        $message = match ($exercise->status) {
            Exercise::STATUS_APPROVED => 'Ejercicio validado y publicado en el catálogo global.',
            Exercise::STATUS_REJECTED => 'Ejercicio rechazado. No aparecerá en el catálogo global ni en la cola de pendientes.',
            Exercise::STATUS_PENDING => 'Ejercicio marcado como pendiente. Solo será visible para su autor hasta su validación.',
            default => 'Estado del ejercicio actualizado.',
        };

        return response()->json([
            'message' => $message,
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
