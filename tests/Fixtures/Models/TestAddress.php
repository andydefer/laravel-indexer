<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\Proxies\ClusterVOProxy;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use Illuminate\Database\Eloquent\Model;

class TestAddress extends Model implements Indexable
{
    protected $table = 'test_addresses';

    protected $fillable = [
        'id',
        'user_id',
        'street',
        'city',
        'country',
        'postal_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function user()
    {
        return $this->belongsTo(TestUser::class);
    }

    public function shouldBeIndexed(): bool
    {
        return $this->is_active;
    }

    public function getIndexableData(): StrictAssociative
    {
        $this->loadMissing('user');

        return StrictAssociative::from([
            'address' => [
                'street' => $this->street,
                'city' => $this->city,
                'country' => $this->country,
                'postal_code' => $this->postal_code,
            ],
            'user' => [
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'full_address' => $this->getFullAddress(),
        ]);
    }

    public function getSearchResultFormat(): StrictAssociative
    {
        return StrictAssociative::from([
            'id' => $this->id,
            'user_id' => $this->user_id,
            'street' => $this->street,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'full_address' => $this->getFullAddress(),
            'is_active' => $this->is_active,
        ]);
    }

    public function getFullAddress(): string
    {
        return implode(', ', array_filter([
            $this->street,
            $this->city,
            $this->country,
            $this->postal_code,
        ]));
    }

    public function getMorphClass()
    {
        return self::class;
    }

    public function getKey()
    {
        return $this->id;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVOProxy::make([
            'type' => 'address',
            'status' => $this->is_active,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
        ]);
    }
}
