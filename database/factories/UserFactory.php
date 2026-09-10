<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Regular,
            'credit' => UserRole::Regular->monthlyAllowance(),
        ];
    }

    public function owner(): static
    {
        return $this->role(UserRole::Owner);
    }

    public function regular(): static
    {
        return $this->role(UserRole::Regular);
    }

    public function premium(): static
    {
        return $this->role(UserRole::Premium);
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn () => ['role' => $role, 'credit' => $role->monthlyAllowance()]);
    }

    public function withCredit(int $credit): static
    {
        return $this->state(fn () => ['credit' => $credit]);
    }
}
