<?php
/**
 * Compatibility Class
 *
 * @file The Autoptimize Model file
 * @package HMWP/Compatibility/Autoptimize
 * @since 7.0.0
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Compatibility_Autoptimize extends HMWP_Models_Compatibility_Abstract {

	public function hookFrontend() {

		//don't use Smush CDN with Autoptimize
		if ( HMWP_Classes_Tools::isPluginActive( 'wp-smush-pro/wp-smush.php' ) ) {
			if ( $smush = get_option( 'wp-smush-cdn_status' ) ) {
				if ( isset( $smush->cdn_enabled ) && $smush->cdn_enabled ) {
					return;
				}
			}
		}

		add_filter( 'autoptimize_html_after_minify', array( $this, 'findReplaceCache' ), PHP_INT_MAX );

		// Fonts and images in the CSS files Autoptimize writes to its cache
		add_filter( 'autoptimize_css_after_minify', array( $this, 'findReplaceCss' ), PHP_INT_MAX );
		add_filter( 'autoptimize_filter_css_single_after_minify', array( $this, 'findReplaceCss' ), PHP_INT_MAX );

	}

	/**
	 * Change the old paths in a CSS file before Autoptimize saves it
	 *
	 * @param string $css
	 *
	 * @return string
	 * @throws Exception
	 */
	public function findReplaceCss( $css ) {
		if ( ! is_string( $css ) || $css === '' || ! HMWP_Classes_Tools::doChangePaths() ) {
			return $css;
		}

		$cache = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Cache' );
		$cache->buildRedirect();

		return $cache->findReplace( $css );
	}

}
