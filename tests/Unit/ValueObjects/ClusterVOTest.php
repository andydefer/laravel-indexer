<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Unit\ValueObjects;

use AndyDefer\LaravelIndexer\Tests\Fixtures\Enums\Language;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Enums\UserStatus;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Enums\UserType;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClusterVOTest extends TestCase
{
    // ============================================================
    // TESTS DE CONSTRUCTION - SANS MODE (STOCKAGE)
    // ============================================================

    public function test_can_create_cluster_without_mode_for_storage(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor|status:active');

        $this->assertSame('type:user|role:doctor|status:active', $cluster->getValue());
        $this->assertNull($cluster->getMode());
        $this->assertFalse($cluster->hasMode());
        $this->assertFalse($cluster->isAnd());
        $this->assertFalse($cluster->isOr());
    }

    public function test_can_create_cluster_with_single_pair_without_mode(): void
    {
        $cluster = ((new ClusterVO('type:user')));

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame('user', $cluster->get('type'));
        $this->assertNull($cluster->getMode());
    }

    // ============================================================
    // TESTS DE CONSTRUCTION - AVEC MODE (RECHERCHE)
    // ============================================================

    public function test_can_create_cluster_with_and_mode(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor|status:active@AND');

        $this->assertSame('type:user|role:doctor|status:active@AND', $cluster->getValue());
        $this->assertSame('AND', $cluster->getMode());
        $this->assertTrue($cluster->hasMode());
        $this->assertTrue($cluster->isAnd());
        $this->assertFalse($cluster->isOr());
    }

    public function test_can_create_cluster_with_or_mode(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor|status:active@OR');

        $this->assertSame('type:user|role:doctor|status:active@OR', $cluster->getValue());
        $this->assertSame('OR', $cluster->getMode());
        $this->assertTrue($cluster->hasMode());
        $this->assertTrue($cluster->isOr());
        $this->assertFalse($cluster->isAnd());
    }

    public function test_can_create_cluster_with_single_pair_with_mode(): void
    {
        $cluster = (new ClusterVO('type:user@AND'));

        $this->assertSame('type:user@AND', $cluster->getValue());
        $this->assertSame('user', $cluster->get('type'));
        $this->assertSame('AND', $cluster->getMode());
    }

    public function test_can_create_cluster_with_underscore_in_key(): void
    {
        $cluster = new ClusterVO('role_doctor:true|status:active@AND');

        $this->assertSame('role_doctor:true|status:active@AND', $cluster->getValue());
        $this->assertSame('true', $cluster->get('role_doctor'));
        $this->assertSame('active', $cluster->get('status'));
        $this->assertSame('AND', $cluster->getMode());
    }

    public function test_throws_exception_when_cluster_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster value cannot be empty');

        new ClusterVO('');
    }

    public function test_throws_exception_when_cluster_has_invalid_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mode. Expected "AND", "OR" or "NOT", got "INVALID"');

        new ClusterVO('type:user|role:doctor@INVALID');
    }

    public function test_throws_exception_when_cluster_has_no_pair(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid pair format. Expected "key:value", got "invalid"');

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
        $this->expectExceptionMessage('Cluster value cannot be empty for key "type"');

        new ClusterVO('type:|role:doctor');
    }

    public function test_throws_exception_when_cluster_part_empty_with_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster cannot be empty');

        new ClusterVO('@AND');
    }

    public function test_throws_exception_when_key_has_dash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster key "role-doctor" must contain only alphanumeric characters and underscore (a-z, A-Z, 0-9, _)');

        new ClusterVO('role-doctor:user');
    }

    public function test_throws_exception_when_key_has_dot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster key "role.doctor" must contain only alphanumeric characters and underscore (a-z, A-Z, 0-9, _)');

        new ClusterVO('role.doctor:user');
    }

    // ============================================================
    // TESTS DE LA MÉTHODE make()
    // ============================================================
    public function test_can_create_cluster_with_make_and_mode(): void
    {
        $cluster = ClusterVO::make('type', 'user', 'AND');

        $this->assertSame('type:user@AND', $cluster->getValue());
        $this->assertSame('AND', $cluster->getMode());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE get()
    // ============================================================

    public function test_can_get_value_by_key(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor|status:active');

        $this->assertSame('user', $cluster->get('type'));
        $this->assertSame('doctor', $cluster->get('role'));
        $this->assertSame('active', $cluster->get('status'));
    }

    public function test_get_returns_null_for_unknown_key(): void
    {
        $cluster = ((new ClusterVO('type:user')));

        $this->assertNull($cluster->get('unknown'));
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
    // TESTS DE LA MÉTHODE all()
    // ============================================================

    public function test_can_get_all_pairs(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor|status:active');

        $expected = [
            'type' => 'user',
            'role' => 'doctor',
            'status' => 'active',
        ];

        $this->assertSame($expected, $cluster->all());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE getMode()
    // ============================================================

    public function test_can_get_mode(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor@AND');
        $this->assertSame('AND', $cluster->getMode());

        $cluster2 = new ClusterVO('type:user|role:doctor@OR');
        $this->assertSame('OR', $cluster2->getMode());

        $cluster3 = new ClusterVO('type:user|role:doctor');
        $this->assertNull($cluster3->getMode());
    }

    public function test_has_mode_returns_correct_value(): void
    {
        $cluster = (new ClusterVO('type:user@AND'));
        $this->assertTrue($cluster->hasMode());

        $cluster2 = ((new ClusterVO('type:user')));
        $this->assertFalse($cluster2->hasMode());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE getClusterPart()
    // ============================================================

    public function test_can_get_cluster_part_without_mode(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor@AND');
        $this->assertSame('type:user|role:doctor', $cluster->getClusterPart());

        $cluster2 = new ClusterVO('type:user|role:doctor');
        $this->assertSame('type:user|role:doctor', $cluster2->getClusterPart());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE with()
    // ============================================================

    public function test_can_add_new_key_with_with(): void
    {
        $cluster = ((new ClusterVO('type:user')));
        $newCluster = $cluster->with('status', 'active');

        $this->assertNotSame($cluster, $newCluster);
        $this->assertSame('type:user|status:active', $newCluster->getValue());
        $this->assertSame('user', $cluster->get('type'));
        $this->assertNull($cluster->get('status'));
    }

    public function test_can_add_new_key_with_mode_preserved(): void
    {
        $cluster = (new ClusterVO('type:user@AND'));
        $newCluster = $cluster->with('status', 'active');

        $this->assertSame('type:user|status:active@AND', $newCluster->getValue());
        $this->assertSame('AND', $newCluster->getMode());
    }

    public function test_can_update_existing_key_with_with(): void
    {
        $cluster = ((new ClusterVO('type:user')));
        $newCluster = $cluster->with('type', 'admin');

        $this->assertSame('type:admin', $newCluster->getValue());
        $this->assertSame('admin', $newCluster->get('type'));
    }

    public function test_with_preserves_mode(): void
    {
        $cluster = new ClusterVO('type:user@OR');
        $newCluster = $cluster->with('status', 'active');

        $this->assertSame('type:user|status:active@OR', $newCluster->getValue());
        $this->assertSame('OR', $newCluster->getMode());
    }

    public function test_with_allows_underscore_in_key(): void
    {
        $cluster = ((new ClusterVO('type:user')));
        $newCluster = $cluster->with('role_doctor', 'true');

        $this->assertSame('type:user|role_doctor:true', $newCluster->getValue());
        $this->assertSame('true', $newCluster->get('role_doctor'));
    }

    public function test_with_throws_exception_for_dash_in_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster key "type-user" must contain only alphanumeric characters and underscore (a-z, A-Z, 0-9, _)');

        $cluster = ((new ClusterVO('type:user')));
        $cluster->with('type-user', 'value');
    }

    // ============================================================
    // TESTS DE LA MÉTHODE withIf()
    // ============================================================

    public function test_with_if_adds_value_when_condition_is_true(): void
    {
        $cluster = ((new ClusterVO('type:user')));
        $newCluster = $cluster->withIf(true, 'status', 'active');

        $this->assertSame('type:user|status:active', $newCluster->getValue());
        $this->assertSame('active', $newCluster->get('status'));
    }

    public function test_with_if_does_not_add_value_when_condition_is_false(): void
    {
        $cluster = ((new ClusterVO('type:user')));
        $newCluster = $cluster->withIf(false, 'status', 'active');

        $this->assertSame($cluster, $newCluster);
        $this->assertSame('type:user', $newCluster->getValue());
        $this->assertNull($newCluster->get('status'));
    }

    public function test_with_if_can_be_chained(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->withIf(true, 'status', 'active')
            ->withIf(false, 'role', 'doctor')
            ->withIf(true, 'verified', 'true');

        $this->assertSame('type:user|status:active|verified:true', $cluster->getValue());
    }

    public function test_with_if_allows_underscore(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->withIf(true, 'role_doctor', 'true');

        $this->assertSame('type:user|role_doctor:true', $cluster->getValue());
        $this->assertSame('true', $cluster->get('role_doctor'));
    }

    // ============================================================
    // TESTS DE LA MÉTHODE without()
    // ============================================================

    public function test_can_remove_key_with_without(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor');
        $newCluster = $cluster->without('role');

        $this->assertSame('type:user', $newCluster->getValue());
        $this->assertFalse($newCluster->has('role'));
    }

    public function test_without_returns_same_instance_if_key_not_found(): void
    {
        $cluster = ((new ClusterVO('type:user')));
        $newCluster = $cluster->without('unknown');

        $this->assertSame($cluster, $newCluster);
    }

    public function test_without_keeps_mode_when_removing_key(): void
    {
        $cluster = new ClusterVO('type:user|status:active@OR');
        $newCluster = $cluster->without('status');

        $this->assertSame('type:user@OR', $newCluster->getValue());
        $this->assertSame('OR', $newCluster->getMode());
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
    // TESTS DE LA MÉTHODE withMode()
    // ============================================================

    public function test_can_add_mode_with_with_mode(): void
    {
        $cluster = ((new ClusterVO('type:user')));
        $newCluster = $cluster->withMode('AND');

        $this->assertSame('type:user@AND', $newCluster->getValue());
        $this->assertSame('AND', $newCluster->getMode());
    }

    public function test_can_change_mode_with_with_mode(): void
    {
        $cluster = (new ClusterVO('type:user@AND'));
        $newCluster = $cluster->withMode('OR');

        $this->assertSame('type:user@OR', $newCluster->getValue());
        $this->assertSame('OR', $newCluster->getMode());
    }

    public function test_with_mode_throws_exception_for_invalid_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mode. Expected "AND", "OR" or "NOT", got "INVALID"');

        $cluster = ((new ClusterVO('type:user')));
        $cluster->withMode('INVALID');
    }

    // ============================================================
    // TESTS DE LA MÉTHODE toAnd() ET toOr()
    // ============================================================

    public function test_can_convert_to_and(): void
    {
        $cluster = new ClusterVO('type:user@OR');
        $newCluster = $cluster->toAnd();

        $this->assertSame('type:user@AND', $newCluster->getValue());
        $this->assertSame('AND', $newCluster->getMode());
    }

    public function test_can_convert_to_or(): void
    {
        $cluster = (new ClusterVO('type:user@AND'));
        $newCluster = $cluster->toOr();

        $this->assertSame('type:user@OR', $newCluster->getValue());
        $this->assertSame('OR', $newCluster->getMode());
    }

    public function test_can_add_mode_to_cluster_without_mode(): void
    {
        $cluster = ((new ClusterVO('type:user')));
        $newCluster = $cluster->toAnd();

        $this->assertSame('type:user@AND', $newCluster->getValue());
        $this->assertSame('AND', $newCluster->getMode());
    }

    // ============================================================
    // TESTS DES MÉTHODES CONDITIONNELLES
    // ============================================================

    public function test_when_not_empty_adds_value_when_not_empty(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNotEmpty('city', 'New York');

        $this->assertSame('type:user|city:New York', $cluster->getValue());
        $this->assertSame('New York', $cluster->get('city'));
    }

    public function test_when_not_empty_does_not_add_when_empty(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNotEmpty('city', '');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertNull($cluster->get('city'));
    }

    public function test_when_not_empty_does_not_add_when_null(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNotEmpty('city', null);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertNull($cluster->get('city'));
    }

    public function test_when_not_empty_handles_false_as_valid(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNotEmpty('active', false);

        $this->assertSame('type:user|active:false', $cluster->getValue());
        $this->assertSame('false', $cluster->get('active'));
    }

    public function test_when_not_empty_handles_zero_as_valid(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNotEmpty('count', 0);

        $this->assertSame('type:user|count:0', $cluster->getValue());
        $this->assertSame('0', $cluster->get('count'));
    }

    public function test_when_not_empty_handles_string_zero_as_valid(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNotEmpty('count', '0');

        $this->assertSame('type:user|count:0', $cluster->getValue());
        $this->assertSame('0', $cluster->get('count'));
    }

    public function test_when_bool_adds_true_when_value_is_true(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenBool('verified', true);

        $this->assertSame('type:user|verified:true', $cluster->getValue());
        $this->assertSame('true', $cluster->get('verified'));
    }

    public function test_when_bool_adds_false_when_value_is_false(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenBool('verified', false);

        $this->assertSame('type:user|verified:false', $cluster->getValue());
        $this->assertSame('false', $cluster->get('verified'));
    }

    public function test_when_bool_does_not_add_when_value_is_null(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenBool('verified', null);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertNull($cluster->get('verified'));
    }

    public function test_when_bool_does_not_add_when_value_is_not_bool(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenBool('verified', 'not a bool');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertNull($cluster->get('verified'));
    }

    public function test_when_not_null_adds_value_when_not_null(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNotNull('role', 'doctor');

        $this->assertSame('type:user|role:doctor', $cluster->getValue());
        $this->assertSame('doctor', $cluster->get('role'));
    }

    public function test_when_not_null_does_not_add_when_null(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNotNull('role', null);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertNull($cluster->get('role'));
    }

    public function test_when_not_null_does_not_add_when_empty(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNotNull('role', '');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertNull($cluster->get('role'));
    }

    public function test_when_numeric_adds_value_when_value_is_numeric(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNumeric('age', 25);

        $this->assertSame('type:user|age:25', $cluster->getValue());
        $this->assertSame('25', $cluster->get('age'));
    }

    public function test_when_numeric_adds_value_when_value_is_numeric_string(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNumeric('age', '30');

        $this->assertSame('type:user|age:30', $cluster->getValue());
        $this->assertSame('30', $cluster->get('age'));
    }

    public function test_when_numeric_does_not_add_when_value_is_null(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNumeric('age', null);

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertNull($cluster->get('age'));
    }

    public function test_when_numeric_does_not_add_when_value_is_not_numeric(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNumeric('age', 'not a number');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertNull($cluster->get('age'));
    }

    public function test_when_numeric_does_not_add_when_value_is_empty(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNumeric('age', '');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertNull($cluster->get('age'));
    }

    // ============================================================
    // TESTS DE CHAÎNAGE
    // ============================================================

    public function test_can_chain_methods_without_mode(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->with('role', 'doctor')
            ->with('status', 'active')
            ->without('role')
            ->with('role', 'admin');

        $this->assertSame('type:user|status:active|role:admin', $cluster->getValue());
        $this->assertSame('admin', $cluster->get('role'));
        $this->assertNull($cluster->getMode());
    }

    public function test_can_chain_methods_with_mode(): void
    {
        $cluster = (new ClusterVO('type:user@AND'))
            ->with('role', 'doctor')
            ->with('status', 'active')
            ->without('role')
            ->with('role', 'admin');

        $this->assertSame('type:user|status:active|role:admin@AND', $cluster->getValue());
        $this->assertSame('admin', $cluster->get('role'));
        $this->assertSame('AND', $cluster->getMode());
    }

    public function test_can_chain_conditional_methods(): void
    {
        $cluster = (new ClusterVO('type:user@AND'))
            ->whenNotEmpty('city', 'New York')
            ->whenBool('verified', true)
            ->whenNumeric('age', 30)
            ->whenNotNull('country', 'France');

        $this->assertSame('type:user|city:New York|verified:true|age:30|country:France@AND', $cluster->getValue());
    }

    public function test_can_chain_with_mode_changes(): void
    {
        $cluster = (new ClusterVO('type:user@AND'))
            ->with('role', 'doctor')
            ->toOr()
            ->with('status', 'active');

        $this->assertSame('type:user|role:doctor|status:active@OR', $cluster->getValue());
        $this->assertSame('OR', $cluster->getMode());
    }

    // ============================================================
    // TESTS D'IMMUTABILITÉ
    // ============================================================

    public function test_all_methods_return_new_instance(): void
    {
        $cluster = ((new ClusterVO('type:user')));

        $with = $cluster->with('role', 'doctor');
        $withIf = $cluster->withIf(true, 'status', 'active');
        $whenNotEmpty = $cluster->whenNotEmpty('city', 'Paris');
        $whenBool = $cluster->whenBool('verified', true);
        $whenNumeric = $cluster->whenNumeric('age', 30);
        $whenNotNull = $cluster->whenNotNull('country', 'France');
        $toAnd = $cluster->toAnd();
        $withMode = $cluster->withMode('AND');

        $this->assertNotSame($cluster, $with);
        $this->assertNotSame($cluster, $withIf);
        $this->assertNotSame($cluster, $whenNotEmpty);
        $this->assertNotSame($cluster, $whenBool);
        $this->assertNotSame($cluster, $whenNumeric);
        $this->assertNotSame($cluster, $whenNotNull);
        $this->assertNotSame($cluster, $toAnd);
        $this->assertNotSame($cluster, $withMode);
        $this->assertSame('type:user', $cluster->getValue());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE toArray()
    // ============================================================

    public function test_can_convert_to_array(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor|status:active');

        $expected = [
            'type' => 'user',
            'role' => 'doctor',
            'status' => 'active',
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

        $cluster2 = new ClusterVO('type:user|role:doctor@AND');
        $this->assertSame('type:user|role:doctor@AND', (string) $cluster2);
    }

    // ============================================================
    // TESTS DE CAS LIMITES
    // ============================================================

    public function test_accepts_alphanumeric_keys_and_values(): void
    {
        $cluster = new ClusterVO('type1:user2|role3:doctor4|status5:active6');

        $this->assertSame('type1:user2|role3:doctor4|status5:active6', $cluster->getValue());
        $this->assertSame('user2', $cluster->get('type1'));
        $this->assertSame('doctor4', $cluster->get('role3'));
        $this->assertSame('active6', $cluster->get('status5'));
    }

    public function test_accepts_uppercase_keys_and_values(): void
    {
        $cluster = new ClusterVO('TYPE:USER|ROLE:DOCTOR');

        $this->assertSame('TYPE:USER|ROLE:DOCTOR', $cluster->getValue());
        $this->assertSame('USER', $cluster->get('TYPE'));
        $this->assertSame('DOCTOR', $cluster->get('ROLE'));
    }

    public function test_accepts_underscore_in_keys_and_values(): void
    {
        $cluster = new ClusterVO('role_doctor:true|user_type:admin|email:john_doe');

        $this->assertSame('role_doctor:true|user_type:admin|email:john_doe', $cluster->getValue());
        $this->assertSame('true', $cluster->get('role_doctor'));
        $this->assertSame('admin', $cluster->get('user_type'));
        $this->assertSame('john_doe', $cluster->get('email'));
    }

    public function test_throws_exception_for_dot_in_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster key "user.type" must contain only alphanumeric characters and underscore (a-z, A-Z, 0-9, _)');

        new ClusterVO('user.type:user');
    }

    public function test_throws_exception_for_dash_in_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster key "user-type" must contain only alphanumeric characters and underscore (a-z, A-Z, 0-9, _)');

        new ClusterVO('user-type:user');
    }

    public function test_accepts_special_characters_in_values(): void
    {
        // Les caractères autorisés dans les valeurs : espaces, points, tirets, underscores
        // MAIS PAS : @, :, | (réservés)
        $cluster = new ClusterVO('city:New York|email:john.doe|name:Jean-Pierre');

        $this->assertSame('city:New York|email:john.doe|name:Jean-Pierre', $cluster->getValue());
        $this->assertSame('New York', $cluster->get('city'));
        $this->assertSame('john.doe', $cluster->get('email'));
        $this->assertSame('Jean-Pierre', $cluster->get('name'));
    }

    public function test_with_allows_special_characters_in_value(): void
    {
        $cluster = (new ClusterVO('type:user'));
        $newCluster = $cluster->with('email', 'john.doe');

        $this->assertSame('type:user|email:john.doe', $newCluster->getValue());
        $this->assertSame('john.doe', $newCluster->get('email'));
    }

    public function test_when_not_empty_allows_special_characters_in_value(): void
    {
        $cluster = ((new ClusterVO('type:user')))
            ->whenNotEmpty('email', 'john.doe');

        $this->assertSame('type:user|email:john.doe', $cluster->getValue());
        $this->assertSame('john.doe', $cluster->get('email'));
    }

    public function test_can_create_cluster_with_make(): void
    {
        $cluster = ClusterVO::make('type', 'user', '');

        $this->assertSame('type:user', $cluster->getValue());
        $this->assertSame('user', $cluster->get('type'));
        $this->assertNull($cluster->getMode());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE withCases()
    // ============================================================

    public function test_with_cases_adds_pairs_for_each_value(): void
    {
        $languages = ['fr', 'en', 'lu', 'ln'];
        $cluster = (new ClusterVO('type:user'))
            ->withCases('lang_', $languages);

        $this->assertSame('type:user|lang_fr:true|lang_en:true|lang_lu:true|lang_ln:true', $cluster->getValue());
        $this->assertSame('true', $cluster->get('lang_fr'));
        $this->assertSame('true', $cluster->get('lang_en'));
        $this->assertSame('true', $cluster->get('lang_lu'));
        $this->assertSame('true', $cluster->get('lang_ln'));
    }

    public function test_with_cases_with_suffix(): void
    {
        $languages = ['fr', 'en'];
        $cluster = (new ClusterVO('type:user'))
            ->withCases('lang_', $languages, '_speaks');

        $this->assertSame('type:user|lang_fr_speaks:true|lang_en_speaks:true', $cluster->getValue());
        $this->assertSame('true', $cluster->get('lang_fr_speaks'));
        $this->assertSame('true', $cluster->get('lang_en_speaks'));
    }

    public function test_with_cases_preserves_mode(): void
    {
        $languages = ['fr', 'en'];
        $cluster = (new ClusterVO('type:user@AND'))
            ->withCases('lang_', $languages);

        $this->assertSame('type:user|lang_fr:true|lang_en:true@AND', $cluster->getValue());
        $this->assertSame('AND', $cluster->getMode());
    }

    public function test_with_cases_throws_exception_for_invalid_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster key "lang-fr" must contain only alphanumeric characters and underscore (a-z, A-Z, 0-9, _)');

        $languages = ['fr'];
        $cluster = (new ClusterVO('type:user'));
        $cluster->withCases('lang-', $languages);
    }

    public function test_with_cases_with_empty_array_does_nothing(): void
    {
        $cluster = (new ClusterVO('type:user'))
            ->withCases('lang_', []);

        $this->assertSame('type:user', $cluster->getValue());
    }

    // ============================================================
    // TESTS DE LA MÉTHODE withEnum()
    // ============================================================

    public function test_with_enum_adds_pairs_for_each_enum_case(): void
    {
        $cluster = (new ClusterVO('type:user'))
            ->withEnum('role_', UserType::class);

        $this->assertStringContainsString('role_patient:true', $cluster->getValue());
        $this->assertStringContainsString('role_doctor:true', $cluster->getValue());
        $this->assertStringContainsString('role_admin:true', $cluster->getValue());
        $this->assertStringContainsString('role_staff:true', $cluster->getValue());

        $this->assertSame('true', $cluster->get('role_patient'));
        $this->assertSame('true', $cluster->get('role_doctor'));
        $this->assertSame('true', $cluster->get('role_admin'));
        $this->assertSame('true', $cluster->get('role_staff'));
    }

    public function test_with_enum_with_suffix(): void
    {
        $cluster = (new ClusterVO('type:user'))
            ->withEnum('role_', UserType::class, '_enabled');

        $this->assertStringContainsString('role_patient_enabled:true', $cluster->getValue());
        $this->assertStringContainsString('role_doctor_enabled:true', $cluster->getValue());
        $this->assertStringContainsString('role_admin_enabled:true', $cluster->getValue());
        $this->assertStringContainsString('role_staff_enabled:true', $cluster->getValue());

        $this->assertSame('true', $cluster->get('role_patient_enabled'));
        $this->assertSame('true', $cluster->get('role_doctor_enabled'));
    }

    public function test_with_enum_preserves_mode(): void
    {
        $cluster = (new ClusterVO('type:user@AND'))
            ->withEnum('role_', UserType::class);

        $this->assertStringContainsString('@AND', $cluster->getValue());
        $this->assertSame('AND', $cluster->getMode());
    }

    public function test_with_enum_throws_exception_for_invalid_enum_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Enum class "Invalid\Enum\Class" does not exist');

        $cluster = (new ClusterVO('type:user'));
        $cluster->withEnum('role_', 'Invalid\Enum\Class');
    }

    public function test_with_enum_throws_exception_for_invalid_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster key "role-patient" must contain only alphanumeric characters and underscore (a-z, A-Z, 0-9, _)');

        $cluster = (new ClusterVO('type:user'));
        $cluster->withEnum('role-', UserType::class);
    }

    // ============================================================
    // TESTS DE LA MÉTHODE withEnumValues()
    // ============================================================

    public function test_with_enum_values_for_backed_enum(): void
    {
        $cluster = (new ClusterVO('type:user'))
            ->withEnumValues('status_', UserStatus::class);

        $this->assertStringContainsString('status_active:true', $cluster->getValue());
        $this->assertStringContainsString('status_inactive:true', $cluster->getValue());
        $this->assertStringContainsString('status_pending:true', $cluster->getValue());
        $this->assertStringContainsString('status_banned:true', $cluster->getValue());

        $this->assertSame('true', $cluster->get('status_active'));
        $this->assertSame('true', $cluster->get('status_inactive'));
    }

    public function test_with_enum_values_for_unit_enum_uses_name(): void
    {
        $cluster = (new ClusterVO('type:user'))
            ->withEnumValues('lang_', Language::class);

        $this->assertStringContainsString('lang_fr:true', $cluster->getValue());
        $this->assertStringContainsString('lang_en:true', $cluster->getValue());
        $this->assertStringContainsString('lang_lu:true', $cluster->getValue());
        $this->assertStringContainsString('lang_ln:true', $cluster->getValue());

        $this->assertSame('true', $cluster->get('lang_fr'));
        $this->assertSame('true', $cluster->get('lang_en'));
    }

    public function test_with_enum_values_with_suffix(): void
    {
        $cluster = (new ClusterVO('type:user'))
            ->withEnumValues('status_', UserStatus::class, '_flag');

        $this->assertStringContainsString('status_active_flag:true', $cluster->getValue());
        $this->assertStringContainsString('status_inactive_flag:true', $cluster->getValue());
        $this->assertStringContainsString('status_pending_flag:true', $cluster->getValue());
        $this->assertSame('true', $cluster->get('status_active_flag'));
    }

    public function test_with_enum_values_throws_exception_for_invalid_enum_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Enum class "Invalid\Enum\Class" does not exist');

        $cluster = (new ClusterVO('type:user'));
        $cluster->withEnumValues('status_', 'Invalid\Enum\Class');
    }

    public function test_with_enum_values_throws_exception_for_invalid_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster key "status-active" must contain only alphanumeric characters and underscore (a-z, A-Z, 0-9, _)');

        $cluster = (new ClusterVO('type:user'));
        $cluster->withEnumValues('status-', UserStatus::class);
    }
}
