<?php

use App\Http\Controllers\Api\AdminExerciseController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BodyAreaController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ExerciseController;
use App\Http\Controllers\Api\FundamentalController;
use App\Http\Controllers\Api\MethodologyController;
use App\Http\Controllers\Api\TrainerController;
use App\Http\Controllers\Api\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Auth y perfil
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'updatePassword']);
    Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [AuthController::class, 'removeAvatar']);
    Route::post('/profile/banner', [AuthController::class, 'uploadBanner']);
    Route::delete('/profile/banner', [AuthController::class, 'removeBanner']);

    // Catálogos
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/body-areas', [BodyAreaController::class, 'index']);
    Route::get('/methodologies', [MethodologyController::class, 'index']);
    Route::get('/fundamentals', [FundamentalController::class, 'index']);

    // Ejercicios (catálogo global)
    Route::apiResource('exercises', ExerciseController::class);
    Route::post('/exercises/{exercise}/upload-image', [ExerciseController::class, 'uploadImage']);

    // Entrenamientos
    Route::apiResource('workouts', WorkoutController::class);
    Route::put('/workouts/{workout}/exercises', [WorkoutController::class, 'syncExercises']);
    Route::post('/workouts/{workout}/add-exercise', [WorkoutController::class, 'addExercise']);
    Route::put('/workouts/{workout}/exercises/{exercise}', [WorkoutController::class, 'updateExercise']);
    Route::put('/workouts/{workout}/exercises/{exercise}/order', [WorkoutController::class, 'updateExerciseOrder']);
    Route::put('/workouts/{workout}/exercises/{exercise}/feedback', [WorkoutController::class, 'updateExerciseFeedback']);
    Route::delete('/workouts/{workout}/exercises/{exercise}', [WorkoutController::class, 'removeExercise']);
    Route::put('/workouts/{workout}/feedback', [WorkoutController::class, 'updateFeedback']);

    // Red social de entrenadores
    Route::get('/trainers', [TrainerController::class, 'index']);
    Route::get('/trainers/{username}', [TrainerController::class, 'show']);
    Route::get('/feed', [TrainerController::class, 'feed']);

    // Administración: validación de ejercicios de la comunidad
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/summary', [AdminExerciseController::class, 'summary']);
        Route::get('/exercises', [AdminExerciseController::class, 'index']);
        Route::patch('/exercises/{exercise}/validation', [AdminExerciseController::class, 'updateValidation']);
    });
});
