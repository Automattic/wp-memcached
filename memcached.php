<?php
/**
 * Plugin Name: Memcached
 * Version: 4.0.0
 * Plugin URI: https://wordpress.org/plugins/memcached/
 * Description: Memcached backend for the WP Object Cache.
 * Author: Ryan Boren, Denis de Bernardy, Matt Martz, Andy Skelton
 * Author URI: https://github.com/Automattic/wp-memcached
 * Requires at least: 5.3
 * Tested up to: 6.0.1
 * Requires PHP: 7.4.0
 * License: GPL-2.0 license
 */

if ( !defined('ABSPATH') ) die('forbidden');

function automattic_memcached_plugin_install_object_cache(){
    if ( !file_exists($target = wp_normalize_path(WP_CONTENT_DIR . '/object-cache.php')) ) {
        $object = wp_normalize_path(dirname(__FILE__) . '/object-cache.php');
        @copy($object,$target);
    }
}

function automattic_memcached_plugin_uninstall_object_cache(){
    if ( file_exists($object = wp_normalize_path(WP_CONTENT_DIR . '/object-cache.php')) ) {
        @unlink($object);
    }
}

/**
 * Activation
 */
register_activation_hook(__FILE__, 'automattic_memcached_plugin_activation');

function automattic_memcached_plugin_activation(){

    // Install the object cache
    automattic_memcached_plugin_install_object_cache();
}

/**
 * Deactivation
 */
register_deactivation_hook( __FILE__, 'automattic_memcached_plugin_deactivation' );

function automattic_memcached_plugin_deactivation(){

    // Remove the object cache
    automattic_memcached_plugin_uninstall_object_cache();
}

/**
 * Update
 */
add_action('upgrader_process_complete', 'automattic_memcached_plugin_update', 10, 2);

function automattic_memcached_plugin_update($upgrader, $options = []){
    if ( $options['action'] == 'update' && $options['type'] == 'plugin' ){
        if ( isset($options['plugins']) ) {
            foreach($options['plugins'] as $plugin){
                if ( $plugin == 'memcached/memcached.php') {
                    // Update (Override) the object cache with latest version
                    automattic_memcached_plugin_install_object_cache();
                }
            }
        }
    }
}

/**
 * Notices
 */
add_action('plugins_loaded','automattic_memcached_plugin_loaded');

function automattic_memcached_plugin_loaded(){
    if ( defined('WP_CACHE_KEY_SALT') && WP_CACHE_KEY_SALT == '' ) {
       add_action('admin_notices', 'automattic_memcached_plugin_check_key_salt');
    }
    if ( !class_exists('Memcache') ) {
        add_action('admin_notices', 'automattic_memcached_plugin_check_memcache_server');
    }
}

function automattic_memcached_plugin_check_key_salt() {
    $output  = '<div class="notice notice-warning is-dismissible">';
    $output .= '<p><strong>Memcached</strong>: ';
    $output .= __('Add the WP_CACHE_KEY_SALT constant to the wp-config.php!','wp-memcached');
    $output .= '</p></div>';
    echo $output;
}

function automattic_memcached_plugin_check_memcache_server() {
    $output  = '<div class="notice notice-warning is-dismissible">';
    $output .= '<p><strong>Memcached</strong> ';
    $output .= __('not installed on your server!','wp-memcached');
    $output .= ' <a href="https://wordpress.org/plugins/memcached/#installation" target="_blank">';
    $output .= __('How to install?','wp-memcached');
    $output .= '</a></p></div>';
    echo $output;
}
