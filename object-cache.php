<?php
/**
* Memcache
*
*/

/*
Plugin Name: Memcached
Description: Memcached backend for the WP Object Cache.
Version: 3.0.1
Plugin URI: http://wordpress.org/extend/plugins/memcached/
Author: Ryan Boren, Denis de Bernardy, Matt Martz, Andy Skelton

Install this file to wp-content/object-cache.php
*/

// Users with setups where multiple installs share a common wp-config.php or $table_prefix
// can use this to guarantee uniqueness for the keys generated by this object cache
if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
	define( 'WP_CACHE_KEY_SALT', '' );
}

/**
 * WP Cache Add.
 *
 * @access public
 * @param mixed  $key Key.
 * @param mixed  $data Data.
 * @param string $group (default: '') Group.
 * @param int    $expire (default: 0) Expire.
 * @return void
 */
function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	return $wp_object_cache->add( $key, $data, $group, $expire );
}

/**
 * wp_cache_incr function.
 *
 * @access public
 * @param mixed  $key Key.
 * @param int    $n (default: 1)
 * @param string $group (default: '') Group.
 * @return void
 */
function wp_cache_incr( $key, $n = 1, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->incr( $key, $n, $group );
}

/**
 * wp_cache_decr function.
 *
 * @access public
 * @param mixed  $key
 * @param int    $n (default: 1)
 * @param string $group (default: '')
 * @return void
 */
function wp_cache_decr( $key, $n = 1, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->decr( $key, $n, $group );
}

/**
 * wp_cache_close function.
 *
 * @access public
 * @return void
 */
function wp_cache_close() {
	global $wp_object_cache;

	return $wp_object_cache->close();
}

/**
 * wp_cache_delete function.
 *
 * @access public
 * @param mixed  $key
 * @param string $group (default: '')
 * @return void
 */
function wp_cache_delete( $key, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->delete( $key, $group );
}

/**
 * wp_cache_flush function.
 *
 * @access public
 * @return void
 */
function wp_cache_flush() {
	global $wp_object_cache;

	return $wp_object_cache->flush();
}

/**
 * wp_cache_get function.
 *
 * @access public
 * @param mixed  $key
 * @param string $group (default: '')
 * @param bool   $force (default: false)
 * @return void
 */
function wp_cache_get( $key, $group = '', $force = false ) {
	global $wp_object_cache;

	return $wp_object_cache->get( $key, $group, $force );
}

/**
 * wp_cache_init function.
 *
 * @access public
 * @return void
 */
function wp_cache_init() {
	global $wp_object_cache;

	$wp_object_cache = new WP_Object_Cache();
}

/**
 * wp_cache_replace function.
 *
 * @access public
 * @param mixed  $key
 * @param mixed  $data
 * @param string $group (default: '')
 * @param int    $expire (default: 0)
 * @return void
 */
function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	return $wp_object_cache->replace( $key, $data, $group, $expire );
}

/**
 * wp_cache_set function.
 *
 * @access public
 * @param mixed  $key
 * @param mixed  $data
 * @param string $group (default: '')
 * @param int    $expire (default: 0)
 * @return void
 */
function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	if ( defined( 'WP_INSTALLING' ) == false ) {
		return $wp_object_cache->set( $key, $data, $group, $expire );
	} else {
		return $wp_object_cache->delete( $key, $group );
	}
}

/**
 * wp_cache_switch_to_blog function.
 *
 * @access public
 * @param mixed $blog_id
 * @return void
 */
function wp_cache_switch_to_blog( $blog_id ) {
	global $wp_object_cache;

	return $wp_object_cache->switch_to_blog( $blog_id );
}

/**
 * wp_cache_add_global_groups function.
 *
 * @access public
 * @param mixed $groups
 * @return void
 */
function wp_cache_add_global_groups( $groups ) {
	global $wp_object_cache;

	$wp_object_cache->add_global_groups( $groups );
}

/**
 * wp_cache_add_non_persistent_groups function.
 *
 * @access public
 * @param mixed $groups
 * @return void
 */
function wp_cache_add_non_persistent_groups( $groups ) {
	global $wp_object_cache;

	$wp_object_cache->add_non_persistent_groups( $groups );
}

/**
 * WP_Object_Cache class.
 */
class WP_Object_Cache {

	/**
	 * global_groups
	 *
	 * (default value: array( 'WP_Object_Cache_global' ))
	 *
	 * @var string
	 * @access public
	 */
	var $global_groups = array( 'WP_Object_Cache_global' );

	/**
	 * no_mc_groups
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 */
	var $no_mc_groups = array();

	/**
	 * cache
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 */
	var $cache = array();

	/**
	 * mc
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 */
	var $mc = array();

	/**
	 * stats
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 */
	var $stats = array();

	/**
	 * group_ops
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 */
	var $group_ops = array();

	/**
	 * flush_number
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 */
	var $flush_number = array();

	/**
	 * global_flush_number
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access public
	 */
	var $global_flush_number = null;

	/**
	 * cache_enabled
	 *
	 * (default value: true)
	 *
	 * @var bool
	 * @access public
	 */
	var $cache_enabled = true;

	/**
	 * default_expiration
	 *
	 * (default value: 0)
	 *
	 * @var int
	 * @access public
	 */
	var $default_expiration = 0;

	/**
	 * max_expiration
	 *
	 * (default value: 2592000)
	 *
	 * @var int
	 * @access public
	 */
	var $max_expiration = 2592000; // 30 days

	/**
	 * stats_callback
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access public
	 */
	var $stats_callback = null;

	/**
	 * connection_errors
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 */
	var $connection_errors = array();

	/**
	 * add function.
	 *
	 * @access public
	 * @param mixed  $id
	 * @param mixed  $data
	 * @param string $group (default: 'default')
	 * @param int    $expire (default: 0)
	 * @return void
	 */
	function add( $id, $data, $group = 'default', $expire = 0 ) {
		$key = $this->key( $id, $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		if ( in_array( $group, $this->no_mc_groups ) ) {
			$this->cache[ $key ] = $data;

			return true;
		} elseif ( isset( $this->cache[ $key ] ) && false !== $this->cache[ $key ] ) {
			return false;
		}

		$mc =& $this->get_mc( $group );

		$expire = intval( $expire );
		if ( 0 === $expire || $expire > $this->max_expiration ) {
			$expire = $this->default_expiration;
		}

		$result = $mc->add( $key, $data, false, $expire );

		if ( false !== $result ) {
			++$this->stats['add'];

			$this->group_ops[ $group ][] = "add $id";
			$this->cache[ $key ]         = $data;
		} elseif ( false === $result && true === isset( $this->cache[ $key ] ) && false === $this->cache[ $key ] ) {

			/*
			 * Here we unset local cache if remote add failed and local cache value is equal to `false` in order
			 * to update the local cache anytime we get a new information from remote server. This way, the next
			 * cache get will go to remote server and will fetch recent data.
			 */
			unset( $this->cache[ $key ] );
		}

		return $result;
	}

	/**
	 * add_global_groups function.
	 *
	 * @access public
	 * @param mixed $groups
	 * @return void
	 */
	function add_global_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->global_groups = array_merge( $this->global_groups, $groups );
		$this->global_groups = array_unique( $this->global_groups );
	}

	/**
	 * add_non_persistent_groups function.
	 *
	 * @access public
	 * @param mixed $groups
	 * @return void
	 */
	function add_non_persistent_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->no_mc_groups = array_merge( $this->no_mc_groups, $groups );
		$this->no_mc_groups = array_unique( $this->no_mc_groups );
	}

	/**
	 * incr function.
	 *
	 * @access public
	 * @param mixed  $id
	 * @param int    $n (default: 1)
	 * @param string $group (default: 'default')
	 * @return void
	 */
	function incr( $id, $n = 1, $group = 'default' ) {
		$key = $this->key( $id, $group );
		$mc =& $this->get_mc( $group );

		$this->cache[ $key ] = $mc->increment( $key, $n );

		return $this->cache[ $key ];
	}

	/**
	 * decr function.
	 *
	 * @access public
	 * @param mixed  $id
	 * @param int    $n (default: 1)
	 * @param string $group (default: 'default')
	 * @return void
	 */
	function decr( $id, $n = 1, $group = 'default' ) {
		$key = $this->key( $id, $group );
		$mc =& $this->get_mc( $group );

		$this->cache[ $key ] = $mc->decrement( $key, $n );

		return $this->cache[ $key ];
	}

	/**
	 * close function.
	 *
	 * @access public
	 * @return void
	 */
	function close() {
		foreach ( $this->mc as $bucket => $mc ) {
			$mc->close();
		}
	}

	/**
	 * delete function.
	 *
	 * @access public
	 * @param mixed  $id
	 * @param string $group (default: 'default')
	 * @return void
	 */
	function delete( $id, $group = 'default' ) {
		$key = $this->key( $id, $group );

		if ( in_array( $group, $this->no_mc_groups ) ) {
			unset( $this->cache[ $key ] );

			return true;
		}

		$mc =& $this->get_mc( $group );

		$result = $mc->delete( $key );

		++$this->stats['delete'];

		$this->group_ops[ $group ][] = "delete $id";

		if ( false !== $result ) {
			unset( $this->cache[ $key ] );
		}

		return $result;
	}

	/**
	 * flush function.
	 *
	 * @access public
	 * @return void
	 */
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

	/**
	 * rotate_site_keys function.
	 *
	 * @access public
	 * @return void
	 */
	function rotate_site_keys() {
		$this->add( 'flush_number', intval( microtime( true ) * 1e6 ), 'WP_Object_Cache' );

		$this->flush_number[ $this->blog_prefix ] = $this->incr( 'flush_number', 1, 'WP_Object_Cache' );
	}

	/**
	 * rotate_global_keys function.
	 *
	 * @access public
	 * @return void
	 */
	function rotate_global_keys() {
		$this->add( 'flush_number', intval( microtime( true ) * 1e6 ), 'WP_Object_Cache_global' );

		$this->global_flush_number = $this->incr( 'flush_number', 1, 'WP_Object_Cache_global' );
	}

	/**
	 * get function.
	 *
	 * @access public
	 * @param mixed  $id
	 * @param string $group (default: 'default')
	 * @param bool   $force (default: false)
	 * @return void
	 */
	function get( $id, $group = 'default', $force = false ) {
		$key = $this->key( $id, $group );
		$mc =& $this->get_mc( $group );

		if ( isset( $this->cache[ $key ] ) && ( ! $force || in_array( $group, $this->no_mc_groups ) ) ) {
			if ( is_object( $this->cache[ $key ] ) ) {
				$value = clone $this->cache[ $key ];
			} else {
				$value = $this->cache[ $key ];
			}
		} elseif ( in_array( $group, $this->no_mc_groups ) ) {
			$this->cache[ $key ] = $value = false;
		} else {
			$value = $mc->get( $key );

			if ( null === $value ) {
				$value = false;
			}

			$this->cache[ $key ] = $value;
		}

		++$this->stats['get'];

		$this->group_ops[ $group ][] = "get $id";

		if ( 'checkthedatabaseplease' === $value ) {
			unset( $this->cache[ $key ] );

			$value = false;
		}

		return $value;
	}

	/**
	 * get_multi function.
	 *
	 * @access public
	 * @param mixed $groups
	 * @return void
	 */
	function get_multi( $groups ) {
		/*
		format: $get['group-name'] = array( 'key1', 'key2' );
		*/
		$return = array();

		foreach ( $groups as $group => $ids ) {
			$mc =& $this->get_mc( $group );

			foreach ( $ids as $id ) {
				$key = $this->key( $id, $group );

				if ( isset( $this->cache[ $key ] ) ) {
					if ( is_object( $this->cache[ $key ] ) ) {
						$return[ $key ] = clone $this->cache[ $key ];
					} else {
						$return[ $key ] = $this->cache[ $key ];
					}

					continue;
				} elseif ( in_array( $group, $this->no_mc_groups ) ) {
					$return[ $key ] = false;

					continue;
				} else {
					$return[ $key ] = $mc->get( $key );
				}
			}

			if ( $to_get ) {
				$vals = $mc->get_multi( $to_get );

				$return = array_merge( $return, $vals );
			}
		}

		++$this->stats['get_multi'];

		$this->group_ops[ $group ][] = "get_multi $id";

		$this->cache = array_merge( $this->cache, $return );

		return $return;
	}

	/**
	 * flush_prefix function.
	 *
	 * @access public
	 * @param mixed $group
	 * @return void
	 */
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

	/**
	 * key function.
	 *
	 * @access public
	 * @param mixed $key
	 * @param mixed $group
	 * @return void
	 */
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

	/**
	 * replace function.
	 *
	 * @access public
	 * @param mixed  $id
	 * @param mixed  $data
	 * @param string $group (default: 'default')
	 * @param int    $expire (default: 0)
	 * @return void
	 */
	function replace( $id, $data, $group = 'default', $expire = 0 ) {
		$key    = $this->key( $id, $group );

		$expire = intval( $expire );
		if ( 0 === $expire || $expire > $this->max_expiration ) {
			$expire = $this->default_expiration;
		}

		$mc     =& $this->get_mc( $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$result = $mc->replace( $key, $data, false, $expire );

		if ( false !== $result ) {
			$this->cache[ $key ] = $data;
		}

		return $result;
	}

	/**
	 * set function.
	 *
	 * @access public
	 * @param mixed  $id
	 * @param mixed  $data
	 * @param string $group (default: 'default')
	 * @param int    $expire (default: 0)
	 * @return void
	 */
	function set( $id, $data, $group = 'default', $expire = 0 ) {
		$key = $this->key( $id, $group );

		if ( isset( $this->cache[ $key ] ) && ( 'checkthedatabaseplease' === $this->cache[ $key ] ) ) {
			return false;
		}

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$this->cache[ $key ] = $data;

		if ( in_array( $group, $this->no_mc_groups ) ) {
			return true;
		}

		$expire = intval( $expire );
		if ( 0 === $expire || $expire > $this->max_expiration ) {
			$expire = $this->default_expiration;
		}

		$mc     =& $this->get_mc( $group );
		$result = $mc->set( $key, $data, false, $expire );

		++$this->stats['set'];
		$this->group_ops[ $group ][] = "set $id";

		return $result;
	}

	/**
	 * switch_to_blog function.
	 *
	 * @access public
	 * @param mixed $blog_id
	 * @return void
	 */
	function switch_to_blog( $blog_id ) {
		global $table_prefix;

		$blog_id = (int) $blog_id;

		$this->blog_prefix = ( is_multisite() ? $blog_id : $table_prefix );
	}

	/**
	 * colorize_debug_line function.
	 *
	 * @access public
	 * @param mixed $line
	 * @return void
	 */
	function colorize_debug_line( $line ) {
		$colors = array(
			'get' => 'green',
			'set' => 'purple',
			'add' => 'blue',
			'delete' => 'red',
		);

		$cmd = substr( $line, 0, strpos( $line, ' ' ) );

		$cmd2 = "<span style='color:{$colors[$cmd]}'>$cmd</span>";

		return $cmd2 . substr( $line, strlen( $cmd ) ) . "\n";
	}

	/**
	 * stats function.
	 *
	 * @access public
	 * @return void
	 */
	function stats() {
		if ( $this->stats_callback && is_callable( $this->stats_callback ) ) {
			return call_user_func( $this->stats_callback );
		}

		echo "<p>\n";

		foreach ( $this->stats as $stat => $n ) {
			echo "<strong>$stat</strong> $n";
			echo "<br/>\n";
		}

		echo "</p>\n";
		echo '<h3>Memcached:</h3>';

		foreach ( $this->group_ops as $group => $ops ) {
			if ( ! isset( $_GET['debug_queries'] ) && 500 < count( $ops ) ) {
				$ops = array_slice( $ops, 0, 500 );
				echo "<big>Too many to show! <a href='" . add_query_arg( 'debug_queries', 'true' ) . "'>Show them anyway</a>.</big>\n";
			}

			echo "<h4>$group commands</h4>";
			echo "<pre>\n";

			$lines = array();

			foreach ( $ops as $op ) {
				$lines[] = $this->colorize_debug_line( $op );
			}

			print_r( $lines );

			echo "</pre>\n";
		}
	}

	/**
	 * get_mc function.
	 *
	 * @access public
	 * @param mixed $group
	 * @return void
	 */
	function &get_mc( $group ) {
		if ( isset( $this->mc[ $group ] ) ) {
			return $this->mc[ $group ];
		}

		return $this->mc['default'];
	}

	/**
	 * failure_callback function.
	 *
	 * @access public
	 * @param mixed $host
	 * @param mixed $port
	 * @return void
	 */
	function failure_callback( $host, $port ) {
		$this->connection_errors[] = array(
			'host' => $host,
			'port' => $port,
		);
	}

	/**
	 * salt_keys function.
	 *
	 * @access public
	 * @param mixed $key_salt
	 * @return void
	 */
	function salt_keys( $key_salt ) {
		if ( strlen( $key_salt ) ) {
			$this->key_salt = $key_salt . ':';
		} else {
			$this->key_salt = '';
		}
	}

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	function __construct() {
		$this->stats = array(
			'get'        => 0,
			'get_multi'  => 0,
			'add'        => 0,
			'set'        => 0,
			'delete'     => 0,
		);

		global $memcached_servers;

		if ( isset( $memcached_servers ) ) {
			$buckets = $memcached_servers;
		} else {
			$buckets = array( '127.0.0.1:11211' );
		}

		reset( $buckets );

		if ( is_int( key( $buckets ) ) ) {
			$buckets = array(
				'default' => $buckets,
			);
		}

		foreach ( $buckets as $bucket => $servers ) {
			$this->mc[ $bucket ] = new Memcache();

			foreach ( $servers as $server ) {
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
}
