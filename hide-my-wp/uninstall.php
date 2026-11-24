<?php
/**
 * HMWP plugin file.
 *
 * @package HMWP\Uninstall
 */

// Called on plugin uninstall
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Call config files
require dirname( __FILE__ ) . '/index.php';

// Uninstall the temporary logins on plugin uninstall
HMWP_Classes_ObjController::getClass( 'HMWP_Classes_Tools' );
if ( HMWP_Classes_Tools::getOption( 'hmwp_templogin_delete_uninstal' ) ) {
	HMWP_Classes_ObjController::getClass( 'HMWP_Models_Templogin' )->deleteTempLogins();
}

// Remove the security threats log table with the rest of the plugin data
/** @var HMWP_Models_Firewall_Database $database */
$database = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Firewall_Database' );
$database->deleteTable();

//remove user capability
HMWP_Classes_ObjController::getClass( 'HMWP_Models_RoleManager' )->removeHMWPCaps();

// Delete the record from database
delete_option( HMWP_OPTION );
delete_option( HMWP_OPTION_SAFE );
delete_option( HMWP_SECURITY_CHECK );
delete_option( HMWP_SECURITY_CHECK_IGNORE );
delete_option( HMWP_SECURITY_CHECK_TIME );
delete_option( HMWP_SALT_CHANGED );
wp_clear_scheduled_hook( HMWP_CRON );

// Delete the Geo Country database saved in uploads
$geoLocator = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Geoip_GeoLocator' );
wp_delete_file( $geoLocator->getDatabasePath() );
if ( basename( $geoLocator->getDatabaseDirectory() ) === 'wp-ghost' ) {
	wp_delete_file( $geoLocator->getDatabaseDirectory() . '/index.php' );
	@rmdir( $geoLocator->getDatabaseDirectory() ); //phpcs:ignore
}
wp_clear_scheduled_hook( 'hmwp_geoip_download_event' );
delete_transient( 'hmwp_geoip_failed' );
delete_transient( 'hmwp_geoip_download' );

// Remove the one-time cron event, the stored version list and the plugin's cached values
wp_clear_scheduled_hook( HMWP_CRON_ONCE );
delete_site_transient( 'hmwp_rollback_versions' );
// The update record a site keeps after moving from the premium edition
delete_option( 'external_updates-hide-my-wp' );
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_hmwp\_%' OR option_name LIKE '\_transient\_timeout\_hmwp\_%'" ); //phpcs:ignore

// Remove the reports and timers the plugin keeps as separate records
$hmwp_names = array( 'HMWP_VULNERABILITIES', 'HMWP_VULNERABILITIES_TIME', 'HMWP_GHOSTDOCTOR_REPORT', 'HMWP_GHOSTDOCTOR_SNAPSHOT', 'HMWP_AI_EXPLAIN', 'HMWP_THREATS_PURGE', 'HMWP_SECURITY_CHECK', 'HMWP_SECURITY_CHECK_IGNORE', 'HMWP_SECURITY_CHECK_TIME', 'HMWP_SALT_CHANGED' );
$hmwp_clean = function () use ( $hmwp_names ) {
	foreach ( $hmwp_names as $hmwp_name ) {
		if ( defined( $hmwp_name ) ) {
			delete_option( constant( $hmwp_name ) );
		}
	}
	delete_option( 'hmwp_weekly_digest_time' );
};
$hmwp_clean();

// On a network, the roles, cron events and stored values live on every site
if ( is_multisite() && ! wp_is_large_network() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $hmwp_site_id ) {
		if ( (int) $hmwp_site_id === get_current_blog_id() ) {
			continue;
		}
		switch_to_blog( $hmwp_site_id );
		$hmwp_clean();
		HMWP_Classes_ObjController::getClass( 'HMWP_Models_RoleManager' )->removeHMWPCaps();
		wp_clear_scheduled_hook( HMWP_CRON );
		wp_clear_scheduled_hook( HMWP_CRON_ONCE );
		wp_clear_scheduled_hook( 'hmwp_geoip_download_event' );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_hmwp\_%' OR option_name LIKE '\_transient\_timeout\_hmwp\_%'" ); //phpcs:ignore
		restore_current_blog();
	}
}
