<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('grants the allowance of the chosen role on registration', function (string $role, int $credit) {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana',
        'email' => "$role@example.com",
        'password' => 'secret-123',
        'role' => $role,
    ]);

    $response->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.role', $role)
        ->assertJsonPath('user.credit', $credit)
        ->assertJsonMissingPath('user.password');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
    $this->assertDatabaseHas('users', ['email' => "$role@example.com", 'role' => $role, 'credit' => $credit]);
    $this->assertDatabaseCount('credit_transactions', $credit > 0 ? 1 : 0);
})->with([
    'owner' => ['owner', 0],
    'regular' => ['regular', 20],
    'premium' => ['premium', 40],
]);

it('rejects a duplicate email with a validation problem', function () {
    User::factory()->create(['email' => 'ana@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana again',
        'email' => 'ANA@example.com',
        'password' => 'secret-123',
        'role' => 'premium',
    ])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', '/problems/validation')
        ->assertJsonValidationErrors(['email']);
});

it('returns field errors for an invalid payload', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'role' => 'admin',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
});

it('logs in with valid credentials and rejects bad ones', function () {
    User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret-123']);

    $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'secret-123'])
        ->assertOk()
        ->assertJsonPath('user.email', 'ana@example.com')
        ->assertJsonStructure(['token', 'token_type', 'expires_in', 'user']);

    $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'wrong'])
        ->assertUnauthorized()
        ->assertJsonPath('type', '/problems/unauthorized');
});

it('requires a valid token for the current user endpoint', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('type', '/problems/unauthorized');
    $this->withToken('garbage')->getJson('/api/v1/auth/me')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->premium()->create());

    $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('credit', 40)->assertJsonPath('role', 'premium');
});

it('accepts the issued token on protected endpoints', function () {
    $token = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret-123', 'role' => 'regular',
    ])->json('token');

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('email', 'ana@example.com');
});
