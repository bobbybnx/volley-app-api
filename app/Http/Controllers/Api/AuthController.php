<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const AVATAR_RULES = 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120';

    private const BANNER_RULES = 'required|image|mimes:jpeg,png,jpg,gif,webp|max:8192';
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username'),
            ],
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? $validated['name'],
            'username' => $validated['username']
                ?? User::generateUsername($validated['email'], $validated['name']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'theme' => $request->input('theme', 'light'),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $this->serializeUser($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $this->serializeUser($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($this->serializeUser($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'bio' => 'nullable|string|max:500',
            'is_private' => 'sometimes|boolean',
            'theme' => 'sometimes|required|in:light,dark',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return response()->json($this->serializeUser($user->fresh()));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        return $this->uploadProfileImage(
            $request,
            'profile_image_path',
            'users/avatars',
            self::AVATAR_RULES,
            'Foto de perfil actualizada correctamente'
        );
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        return $this->removeProfileImage(
            $request,
            'profile_image_path',
            'Foto de perfil eliminada correctamente'
        );
    }

    public function uploadBanner(Request $request): JsonResponse
    {
        return $this->uploadProfileImage(
            $request,
            'banner_image_path',
            'users/banners',
            self::BANNER_RULES,
            'Banner actualizado correctamente'
        );
    }

    public function removeBanner(Request $request): JsonResponse
    {
        return $this->removeProfileImage(
            $request,
            'banner_image_path',
            'Banner eliminado correctamente'
        );
    }

    private function uploadProfileImage(
        Request $request,
        string $column,
        string $directory,
        string $rules,
        string $message
    ): JsonResponse {
        $request->validate(['image' => $rules]);

        $user = $request->user();

        if ($user->{$column}) {
            Storage::disk('public')->delete($user->{$column});
        }

        $user->update([
            $column => $request->file('image')->store($directory, 'public'),
        ]);

        return response()->json([
            'message' => $message,
            'user' => $this->serializeUser($user->fresh()),
        ]);
    }

    private function removeProfileImage(Request $request, string $column, string $message): JsonResponse
    {
        $user = $request->user();

        if ($user->{$column}) {
            Storage::disk('public')->delete($user->{$column});
            $user->update([$column => null]);
        }

        return response()->json([
            'message' => $message,
            'user' => $this->serializeUser($user->fresh()),
        ]);
    }

    private function serializeUser(User $user): User
    {
        $user->loadCount([
            'exercises',
            'workouts',
            'publicWorkouts',
        ]);

        return $user;
    }
}
