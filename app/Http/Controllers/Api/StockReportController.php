<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockReportRequest;
use App\Http\Resources\StockReportResource;
use App\Services\StockService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockReportController extends Controller
{
    public function index(StockReportRequest $request, StockService $stock): AnonymousResourceCollection
    {
        $date = $request->validated('date');
        $storageId = $request->validated('storage_id');
        $rows = $stock->historicalStock($date, $storageId === null ? null : (int) $storageId);

        return StockReportResource::collection($rows)->additional(['meta' => ['date' => $date]]);
    }
}
