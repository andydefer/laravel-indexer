<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TestPharmacy extends Model implements Indexable
{
    protected $table = 'test_pharmacies';

    protected $fillable = [
        'id',
        'name',
        'address',
        'city',
        'postal_code',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function medications(): BelongsToMany
    {
        return $this->belongsToMany(TestMedication::class, 'test_medication_pharmacy', 'pharmacy_id', 'medication_id');
    }

    public function shouldBeIndexed(): bool
    {
        return $this->is_active;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
            'email' => $this->email,
        ]);
    }

    public function getSearchResultFormat(): StrictAssociative
    {
        $this->loadMissing('medications');

        return StrictAssociative::from([
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'medications_count' => $this->medications->count(),
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

    public function getIndexableCluster(): ClusterVO
    {
        $this->loadMissing('medications');

        return new ClusterVO([
            'type' => 'pharmacy',
            'status' => $this->is_active,
            'city' => $this->city,
            'medications' => $this->medications->map(fn ($m) => [
                'name' => $m->name,
                'laboratory' => $m->laboratory,
                'active_substance' => $m->active_substance,
            ])->values()->toArray(),
        ]);
    }
}
