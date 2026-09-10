<?php

use App\Models\Kost;
use App\Models\User;

beforeEach(function () {
    $owner = User::factory()->owner()->create();
    Kost::factory()->for($owner, 'owner')->create(['name' => 'Kost Melati', 'location' => 'Yogyakarta', 'price' => 1_500_000, 'available_rooms' => 2]);
    Kost::factory()->for($owner, 'owner')->create(['name' => 'Kost Mawar', 'location' => 'Sleman, Yogyakarta', 'price' => 900_000, 'available_rooms' => 0]);
    Kost::factory()->for($owner, 'owner')->create(['name' => 'Griya Anggrek', 'location' => 'Bandung', 'price' => 2_200_000, 'available_rooms' => 5]);
});

it('is public and sorts by price ascending by default', function () {
    $this->getJson('/api/v1/kosts')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.price', 900_000)
        ->assertJsonPath('data.2.price', 2_200_000);
});

it('filters by name case-insensitively', function () {
    $this->getJson('/api/v1/kosts?name=melati')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Kost Melati');
});

it('filters by location', function () {
    $this->getJson('/api/v1/kosts?location=yogya')->assertOk()->assertJsonPath('meta.total', 2);
});

it('filters by price range', function () {
    $this->getJson('/api/v1/kosts?min_price=1000000&max_price=2000000')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Kost Melati');
});

it('sorts by price descending', function () {
    $this->getJson('/api/v1/kosts?sort=price&order=desc')
        ->assertOk()
        ->assertJsonPath('data.0.price', 2_200_000)
        ->assertJsonPath('data.2.price', 900_000);
});

it('paginates with meta', function () {
    $this->getJson('/api/v1/kosts?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.last_page', 2);
});

it('rejects unknown sort fields and oversized pages', function () {
    $this->getJson('/api/v1/kosts?sort=name')->assertUnprocessable()->assertJsonValidationErrors(['sort']);
    $this->getJson('/api/v1/kosts?per_page=500')->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
});

it('escapes like wildcards in filters', function () {
    $this->getJson('/api/v1/kosts?name=%25')->assertOk()->assertJsonPath('meta.total', 0);
});

it('shows a kost detail or 404', function () {
    $kost = Kost::query()->where('name', 'Griya Anggrek')->firstOrFail();

    $this->getJson("/api/v1/kosts/{$kost->id}")
        ->assertOk()
        ->assertJsonPath('name', 'Griya Anggrek')
        ->assertJsonPath('available_rooms', 5);

    $this->getJson('/api/v1/kosts/999999')->assertNotFound()->assertJsonPath('type', '/problems/not-found');
});
