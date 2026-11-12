<?php
/**
 * Compatibility Class
 *
 * @file The Elementor Model file
 * @package HMWP/Compatibility/Elementor
 * @since 8.0.0
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Compatibility_Elementor extends HMWP_Models_Compatibility_Abstract {

	public function hookFrontend() {

		// The Elementor Pro login widget has no hooks in its form, so the captcha is added to its HTML
		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce' ) && HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_login' ) ) {
			add_filter( 'elementor/widget/render_content', array( $this, 'addLoginCaptcha' ), 10, 2 );
		}

	}

	/**
	 * Add the WP Ghost captcha before the submit button of the Elementor Pro login widget
	 *
	 * @param string $content Widget HTML
	 * @param object $widget Elementor widget
	 *
	 * @return string
	 */
	public function addLoginCaptcha( $content, $widget ) {

		if ( ! is_string( $content ) || ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || $widget->get_name() !== 'login' || is_user_logged_in() ) {
			return $content;
		}

		// Already has a captcha field
		if ( strpos( $content, 'brute_ck' ) !== false || strpos( $content, 'g-recaptcha' ) !== false ) {
			return $content;
		}

		ob_start();
		HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getInstance()->form();
		$captcha = ob_get_clean();

		if ( ! $captcha ) {
			return $content;
		}

		$new = preg_replace_callback( '/<div[^>]*elementor-field-type-submit/', function ( $m ) use ( $captcha ) {
			return $captcha . $m[0];
		}, $content, 1, $count );

		return ( $new !== null && $count ) ? $new : $content;
	}

}
