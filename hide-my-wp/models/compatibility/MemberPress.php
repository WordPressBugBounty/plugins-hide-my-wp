<?php
/**
 * Compatibility Class
 *
 * @file The MemberPress Model file
 * @package HMWP/Compatibility/MemberPress
 * @since 7.0.0
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Compatibility_MemberPress extends HMWP_Models_Compatibility_Abstract {

	public function __construct() {
		parent::__construct();

		$login = $this->getLoginPath();

		if ( $login ) {
			defined( 'HMWP_DEFAULT_LOGIN' ) || define( 'HMWP_DEFAULT_LOGIN', $login );

			if ( HMWP_DEFAULT_LOGIN == 'login' ) {
				add_filter( 'hmwp_option_hmwp_hide_login', '__return_false' );
			}

			add_filter( 'hmwp_option_hmwp_lostpassword_url', '__return_false' );
			add_filter( 'hmwp_option_hmwp_register_url', '__return_false' );
			add_filter( 'hmwp_option_hmwp_logout_url', '__return_false' );
		}

		//load the brute force
		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce' ) && ! HMWP_Classes_Tools::isPluginActive( 'memberpress-math-captcha/main.php' ) ) {

			$this->hookBruteForce();
		}
	}

	public function hookBruteForce() {

		// Get the active brute force class
		$bruteforce = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getInstance();

		// MemberPress filters its errors array; the captcha error is added to it (failed attempts count on wp_login_failed)
		add_filter( 'mepr-validate-login', array( $this, 'validateCaptcha' ) );
		add_action( 'mepr-login-form-before-submit', array( $bruteforce, 'head' ) );
		add_action( 'mepr-login-form-before-submit', array( $bruteforce, 'form' ) );

		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_lostpassword' ) ) {
			add_filter( 'mepr-validate-forgot-password', array( $this, 'validateCaptcha' ) );
			add_action( 'mepr-forgot-password-form', array( $bruteforce, 'head' ) );
			add_action( 'mepr-forgot-password-form', array( $bruteforce, 'form' ) );
		}

	}

	/**
	 * Check the captcha on the MemberPress login and forgot password forms
	 *
	 * @param array $errors MemberPress validation errors
	 *
	 * @return array
	 * @throws Exception
	 */
	public function validateCaptcha( $errors ) {
		$errors = is_array( $errors ) ? $errors : array();

		$response = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->bruteForceCheck();
		if ( isset( $response['status'] ) && $response['status'] == 'whitelist' ) {
			return $errors;
		}

		$bruteforce = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getInstance();
		if ( method_exists( $bruteforce, 'call' ) && ( $message = $bruteforce->call() ) ) {
			$errors[] = wp_strip_all_tags( $message );
		}

		return $errors;
	}

	/**
	 * Get the options
	 *
	 * @return false|mixed|null
	 */
	public function getOptions() {
		return get_option( 'mepr_options' );
	}

	/**
	 * Get the login path
	 *
	 * @return false|string
	 */
	public function getLoginPath() {

		$options = $this->getOptions();

		if ( isset( $options['login_page_id'] ) && (int) $options['login_page_id'] > 0 ) {
			$post = get_post( (int) $options['login_page_id'] );

			if ( $post instanceof WP_Post && $post->post_status == 'publish' ) {
				return $post->post_name;
			}
		}

		return false;
	}

	/**
	 * Check the reCaptcha on login, register and password reset
	 *
	 * @param $args
	 *
	 * @return void
	 * @throws Exception
	 */
	public function checkReCaptcha( $args ) {
		if ( class_exists( 'UM' ) ) {

			// Get the active brute force class
			$bruteforce = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getInstance();

			$errors = $bruteforce->pre_authentication( false );

			if ( is_wp_error( $errors ) ) {
				if ( isset( $args['mode'] ) ) {
					switch ( $args['mode'] ) {
						case 'login':
							UM()->form()->add_error( 'username', wp_strip_all_tags( $errors->get_error_message() ) );
							break;
						case 'register':
							UM()->form()->add_error( 'user_login', wp_strip_all_tags( $errors->get_error_message() ) );
							break;
						case 'password':
							UM()->form()->add_error( 'username_b', wp_strip_all_tags( $errors->get_error_message() ) );
							break;

					}
				}
			}

		}
	}
}
