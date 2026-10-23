<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fundamental;
use Illuminate\Http\JsonResponse;

class FundamentalController extends Controller
{
    public function index(): JsonResponse
    {
        $fundamentals = Fundamental::all();
        return response()->json($fundamentals);
    }
}
