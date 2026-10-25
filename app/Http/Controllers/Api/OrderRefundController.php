<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRefundRequest;
use App\Http\Resources\SaleRefundResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OrderRefundController extends Controller
{
    public function store(StoreRefundRequest $request, Order $order, OrderService $orders): JsonResponse
    {
        $refunds = $orders->refund($order, $request->validated());

        return SaleRefundResource::collection($refunds)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
