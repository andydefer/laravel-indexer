<?php

// tests/Fixtures/CodeSnippets/IndexableFieldSnippets.php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Fixtures\CodeSnippets;

final class IndexableFieldSnippets
{
    public const SIMPLE_MODEL = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class User implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'slug' => $this->slug,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'user']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const NESTED_FIELDS = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class User implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'profile' => [
                'bio' => $this->bio,
                'social' => [
                    'twitter' => $this->twitter,
                    'github' => $this->github,
                ],
            ],
            'email' => $this->email,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'user']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const DEEP_NESTED_FIELDS = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class User implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'profile' => [
                'personal' => [
                    'bio' => $this->bio,
                    'social' => [
                        'twitter' => $this->twitter,
                        'github' => $this->github,
                        'linkedin' => [
                            'url' => $this->linkedin_url,
                            'handle' => $this->linkedin_handle,
                        ],
                    ],
                ],
                'professional' => [
                    'title' => $this->title,
                    'company' => $this->company,
                    'experience' => [
                        'years' => $this->years_experience,
                        'seniority' => $this->seniority,
                    ],
                ],
            ],
            'email' => $this->email,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'user']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const WITH_VARIABLE = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class Drug implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        $data = [
            'name' => $this->name,
            'generic_name' => $this->generic_name,
            'slug' => $this->slug,
            'description' => $this->description,
        ];

        return StrictAssociative::from($data);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'drug']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const WITH_COMMENTS = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class Hospital implements Indexable
{
    /**
     * Get the indexable data.
     */
    public function getIndexableData(): StrictAssociative
    {
        // Return hospital data
        return StrictAssociative::from([
            'name' => $this->name,      // Hospital name
            'slug' => $this->slug,      // Hospital slug
            'description' => $this->description, // Description
            'type' => $this->type,       // Hospital type
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'hospital']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const PHARMACY_MODEL = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class Pharmacy implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'description' => $this->description,
            'website' => $this->website,
            'phone_number' => $this->phone_number,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'pharmacy']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const SPECIALTY_MODEL = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class Specialty implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'specialty']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const WITHOUT_GET_INDEXABLE_DATA = <<<'PHP'
<?php

namespace App\Models;

class NotIndexable
{
    public function someMethod(): void
    {
        // No getIndexableData method
    }
}
PHP;

    public const WITHOUT_RETURN = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class Test implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        // No return statement
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'test']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const MULTIPLE_MODELS = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class User implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'user']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}

class Product implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'price' => $this->price,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'product']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const NO_NAMESPACE = <<<'PHP'
<?php

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class User implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'user']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const USER_MODEL_WITH_PROFILE = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class User implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'slug' => $this->slug,
            'city' => $this->city,
            'profile' => [
                'titles' => $this->profile?->titles,
                'bio' => $this->profile?->bio,
                'website' => $this->profile?->website,
            ],
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'user']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const DRUG_MODEL = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class Drug implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'generic_name' => $this->generic_name,
            'slug' => $this->slug,
            'description' => $this->description,
            'form' => $this->form,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'drug']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const HOSPITAL_MODEL = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class Hospital implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'specialties' => $this->specialties->pluck('name')->implode(', '),
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'hospital']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const NO_SLUG_MODEL = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class TestModel implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'description' => $this->description,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'test']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;

    public const USER_MODEL_REAL = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\Enums\BinaryChoice;
use AndyDefer\LaravelCluster\Proxies\ClusterVOProxy;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelRattachments\Contracts\RattachmentInterface;
use AndyDefer\LaravelRattachments\Enums\HookPosition;
use AndyDefer\LaravelRattachments\Traits\HasRattachments;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\PhpVo\ValueObjects\SlugVO;
use AndyDefer\Repository\Proxies\AttributeProxy;
use App\Enums\ApplicationRole;
use App\Enums\ProfileRole;
use App\Models\DoctorProfile;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Model implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'slug' => $this->slug,
            'city' => $this->city?->value,
            'profile' => [
                'titles' => $this->doctorProfile?->titles,
                'bio' => $this->doctorProfile?->bio,
                'website' => $this->doctorProfile?->website,
            ],
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'user']);
    }

    public function shouldBeIndexed(): bool
    {
        return true;
    }
}
PHP;
}
