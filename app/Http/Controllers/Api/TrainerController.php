<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    /**
     * Directorio de entrenadores con perfil público.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('is_private', false)
            ->whereNotNull('username')
            ->withCount([
                'publicWorkouts',
                'exercises' => fn ($q) => $q->where('status', \App\Models\Exercise::STATUS_APPROVED),
            ]);

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $trainers = $query->orderByDesc('public_workouts_count')
            ->orderBy('name')
            ->paginate(24)
            ->through(function (User $trainer) {
                // El correo nunca se expone en el directorio público.
                return $trainer->makeHidden('email');
            });

        return response()->json($trainers);
    }

    /**
     * Perfil público de un entrenador y sus entrenamientos compartidos.
     */
    public function show(Request $request, string $username): JsonResponse
    {
        $trainer = User::where('username', $username)->firstOrFail();
        $viewer = $request->user();
        $isOwner = $viewer->id === $trainer->id;

        // Un perfil privado solo muestra sus entrenamientos a su propietario.
        $canSeeWorkouts = $isOwner || ! $trainer->is_private;

        $workouts = null;

        if ($canSeeWorkouts) {
            $trainer->loadCount([
                'publicWorkouts',
                'exercises' => fn ($q) => $q->when(
                    ! $isOwner,
                    fn ($query) => $query->where('status', \App\Models\Exercise::STATUS_APPROVED)
                ),
            ]);

            $workoutQuery = $trainer->workouts()
                ->when(! $isOwner, fn ($q) => $q->where('is_public', true))
                ->with(['exercises' => function ($query) use ($isOwner) {
                    if (! $isOwner) {
                        $query->where('status', \App\Models\Exercise::STATUS_APPROVED);
                    }

                    $query->select('exercises.id', 'exercises.title', 'exercises.type');
                }])
                ->orderByDesc('date')
                ->orderByDesc('created_at');

            if ($isOwner) {
                $workoutQuery->withCount('exercises');
            } else {
                $workoutQuery->withCount([
                    'exercises' => fn ($q) => $q->where('status', \App\Models\Exercise::STATUS_APPROVED),
                ]);
            }

            $workouts = $workoutQuery->paginate(20);
        } else {
            $trainer->loadCount([
                'exercises' => fn ($q) => $q->when(
                    ! $isOwner,
                    fn ($query) => $query->where('status', \App\Models\Exercise::STATUS_APPROVED)
                ),
            ]);
            $trainer->setAttribute('public_workouts_count', 0);
        }

        $trainer->setAttribute('is_owner', $isOwner);
        $trainer->setAttribute('can_see_workouts', $canSeeWorkouts);

        if (! $isOwner) {
            $trainer->makeHidden('email');
        }

        return response()->json([
            'trainer' => $trainer,
            'can_see_workouts' => $canSeeWorkouts,
            'workouts' => $workouts,
        ]);
    }

    /**
     * Muro público: entrenamientos compartidos de perfiles abiertos.
     */
    public function feed(Request $request): JsonResponse
    {
        $workouts = Workout::query()
            ->where('is_public', true)
            ->whereHas('user', fn ($q) => $q->where('is_private', false))
            ->with([
                'user:id,name,username,display_name,profile_image_path',
                'exercises' => fn ($q) => $q->where('status', \App\Models\Exercise::STATUS_APPROVED)
                    ->select('exercises.id', 'exercises.title', 'exercises.type'),
            ])
            ->withCount([
                'exercises' => fn ($q) => $q->where('status', \App\Models\Exercise::STATUS_APPROVED),
            ])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($workouts);
    }
}
