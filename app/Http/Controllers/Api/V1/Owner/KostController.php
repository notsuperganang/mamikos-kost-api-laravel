<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\KostRequest;
use App\Http\Requests\ListKostRequest;
use App\Http\Resources\KostResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\Kost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Owner-only management of their own kosts. The "owner" role is enforced by route middleware;
 * ownership of the individual kost by KostPolicy.
 */
class KostController extends Controller
{
    public function index(ListKostRequest $request): JsonResponse
    {
        $kosts = $request->user()->kosts()->latest('id')->paginate($request->perPage());

        return PaginatedResponse::make($kosts, KostResource::class);
    }

    public function store(KostRequest $request): JsonResponse
    {
        $kost = $request->user()->kosts()->create($request->validated());

        return (new KostResource($kost))->response()->setStatusCode(201);
    }

    public function update(KostRequest $request, Kost $kost): KostResource
    {
        Gate::authorize('update', $kost);

        $kost->update($request->validated());

        return new KostResource($kost);
    }

    public function destroy(Kost $kost): Response
    {
        Gate::authorize('delete', $kost);

        $kost->delete();

        return response()->noContent();
    }
}
