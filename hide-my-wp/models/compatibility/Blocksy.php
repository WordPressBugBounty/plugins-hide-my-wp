<?php
/**
 * Compatibility Class
 *
 * @file The Blocksy Companion Model file
 * @package HMWP/Compatibility/Blocksy
 * @since 9.1.15
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Compatibility_Blocksy extends HMWP_Models_Compatibility_Abstract {

	public function __construct() {
		parent::__construct();

		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce' ) ) {
			// The account modal sends its login and lost password forms over AJAX together with the captcha field
			add_filter( 'hmwp_preauth_captcha_check', array( $this, 'captchaCheck' ), 20 );
		}

		// The 2FA step needs nothing here: the modal mounts the WP Ghost 2FA form by its name
	}

	/**
	 * Check the captcha the account modal shows on login and lost password
	 *
	 * @param bool $check Check the captcha.
	 *
	 * @return bool
	 */
	public function captchaCheck( $check ) {
		if ( ! HMWP_Classes_Tools::isAjax() || ! in_array( HMWP_Classes_Tools::getValue( 'action' ), array( 'blc_implement_user_login', 'blc_implement_user_lostpassword' ), true ) ) {
			return $check;
		}

		// Math and reCAPTCHA v2 work in the modal; reCAPTCHA v3 binds to the forms on page load, before the modal forms exist
		$name = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getName();

		return in_array( $name, array( 'Math', 'GoogleV2' ), true ) ? true : $check;
	}

}
