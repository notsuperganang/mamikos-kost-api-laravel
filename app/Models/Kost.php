<?php

namespace App\Models;

use App\Data\KostSearch;
use Database\Factories\KostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'location', 'price', 'available_rooms', 'description'])]
class Kost extends Model
{
    /** @use HasFactory<KostFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'available_rooms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->getKey();
    }

    /**
     * Apply the public search filters and ordering. Unspecified criteria add no SQL.
     *
     * @param  Builder<Kost>  $query
     * @return Builder<Kost>
     */
    public function scopeSearch(Builder $query, KostSearch $search): Builder
    {
        return $query
            ->when($search->name, fn (Builder $q, string $name) => $q->where('name', 'ilike', self::contains($name)))
            ->when($search->location, fn (Builder $q, string $location) => $q->where('location', 'ilike', self::contains($location)))
            ->when($search->minPrice !== null, fn (Builder $q) => $q->where('price', '>=', $search->minPrice))
            ->when($search->maxPrice !== null, fn (Builder $q) => $q->where('price', '<=', $search->maxPrice))
            ->orderBy($search->sort, $search->order)
            ->orderBy('id');
    }

    private static function contains(string $value): string
    {
        return '%'.addcslashes(trim($value), '\\%_').'%';
    }
}
