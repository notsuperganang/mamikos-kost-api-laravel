<?php

namespace App\Data;

use App\Models\RoomAvailabilityInquiry;

final readonly class InquiryResult
{
    public function __construct(
        public RoomAvailabilityInquiry $inquiry,
        public int $remainingCredit,
    ) {}
}
