<?php

use App\Models\Kost;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->kost = Kost::factory()->for($this->owner, 'owner')->create(['available_rooms' => 2]);
});

it('deducts five credits and records the inquiry', function () {
    $user = User::factory()->regular()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/kosts/{$this->kost->id}/availability-inquiries")
        ->assertCreated()
        ->assertJsonPath('kost_id', $this->kost->id)
        ->assertJsonPath('available_rooms', 2)
        ->assertJsonPath('is_available', true)
        ->assertJsonPath('credits_charged', 5)
        ->assertJsonPath('remaining_credit', 15);

    expect($user->fresh()->credit)->toBe(15);
    $this->assertDatabaseHas('room_availability_inquiries', ['user_id' => $user->id, 'kost_id' => $this->kost->id, 'credits_charged' => 5]);
    $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id, 'type' => 'availability_inquiry', 'amount' => -5, 'balance_after' => 15, 'reference_id' => $this->kost->id]);
});

it('lets a premium user ask eight times before running out', function () {
    $user = User::factory()->premium()->create();
    Sanctum::actingAs($user);

    foreach (range(1, 8) as $i) {
        $this->postJson("/api/v1/kosts/{$this->kost->id}/availability-inquiries")->assertCreated();
    }

    $this->postJson("/api/v1/kosts/{$this->kost->id}/availability-inquiries")
        ->assertUnprocessable()
        ->assertJsonPath('type', '/problems/insufficient-credit');
    expect($user->fresh()->credit)->toBe(0);
});

it('leaves the balance untouched when credit is insufficient', function () {
    $user = User::factory()->regular()->withCredit(4)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/kosts/{$this->kost->id}/availability-inquiries")->assertUnprocessable();

    expect($user->fresh()->credit)->toBe(4);
    $this->assertDatabaseCount('room_availability_inquiries', 0);
});

it('forbids owners and anonymous users', function () {
    $this->postJson("/api/v1/kosts/{$this->kost->id}/availability-inquiries")->assertUnauthorized();

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/kosts/{$this->kost->id}/availability-inquiries")->assertForbidden();
});

it('returns 404 for an unknown kost without charging', function () {
    $user = User::factory()->regular()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/kosts/999999/availability-inquiries')->assertNotFound();
    expect($user->fresh()->credit)->toBe(20);
});
