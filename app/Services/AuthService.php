<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private readonly CreditService $credits) {}

    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     * @return array<string, mixed>
     */
    public function register(array $data): array
    {
        $role = UserRole::from($data['role']);

        $user = DB::transaction(function () use ($data, $role): User {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $role,
                'credit' => $role->monthlyAllowance(),
            ]);

            $this->credits->recordInitialGrant($user);

            return $user;
        });

        return $this->issueToken($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        return $this->issueToken($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function issueToken(User $user): array
    {
        $minutes = (int) config('sanctum.expiration');
        $expiresAt = $minutes > 0 ? now()->addMinutes($minutes) : null;

        return [
            'token' => $user->createToken('api', ['*'], $expiresAt)->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $minutes > 0 ? $minutes * 60 : null,
            'user' => (new UserResource($user))->resolve(),
        ];
    }
}
