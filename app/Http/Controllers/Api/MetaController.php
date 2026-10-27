<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\EditorialQueries;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function filters(EditorialQueries $queries): JsonResponse
    {
        return response()->json(['data' => $queries->filterOptions()]);
    }
}
