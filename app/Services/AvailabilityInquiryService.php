<?php

namespace App\Services;

use App\Data\InquiryResult;
use App\Enums\CreditTransactionType;
use App\Models\Kost;
use App\Models\RoomAvailabilityInquiry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AvailabilityInquiryService
{
    public function __construct(private readonly CreditService $credits) {}

    /**
     * Charges the inquiry fee and records the answer; everything rolls back if any step fails.
     */
    public function ask(User $user, Kost $kost): InquiryResult
    {
        if (! $user->role->canInquire()) {
            throw new AuthorizationException('Owners cannot ask about room availability.');
        }

        return DB::transaction(function () use ($user, $kost): InquiryResult {
            $cost = $this->credits->inquiryCost();
            $remaining = $this->credits->deduct($user, $cost, CreditTransactionType::AvailabilityInquiry, $kost->getKey());

            $inquiry = RoomAvailabilityInquiry::create([
                'user_id' => $user->getKey(),
                'kost_id' => $kost->getKey(),
                'credits_charged' => $cost,
                'available_rooms_snapshot' => $kost->available_rooms,
            ]);
            $inquiry->setRelation('kost', $kost);

            return new InquiryResult($inquiry, $remaining);
        });
    }
}
