<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BodyArea;
use Illuminate\Http\JsonResponse;

class BodyAreaController extends Controller
{
    public function index(): JsonResponse
    {
        $bodyAreas = BodyArea::all();
        return response()->json($bodyAreas);
    }
}
