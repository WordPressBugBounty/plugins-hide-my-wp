<?php
/**
 * Rollback Model
 * Called to handle the plugin installation in background
 *
 * @file  The Rollback Model file
 * @package HMWP/RollbackModel
 * @since 5.0.0
 */


defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Rollback {

	/**
	 * @var string Package URL.
	 */
	protected $package_url;

	/**
	 * @var string Package URL.
	 */
	protected $version;

	/**
	 * @var string Plugin name.
	 */
	protected $plugin_name;

	/**
	 * @var string Plugin slug.
	 */
	protected $plugin_slug;

	/**
	 * Get the versions that can be installed: the current one and up to 5 previous stable ones from WordPress.org
	 *
	 * @return array Version numbers, the current version first, then newest to oldest.
	 */
	public function getVersions() {

		$versions = get_site_transient( 'hmwp_rollback_versions' );

		if ( ! is_array( $versions ) ) {
			$versions = array();
			$response = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/hide-my-wp.json', array( 'timeout' => 5 ) );

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( ! empty( $data['versions'] ) && is_array( $data['versions'] ) ) {
					$versions = array_map( 'strval', array_keys( $data['versions'] ) );
				}
			}

			// Ask again sooner if WordPress.org didn't answer
			set_site_transient( 'hmwp_rollback_versions', $versions, $versions ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		}

		$previous = array();

		foreach ( ( $versions ? $versions : array( HMWP_STABLE_VERSION ) ) as $version ) {
			if ( preg_match( '/^\d+(\.\d+)+$/', $version ) && version_compare( $version, HMWP_VERSION, '<' ) && version_compare( $version, HMWP_STABLE_VERSION, '>=' ) ) {
				$previous[] = $version;
			}
		}

		usort( $previous, function ( $a, $b ) {
			return version_compare( $b, $a );
		} );

		return array_merge( array( HMWP_VERSION ), array_slice( $previous, 0, 5 ) );
	}

	public function set_plugin( $args = array() ) {
		foreach ( $args as $key => $value ) {
			$this->{$key} = $value;
		}

		return $this;
	}

	/**
	 * Apply package.
	 *
	 * Change the plugin data when WordPress checks for updates. This method
	 * modifies package data to update the plugin from a specific URL containing
	 * the version package.
	 */
	protected function apply_package() {

		$update_plugins = get_site_transient( 'update_plugins' );
		if ( ! is_object( $update_plugins ) ) {
			$update_plugins = new \stdClass();
		}

		$plugin_info              = new \stdClass();
		$plugin_info->new_version = $this->version;
		$plugin_info->slug        = $this->plugin_slug;
		$plugin_info->package     = $this->package_url;
		$plugin_info->url         = _HMWP_ACCOUNT_SITE_;

        $update_plugins->response[ $this->plugin_name ] = $plugin_info;

		set_site_transient( 'update_plugins', $update_plugins );
	}

	/**
	 * Install the package set with apply_package()
	 *
	 * @return true|WP_Error True if the version was installed.
	 */
	protected function upgrade() {
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		// Same route as the Updates page: the plugin stays active with its rules in place
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->bulk_upgrade( array( $this->plugin_name ) );

		if ( $skin->get_errors()->has_errors() ) {
			return $skin->get_errors();
		}

		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}

		if ( empty( $result[ $this->plugin_name ] ) || is_wp_error( $result[ $this->plugin_name ] ) ) {
			return new \WP_Error( 'hmwp_rollback', esc_html__( "WordPress can't write the plugin files on this server.", 'hide-my-wp' ) );
		}

		return true;
	}

	/**
	 * Install the selected version
	 *
	 * @return true|WP_Error True if the version was installed.
	 */
	public function run() {
		$this->apply_package();
		$result = $this->upgrade();

		// Drop the update entry added above, also if the install failed
		delete_site_transient( 'update_plugins' );

		return $result;
	}

    /**
     * Handles the installation process of a plugin by setting up necessary
     * includes and running the Plugin_Upgrader with the provided arguments.
     *
     * @param  mixed  $args  The arguments needed to set up the installation process.
     *
     * @return bool|WP_Error True if installation was successful, WP_Error on failure.
     */
	public function install( $args ) {

		// Includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
		include_once( ABSPATH . 'wp-admin/includes/file.php' );
		include_once( ABSPATH . 'wp-admin/includes/misc.php' );
		include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

		$this->set_plugin( $args )->apply_package();

		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );

		return $upgrader->install( $this->package_url, array( 'overwrite_package' => true ) );

	}

    /**
     * Activates a specified plugin by updating the list of active plugins
     * and triggering activation hooks.
     *
     * @param  string  $plugin  The plugin to be activated.
     *
     * @return null
     */
	public function activate( $plugin ) {

		$plugin  = trim( $plugin );
		$current = get_option( 'active_plugins' );
		$plugin  = plugin_basename( $plugin );

		if ( $plugin <> '' && ! in_array( $plugin, $current ) ) {

			$current[] = $plugin;
			sort( $current );

			try {
				do_action( 'activate_plugin', $plugin, true );
				update_option( 'active_plugins', $current );
				do_action( 'activate_' . $plugin );
				do_action( 'activated_plugin', $plugin, true );
			} catch ( Exception $e ) {
			}
		}

		return null;
	}

}
