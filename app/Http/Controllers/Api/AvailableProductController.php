<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockQueryRequest;
use App\Http\Resources\AvailableProductResource;
use App\Services\StockService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AvailableProductController extends Controller
{
    public function index(StockQueryRequest $request, StockService $stock): AnonymousResourceCollection
    {
        $storageId = $request->validated('storage_id');
        $products = $stock->availableProducts($storageId === null ? null : (int) $storageId);

        return AvailableProductResource::collection($products);
    }
}
