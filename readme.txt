=== Memcached Object Cache ===
Contributors: ryan, sivel, andy
Tags: cache, memcached
Requires at least: 3.0
Tested up to: 4.5
Stable tag: 3.0.1

Use memcached and the PECL memcache extension to provide a backing store for the WordPress object cache.

== Description ==
Memcached Object Cache provides a persistent backend for the WordPress object cache. A memcached server and the PECL memcache extension are required.

== Installation ==
1. Install [memcached](http://danga.com/memcached) on at least one server. Note the connection info. The default is `127.0.0.1:11211`.

1. Install the [PECL memcache extension](http://pecl.php.net/package/memcache)

1. Copy object-cache.php to wp-content

== Frequently Asked Questions ==

= How can I manually specify the memcached server(s)? =

Add something similar to the following to wp-config.php above `/* That's all, stop editing! Happy blogging. */`:

`
$memcached_servers = array(
	'default' => array(
		'10.10.10.20:11211',
		'10.10.10.30:11211'
	)
);
`

The top level array keys, are cache groups, where 'default' corresponds to any cache group that is not explicitly defined. This allows for specifying memcached servers that only handle certain cache groups. The most common use is only specifying 'default'.

Possible cache groups are:

`
{$taxonomy}_relationships
{$meta_type}_meta
{$taxonomy}_relationships
blog-details
blog-id-cache
blog-lookup
bookmark
calendar
category
comment
counts
general
global-posts
options
plugins
post_ancestors
post_meta
posts
rss
site-lookup
site-options
site-transient
terms
themes
timeinfo
transient
user_meta
useremail
userlogins
usermeta
users
userslugs
widget
`

== Changelog ==

= 3.0.1 =
* Fix key generation error in switch_to_blog()

= 3.0.0 =
* Flush site cache by rotating keys
* Flush global cache when flushing main site

= 2.0.6 =
* Flush the local cache on wp_cache_flush()

= 2.0.5 =
* Fix missing global in switch_to_blog

= 2.0.4 =
* Remove deprecated constructor

= 2.0.3 =
* Support for unix sockets

= 2.0.2 =
* Break references by cloning objects
* Keep local cache in sync with memcached when using incr and decr
* Handle limited environments where is_multisite() is not defined
* Fix setting and getting 0
* PHP 5.2.4 is now required
* Use the WP_CACHE_KEY_SALT constant if available to gaurantee uniqueness of keys
