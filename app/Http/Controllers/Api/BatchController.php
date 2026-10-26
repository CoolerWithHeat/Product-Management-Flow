<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchRequest;
use App\Http\Resources\BatchResource;
use App\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BatchController extends Controller
{
    public function store(StoreBatchRequest $request, BatchService $batches): JsonResponse
    {
        $batch = $batches->purchase($request->validated());

        return (new BatchResource($batch))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
