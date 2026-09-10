<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AvailabilityInquiryResource;
use App\Models\Kost;
use App\Services\AvailabilityInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityInquiryController extends Controller
{
    public function __construct(private readonly AvailabilityInquiryService $inquiries) {}

    public function store(Request $request, Kost $kost): JsonResponse
    {
        $result = $this->inquiries->ask($request->user(), $kost);

        return (new AvailabilityInquiryResource($result->inquiry, $result->remainingCredit))
            ->response()
            ->setStatusCode(201);
    }
}
