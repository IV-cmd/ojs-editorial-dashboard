<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SyncRunResource;
use App\Models\SyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncRunController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $run = SyncRun::query()->latest('id')->first();

        return response()->json(['data' => $run === null ? null : (new SyncRunResource($run))->resolve($request)]);
    }
}
