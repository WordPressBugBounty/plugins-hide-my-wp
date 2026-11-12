<?php
/**
 * Brute Force Protection Model
 * Called from Brute Force Class
 *
 * @file  The Brute Force LostPassword file
 * @package HMWP/BruteForce/LostPassword
 * @since 8.1
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Bruteforce_Login {

	public function __construct() {

		// Get the active brute force class
		$bruteforce = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getInstance();

		// Listen to the login process and check for brute force attempts
		add_filter( 'authenticate', array( $bruteforce, 'pre_authentication' ), 99, 1 );

		add_action( 'wp_login_failed', array( $bruteforce, 'failed' ), 99 );
		add_action( 'login_head', array( $bruteforce, 'head' ), 99 );
		add_action( 'login_form', array( $bruteforce, 'form' ), 99 );

		// Core wp_login_form() (widgets, membership plugins) doesn't run the login_form action
		add_filter( 'login_form_middle', array( $this, 'loginFormMiddle' ), 99 );

	}

	/**
	 * Add the captcha field to forms made with wp_login_form()
	 *
	 * @param string $content Extra HTML before the remember me checkbox
	 *
	 * @return string
	 */
	public function loginFormMiddle( $content ) {
		if ( is_user_logged_in() ) {
			return $content;
		}

		ob_start();
		HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getInstance()->form();

		return $content . ob_get_clean();
	}


}
