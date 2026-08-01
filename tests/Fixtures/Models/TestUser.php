<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\Proxies\ClusterVOProxy;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestUser extends Model implements Indexable
{
    protected $table = 'test_users';

    protected $fillable = [
        'id',
        'name',
        'email',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function shouldBeIndexed(): bool
    {
        return $this->is_active;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'description' => $this->description,
        ]);
    }

    public function getSearchResultFormat(): StrictAssociative
    {
        return StrictAssociative::from([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ]);
    }

    public function getMorphClass()
    {
        return self::class;
    }

    public function getKey()
    {
        return $this->id;
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(TestAddress::class);
    }

    public function getIndexableCluster(): ClusterVO
    {
        $this->loadMissing('addresses');

        return ClusterVOProxy::make([
            'type' => 'user',
            'status' => $this->is_active,
            'email' => $this->email,
            'addresses' => $this->addresses->map(fn ($address) => [
                'city' => $address->city,
                'country' => $address->country,
                'postal_code' => $address->postal_code,
                'street' => $address->street,
            ])->values()->toArray(),
        ]);
    }
}
