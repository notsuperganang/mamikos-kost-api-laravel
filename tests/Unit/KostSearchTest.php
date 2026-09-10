<?php

use App\Data\KostSearch;

it('defaults to price ascending with 15 per page', function () {
    $search = KostSearch::fromValidated([]);

    expect($search->sort)->toBe('price')
        ->and($search->order)->toBe('asc')
        ->and($search->perPage)->toBe(15)
        ->and($search->minPrice)->toBeNull();
});

it('casts numeric filters from validated input', function () {
    $search = KostSearch::fromValidated(['min_price' => '100', 'max_price' => '200', 'per_page' => '5', 'sort' => 'created_at', 'order' => 'desc']);

    expect($search->minPrice)->toBe(100)
        ->and($search->maxPrice)->toBe(200)
        ->and($search->perPage)->toBe(5)
        ->and($search->sort)->toBe('created_at')
        ->and($search->order)->toBe('desc');
});
