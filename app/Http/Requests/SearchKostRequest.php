<?php

namespace App\Http\Requests;

use App\Data\KostSearch;
use Illuminate\Foundation\Http\FormRequest;

class SearchKostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0', 'gte:min_price'],
            'sort' => ['nullable', 'in:price,created_at'],
            'order' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.KostSearch::MAX_PER_PAGE],
        ];
    }

    public function toSearch(): KostSearch
    {
        return KostSearch::fromValidated($this->validated());
    }
}
