<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BatchProfitResource;
use App\Services\ProfitService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BatchProfitController extends Controller
{
    public function index(ProfitService $profit): AnonymousResourceCollection
    {
        return BatchProfitResource::collection($profit->batches());
    }
}
