<?php

namespace App\Http\Resources;

use App\Models\RoomAvailabilityInquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoomAvailabilityInquiry
 */
class AvailabilityInquiryResource extends JsonResource
{
    public function __construct(RoomAvailabilityInquiry $inquiry, private readonly int $remainingCredit)
    {
        parent::__construct($inquiry);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kost_id' => $this->kost_id,
            'kost_name' => $this->kost->name,
            'available_rooms' => $this->available_rooms_snapshot,
            'is_available' => $this->available_rooms_snapshot > 0,
            'credits_charged' => $this->credits_charged,
            'remaining_credit' => $this->remainingCredit,
            'created_at' => $this->created_at,
        ];
    }
}
