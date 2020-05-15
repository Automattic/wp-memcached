<?php

/*
Plugin Name: Memcached
Description: Memcached backend for the WP Object Cache.
Version: 3.2.0
Plugin URI: http://wordpress.org/extend/plugins/memcached/
Author: Ryan Boren, Denis de Bernardy, Matt Martz, Andy Skelton

Install this file to wp-content/object-cache.php
*/

// Users with setups where multiple installs share a common wp-config.php or $table_prefix
// can use this to guarantee uniqueness for the keys generated by this object cache
if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
	define( 'WP_CACHE_KEY_SALT', '' );
}

function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	return $wp_object_cache->add( $key, $data, $group, $expire );
}

function wp_cache_incr( $key, $n = 1, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->incr( $key, $n, $group );
}

function wp_cache_decr( $key, $n = 1, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->decr( $key, $n, $group );
}

function wp_cache_close() {
	global $wp_object_cache;

	return $wp_object_cache->close();
}

function wp_cache_delete( $key, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->delete( $key, $group );
}

function wp_cache_flush() {
	global $wp_object_cache;

	return $wp_object_cache->flush();
}

function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	global $wp_object_cache;

	return $wp_object_cache->get( $key, $group, $force, $found );
}

/**
 * Retrieve multiple cache entries
 *
 * @param array $groups Array of arrays, of groups and keys to retrieve
 * @return mixed
 */
function wp_cache_get_multi( $groups ) {
	global $wp_object_cache;

	return $wp_object_cache->get_multi( $groups );
}

function wp_cache_init() {
	global $wp_object_cache;

	$wp_object_cache = new WP_Object_Cache();
}

function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	return $wp_object_cache->replace( $key, $data, $group, $expire );
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	if ( defined( 'WP_INSTALLING' ) == false ) {
		return $wp_object_cache->set( $key, $data, $group, $expire );
	} else {
		return $wp_object_cache->delete( $key, $group );
	}
}

function wp_cache_switch_to_blog( $blog_id ) {
	global $wp_object_cache;

	return $wp_object_cache->switch_to_blog( $blog_id );
}

function wp_cache_add_global_groups( $groups ) {
	global $wp_object_cache;

	$wp_object_cache->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {
	global $wp_object_cache;

	$wp_object_cache->add_non_persistent_groups( $groups );
}

class WP_Object_Cache {
	var $global_groups = array( 'WP_Object_Cache_global' );

	var $no_mc_groups = array();

	var $cache     = array();
	var $mc        = array();
	var $stats     = array();
	var $group_ops = array();

	var $flush_number        = array();
	var $global_flush_number = null;

	var $cache_enabled      = true;
	var $default_expiration = 0;
	var $max_expiration     = 2592000; // 30 days

	var $stats_callback = null;

	var $connection_errors = array();

	var $time_total = 0;
	var $size_total = 0;
	var $slow_op_microseconds = 0.005; // 5ms

	function add( $id, $data, $group = 'default', $expire = 0 ) {
		$key = $this->key( $id, $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		if ( in_array( $group, $this->no_mc_groups ) ) {
			$this->cache[ $key ] = [
				'value' => $data,
				'found' => false,
			];

			return true;
		} elseif ( isset( $this->cache[ $key ][ 'value' ] ) && false !== $this->cache[ $key ][ 'value' ] ) {
			return false;
		}

		$mc =& $this->get_mc( $group );

		$expire = intval( $expire );
		if ( 0 === $expire || $expire > $this->max_expiration ) {
			$expire = $this->default_expiration;
		}

		$size = $this->get_data_size( $data );
		$this->timer_start();
		$result = $mc->add( $key, $data, false, $expire );
		$elapsed = $this->timer_stop();

		$comment = '';
		if ( isset( $this->cache[ $key ] ) ) {
			$comment .= ' [lc already]';
		}
		if ( false === $result ) {
			$comment .= ' [mc already]';
		}

		$this->group_ops_stats( 'add', $key, $group, $size, $elapsed, $comment );

		if ( false !== $result ) {
			$this->cache[ $key ] = [
				'value' => $data,
				'found' => true,
			];
		} else if ( false === $result && true === isset( $this->cache[$key][ 'value' ] ) && false === $this->cache[$key][ 'value' ] ) {
			/*
			 * Here we unset local cache if remote add failed and local cache value is equal to `false` in order
			 * to update the local cache anytime we get a new information from remote server. This way, the next
			 * cache get will go to remote server and will fetch recent data.
			 */
			unset( $this->cache[$key] );
		}

		return $result;
	}

	function add_global_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->global_groups = array_merge( $this->global_groups, $groups );
		$this->global_groups = array_unique( $this->global_groups );
	}

	function add_non_persistent_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->no_mc_groups = array_merge( $this->no_mc_groups, $groups );
		$this->no_mc_groups = array_unique( $this->no_mc_groups );
	}

	function incr( $id, $n = 1, $group = 'default' ) {
		$key = $this->key( $id, $group );
		$mc =& $this->get_mc( $group );

		$incremented = $mc->increment( $key, $n );

		$this->cache[ $key ] = [
			'value' => $incremented,
			'found' => false !== $incremented,
		];

		return $this->cache[ $key ][ 'value' ];
	}

	function decr( $id, $n = 1, $group = 'default' ) {
		$key = $this->key( $id, $group );
		$mc =& $this->get_mc( $group );

		$decremented = $mc->decrement( $key, $n );
		$this->cache[ $key ] = [
			'value' => $decremented,
			'found' => false !== $decremented,
		];

		return $this->cache[ $key ][ 'value' ];
	}

	function close() {
		foreach ( $this->mc as $bucket => $mc ) {
			$mc->close();
		}
	}

	function delete( $id, $group = 'default' ) {
		$key = $this->key( $id, $group );

		if ( in_array( $group, $this->no_mc_groups ) ) {
			unset( $this->cache[ $key ] );

			return true;
		}

		$mc =& $this->get_mc( $group );

		$this->timer_start();
		$result = $mc->delete( $key );
		$elapsed = $this->timer_stop();

		$this->group_ops_stats( 'delete', $key, $group, null, $elapsed );

		if ( false !== $result ) {
			unset( $this->cache[ $key ] );
		}

		return $result;
	}

	function flush() {
		// Do not use the memcached flush method. It acts on an
		// entire memcached server, affecting all sites.
		// Flush is also unusable in some setups, e.g. twemproxy.
		// Instead, rotate the key prefix for the current site.
		// Global keys are rotated when flushing on the main site.
		$this->cache = array();

		$this->rotate_site_keys();

		if ( is_main_site() ) {
			$this->rotate_global_keys();
		}
	}

	function rotate_site_keys() {
		$this->add( 'flush_number', intval( microtime( true ) * 1e6 ), 'WP_Object_Cache' );

		$this->flush_number[ $this->blog_prefix ] = $this->incr( 'flush_number', 1, 'WP_Object_Cache' );
	}

	function rotate_global_keys() {
		$this->add( 'flush_number', intval( microtime( true ) * 1e6 ), 'WP_Object_Cache_global' );

		$this->global_flush_number = $this->incr( 'flush_number', 1, 'WP_Object_Cache_global' );
	}

	function get( $id, $group = 'default', $force = false, &$found = null ) {
		$key = $this->key( $id, $group );
		$mc =& $this->get_mc( $group );
		$found = true;

		if ( isset( $this->cache[ $key ] ) && ( ! $force || in_array( $group, $this->no_mc_groups ) ) ) {
			if ( isset( $this->cache[ $key ][ 'value' ] ) && is_object( $this->cache[ $key ][ 'value' ] ) ) {
				$value = clone $this->cache[ $key ][ 'value' ];
			} else {
				$value = $this->cache[ $key ][ 'value' ];
			}
			$found = $this->cache[ $key ][ 'found' ];

			$this->group_ops_stats( 'get_local', $key, $group, null, null, 'local' );
		} else if ( in_array( $group, $this->no_mc_groups ) ) {
			$this->cache[ $key ] = [
				'value' => $value = false,
				'found' => false,
			];

			$found = false;

			$this->group_ops_stats( 'get_local', $key, $group, null, null, 'not_in_local' );
		} else {
			$flags = false;
			$this->timer_start();
			$value = $mc->get( $key, $flags );
			$elapsed = $this->timer_stop();

			// Value will be unchanged if the key doesn't exist.
			if ( false === $flags ) {
				$found = false;
				$value = false;
			}

			$this->cache[ $key ] = [
				'value' => $value,
				'found' => $found,
			];

			if ( is_null( $value ) || $value === false ) {
				$this->group_ops_stats( 'get', $key, $group, null, $elapsed, 'not_in_memcache' );
			} else if ( 'checkthedatabaseplease' === $value ) {
				$this->group_ops_stats( 'get', $key, $group, null, $elapsed, 'checkthedatabaseplease' );
			} else {
				$size = $this->get_data_size( $value );
				$this->group_ops_stats( 'get', $key, $group, $size, $elapsed, 'memcache' );
			}
		}

		if ( 'checkthedatabaseplease' === $value ) {
			unset( $this->cache[ $key ] );

			$found = false;
			$value = false;
		}

		return $value;
	}

	function get_multi( $groups ) {
		/*
		format: $get['group-name'] = array( 'key1', 'key2' );
		*/
		$return = array();
		$return_cache = array(
			'value' => false,
			'found' => false,
		);

		foreach ( $groups as $group => $ids ) {
			$mc =& $this->get_mc( $group );
			$keys = array();
			$this->timer_start();

			foreach ( $ids as $id ) {
				$key = $this->key( $id, $group );
				$keys[] = $key;

				if ( isset( $this->cache[ $key ] ) ) {
					if ( is_object( $this->cache[ $key ][ 'value'] ) ) {
						$return[ $key ] = clone $this->cache[ $key ][ 'value'];
						$return_cache[ $key ] = [
							'value' => clone $this->cache[ $key ][ 'value'],
							'found' => $this->cache[ $key ][ 'found'],
						];
					} else {
						$return[ $key ] = $this->cache[ $key ][ 'value'];
						$return_cache[ $key ] = [
							'value' => $this->cache[ $key ][ 'value' ],
							'found' => $this->cache[ $key ][ 'found' ],
						];
					}

					continue;
				} else if ( in_array( $group, $this->no_mc_groups ) ) {
					$return[ $key ] = false;
					$return_cache[ $key ] = [
						'value' => false,
						'found' => false,
					];

					continue;
				} else {
					$fresh_get = $mc->get( $key );
					$return[ $key ] = $fresh_get;
					$return_cache[ $key ] = [
						'value' => $fresh_get,
						'found' => false !== $fresh_get,
					];
				}
			}

			$elapsed = $this->timer_stop();
			$this->group_ops_stats( 'get_multi', $keys, $group, null, $elapsed );
		}

		$this->cache = array_merge( $this->cache, $return_cache );

		return $return;
	}

	function flush_prefix( $group ) {
		if ( 'WP_Object_Cache' === $group || 'WP_Object_Cache_global' === $group ) {
			// Never flush the flush numbers.
			$number = '_';
		} elseif ( false !== array_search( $group, $this->global_groups ) ) {
			if ( ! isset( $this->global_flush_number ) ) {
				$this->global_flush_number = intval( $this->get( 'flush_number', 'WP_Object_Cache_global' ) );
			}

			if ( 0 === $this->global_flush_number ) {
				$this->rotate_global_keys();
			}

			$number = $this->global_flush_number;
		} else {
			if ( ! isset( $this->flush_number[ $this->blog_prefix ] ) ) {
				$this->flush_number[ $this->blog_prefix ] = intval( $this->get( 'flush_number', 'WP_Object_Cache' ) );
			}

			if ( 0 === $this->flush_number[ $this->blog_prefix ] ) {
				$this->rotate_site_keys();
			}

			$number = $this->flush_number[ $this->blog_prefix ];
		}

		return $number . ':';
	}

	function key( $key, $group ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		$prefix = $this->key_salt;

		$prefix .= $this->flush_prefix( $group );

		if ( false !== array_search( $group, $this->global_groups ) ) {
			$prefix .= $this->global_prefix;
		} else {
			$prefix .= $this->blog_prefix;
		}

		return preg_replace( '/\s+/', '', "$prefix:$group:$key" );
	}

	function replace( $id, $data, $group = 'default', $expire = 0 ) {
		$key = $this->key( $id, $group );
		$expire = intval( $expire );
		if ( 0 === $expire || $expire > $this->max_expiration ) {
			$expire = $this->default_expiration;
		}
		$mc =& $this->get_mc( $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$size = $this->get_data_size( $data );
		$this->timer_start();
		$result = $mc->replace( $key, $data, false, $expire );
		$elapsed = $this->timer_stop();
		$this->group_ops_stats( 'replace', $key, $group, $size, $elapsed );

		if ( false !== $result ) {
			$this->cache[ $key ] = [
				'value' => $data,
				'found' => true,
			];
		}

		return $result;
	}

	function set( $id, $data, $group = 'default', $expire = 0 ) {
		$key = $this->key( $id, $group );

		if ( isset( $this->cache[ $key ] ) && ( 'checkthedatabaseplease' === $this->cache[ $key ][ 'value' ] ) ) {
			return false;
		}

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$this->cache[ $key ] = [
			'value' => $data,
			'found' => false, // Set to false as not technically found in memcache at this point.
		];

		if ( in_array( $group, $this->no_mc_groups ) ) {
			$this->group_ops_stats( 'set_local', $key, $group, null, null );

			return true;
		}

		$expire = intval( $expire );
		if ( 0 === $expire || $expire > $this->max_expiration ) {
			$expire = $this->default_expiration;
		}

		$mc =& $this->get_mc( $group );

		$size = $this->get_data_size( $data );
		$this->timer_start();
		$result = $mc->set( $key, $data, false, $expire );
		$elapsed = $this->timer_stop();
		$this->group_ops_stats( 'set', $key, $group, $size, $elapsed );

		// Update the found cache value with the result of the set in memcache.
		$this->cache[ $key ][ 'found' ] = $result;

		return $result;
	}

	function switch_to_blog( $blog_id ) {
		global $table_prefix;

		$blog_id = (int) $blog_id;

		$this->blog_prefix = ( is_multisite() ? $blog_id : $table_prefix );
	}

	function colorize_debug_line( $line, $trailing_html = '' ) {
		$colors = array(
			'get' => 'green',
			'get_local' => 'lightgreen',
			'get_multi' => 'fuchsia',
			'set' => 'purple',
			'set_local' => 'orchid',
			'add' => 'blue',
			'delete' => 'red',
			'delete_local' => 'tomato',
			'slow-ops' => 'crimson',
		);

		$cmd = substr( $line, 0, strpos( $line, ' ' ) );

		// Start off with a neutral default color...
		$color_for_cmd = 'brown';
		// And if the cmd has a specific color, use that instead
		if ( isset( $colors[ $cmd ] ) ) {
			$color_for_cmd = $colors[ $cmd ];
		}

		$cmd2 = "<span style='color:" . esc_attr( $color_for_cmd ) . "; font-weight: bold;'>" . esc_html( $cmd ) . "</span>";

		return $cmd2 . esc_html( substr( $line, strlen( $cmd ) ) ) . "$trailing_html\n";
	}

	function js_toggle() {
		echo "
		<script>
		function memcachedToggleVisibility( id, hidePrefix ) {
			var element = document.getElementById( id );
			if ( ! element ) {
				return;
			}

			// Hide all element with `hidePrefix` if given. Used to display only one element at a time.
			if ( hidePrefix ) {
				var groupStats = document.querySelectorAll( '[id^=\"' + hidePrefix + '\"]' );
				groupStats.forEach.call(
					function ( element ) {
					    element.style.display = 'none';
					}
				);
			}

			// Toggle the one we clicked.
			if ( 'none' === element.style.display ) {
				element.style.display = 'block';
			} else {
				element.style.display = 'none';
			}
		}
		</script>
		";
	}

	function stats() {
		$this->js_toggle();

		echo '<h2><span>Total memcache query time:</span>' . number_format( sprintf( '%0.1f', $this->time_total * 1000 ), 1, '.', ',' ) . 'ms</h2>';
		echo "\n";
		echo '<h2><span>Total memcache size:</span>' . esc_html( size_format( $this->size_total, 2 ) ) . '</h2>';
		echo "\n";

		foreach ( $this->stats as $stat => $n ) {
			if ( empty( $n ) ) {
				continue;
			}

			echo '<h2>';
			echo $this->colorize_debug_line( "$stat $n" );
			echo '</h2>';
		}

		echo "<ul class='debug-menu-links'>\n";
		$groups = array_keys( $this->group_ops );
		usort( $groups, 'strnatcasecmp' );

		$active_group = $groups[0];
		// Always show `slow-ops` first
		if ( in_array( 'slow-ops', $groups ) ) {
			$slow_ops_key = array_search( 'slow-ops', $groups );
			$slow_ops = $groups[ $slow_ops_key ];
			unset( $groups[ $slow_ops_key ] );
			array_unshift( $groups, $slow_ops );
			$active_group = 'slow-ops';
		}

		$total_ops = 0;
		$group_titles = array();
		foreach ( $groups as $group ) {
			$group_name = $group;
			if ( empty( $group_name ) ) {
				$group_name = 'default';
			}
			$group_ops = count( $this->group_ops[ $group ] );
			$group_size = size_format( array_sum( array_map( function ( $op ) { return $op[2]; }, $this->group_ops[ $group ] ) ), 2 );
			$group_time = number_format( sprintf( '%0.1f', array_sum( array_map( function ( $op ) { return $op[3]; }, $this->group_ops[ $group ] ) ) * 1000 ), 1, '.', ',' );
			$total_ops += $group_ops;
			$group_title = "{$group_name} [$group_ops][$group_size][{$group_time}ms]";
			$group_titles[ $group ] = $group_title;
			echo "\t<li><a href='#' onclick='memcachedToggleVisibility( \"object-cache-stats-menu-target-" . esc_js( $group ) . "\", \"object-cache-stats-menu-target-\" );'>" . esc_html( $group_title ) . "</a></li>\n";
		}
		echo "</ul>\n";

		echo "<div id='object-cache-stats-menu-targets'>\n";
		foreach ( $groups as $group ) {
			$current = $active_group == $group ? 'style="display: block"' : 'style="display: none"';
			echo "<div id='object-cache-stats-menu-target-" . esc_attr( $group ) . "' class='object-cache-stats-menu-target' $current>\n";
			echo '<h3>' . esc_html( $group_titles[ $group ] ) . '</h3>' . "\n";
			echo "<pre>\n";
			foreach ( $this->group_ops[ $group ] as $o => $arr ) {
				printf( '%3d ', $o );
				echo $this->get_group_ops_line( $arr );
			}
			echo "</pre>\n";
			echo "</div>";
		}

		echo "</div>";
	}

	function get_group_ops_line( $arr ) {
		// operation
		$line = "{$arr[0]} ";

		// key
		$json_encoded_key = json_encode( $arr[1] );
		$line .= $json_encoded_key . " ";

		// comment
		if ( ! empty( $arr[4] ) ) {
			$line .= "{$arr[4]} ";
		}

		// size
		if ( isset( $arr[2] ) ) {
			$line .= '(' . size_format( $arr[2], 2 ) . ') ';
		}

		// time
		if ( isset( $arr[3] ) ) {
			$line .= '(' . number_format( sprintf( '%0.1f', $arr[3] * 1000 ), 1, '.', ',' ) . ' ms)';
		}

		// backtrace
		$bt_link = '';
		if ( isset( $arr[6] ) ) {
			$key_hash = md5( $json_encoded_key );
			$bt_link = " <small><a href='#' onclick='memcachedToggleVisibility( \"object-cache-stats-debug-$key_hash\" );'>Toggle Backtrace</a></small>";
			$bt_link .= "<pre id='object-cache-stats-debug-$key_hash' style='display:none'>" . esc_html( $arr[6] ) . "</pre>";
		}

		return $this->colorize_debug_line( $line, $bt_link );
	}

	function &get_mc( $group ) {
		if ( isset( $this->mc[ $group ] ) ) {
			return $this->mc[ $group ];
		}

		return $this->mc['default'];
	}

	function failure_callback( $host, $port ) {
		$this->connection_errors[] = array(
			'host' => $host,
			'port' => $port,
		);
	}

	function salt_keys( $key_salt ) {
		if ( strlen( $key_salt ) ) {
			$this->key_salt = $key_salt . ':';
		} else {
			$this->key_salt = '';
		}
	}

	function __construct() {
		$this->stats = array(
			'get' => 0,
			'get_local' => 0,
			'get_multi' => 0,
			'set' => 0,
			'set_local' => 0,
			'add' => 0,
			'delete' => 0,
			'delete_local' => 0,
			'slow-ops' => 0,
		);

		global $memcached_servers;

		if ( isset( $memcached_servers ) ) {
			$buckets = $memcached_servers;
		} else {
			$buckets = array( '127.0.0.1:11211' );
		}

		reset( $buckets );

		if ( is_int( key( $buckets ) ) ) {
			$buckets = array( 'default' => $buckets );
		}

		foreach ( $buckets as $bucket => $servers ) {
			$this->mc[ $bucket ] = new Memcache();

			foreach ( $servers as $server  ) {
				if ( 'unix://' == substr( $server, 0, 7 ) ) {
					$node = $server;
					$port = 0;
				} else {
					list ( $node, $port ) = explode( ':', $server );

					if ( ! $port ) {
						$port = ini_get( 'memcache.default_port' );
					}

					$port = intval( $port );

					if ( ! $port ) {
						$port = 11211;
					}
				}

				$this->mc[ $bucket ]->addServer( $node, $port, true, 1, 1, 15, true, array( $this, 'failure_callback' ) );
				$this->mc[ $bucket ]->setCompressThreshold( 20000, 0.2 );
			}
		}

		global $blog_id, $table_prefix;

		$this->global_prefix = '';
		$this->blog_prefix  = '';

		if ( function_exists( 'is_multisite' ) ) {
			$this->global_prefix = ( is_multisite() || defined( 'CUSTOM_USER_TABLE' ) && defined( 'CUSTOM_USER_META_TABLE' ) ) ? '' : $table_prefix;
			$this->blog_prefix   = ( is_multisite() ? $blog_id : $table_prefix );
		}

		$this->salt_keys( WP_CACHE_KEY_SALT );

		$this->cache_hits   =& $this->stats['get'];
		$this->cache_misses =& $this->stats['add'];
	}

	function increment_stat( $field, $num = 1 ) {
		if ( ! isset( $this->stats[ $field ] ) ) {
			$this->stats[ $field ] = $num;
		} else {
			$this->stats[ $field ] += $num;
		}
	}

	function group_ops_stats( $op, $keys, $group, $size, $time, $comment = '' ) {
		$this->increment_stat( $op );

		// we have no use of the local ops details for now
		if ( strpos( $op, '_local' ) ) {
			return;
		}

		$this->size_total += $size;

		$strip_keys = apply_filters( 'memcached_strip_keys', true );
		if ( $strip_keys ) {
			$keys = $this->strip_memcached_keys( $keys );
		}

		if ( $time > $this->slow_op_microseconds && 'get_multi' !== $op ) {
			$this->increment_stat( 'slow-ops' );
			$backtrace = null;
			if ( function_exists( 'wp_debug_backtrace_summary' ) ) {
				$backtrace = wp_debug_backtrace_summary();
			}
			$this->group_ops['slow-ops'][] = array( $op, $keys, $size, $time, $comment, $group, $backtrace );
		}

		$this->group_ops[ $group ][] = array( $op, $keys, $size, $time, $comment );
	}

	/**
	 * Key format: key:flush_timer:table_prefix:key_name
	 * We want to strip the `key:flush_timer` part to not leak the memcached keys.
	 */
	function strip_memcached_keys( $keys ) {
		if ( ! is_array( $keys ) ) {
			$keys = [ $keys ];
		}

		foreach ( $keys as $key => $value ) {
			$second_colon = strpos( $value, ':', strpos( $value, ':' ) + 1 );
			$keys[ $key ] = substr( $value, $second_colon + 1 );
		}

		if ( 1 === count( $keys ) ) {
			return $keys[0];
		}

		return $keys;
	}

	function timer_start() {
		$this->time_start = microtime( true );

		return true;
	}

	function timer_stop() {
		$time_total = microtime( true ) - $this->time_start;
		$this->time_total += $time_total;

		return $time_total;
	}

	function get_data_size( $data ) {
		if ( is_string( $data ) ) {
			return strlen( $data );
		}

		$serialized = serialize( $data );

		return strlen( $serialized );
	}
}
