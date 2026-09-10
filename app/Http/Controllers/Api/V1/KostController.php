<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchKostRequest;
use App\Http\Resources\KostResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\Kost;
use Illuminate\Http\JsonResponse;

/**
 * Public search and detail endpoints.
 */
class KostController extends Controller
{
    public function index(SearchKostRequest $request): JsonResponse
    {
        $search = $request->toSearch();

        return PaginatedResponse::make(Kost::query()->search($search)->paginate($search->perPage), KostResource::class);
    }

    public function show(Kost $kost): KostResource
    {
        return new KostResource($kost);
    }
}
