<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for creating and fully updating a kost (PUT semantics).
 */
class KostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'location' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'available_rooms' => ['required', 'integer', 'min:0', 'max:10000'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
