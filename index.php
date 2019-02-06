<?php

/*
Plugin Name: Memcached
Description: Memcached backend for the WP Object Cache.
Version: 3.1.0
Plugin URI: http://wordpress.org/extend/plugins/memcached/
Author: Ryan Boren, Denis de Bernardy, Matt Martz, Andy Skelton
*/


class WP_Memcached_Enabler
{
	private $constName = 'WP_MEMCHACED_PLUGIN_USED';

	// Activation and deactivation
	public function __construct() 
	{
		add_action('admin_notices',				[ $this, 'check_if_activated']	);
		add_action('network_admin_notices',		[ $this, 'check_if_activated']	);
		register_activation_hook( __FILE__,		[ $this, 'activate' ]			);
		register_deactivation_hook( __FILE__,	[ $this, 'deactivate']			);
	}

	private $source			= __DIR__ . '/object-cache.php';
	private $destination	= WP_CONTENT_DIR . '/object-cache.php';

	public function activate( $sitewide = false ) {
		if ( class_exists('Memcache'))
		{
			if ( ! file_exists( $this->destination ) ) {
				if ( function_exists( 'symlink' ) ){
					symlink( $this->source, $this->destination); 
				}
				else {
					// Lets don't do it automatically, instead make a notice to user
					// copy( $this->source, $this->destination );
				}
			}

		}
	}

	public function deactivate() {  
		// Only delete file if it belongs to this plugin
		if ( defined($this->constName) && file_exists( $this->destination )  ) {
			unlink( $this->destination );
		}
	}

	// Check if current plugin is active, but it's cache not being used. If so, show warning to user to deactivate this plugin to avoid any confusion
	public function check_if_activated() {
		$plugin_basename= plugin_basename(__FILE__); 
		$plugin_name	= dirname($plugin_basename);

		if ( is_plugin_active ($plugin_basename) && ! defined($this->constName) )
		{	?>
			<div class="notice notice-error">
				<p>
				<h4><?php _e("Plugin");?> (<?php echo basename($plugin_name);?>) <?php _e("has the following error:");?></h4>
				<?php 
				if( ! class_exists('Memcache') ) {
					echo '<li>' . __('<code>Memcache</code> class is not detected in your hosting, and there is no way you can use that. You have to install it at first, or try to conctact your server administrators. Untill you do that, it\'s better to deactivate this plugin', 'memcached-enabler') .'.</li>';
				}
				else if ( ! function_exists( 'symlink' ) )
				{
						?>
						<li><?php echo sprintf( __('<code>symlink</code> function is not enabled in your hosting. So, you have to manually copy <code>%s</code> to <code>%s</code>. In that case, if you ever <b>manually</b> (beyond WP) delete this plugin, you will also need to remove that file manually, otherwise it will remain in use.', 'memcached-enabler'), $this->source, $this->destination ); ?>.</li>
						<?php
				}
				else if ( file_exists( $this->destination ) )
				{
					?>
					<li><?php echo sprintf( __('This plugin doesn\'t seem to have its own file ( <code>%s</code> ), probably some other plugin has replaced that file. So, this plugin ( <code>'. basename($plugin_name).'</code>) is now non-functional. Please <a href="'.network_admin_url("plugins.php") .'" target="_blank">deactivate it</a> to avoid any confusion. In case you really want to retain only this plugin, that remove the existing <code>%s</code> and re-activate this plugin', 'memcached-enabler'), basename($plugin_name), 'wp-content/'. basename($this->destination), 'wp-content/'. basename($this->destination) ) ;?>.</li>
					<?php
				}
				else
				{
						?>
						<li><?php echo sprintf( __('Target file (<code>%s</code>) doesn\'t exist, so this plugin doesn\'t function. Not sure what has happened, but you may try to re-activate this plugin )', 'memcached-enabler'), 'wp-content/'. basename($this->destination), 'wp-content/'. basename($this->destination) ) ;?>.</li>
						<?php
				}
				?>
				</p>
			</div>
			<?php 
		}
	}

}

new WP_Memcached_Enabler();
?>
