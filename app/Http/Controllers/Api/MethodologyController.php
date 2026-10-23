<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Methodology;
use Illuminate\Http\JsonResponse;

class MethodologyController extends Controller
{
    public function index(): JsonResponse
    {
        $methodologies = Methodology::all();
        return response()->json($methodologies);
    }
}
