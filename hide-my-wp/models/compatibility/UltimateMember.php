<?php
/**
 * Compatibility Class
 *
 * @file The UltimateMember Model file
 * @package HMWP/Compatibility/UltimateMember
 * @since 6.0.0
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Compatibility_UltimateMember extends HMWP_Models_Compatibility_Abstract {

	/**
	 * @throws Exception
	 */
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
		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce' ) ) {

			$this->hookBruteForce();

		}
	}

	/**
	 * @throws Exception
	 */
	public function hookBruteForce() {

		// Get the active brute force class
		$bruteforce = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getInstance();

		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_login' ) ) {
			add_action( 'um_submit_form_login', array( $this, 'checkReCaptcha' ) );
			add_action( 'um_after_login_fields', array( $bruteforce, 'head' ), 99 );
			add_action( 'um_after_login_fields', array( $bruteforce, 'form' ), 99 );
		}

		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_register' ) ) {
			// Check on the validation hook, before Ultimate Member creates the account
			add_action( 'um_submit_form_errors_hook__registration', array( $this, 'checkReCaptcha' ), 9, 2 );
			add_action( 'um_after_register_fields', array( $bruteforce, 'head' ), 99 );
			add_action( 'um_after_register_fields', array( $bruteforce, 'form' ), 99 );
		}

		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_lostpassword' ) ) {
			add_action( 'um_reset_password_errors_hook', array( $this, 'checkReCaptcha' ) );
			add_action( 'um_after_password_reset_fields', array( $bruteforce, 'head' ), 99 );
			add_action( 'um_after_password_reset_fields', array( $bruteforce, 'form' ), 99 );
		}

	}

	/**
	 * Get the options
	 *
	 * @return false|mixed|null
	 */
	public function getOptions() {
		return get_option( 'um_options' );
	}

	/**
	 * Get the login path
	 *
	 * @return false|string
	 */
	public function getLoginPath() {

		$options = $this->getOptions();

		if ( isset( $options['core_login'] ) && (int) $options['core_login'] > 0 ) {
			$post = get_post( (int) $options['core_login'] );

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
	 * @param $form_data
	 *
	 * @return void
	 * @throws Exception
	 */
	public function checkReCaptcha( $args, $form_data = array() ) {
		if ( class_exists( 'UM' ) ) {

			// Get the active brute force class
			$bruteforce = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getInstance();

			$errors = $bruteforce->pre_authentication( false );

			if ( is_wp_error( $errors ) ) {
				// Newer Ultimate Member versions keep the form mode in the second argument
				$mode = isset( $args['mode'] ) ? $args['mode'] : ( isset( $form_data['mode'] ) ? $form_data['mode'] : false );

				if ( $mode ) {
					switch ( $mode ) {
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

		return $args;
	}
}
