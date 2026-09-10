<?php

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

it('resets regular and premium users to their allowance and leaves owners alone', function () {
    $owner = User::factory()->owner()->withCredit(99)->create();
    $regular = User::factory()->regular()->withCredit(3)->create();
    $premium = User::factory()->premium()->withCredit(0)->create();

    $this->artisan('credits:recharge')
        ->expectsOutputToContain('2 user(s) updated')
        ->assertSuccessful();

    expect($regular->fresh()->credit)->toBe(20)
        ->and($premium->fresh()->credit)->toBe(40)
        ->and($owner->fresh()->credit)->toBe(99);

    $this->assertDatabaseHas('credit_transactions', ['user_id' => $regular->id, 'type' => 'monthly_recharge', 'amount' => 17, 'balance_after' => 20]);
    $this->assertDatabaseHas('credit_transactions', ['user_id' => $premium->id, 'type' => 'monthly_recharge', 'amount' => 40, 'balance_after' => 40]);
    expect(CreditTransaction::query()->where('user_id', $owner->id)->count())->toBe(0);
});

it('is a no-op when run twice in the same month', function () {
    $user = User::factory()->regular()->withCredit(0)->create();

    $this->artisan('credits:recharge')->assertSuccessful();
    $user->forceFill(['credit' => 10])->save();

    $this->artisan('credits:recharge')->expectsOutputToContain('0 user(s) updated')->assertSuccessful();
    expect($user->fresh()->credit)->toBe(10);
});

it('recharges again once a new month starts', function () {
    $user = User::factory()->regular()->withCredit(0)->create();
    $this->artisan('credits:recharge')->assertSuccessful();

    DB::table('credit_transactions')->where('type', 'monthly_recharge')->update(['created_at' => now()->subDays(40)]);
    $user->forceFill(['credit' => 5])->save();

    $this->artisan('credits:recharge')->expectsOutputToContain('1 user(s) updated')->assertSuccessful();
    expect($user->fresh()->credit)->toBe(20);
});

it('reports eligible users without changing anything in dry-run mode', function () {
    $user = User::factory()->premium()->withCredit(1)->create();

    $this->artisan('credits:recharge --dry-run')
        ->expectsOutputToContain('premium: 1 user(s) would be recharged')
        ->assertSuccessful();

    expect($user->fresh()->credit)->toBe(1);
});

it('is scheduled on the first day of every month, on one server, without overlapping', function () {
    $this->artisan('schedule:list')->assertSuccessful(); // boots the console routes

    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command ?? '', 'credits:recharge'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 1 * *')
        ->and($event->timezone)->toBe(config('credits.timezone'))
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
});
