<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRefundRequest;
use App\Http\Resources\PurchaseRefundResource;
use App\Models\Batch;
use App\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PurchaseRefundController extends Controller
{
    public function store(StoreRefundRequest $request, Batch $batch, BatchService $batches): JsonResponse
    {
        $refunds = $batches->refund($batch, $request->validated());

        return PurchaseRefundResource::collection($refunds)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
