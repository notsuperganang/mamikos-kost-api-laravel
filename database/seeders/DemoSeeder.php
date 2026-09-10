<?php

namespace Database\Seeders;

use App\Models\Kost;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Database\Seeder;

/**
 * Demo accounts (password for all: "password") and a handful of kosts to search.
 */
class DemoSeeder extends Seeder
{
    public function __construct(private readonly CreditService $credits) {}

    public function run(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Budi Owner', 'email' => 'owner@example.com']);
        $secondOwner = User::factory()->owner()->create(['name' => 'Sari Owner', 'email' => 'owner2@example.com']);
        $regular = User::factory()->regular()->create(['name' => 'Rina Regular', 'email' => 'regular@example.com']);
        $premium = User::factory()->premium()->create(['name' => 'Putri Premium', 'email' => 'premium@example.com']);

        foreach ([$regular, $premium] as $user) {
            $this->credits->recordInitialGrant($user);
        }

        Kost::factory()->for($owner, 'owner')->create(['name' => 'Kost Melati', 'location' => 'Yogyakarta', 'price' => 1_500_000, 'available_rooms' => 2]);
        Kost::factory()->for($owner, 'owner')->create(['name' => 'Kost Mawar', 'location' => 'Sleman, Yogyakarta', 'price' => 900_000, 'available_rooms' => 0]);
        Kost::factory()->for($owner, 'owner')->create(['name' => 'Griya Anggrek', 'location' => 'Bandung', 'price' => 2_200_000, 'available_rooms' => 5]);
        Kost::factory()->for($secondOwner, 'owner')->count(3)->create();
    }
}
