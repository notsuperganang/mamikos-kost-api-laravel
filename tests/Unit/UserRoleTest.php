<?php

use App\Enums\UserRole;

it('has the allowances from the business rules', function () {
    expect(UserRole::Owner->monthlyAllowance())->toBe(0)
        ->and(UserRole::Regular->monthlyAllowance())->toBe(20)
        ->and(UserRole::Premium->monthlyAllowance())->toBe(40);
});

it('only lets non-owners inquire', function () {
    expect(UserRole::Owner->canInquire())->toBeFalse()
        ->and(UserRole::Regular->canInquire())->toBeTrue()
        ->and(UserRole::Premium->canInquire())->toBeTrue();
});
