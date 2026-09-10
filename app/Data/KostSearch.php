<?php

namespace App\Data;

/**
 * Validated, whitelisted search parameters for the public kost listing.
 */
final readonly class KostSearch
{
    public const int MAX_PER_PAGE = 50;

    public function __construct(
        public ?string $name = null,
        public ?string $location = null,
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public string $sort = 'price',
        public string $order = 'asc',
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            name: $validated['name'] ?? null,
            location: $validated['location'] ?? null,
            minPrice: isset($validated['min_price']) ? (int) $validated['min_price'] : null,
            maxPrice: isset($validated['max_price']) ? (int) $validated['max_price'] : null,
            sort: $validated['sort'] ?? 'price',
            order: $validated['order'] ?? 'asc',
            perPage: isset($validated['per_page']) ? (int) $validated['per_page'] : 15,
        );
    }
}
