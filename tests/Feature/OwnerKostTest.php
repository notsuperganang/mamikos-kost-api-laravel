<?php

use App\Models\Kost;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

$validBody = [
    'name' => 'Kost Melati',
    'location' => 'Yogyakarta',
    'price' => 1_500_000,
    'available_rooms' => 3,
    'description' => 'Near campus',
];

it('lets an owner create, update, list and delete kosts', function () use ($validBody) {
    $owner = User::factory()->owner()->create();
    Sanctum::actingAs($owner);

    $created = $this->postJson('/api/v1/owner/kosts', $validBody)
        ->assertCreated()
        ->assertJsonPath('name', 'Kost Melati')
        ->assertJsonPath('owner_id', $owner->id);
    $id = $created->json('id');

    $this->postJson('/api/v1/owner/kosts', [...$validBody, 'name' => 'Kost Mawar'])->assertCreated();

    $this->getJson('/api/v1/owner/kosts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);

    $this->putJson("/api/v1/owner/kosts/$id", [...$validBody, 'name' => 'Kost Melati Baru', 'price' => 1_750_000])
        ->assertOk()
        ->assertJsonPath('name', 'Kost Melati Baru')
        ->assertJsonPath('price', 1_750_000);

    $this->deleteJson("/api/v1/owner/kosts/$id")->assertNoContent();
    $this->assertDatabaseMissing('kosts', ['id' => $id]);
    $this->getJson("/api/v1/kosts/$id")->assertNotFound()->assertJsonPath('type', '/problems/not-found');
});

it('only lists the kosts of the authenticated owner', function () {
    $alice = User::factory()->owner()->create();
    $bob = User::factory()->owner()->create();
    Kost::factory()->for($alice, 'owner')->create(['name' => 'Kost Alice']);
    Kost::factory()->for($bob, 'owner')->create(['name' => 'Kost Bob']);

    Sanctum::actingAs($alice);

    $this->getJson('/api/v1/owner/kosts')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Kost Alice');
});

it("forbids editing or deleting another owner's kost", function () use ($validBody) {
    $alice = User::factory()->owner()->create();
    $bobsKost = Kost::factory()->create();
    Sanctum::actingAs($alice);

    $this->putJson("/api/v1/owner/kosts/{$bobsKost->id}", $validBody)
        ->assertForbidden()
        ->assertJsonPath('type', '/problems/forbidden');
    $this->deleteJson("/api/v1/owner/kosts/{$bobsKost->id}")->assertForbidden();
    $this->assertDatabaseHas('kosts', ['id' => $bobsKost->id, 'name' => $bobsKost->name]);
});

it('rejects non-owners and anonymous users', function () use ($validBody) {
    $this->postJson('/api/v1/owner/kosts', $validBody)->assertUnauthorized();
    $this->getJson('/api/v1/owner/kosts')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->regular()->create());

    $this->postJson('/api/v1/owner/kosts', $validBody)->assertForbidden()->assertJsonPath('type', '/problems/forbidden');
    $this->getJson('/api/v1/owner/kosts')->assertForbidden();
});

it('validates the kost payload', function () {
    Sanctum::actingAs(User::factory()->owner()->create());

    $this->postJson('/api/v1/owner/kosts', ['name' => '', 'location' => '', 'price' => -1, 'available_rooms' => -1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'location', 'price', 'available_rooms']);
});
