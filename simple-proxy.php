<?php
/*
Plugin Name: Simple Proxy
Description: A very simple proxy. Useful when you're moving from one server to another.
Author: Greg Priday
Author URI: http://siteorigin.com/
Version: 1.0
*/

function simple_proxy_admin_menu(){
	add_submenu_page('options-general.php', __('Simple Proxy Settings', 'simple-proxy'), __('Simple Proxy', 'simple-proxy'), 'manage_options', 'so-simple-proxy', 'simple_proxy_admin_page');

	if(!empty($_POST['simple_proxy']) && !empty($_POST['_spnonce']) && wp_verify_nonce($_POST['_spnonce'], 'simple-proxy-save')) {
		$settings = $_POST['simple_proxy'];
		$settings['enabled'] = !empty($settings['enabled']);
		$settings['url'] = rtrim($settings['url'], '/');

		update_option('simple_proxy_settings', $settings);

		if( !empty( $_POST['simple_proxy_clear'] ) && WP_Filesystem() ) {
			// Clear the proxy cache
			global $wp_filesystem;
			$cache_folder = $wp_filesystem->wp_content_dir().'proxy_cache/';
			$wp_filesystem->rmdir($cache_folder, true);
		}
	}
}
add_action('admin_menu', 'simple_proxy_admin_menu');

function simple_proxy_admin_page(){
	$settings = get_option( 'simple_proxy_settings', array() );

	?>
	<div class="wrap">
		<h2><?php _e('Simple Proxy Settings', 'simple-proxy') ?></h2>
		<form action="<?php echo add_query_arg(false, false) ?>" method="post">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><?php _e('Proxy URL', 'simple-proxy') ?></th>
						<td>
							<input type="text" class="widefat" name="simple_proxy[url]" value="<?php echo (!empty($settings['url'])) ? esc_attr($settings['url']) : '' ?>" placeholder="http://">
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Proxy Enabled', 'simple-proxy') ?></th>
						<td>
							<label>
								<input type="checkbox" name="simple_proxy[enabled]" <?php checked(!empty($settings['enabled'])) ?>>
								<?php _e('enabled', 'simple-proxy') ?>
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Clear Cache', 'simple-proxy') ?></th>
						<td>
							<label>
								<input type="checkbox" name="simple_proxy_clear">
								<?php _e('check to clear the cache (once off)', 'simple-proxy') ?>
							</label>
						</td>
					</tr>

				</tbody>
			</table>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'simple-proxy') ?>"></p>
			<?php wp_nonce_field('simple-proxy-save', '_spnonce') ?>
		</form>
	</div>
	<?php
}

function simple_proxy_init() {
	// Don't process this if we're in the admin or login/register pages
	if( is_admin() || in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) ) ) return;

	$settings = get_option( 'simple_proxy_settings', array() );
	if(empty($settings['enabled']) || empty($settings['url'])) return;

	require_once(ABSPATH . 'wp-admin/includes/file.php');
	if(!WP_Filesystem()) return;
	global $wp_filesystem;

	$cache_folder = $wp_filesystem->wp_content_dir().'proxy_cache/';

	if(!$wp_filesystem->is_dir( $cache_folder )) {
		$wp_filesystem->mkdir($cache_folder);
	}


	$url = esc_url( $settings['url'] ) . $_SERVER['REQUEST_URI'];

	// Check if we have this in cache
	$cache_file = $cache_folder.md5($url).'.dat';

	if( $wp_filesystem->is_file($cache_file) && ( time() - $wp_filesystem->mtime($cache_file) < 86400 ) ) {
		$response = unserialize($wp_filesystem->get_contents($cache_file));
	}
	else {
		$response = wp_remote_get(
			add_query_arg('no_cache', rand(0, 65536), $url),
			array(
				'timeout' => 120,
			)
		);

		if(!is_wp_error($response) && isset($response['response']['code']) && $response['response']['code'] == 200) {
			$wp_filesystem->put_contents($cache_file, serialize($response));
		}
		elseif($wp_filesystem->is_file($cache_file)) {
			// The cache file still exists, use it so long
			$response = unserialize($wp_filesystem->get_contents($cache_file));
		}
	}

	foreach($response['headers'] as $name => $value) {
		header($name.': '.$value, true);
	}

	$body = str_replace($settings['url'], site_url(), $response['body']);

	if(empty($body)) return;

	echo $body;
	exit();
}


add_action('init', 'simple_proxy_init');