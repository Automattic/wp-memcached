<?php

class Test_WP_Object_Cache extends WP_UnitTestCase {

	/**
	 * @var WP_Object_Cache
	 */
	private $object_cache;

	public function setUp(): void {
		parent::setUp();
		$host1 = getenv( 'MEMCACHED_HOST_1' );
		$host2 = getenv( 'MEMCACHED_HOST_2' );

		$host1 = $host1 ? "{$host1}" : 'localhost:11211';
		$host2 = $host2 ? "{$host2}" : 'localhost:11212';

		$GLOBALS['memcached_servers'] = array( $host1, $host2 );
		$GLOBALS['wp_object_cache'] = $this->object_cache = new WP_Object_Cache();
	}

	public function tearDown(): void {
		$this->object_cache->flush();
	}

	public function test_server_without_port() {
		$GLOBALS['memcached_servers'] = array( 'localhost', 'localhost:0' );
		new WP_Object_Cache(); // NOSONAR
		self::assertTrue( true );
	}

	// Tests for adding.

	public function test_add_returns_true_if_added_successfully(): void {
		// String.
		$added_string = $this->object_cache->add( 'foo1', 'data' );

		$this->assertTrue( $added_string );

		// Array.
		$added_arr = $this->object_cache->add( 'foo2', [ 'data' ] );

		$this->assertTrue( $added_arr );

		// Object.
		$data = new StdClass();
		$added_obj = $this->object_cache->add( 'foo3', $data );

		$this->assertTrue( $added_obj );
	}

	public function test_add_creates_local_cache_entry(): void {
		$added = $this->object_cache->add( 'foo', 'data' );
		$key = $this->object_cache->key( 'foo', 'default' );

		$this->assertNotEmpty( $this->object_cache->cache[ $key ] );
		$this->assertEquals( 'data', $this->object_cache->cache[ $key ]['value'] );
		$this->assertTrue( $this->object_cache->cache[ $key ]['found'] );
	}

	public function test_add_returns_false_if_entry_already_exists_in_memcache(): void {
		$added1 = $this->object_cache->add( 'foo', 'data' );
		$key = $this->object_cache->key( 'foo', 'default' );
		$this->object_cache->cache[ $key ] = [];
		$added2 = $this->object_cache->add( 'foo', 'data' );

		$this->assertFalse( $added2 );
	}

	public function test_add_returns_false_if_entry_already_exists_in_local_cache(): void {
		$added1 = $this->object_cache->add( 'foo', 'data' );
		$key = $this->object_cache->key( 'foo', 'default' );
		$this->assertNotEmpty( $this->object_cache->cache[ $key ] );

		$added2 = $this->object_cache->add( 'foo', 'data' );

		$this->assertFalse( $added2 );
	}

	public function test_add_uses_local_cache_when_using_non_persistent_groups(): void {
		$group = 'do-not-persist-me';
		$this->object_cache->add_non_persistent_groups( [ $group ] );
		$added = $this->object_cache->add( 'foo', 'data', $group );

		$this->assertTrue( $added );

		$expected_cache = [
			'value' => 'data',
			'found' => false,
		];

		$key = $this->object_cache->key( 'foo', $group );
		$this->assertEquals( $expected_cache , $this->object_cache->cache[ $key ] );
	}

	public function test_add_local_cache_is_unset_on_memcache_failure(): void {
		$this->markTestSkipped( 'Unable to test without refactoring to inject a Memcache instance.' );
	}

	// Tests for adding global groups.

	public function test_add_global_groups_converts_to_array(): void {
		$group = 'test-group';

		$this->object_cache->add_global_groups( $group );
		$this->assertContains( $group, $this->object_cache->global_groups );
	}

	public function test_add_global_groups_add_a_global_group_from_array(): void {
		$groups = [
			'group-1',
			'group-2',
		];

		$this->object_cache->add_global_groups( $groups );
		foreach ( $groups as $group ) {
			$this->assertContains( $group, $this->object_cache->global_groups );
		}
	}

	public function test_add_global_groups_does_not_allow_duplicate_groups(): void {
		$groups = [
			'group-1',
			'group-1',
		];

		$this->object_cache->add_global_groups( $groups );
		$this->assertCount( 1, array_keys( $this->object_cache->global_groups, 'group-1' ) );
	}

	// Tests for adding non persistent groups.

	public function test_add_non_persistent_groups_converts_to_array(): void {
		$group = 'test-group';

		$this->object_cache->add_non_persistent_groups( $group );
		$this->assertContains( $group, $this->object_cache->no_mc_groups );
	}

	public function test_add_non_persistent_groups_add_a_global_group_from_array(): void {
		$groups = [
			'group-1',
			'group-2',
		];

		$this->object_cache->add_non_persistent_groups( $groups );
		foreach ( $groups as $group ) {
			$this->assertContains( $group, $this->object_cache->no_mc_groups );
		}
	}

	public function test_add_non_persistent_groups_does_not_allow_duplicate_groups(): void {
		$groups = [
			'group-1',
			'group-1',
		];

		$this->object_cache->add_non_persistent_groups( $groups );
		$this->assertCount( 1, array_keys( $this->object_cache->no_mc_groups, 'group-1' ) );
	}

	// Tests for increment.

	public function test_incr_increments_a_numeric_value(): void {
		$this->object_cache->add( 'foo1', 1 );
		$incremented = $this->object_cache->incr( 'foo1', 1 );
		$this->assertEquals( 2, $incremented );

		$this->object_cache->add( 'foo2', 1 );
		$incremented = $this->object_cache->incr( 'foo2', 5 );
		$this->assertEquals( 6, $incremented );
	}

	public function test_incr_returns_false_if_item_does_not_exist(): void {
		$incremented = $this->object_cache->incr( 'foo1', 1 );
		$this->assertFalse( $incremented );
	}

	public function test_incr_fails_if_non_numeric_value_is_passed(): void {
		$this->object_cache->add( 'foo2', 1 );
		if ( PHP_MAJOR_VERSION === 8 ) {
			$this->expectException( TypeError::class );
		} else {
			$this->expectWarning();
		}
	
		$this->object_cache->incr( 'foo2', 'non-numeric' );
	}

	public function test_incr_fails_if_existing_item_is_non_numeric(): void {
		$this->object_cache->add( 'foo2', 'non-numeric' );
		$this->expectNotice();
		$this->object_cache->incr( 'foo2', 1 );
	}

	public function test_incr_can_be_performed_per_group(): void {
		$this->object_cache->add( 'foo1', 1, 'group1' );
		$incremented1 = $this->object_cache->incr( 'foo1', 2 );
		$this->assertFalse( $incremented1 );

		$incremented2 = $this->object_cache->incr( 'foo1', 1, 'group1' );
		$this->assertEquals( 2, $incremented2 );
	}

	// Tests for decrement.

	public function test_decr_decrements_a_numeric_value(): void {
		$this->object_cache->add( 'foo1', 1 );
		$decremented = $this->object_cache->decr( 'foo1', 1 );
		$this->assertEquals( 0, $decremented );

		$this->object_cache->add( 'foo2', 10 );
		$decremented = $this->object_cache->decr( 'foo2', 5 );
		$this->assertEquals( 5, $decremented );
	}

	public function test_decr_returns_false_if_item_does_not_exist(): void {
		$decremented = $this->object_cache->decr( 'foo1', 1 );
		$this->assertFalse( $decremented );
	}

	public function test_decr_fails_if_non_numeric_value_is_passed(): void {
		$this->object_cache->add( 'foo2', 1 );
		if ( PHP_MAJOR_VERSION === 8 ) {
			$this->expectException( TypeError::class );
		} else {
			$this->expectWarning();
		}

		$this->object_cache->decr( 'foo2', 'non-numeric' );
	}

	public function test_decr_fails_if_existing_item_is_non_numeric(): void {
		$this->object_cache->add( 'foo2', 'non-numeric' );
		$this->expectNotice();
		$this->object_cache->decr( 'foo2', 1 );
	}

	public function test_decr_can_be_performed_per_group(): void {
		$this->object_cache->add( 'foo1', 2, 'group1' );
		$decremented1 = $this->object_cache->decr( 'foo1', 1 );
		$this->assertFalse( $decremented1 );

		$decremented2 = $this->object_cache->decr( 'foo1', 1, 'group1' );
		$this->assertEquals( 1, $decremented2 );
	}

	// Tests for close.

	public function test_close_calls_close_on_memcache_object(): void {
		$this->markTestSkipped( 'Unable to test without refactoring to inject a Memcache instance.' );
	}

	// Tests for delete.

	public function test_delete_returns_true_if_deleted_successfully(): void {
		// String.
		$this->object_cache->add( 'foo1', 'data' );
		$deleted_string = $this->object_cache->delete( 'foo1' );

		$this->assertTrue( $deleted_string );

		// Array.
		$this->object_cache->add( 'foo2', [ 'data' ] );
		$deleted_arr = $this->object_cache->delete( 'foo2' );

		$this->assertTrue( $deleted_arr );

		// Object.
		$data = new StdClass();
		$this->object_cache->add( 'foo3', $data );
		$deleted_obj = $this->object_cache->delete( 'foo3' );

		$this->assertTrue( $deleted_obj );
	}

	public function test_delete_removes_local_cache_entry(): void {
		$added = $this->object_cache->add( 'foo', 'data' );
		$key = $this->object_cache->key( 'foo', 'default' );

		$this->assertNotEmpty( $this->object_cache->cache[ $key ] );
		$this->assertEquals( 'data', $this->object_cache->cache[ $key ]['value'] );
		$this->assertTrue( $this->object_cache->cache[ $key ]['found'] );

		$deleted = $this->object_cache->delete( 'foo' );

		$this->assertArrayNotHasKey( $key, $this->object_cache->cache );
	}

	public function test_delete_returns_false_if_no_entry_exists_in_memcache(): void {
		$deleted = $this->object_cache->delete( 'foo' );

		$this->assertFalse( $deleted );
	}

	public function test_delete_deletes_local_cache_and_returns_true_when_using_non_persistent_groups(): void {
		$group = 'do-not-persist-me';
		$this->object_cache->add_non_persistent_groups( [ $group ] );
		$added = $this->object_cache->add( 'foo', 'data', $group );

		$expected_cache = [
			'value' => 'data',
			'found' => false,
		];

		$key = $this->object_cache->key( 'foo', $group );
		$this->assertEquals( $expected_cache , $this->object_cache->cache[ $key ] );

		$deleted  = $this->object_cache->delete( 'foo', $group );
		$this->assertArrayNotHasKey( $key, $this->object_cache->cache );
		$this->assertTrue( $deleted );
	}

	public function test_delete_local_cache_is_unset_on_memcache_failure(): void {
		$this->markTestSkipped( 'Unable to test without refactoring to inject a Memcache instance.' );
	}

	// Tests for flush.

	public function test_flush_clears_local_cache(): void {
		$this->object_cache->add( 'foo', 'data' );
		$key = $this->object_cache->key( 'foo', 'default' );
		$this->assertNotEmpty( $this->object_cache->cache[ $key ] );

		$this->object_cache->flush();

		// Local cache won't be completely empty because it stores updated flush number in local cache.
		$this->assertArrayNotHasKey( $key, $this->object_cache->cache );
	}

	public function test_flush_rotates_site_keys(): void {
		$this->object_cache->add( 'foo', 'data' );

		$existing_flush_number = $this->object_cache->flush_number[ $this->object_cache->blog_prefix ];

		$this->object_cache->flush();

		$new_flush_number = $this->object_cache->flush_number[ $this->object_cache->blog_prefix ];

		$this->assertNotEmpty( $new_flush_number );
		$this->assertNotEquals( $existing_flush_number, $new_flush_number );
	}

	public function test_flush_rotates_global_keys_if_main_site(): void {
		$this->object_cache->add( 'foo', 'data' );

		$existing_flush_number = $this->object_cache->global_flush_number;

		$this->object_cache->flush();

		$new_flush_number = $this->object_cache->global_flush_number;

		$this->assertNotEmpty( $new_flush_number );
		$this->assertNotEquals( $existing_flush_number, $new_flush_number );
	}

	public function test_flush_number_upgrades_from_v3(): void {
		$this->object_cache->set( 'foo', 'data' );

		// Reset to v3 flush_number
		$flush_number = $this->object_cache->flush_number[ $this->object_cache->blog_prefix ];
		$key = $this->object_cache->key( $this->object_cache->flush_key, $this->object_cache->flush_group );
		foreach ( $this->object_cache->default_mcs as $mc ) {
			$deleted = $mc->delete( $key );
			$this->assertTrue( $deleted );
		}
		$key = $this->object_cache->key( $this->object_cache->flush_key, $this->object_cache->global_flush_group );
		foreach ( $this->object_cache->default_mcs as $mc ) {
			$deleted = $mc->delete( $key );
			$this->assertTrue( $deleted );
		}
		$this->object_cache->set( $this->object_cache->old_flush_key, $flush_number, $this->object_cache->flush_group );
		$this->object_cache->set( $this->object_cache->old_flush_key, $this->object_cache->global_flush_number, $this->object_cache->global_flush_group );
		$this->object_cache->cache = array();
		$this->object_cache->flush_number = array();
		$this->object_cache->global_flush_number = null;

		$value = $this->object_cache->get( 'foo' );
		$this->assertEquals( $value, 'data' );
	}

	public function test_flush_number_replication_is_repaired(): void {
		$this->object_cache->set( 'foo', 'data' );

		// Remove flush_number from first mc
		$key = $this->object_cache->key( $this->object_cache->flush_key, $this->object_cache->flush_group );
		$deleted = $this->object_cache->default_mcs[0]->delete( $key );

		$this->assertTrue( $deleted );

		// Verify flush_number exists in second mc
		$replica = $this->object_cache->default_mcs[1]->get( $key );

		$this->assertEquals( $this->object_cache->flush_number[ $this->object_cache->blog_prefix ], $replica );

		// Remove local copy of flush_number and reset operations log
		$this->object_cache->flush_number = array();
		$this->object_cache->group_ops = array();

		// Attempt to load flush_number from all mcs
		$value = $this->object_cache->get( 'foo' );

		$this->assertEquals( 'data', $value );

		// Count replication_repair operations
		$repairs = 0;
		foreach ( $this->object_cache->group_ops[ $this->object_cache->flush_group ] as $op ) {
			if ( $op[ 0 ] === 'set_flush_number' && $op[ 4 ] === 'replication_repair' ) {
				$repairs++;
			}
		}

		$this->assertEquals( 1, $repairs );
	}

	// Tests for get.

	public function test_get_returns_data(): void {
		// String.
		$this->object_cache->add( 'foo1', 'data' );

		$this->assertEquals( 'data', $this->object_cache->get( 'foo1' ) );

		// Array.
		$this->object_cache->add( 'foo2', [ 'data' ] );

		$this->assertEquals( [ 'data' ], $this->object_cache->get( 'foo2' ) );

		// Object.
		$data = new StdClass();
		$this->object_cache->add( 'foo3', $data );

		$this->assertEquals( $data, $this->object_cache->get( 'foo3' ) );
	}

	public function test_get_returns_false_if_value_does_not_exist(): void {
		$this->assertFalse( $this->object_cache->get( 'does-not-exist' ) );
	}

	public function test_get_returns_data_from_local_cache(): void {
		$this->object_cache->add( 'foo', 'data' );
		$key = $this->object_cache->key( 'foo', 'default' );

		$this->assertNotEmpty( $this->object_cache->cache[ $key ]);

		// Make a change direct in cache, to check the data is indeed coming from cache.
		$this->object_cache->cache[ $key ][ 'value' ] = 'data-updated';

		$value = $this->object_cache->get( 'foo' );

		$this->assertEquals( $this->object_cache->cache[ $key ][ 'value' ], 'data-updated' );
		$this->assertTrue( $this->object_cache->cache[ $key ][ 'found' ] );
	}

	public function test_get_returns_data_not_from_local_cache(): void {
		$this->object_cache->add( 'foo', 'data' );
		$key = $this->object_cache->key( 'foo', 'default' );

		$this->assertNotEmpty( $this->object_cache->cache[ $key ]);

		// Remove local cache to force it go to memcache.
		unset( $this->object_cache->cache[ $key ] );

		$value = $this->object_cache->get( 'foo' );

		$this->assertEquals( 'data', $value );
	}

	public function test_get_returns_data_for_a_group(): void {
		$this->object_cache->add( 'foo1', 'data', 'group1' );

		$this->assertFalse( $this->object_cache->get( 'foo1' ) );
		$this->assertEquals( 'data', $this->object_cache->get( 'foo1', 'group1' ) );
	}

	public function test_get_only_uses_local_cache_when_using_non_persistent_groups(): void {
		$group = 'do-not-persist-me';
		$this->object_cache->add_non_persistent_groups( [ $group ] );
		$this->object_cache->add( 'foo', 'data', $group );

		$this->assertEquals( 'data', $this->object_cache->get( 'foo', $group ) );

		$expected_cache = [
			'value' => 'data',
			'found' => false,
		];

		$key = $this->object_cache->key( 'foo', $group );
		$this->assertEquals( $expected_cache , $this->object_cache->cache[ $key ] );
	}

	public function test_get_returns_false_if_no_local_cache_when_using_non_persistent_groups(): void {
		$group = 'do-not-persist-me';
		$this->object_cache->add_non_persistent_groups( [ $group ] );
		$this->object_cache->add( 'foo', 'data', $group );
		$key = $this->object_cache->key( 'foo', $group );

		unset( $this->object_cache->cache[ $key ] );

		$this->assertFalse( $this->object_cache->get( 'foo', $group ) );
		$this->assertFalse( $this->object_cache->cache[ $key ][ 'value' ] );
		$this->assertFalse( $this->object_cache->cache[ $key ][ 'found' ] );
	}

	public function test_get_with_value_of_checkthedatabaseplease_will_always_return_false(): void {
		$this->object_cache->add( 'foo', 'checkthedatabaseplease' );

		$this->assertFalse( $this->object_cache->get( 'foo' ) );
		$this->assertFalse( $this->object_cache->get( 'foo' ) );
	}

	public function test_get_always_hits_memcache_if_force_set_to_true(): void {
		$this->object_cache->add( 'foo', 'data' );
		$key = $this->object_cache->key( 'foo', 'default' );

		// Make a change direct in cache, to check the data is indeed coming from cache.
		$this->object_cache->cache[ $key ][ 'value' ] = 'data-updated';

		$value = $this->object_cache->get( 'foo', 'default', true );

		$this->assertNotEquals( $this->object_cache->cache[ $key ][ 'value' ], 'data-updated' );
		$this->assertEquals( $this->object_cache->cache[ $key ][ 'value' ], $value );
	}

	public function test_get_sets_found_to_false_if_not_in_memcache(): void {
		$found = null;
		$this->object_cache->get( 'does-not-exist', 'default', false, $found );
		$this->assertFalse( $found );
	}

	public function test_get_sets_found_to_false_when_using_non_persistent_groups(): void {
		$found = null;
		$group = 'do-not-persist-me';
		$this->object_cache->add_non_persistent_groups( [ $group ] );
		$this->object_cache->add( 'foo', 'data', $group );

		$this->object_cache->get( 'foo', $group, false, $found );
		$this->assertFalse( $found );
	}

	public function test_get_with_value_of_checkthedatabaseplease_sets_found_to_false(): void {
		$found = null;
		$this->object_cache->add( 'foo', 'checkthedatabaseplease' );

		$this->object_cache->get( 'foo', 'default', false, $found );
		$this->assertFalse( $found );
	}

	public function test_get_sets_found_to_true_when_found_in_cache(): void {
		$found = null;
		$this->object_cache->add( 'foo', 'data' );

		$this->object_cache->get( 'foo', 'default', false, $found );
		$this->assertTrue( $found );
	}

	// Test for get_multi.

	public function test_get_multi_returns_array_of_values_from_memcache(): void {
		$groups = [
			'default',
			'another-group',
			'and-another-group',
		];

		// Add data for each group.
		foreach ( $groups as $group ) {
			$this->object_cache->add( 'foo', 'data', $group );
		}

		$values = $this->object_cache->get_multi(
			[
				'default' => [ 'foo' ],
				'another-group' => [ 'foo' ],
			]
		);

		$expected = [
			$this->object_cache->key( 'foo', 'default') => 'data',
			$this->object_cache->key( 'foo', 'another-group') => 'data',
		];

		$this->assertEquals( $expected, $values );

		// Make sure it doesn't include and-another-group as we didn't specify this.
		$this->assertNotContains( $this->object_cache->key( 'foo', 'and-another-group'), array_keys($values) );
	}

	public function test_get_multi_updates_cache_correctly(): void {
		$groups = [
			'default',
			'another-group',
			'and-another-group',
		];

		// Add data for each group.
		foreach ( $groups as $group ) {
			$this->object_cache->add( 'foo', 'data', $group );
		}

		$another_group_key = $this->object_cache->key( 'foo', 'another-group' );

		// Remove one cache entry.
		unset( $this->object_cache->cache[ $another_group_key ] );

		$this->assertArrayNotHasKey( $another_group_key, $this->object_cache->cache );

		// Get values from both groups.
		$values = $this->object_cache->get_multi(
			[
				'default' => [ 'foo' ],
				'another-group' => [ 'foo' ],
			]
		);

		// Ensure another-group cache entry is now present.
		$this->assertArrayHasKey( $another_group_key, $this->object_cache->cache );
		$this->assertEquals( 'data', $this->object_cache->cache[ $another_group_key ][ 'value' ] );
		$this->assertTrue( $this->object_cache->cache[ $another_group_key ][ 'found' ] );
	}

	public function test_get_multi_returns_data_from_local_cache(): void {
		$groups = [
			'default',
			'another-group',
			'and-another-group',
		];

		// Add data for each group.
		foreach ( $groups as $group ) {
			$this->object_cache->add( 'foo', 'data', $group );
		}

		$default_key = $this->object_cache->key( 'foo', 'default' );

		$this->assertNotEmpty( $this->object_cache->cache[ $default_key ]);

		// Make a change direct in cache, to check the data is indeed coming from cache.
		$this->object_cache->cache[ $default_key ][ 'value' ] = 'data-updated';

		// Get values from all groups.
		$values = $this->object_cache->get_multi(
			[
				'default' => [ 'foo' ],
				'another-group' => [ 'foo' ],
				'and-another-group' => [ 'foo' ],
			]
		);

		$expected = [
			$this->object_cache->key( 'foo', 'default') => 'data-updated',
			$this->object_cache->key( 'foo', 'another-group') => 'data',
			$this->object_cache->key( 'foo', 'and-another-group') => 'data',
		];

		$this->assertEquals( $expected, $values );
	}

	public function test_get_multi_returns_data_not_from_local_cache(): void {
		$groups = [
			'default',
			'another-group',
			'and-another-group',
		];

		// Add data for each group.
		foreach ( $groups as $group ) {
			$this->object_cache->add( 'foo', 'data', $group );
		}

		$default_key = $this->object_cache->key( 'foo', 'default' );

		$this->assertNotEmpty( $this->object_cache->cache[ $default_key ]);

		// Remove local cache to force it go to memcache.
		unset( $this->object_cache->cache[ $default_key ] );

		// Get values from all groups.
		$values = $this->object_cache->get_multi(
			[
				'default' => [ 'foo' ],
				'another-group' => [ 'foo' ],
				'and-another-group' => [ 'foo' ],
			]
		);

		$expected = [
			$this->object_cache->key( 'foo', 'default') => 'data',
			$this->object_cache->key( 'foo', 'another-group') => 'data',
			$this->object_cache->key( 'foo', 'and-another-group') => 'data',
		];

		$this->assertEquals( $expected, $values );
	}

	public function test_get_multi_only_uses_local_cache_when_using_non_persistent_groups(): void {
		$group = 'do-not-persist-me';
		$this->object_cache->add_non_persistent_groups( [ $group ] );

		$this->object_cache->add( 'foo', 'data', $group );

		$values = $this->object_cache->get_multi( [ $group => [ 'foo' ] ] );
		$expected = [ $this->object_cache->key( 'foo', $group ) => 'data' ];
		$this->assertEquals( $expected, $values );

		$expected_cache = [
			'value' => 'data',
			'found' => false,
		];

		$key = $this->object_cache->key( 'foo', $group );
		$this->assertEquals( $expected_cache , $this->object_cache->cache[ $key ] );
	}

	public function test_get_multi_returns_false_if_no_local_cache_when_using_non_persistent_groups(): void {
		$group = 'do-not-persist-me';
		$this->object_cache->add_non_persistent_groups( [ $group ] );
		$this->object_cache->add( 'foo', 'data', $group );
		$key = $this->object_cache->key( 'foo', $group );

		unset( $this->object_cache->cache[ $key ] );

		$values = $this->object_cache->get_multi( [ $group => [ 'foo' ] ] );
		$expected = [ $this->object_cache->key( 'foo', $group ) => false ];

		$this->assertEquals( $expected, $values );
		$this->assertFalse( $this->object_cache->cache[ $key ][ 'value' ] );
		$this->assertFalse( $this->object_cache->cache[ $key ][ 'found' ] );
	}

	// Tests for flush_prefix.

	public function test_flush_prefix_is_underscore_for_flush_number_groups(): void {
		$this->assertEquals( '_:', $this->object_cache->flush_prefix( $this->object_cache->flush_group ) );
		$this->assertEquals( '_:', $this->object_cache->flush_prefix( $this->object_cache->global_flush_group ) );
	}

	public function test_flush_prefix_sets_global_flush_number_for_global_groups(): void {
		$this->object_cache->add_global_groups( [ 'global-group' ] );

		$this->assertEmpty( $this->object_cache->global_flush_number );

		$global_flush_number = (int) $this->object_cache->get( $this->object_cache->flush_key, $this->object_cache->global_flush_group );
		$this->assertEquals( $global_flush_number . ':', $this->object_cache->flush_prefix( 'global-group' ) );
		$this->assertEquals( $global_flush_number, $this->object_cache->global_flush_number );

		// Ensure global flush number gets rotated if 0.
		$this->object_cache->global_flush_number = 0;
		$this->assertNotEquals( $global_flush_number . ':', $this->object_cache->flush_prefix( 'global-group' ) );
		$this->assertNotEquals( $global_flush_number, $this->object_cache->global_flush_number );
	}

	public function test_flush_prefix_sets_flush_number_for_non_global_groups(): void {
		$this->assertArrayNotHasKey( $this->object_cache->blog_prefix, $this->object_cache->flush_number );

		$flush_number = (int) $this->object_cache->get( $this->object_cache->flush_key, $this->object_cache->flush_group );
		$this->assertEquals( $flush_number . ':', $this->object_cache->flush_prefix( 'non-global-group' ) );
		$this->assertEquals( $flush_number, $this->object_cache->flush_number[ $this->object_cache->blog_prefix ] );

		// Ensure flush number gets rotated if 0.
		$this->object_cache->flush_number[ $this->object_cache->blog_prefix ] = 0;
		$this->assertNotEquals( $flush_number . ':', $this->object_cache->flush_prefix( 'non-global-group' ) );
		$this->assertNotEquals( $flush_number, $this->object_cache->flush_number[ $this->object_cache->blog_prefix ] );
	}

	// Tests for key.

	public function test_key_returns_cache_key_for_default_group_if_no_group_passed(): void {
		$this->assertStringContainsString( 'default:foo', $this->object_cache->key( 'foo', '') );
	}

	public function test_key_contains_global_prefix_when_using_global_group(): void {
		$this->object_cache->add_global_groups( [ 'global-group' ] );

		// Have to set global prefix here as without multi site it is set to the same value as blog prefix.
		$this->object_cache->global_prefix = 'global_prefix';
		$this->assertStringContainsString( $this->object_cache->global_prefix, $this->object_cache->key( 'foo', 'global-group') );
	}

	public function test_key_contains_blog_prefix_when_using_non_global_group(): void {
		// Have to set blog prefix here as without multi site it is set to the same value as global prefix.
		$this->object_cache->blog_prefix = 'blog_prefix';
		$this->assertStringContainsString( $this->object_cache->blog_prefix, $this->object_cache->key( 'foo', 'non-global-group') );
	}

	// Tests for replace.

	public function test_replace_update_value_in_memcache(): void {
		$this->object_cache->add( 'foo', 'data' );

		$this->assertEquals( 'data', $this->object_cache->get( 'foo' ) );

		// String
		$replaced = $this->object_cache->replace( 'foo', 'data-replaced' );

		$this->assertTrue( $replaced );
		$this->assertEquals( 'data-replaced', $this->object_cache->get( 'foo' ) );

		// Array
		$replaced = $this->object_cache->replace( 'foo', [ 'data-replaced' ] );

		$this->assertTrue( $replaced );
		$this->assertEquals( [ 'data-replaced' ], $this->object_cache->get( 'foo' ) );

		// Object
		$replace_obj = new StdClass();
		$replaced = $this->object_cache->replace( 'foo', $replace_obj );

		$this->assertTrue( $replaced );
		$this->assertEquals( $replace_obj, $this->object_cache->get( 'foo' ) );
	}

	public function test_replace_updates_local_cache(): void {
		$this->object_cache->add( 'foo', 'data' );
		$key = $this->object_cache->key( 'foo', 'default' );

		$this->assertEquals( 'data', $this->object_cache->cache[ $key ][ 'value' ] );
		$this->assertTrue( $this->object_cache->cache[ $key ][ 'found' ] );

		$this->object_cache->replace( 'foo', 'data-replaced' );

		$this->assertEquals( 'data-replaced', $this->object_cache->cache[ $key ][ 'value' ] );
		$this->assertTrue( $this->object_cache->cache[ $key ][ 'found' ] );
	}

	public function test_replace_can_be_performed_per_group(): void {
		$this->object_cache->add( 'foo1', 1, 'group1' );
		$replaced = $this->object_cache->replace( 'foo1', 2 );
		$this->assertFalse( $replaced );

		$replaced2 = $this->object_cache->replace( 'foo1', 2, 'group1' );
		$this->assertEquals( 2, $replaced2 );
	}

	// Tests for set.

	public function test_set_returns_true_if_added_successfully(): void {
		// String.
		$set_string = $this->object_cache->set( 'foo1', 'data' );

		$this->assertTrue( $set_string );

		// Array.
		$set_arr = $this->object_cache->set( 'foo2', [ 'data' ] );

		$this->assertTrue( $set_arr );

		// Object.
		$data = new StdClass();
		$set_obj = $this->object_cache->set( 'foo3', $data );

		$this->assertTrue( $set_obj );
	}

	public function test_set_returns_false_if_cached_value_of_checkthedatabaseplease(): void {
		$set = $this->object_cache->set( 'foo', 'checkthedatabaseplease' );

		// First attempt is fine as not cached yet.
		$this->assertTrue( $set );

		$set = $this->object_cache->set( 'foo', 'something-different' );

		// Subsequent sets will return false as cached we have checkdatabaseplease
		$this->assertFalse( $set );
	}

	public function test_set_caches_data_locally(): void {
		$set = $this->object_cache->set( 'foo', 'data' );

		$key = $this->object_cache->key( 'foo', 'default' );

		$expected = [
			'value' => 'data',
			'found' => true,
		];

		$this->assertEquals( $expected, $this->object_cache->cache[ $key ] );
	}

	public function test_set_returns_true_when_using_non_persistent_groups(): void {
		$group = 'do-not-persist-me';
		$this->object_cache->add_non_persistent_groups( [ $group ] );

		$set = $this->object_cache->set( 'foo', 'data', $group );

		$this->assertTrue( $set );
	}

	public function test_set_can_be_performed_per_group(): void {
		$this->object_cache->set( 'foo1', 'data1', 'group1' );
		$this->assertFalse( $this->object_cache->get( 'foo1' ) );

		$this->object_cache->set( 'foo1', 'data2', 'group2' );
		$this->assertEquals( 'data2', $this->object_cache->get( 'foo1', 'group2' ) );
	}

	public function test_set_updated_found_status_with_memcache_result(): void {
		// Need this to ensure cache is set witout going to memcache.
		$group = 'do-not-persist-me';
		$this->object_cache->add_non_persistent_groups( [ $group ] );

		$this->object_cache->set( 'foo', 'data', $group );
		$key = $this->object_cache->key( 'foo', $group );

		// Check it's false until we use memcache.
		$this->assertFalse( $this->object_cache->cache[ $key ][ 'found' ] );

		// Remove non-persistent group.
		$this->object_cache->no_mc_groups = [];

		// Re-set.
		$this->object_cache->set( 'foo', 'data', $group );

		// Cached found value should now be true.
		$this->assertTrue( $this->object_cache->cache[ $key ][ 'found' ] );
	}

	// Tests for switch_to_blog.

	public function test_switch_to_blog_sets_blog_prefix_depending_on_multi_site_status(): void {
		$this->markTestSkipped( 'Unable to test without refactoring to mock is_multisite() global function.' );
	}

	// Tests for salt_keys.

	public function test_key_salt_can_be_set(): void {
		$this->assertEmpty( $this->object_cache->key_salt );

		$this->object_cache->salt_keys( 'foo' );
		$this->assertEquals( 'foo:', $this->object_cache->key_salt );

		$this->object_cache->salt_keys( '' );
		$this->assertEmpty( $this->object_cache->key_salt );
	}

	/**
	 * @see https://github.com/Automattic/wp-memcached/issues/40
	 */
	public function test_found_gives_an_accurate_representation_of_whether_or_not_it_was_found_in_cache(): void {
		/**
		 * wp> $found = null;
		 * wp> wp_cache_get( 'foo', null, false, $found );
		 * bool(false)
		 * wp> $found;
		 * bool(false)
		 * wp> wp_cache_get( 'foo', null, false, $found );
		 * bool(false)
		 * wp> $found;
		 * bool(true)
		 */

		$found = null;
		$first_get = wp_cache_get( 'foo', null, false, $found );

		$this->assertFalse( $found );

		$second_get = wp_cache_get( 'foo', null, false, $found );

		$this->assertFalse( $found );
	}

	public function get_colorize_debug_line_data() {
		return array(
			array(
				// Command
				'get foo',
				// Expected color
				'green',
			),
			array(
				// Command
				'get_local foo',
				// Expected color
				'lightgreen',
			),
			array(
				// Command
				'get_multi foo',
				// Expected color
				'fuchsia',
			),
			array(
				// Command
				'set foo',
				// Expected color
				'purple',
			),
			array(
				// Command
				'set_local foo',
				// Expected color
				'orchid',
			),
			array(
				// Command
				'add foo',
				// Expected color
				'blue',
			),
			array(
				// Command
				'delete foo',
				// Expected color
				'red',
			),
			array(
				// Command
				'delete_local foo',
				// Expected color
				'tomato',
			),
			array(
				// Command
				'unknown foo',
				// Expected color
				'brown', // Default
			),
		);
	}

	/**
	 * @dataProvider get_colorize_debug_line_data
	 */
	public function test_colorize_debug_line( $command, $expected_color ) {
		$colorized = $this->object_cache->colorize_debug_line( $command );

		$this->assertStringContainsString( $expected_color, $colorized );
	}

	public function test_wp_cache_add_multiple() {
		$found = wp_cache_add_multiple(
			array(
				'foo1' => 'bar',
				'foo2' => 'bar',
				'foo3' => 'bar',
			),
			'group1'
		);

		$expected = array(
			'foo1' => true,
			'foo2' => true,
			'foo3' => true,
		);

		$this->assertSame( $expected, $found );
	}

	public function test_wp_cache_set_multiple() {
		$found = wp_cache_set_multiple(
			array(
				'foo1' => 'bar',
				'foo2' => 'bar',
				'foo3' => 'bar',
			),
			'group1'
		);

		$expected = array(
			'foo1' => true,
			'foo2' => true,
			'foo3' => true,
		);

		$this->assertSame( $expected, $found );
	}

	public function test_wp_cache_delete_multiple() {
		wp_cache_set( 'foo1', 'bar', 'group1' );
		wp_cache_set( 'foo2', 'bar', 'group1' );
		wp_cache_set( 'foo3', 'bar', 'group2' );

		$found = wp_cache_delete_multiple(
			array( 'foo1', 'foo2', 'foo3' ),
			'group1'
		);

		$expected = array(
			'foo1' => true,
			'foo2' => true,
			'foo3' => false,
		);

		$this->assertSame( $expected, $found );
	}
}
