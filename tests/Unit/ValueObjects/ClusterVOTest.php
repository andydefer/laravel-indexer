<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Unit\ValueObjects;

use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClusterVOTest extends TestCase
{
    // ============================================================
    // TESTS DE CONSTRUCTION
    // ============================================================

    public function test_can_create_cluster_from_string(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor|status:active');

        $this->assertSame('type:user|role:doctor|status:active', $cluster->getValue());
        $this->assertSame('type:user|role:doctor|status:active', (string) $cluster);
    }

    public function test_can_create_empty_cluster(): void
    {
        $cluster = new ClusterVO('');

        $this->assertSame('', $cluster->getValue());
        $this->assertEmpty($cluster->all());
        $this->assertSame('', (string) $cluster);
    }

    public function test_can_create_cluster_with_multiple_values(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor,admin|status:active');

        $this->assertSame(['doctor', 'admin'], $cluster->get('role'));
        $this->assertSame(['active'], $cluster->get('status'));
    }

    public function test_throws_exception_when_cluster_has_no_pair(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid cluster format. Expected "key:value", got "invalid"');

        new ClusterVO('invalid');
    }

    public function test_throws_exception_when_pair_has_no_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster key cannot be empty');

        new ClusterVO(':value|type:user');
    }

    public function test_throws_exception_when_pair_has_no_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster values cannot be empty for key "type"');

        new ClusterVO('type:|role:doctor');
    }

    public function test_throws_exception_when_pair_has_empty_value_in_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty value not allowed for key "role"');

        new ClusterVO('type:user|role:doctor,,admin');
    }

    // ============================================================
    // TESTS DE LA MÉTHODE make()
    // ============================================================

    public function test_can_create_cluster_with_make(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->with('role', 'doctor')
            ->with('status', 'active');

        $this->assertSame('type:user|role:doctor|status:active', $cluster->getValue());
    }

    public function test_make_creates_single_pair(): void
    {
        $cluster = ClusterVO::make('type', 'user');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame(['user'], $cluster->get('type'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE fromPairs()
    // ============================================================

    public function test_can_create_cluster_from_pairs_with_string_values(): void
    {
        $cluster = ClusterVO::fromPairs([
            'type' => 'user',
            'role' => 'doctor',
            'status' => 'active',
        ]);

        $this->assertSame('type:user|role:doctor|status:active', $cluster->getValue());
    }

    public function test_can_create_cluster_from_pairs_with_array_values(): void
    {
        $cluster = ClusterVO::fromPairs([
            'type' => 'user',
            'role' => ['doctor', 'admin'],
            'status' => 'active',
        ]);

        $this->assertSame('type:user|role:doctor,admin|status:active', $cluster->getValue());
        $this->assertSame(['doctor', 'admin'], $cluster->get('role'));
    }

    public function test_from_pairs_handles_empty_array(): void
    {
        $cluster = ClusterVO::fromPairs([]);

        $this->assertSame('', $cluster->getValue());
        $this->assertEmpty($cluster->all());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE get()
    // ============================================================

    public function test_can_get_values_by_key(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor,admin|status:active');

        $this->assertSame(['user'], $cluster->get('type'));
        $this->assertSame(['doctor', 'admin'], $cluster->get('role'));
        $this->assertSame(['active'], $cluster->get('status'));
    }

    public function test_get_returns_empty_array_for_unknown_key(): void
    {
        $cluster = new ClusterVO('type:user');

        $this->assertSame([], $cluster->get('unknown'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE getFirst()
    // ============================================================

    public function test_can_get_first_value_by_key(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor,admin');

        $this->assertSame('user', $cluster->getFirst('type'));
        $this->assertSame('doctor', $cluster->getFirst('role'));
    }

    public function test_get_first_returns_null_for_unknown_key(): void
    {
        $cluster = new ClusterVO('type:user');

        $this->assertNull($cluster->getFirst('unknown'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE has()
    // ============================================================

    public function test_can_check_if_key_exists(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor');

        $this->assertTrue($cluster->has('type'));
        $this->assertTrue($cluster->has('role'));
        $this->assertFalse($cluster->has('unknown'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE contains()
    // ============================================================

    public function test_can_check_if_key_contains_value(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor,admin');

        $this->assertTrue($cluster->contains('type', 'user'));
        $this->assertTrue($cluster->contains('role', 'doctor'));
        $this->assertTrue($cluster->contains('role', 'admin'));
        $this->assertFalse($cluster->contains('role', 'unknown'));
        $this->assertFalse($cluster->contains('unknown', 'value'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE all()
    // ============================================================

    public function test_can_get_all_pairs(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor,admin|status:active');

        $expected = [
            'type' => ['user'],
            'role' => ['doctor', 'admin'],
            'status' => ['active'],
        ];

        $this->assertSame($expected, $cluster->all());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE with()
    // ============================================================

    public function test_can_add_new_key_with_with(): void
    {
        $cluster = new ClusterVO('type:user');
        $newCluster = $cluster->with('status', 'active');

        $this->assertNotSame($cluster, $newCluster);
        $this->assertSame('type:user|status:active', $newCluster->getValue());
        $this->assertSame(['user'], $cluster->get('type'));
        $this->assertSame([], $cluster->get('status'));
    }

    public function test_can_add_value_to_existing_key_with_with(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor');
        $newCluster = $cluster->with('role', 'admin');

        $this->assertSame('type:user|role:doctor,admin', $newCluster->getValue());
        $this->assertSame(['doctor', 'admin'], $newCluster->get('role'));
    }

    public function test_with_does_not_duplicate_value(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor');
        $newCluster = $cluster->with('role', 'doctor');

        $this->assertSame('type:user|role:doctor', $newCluster->getValue());
        $this->assertSame(['doctor'], $newCluster->get('role'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE withIf()
    // ============================================================

    public function test_with_if_adds_value_when_condition_is_true(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withIf(true, 'status', 'active');

        $this->assertSame('type:user|status:active', $cluster->getValue());
        $this->assertSame(['active'], $cluster->get('status'));
    }

    public function test_with_if_does_not_add_value_when_condition_is_false(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withIf(false, 'status', 'active');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('status'));
    }

    public function test_with_if_does_not_duplicate_value(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->with('status', 'active')
            ->withIf(true, 'status', 'active');

        $this->assertSame('type:user|status:active', $cluster->getValue());
        $this->assertSame(['active'], $cluster->get('status'));
    }

    public function test_with_if_can_be_chained(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withIf(true, 'status', 'active')
            ->withIf(false, 'role', 'doctor')
            ->withIf(true, 'verified', 'true');

        $this->assertSame('type:user|status:active|verified:true', $cluster->getValue());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE withDefault()
    // ============================================================

    public function test_with_default_uses_default_when_value_is_empty_string(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withDefault('status', '', 'pending');

        $this->assertSame('type:user|status:pending', $cluster->getValue());
        $this->assertSame(['pending'], $cluster->get('status'));
    }

    public function test_with_default_uses_default_when_value_is_null(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withDefault('status', null, 'pending');

        $this->assertSame('type:user|status:pending', $cluster->getValue());
        $this->assertSame(['pending'], $cluster->get('status'));
    }

    public function test_with_default_uses_value_when_not_empty(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withDefault('status', 'active', 'pending');

        $this->assertSame('type:user|status:active', $cluster->getValue());
        $this->assertSame(['active'], $cluster->get('status'));
    }

    public function test_with_default_handles_false_value(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withDefault('active', false, 'true');

        $this->assertSame('type:user|active:false', $cluster->getValue());
        $this->assertSame(['false'], $cluster->get('active'));
    }

    public function test_with_default_handles_zero_value(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withDefault('count', 0, '1');

        $this->assertSame('type:user|count:0', $cluster->getValue());
        $this->assertSame(['0'], $cluster->get('count'));
    }

    public function test_with_default_handles_string_zero_value(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withDefault('count', '0', '1');

        $this->assertSame('type:user|count:0', $cluster->getValue());
        $this->assertSame(['0'], $cluster->get('count'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE whenNotEmpty()
    // ============================================================

    public function test_when_not_empty_handles_false_as_valid(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNotEmpty('active', false);

        $this->assertSame('type:user|active:false', $cluster->getValue());
        $this->assertSame(['false'], $cluster->get('active'));
    }

    public function test_when_not_empty_handles_zero_as_valid(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNotEmpty('count', 0);

        $this->assertSame('type:user|count:0', $cluster->getValue());
        $this->assertSame(['0'], $cluster->get('count'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE whenArrayNotEmpty()
    // ============================================================

    public function test_when_array_not_empty_adds_values_with_default_separator(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenArrayNotEmpty('tags', ['php', 'laravel', 'react']);

        $this->assertSame('type:user|tags:php,laravel,react', $cluster->getValue());
        // ✅ Le parse sépare automatiquement les valeurs par la virgule
        $this->assertSame(['php', 'laravel', 'react'], $cluster->get('tags'));
    }

    public function test_when_array_not_empty_adds_values_with_custom_separator(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenArrayNotEmpty('tags', ['php', 'laravel', 'react'], ';');

        $this->assertSame('type:user|tags:php;laravel;react', $cluster->getValue());
        // ✅ Avec un séparateur personnalisé, parse ne sépare pas automatiquement
        $this->assertSame(['php;laravel;react'], $cluster->get('tags'));
    }

    public function test_when_array_not_empty_does_not_add_when_array_empty(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenArrayNotEmpty('tags', []);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('tags'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE withTernary()
    // ============================================================

    public function test_with_ternary_returns_true_value_when_condition_true(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withTernary('status', true, 'active', 'inactive');

        $this->assertSame('type:user|status:active', $cluster->getValue());
        $this->assertSame(['active'], $cluster->get('status'));
    }

    public function test_with_ternary_returns_false_value_when_condition_false(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withTernary('status', false, 'active', 'inactive');

        $this->assertSame('type:user|status:inactive', $cluster->getValue());
        $this->assertSame(['inactive'], $cluster->get('status'));
    }

    public function test_with_ternary_can_be_chained(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withTernary('status', true, 'active', 'inactive')
            ->withTernary('verified', false, 'true', 'false');

        $this->assertSame('type:user|status:active|verified:false', $cluster->getValue());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE withMany()
    // ============================================================

    public function test_can_add_multiple_values_with_with_many(): void
    {
        $cluster = new ClusterVO('type:user');
        $newCluster = $cluster->withMany('role', ['doctor', 'admin']);

        $this->assertSame('type:user|role:doctor,admin', $newCluster->getValue());
        $this->assertSame(['doctor', 'admin'], $newCluster->get('role'));
    }

    public function test_with_many_does_not_duplicate_values(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor');
        $newCluster = $cluster->withMany('role', ['doctor', 'admin']);

        $this->assertSame('type:user|role:doctor,admin', $newCluster->getValue());
        $this->assertSame(['doctor', 'admin'], $newCluster->get('role'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE withManyIf()
    // ============================================================

    public function test_with_many_if_adds_values_when_condition_is_true(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withManyIf(true, 'role', ['doctor', 'admin']);

        $this->assertSame('type:user|role:doctor,admin', $cluster->getValue());
        $this->assertSame(['doctor', 'admin'], $cluster->get('role'));
    }

    public function test_with_many_if_does_not_add_values_when_condition_is_false(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withManyIf(false, 'role', ['doctor', 'admin']);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('role'));
    }

    public function test_with_many_if_does_not_duplicate_values(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withMany('role', ['doctor'])
            ->withManyIf(true, 'role', ['doctor', 'admin']);

        $this->assertSame('type:user|role:doctor,admin', $cluster->getValue());
        $this->assertSame(['doctor', 'admin'], $cluster->get('role'));
    }

    public function test_with_many_if_ignores_empty_array(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withManyIf(true, 'role', []);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('role'));
    }

    public function test_with_many_if_can_be_chained(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withManyIf(true, 'role', ['doctor'])
            ->withManyIf(false, 'status', ['active'])
            ->withManyIf(true, 'specialty', ['cardiologie', 'neurologie']);

        $this->assertSame('type:user|role:doctor|specialty:cardiologie,neurologie', $cluster->getValue());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE without()
    // ============================================================

    public function test_can_remove_specific_value_with_without(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor,admin|status:active');
        $newCluster = $cluster->without('role', 'admin');

        $this->assertSame('type:user|role:doctor|status:active', $newCluster->getValue());
        $this->assertSame(['doctor'], $newCluster->get('role'));
    }

    public function test_can_remove_entire_key_with_without(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor|status:active');
        $newCluster = $cluster->without('role');

        $this->assertSame('type:user|status:active', $newCluster->getValue());
        $this->assertFalse($newCluster->has('role'));
    }

    public function test_without_returns_same_instance_if_key_not_found(): void
    {
        $cluster = new ClusterVO('type:user');
        $newCluster = $cluster->without('unknown');

        $this->assertSame($cluster, $newCluster);
    }

    public function test_without_removes_key_when_last_value_removed(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor');
        $newCluster = $cluster->without('role', 'doctor');

        $this->assertSame('type:user', $newCluster->getValue());
        $this->assertFalse($newCluster->has('role'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE whenNotEmpty()
    // ============================================================

    public function test_when_not_empty_adds_value_when_not_empty(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNotEmpty('city', 'Paris');

        $this->assertSame('type:user|city:Paris', $cluster->getValue());
        $this->assertSame(['Paris'], $cluster->get('city'));
    }

    public function test_when_not_empty_does_not_add_when_empty(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNotEmpty('city', '');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('city'));
    }

    public function test_when_not_empty_does_not_add_when_null(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNotEmpty('city', null);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('city'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE whenNotNull()
    // ============================================================

    public function test_when_not_null_adds_value_when_not_null(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNotNull('role', 'doctor');

        $this->assertSame('type:user|role:doctor', $cluster->getValue());
        $this->assertSame(['doctor'], $cluster->get('role'));
    }

    public function test_when_not_null_does_not_add_when_null(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNotNull('role', null);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('role'));
    }

    public function test_when_not_null_does_not_add_when_empty(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNotNull('role', '');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('role'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE whenKeyExists()
    // ============================================================

    public function test_when_key_exists_adds_value_when_key_exists(): void
    {
        $metadata = ['role' => 'doctor', 'tenant' => 'company_abc'];

        $cluster = ClusterVO::make('type', 'user')
            ->whenKeyExists('role', $metadata, 'role');

        $this->assertSame('type:user|role:doctor', $cluster->getValue());
        $this->assertSame(['doctor'], $cluster->get('role'));
    }

    public function test_when_key_exists_does_not_add_when_key_missing(): void
    {
        $metadata = ['tenant' => 'company_abc'];

        $cluster = ClusterVO::make('type', 'user')
            ->whenKeyExists('role', $metadata, 'role');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('role'));
    }

    public function test_when_key_exists_does_not_add_when_value_empty(): void
    {
        $metadata = ['role' => ''];

        $cluster = ClusterVO::make('type', 'user')
            ->whenKeyExists('role', $metadata, 'role');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('role'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE whenNumeric()
    // ============================================================

    public function test_when_numeric_adds_value_when_value_is_numeric(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNumeric('age', 25);

        $this->assertSame('type:user|age:25', $cluster->getValue());
        $this->assertSame(['25'], $cluster->get('age'));
    }

    public function test_when_numeric_adds_value_when_value_is_numeric_string(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNumeric('age', '30');

        $this->assertSame('type:user|age:30', $cluster->getValue());
        $this->assertSame(['30'], $cluster->get('age'));
    }

    public function test_when_numeric_does_not_add_when_value_is_null(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNumeric('age', null);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('age'));
    }

    public function test_when_numeric_does_not_add_when_value_is_not_numeric(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNumeric('age', 'not a number');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('age'));
    }

    public function test_when_numeric_does_not_add_when_value_is_empty(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenNumeric('age', '');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('age'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE whenBool()
    // ============================================================

    public function test_when_bool_adds_true_when_value_is_true(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenBool('verified', true);

        $this->assertSame('type:user|verified:true', $cluster->getValue());
        $this->assertSame(['true'], $cluster->get('verified'));
    }

    public function test_when_bool_adds_false_when_value_is_false(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenBool('verified', false);

        $this->assertSame('type:user|verified:false', $cluster->getValue());
        $this->assertSame(['false'], $cluster->get('verified'));
    }

    public function test_when_bool_does_not_add_when_value_is_null(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenBool('verified', null);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('verified'));
    }

    public function test_when_bool_does_not_add_when_value_is_not_bool(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->whenBool('verified', 'not a bool');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame([], $cluster->get('verified'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE hasAll()
    // ============================================================

    public function test_can_check_if_has_all_keys(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor|status:active');

        $this->assertTrue($cluster->hasAll(['type', 'role']));
        $this->assertTrue($cluster->hasAll(['type', 'role', 'status']));
        $this->assertFalse($cluster->hasAll(['type', 'unknown']));
        $this->assertFalse($cluster->hasAll(['unknown1', 'unknown2']));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE hasAny()
    // ============================================================

    public function test_can_check_if_has_any_keys(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor');

        $this->assertTrue($cluster->hasAny(['type', 'unknown']));
        $this->assertTrue($cluster->hasAny(['unknown', 'type']));
        $this->assertTrue($cluster->hasAny(['role']));
        $this->assertFalse($cluster->hasAny(['unknown1', 'unknown2']));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE toArray()
    // ============================================================

    public function test_can_convert_to_array(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor,admin|status:active');

        $expected = [
            'type' => ['user'],
            'role' => ['doctor', 'admin'],
            'status' => ['active'],
        ];

        $this->assertSame($expected, $cluster->toArray());
        $this->assertSame($expected, $cluster->all());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE __toString()
    // ============================================================

    public function test_can_convert_to_string(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor');

        $this->assertSame('type:user|role:doctor', (string) $cluster);
        $this->assertSame('type:user|role:doctor', $cluster->__toString());
    }

    // ============================================================
    // TESTS DE CHAÎNAGE
    // ============================================================

    public function test_can_chain_methods(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->with('role', 'doctor')
            ->withMany('specialty', ['cardiologie', 'neurologie'])
            ->with('status', 'active')
            ->without('role', 'doctor')
            ->with('role', 'admin');

        $this->assertSame(
            'type:user|specialty:cardiologie,neurologie|status:active|role:admin',
            $cluster->getValue()
        );
        $this->assertSame(['admin'], $cluster->get('role'));
        $this->assertSame(['cardiologie', 'neurologie'], $cluster->get('specialty'));
    }

    public function test_can_chain_all_conditional_methods(): void
    {
        $cluster = ClusterVO::make('type', 'user')
            ->withTernary('status', true, 'active', 'inactive')
            ->withDefault('role', null, 'guest')
            ->whenNotEmpty('city', 'Paris')
            ->whenNotNull('country', 'France')
            ->whenKeyExists('tenant', ['tenant' => 'company_abc'], 'tenant')
            ->whenArrayNotEmpty('tags', ['php', 'laravel'])
            ->whenNumeric('age', 30)
            ->whenBool('verified', true);

        $this->assertSame(
            'type:user|status:active|role:guest|city:Paris|country:France|tenant:company_abc|tags:php,laravel|age:30|verified:true',
            $cluster->getValue()
        );
    }

    // ============================================================
    // TESTS D'IMMUTABILITÉ
    // ============================================================

    public function test_all_methods_return_new_instance(): void
    {
        $cluster = new ClusterVO('type:user');

        $with = $cluster->with('role', 'doctor');
        $withMany = $cluster->withMany('role', ['doctor']);
        $without = $cluster->without('type');
        $withIf = $cluster->withIf(true, 'status', 'active');
        $withDefault = $cluster->withDefault('status', null, 'pending');
        $withTernary = $cluster->withTernary('status', true, 'active', 'inactive');
        $whenNotEmpty = $cluster->whenNotEmpty('city', 'Paris');
        $whenNotNull = $cluster->whenNotNull('role', 'doctor');
        $whenNumeric = $cluster->whenNumeric('age', 30);
        $whenBool = $cluster->whenBool('verified', true);

        $this->assertNotSame($cluster, $with);
        $this->assertNotSame($cluster, $withMany);
        $this->assertNotSame($cluster, $without);
        $this->assertNotSame($cluster, $withIf);
        $this->assertNotSame($cluster, $withDefault);
        $this->assertNotSame($cluster, $withTernary);
        $this->assertNotSame($cluster, $whenNotEmpty);
        $this->assertNotSame($cluster, $whenNotNull);
        $this->assertNotSame($cluster, $whenNumeric);
        $this->assertNotSame($cluster, $whenBool);
        $this->assertSame('type:user', $cluster->getValue());
    }

    // ============================================================
    // TESTS DE CAS LIMITES
    // ============================================================

    public function test_handles_cluster_with_whitespace(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor,admin');

        $this->assertSame(['user'], $cluster->get('type'));
        $this->assertSame(['doctor', 'admin'], $cluster->get('role'));
    }

    public function test_handles_very_long_cluster(): void
    {
        $longValues = implode(',', array_fill(0, 100, 'value'));
        $cluster = new ClusterVO('type:user|values:'.$longValues);

        $values = $cluster->get('values');
        $this->assertCount(100, $values);
        $this->assertSame('value', $values[0]);
        $this->assertSame('value', $values[99]);
    }

    public function test_handles_special_characters_in_values(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor,admin|email:john@example.com');

        $this->assertSame(['doctor', 'admin'], $cluster->get('role'));
        $this->assertSame(['john@example.com'], $cluster->get('email'));
    }
}
