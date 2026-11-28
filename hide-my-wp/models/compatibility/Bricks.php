<?php
/**
 * Compatibility Class
 *
 * @file The Bricks Builder Model file
 * @package HMWP/Compatibility/Bricks
 * @since 9.1.15
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Compatibility_Bricks extends HMWP_Models_Compatibility_Abstract {

	public function __construct() {
		parent::__construct();

		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce' ) ) {
			// Show the WP Ghost captcha in Bricks login, lost password and registration forms
			add_filter( 'bricks/frontend/render_element', array( $this, 'addCaptcha' ), 10, 2 );

			// Check it on submit, before Bricks runs the form action
			add_filter( 'bricks/form/validate', array( $this, 'validateCaptcha' ), 10, 2 );
		}

		// A 2FA user logging in through a Bricks form goes on to the WP Ghost 2FA step
		add_filter( 'hmwp_2fa_ajax_response', array( $this, 'twoFactorResponse' ), 10, 2 );
	}

	/**
	 * The protected WP Ghost form types a Bricks form runs
	 *
	 * @param array $actions The Bricks form actions.
	 *
	 * @return array login, lostpassword, register
	 */
	private function getProtectedTypes( $actions ) {
		$actions = array_filter( (array) $actions, 'is_string' );
		$types   = array();

		if ( in_array( 'login', $actions, true ) && HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_login' ) ) {
			$types[] = 'login';
		}
		if ( in_array( 'lost-password', $actions, true ) && HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_lostpassword' ) ) {
			$types[] = 'lostpassword';
		}
		if ( in_array( 'registration', $actions, true ) && HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_register' ) ) {
			$types[] = 'register';
		}

		return $types;
	}

	/**
	 * The form runs its own captcha (Bricks reCAPTCHA, hCaptcha or Turnstile with its key set): WP Ghost adds none
	 *
	 * @param array $settings The Bricks form settings.
	 *
	 * @return bool
	 */
	private function hasOwnCaptcha( $settings ) {
		$own = array( 'enableRecaptcha' => 'apiKeyGoogleRecaptcha', 'enableHCaptcha' => 'apiKeyHCaptcha', 'enableTurnstile' => 'apiKeyTurnstile' );

		foreach ( $own as $setting => $key ) {
			if ( isset( $settings[ $setting ] ) ) {
				if ( ! class_exists( '\Bricks\Database' ) || \Bricks\Database::get_setting( $key, false ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Add the captcha before the submit button of a protected Bricks form
	 *
	 * @param string $html    The rendered element.
	 * @param object $element The Bricks element.
	 *
	 * @return string
	 */
	public function addCaptcha( $html, $element ) {
		if ( ! is_string( $html ) || ! is_object( $element ) || ! isset( $element->name ) || $element->name !== 'form' ) {
			return $html;
		}

		$settings = ( isset( $element->settings ) && is_array( $element->settings ) ) ? $element->settings : array();
		if ( empty( $settings['actions'] ) || ! $this->getProtectedTypes( $settings['actions'] ) || $this->hasOwnCaptcha( $settings ) || strpos( $html, 'hmwp-bricks-captcha' ) !== false ) {
			return $html;
		}

		ob_start();
		HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getInstance()->form();
		$captcha = trim( ob_get_clean() );

		if ( $captcha === '' ) {
			return $html;
		}

		$captcha = '<div class="form-group hmwp-bricks-captcha">' . $captcha . '</div>';

		// Before the submit button, else at the end of the form
		if ( preg_match( '/<div[^>]*class="[^"]*\bsubmit-button-wrapper\b/', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			$pos = $match[0][1];
		} else {
			$pos = strrpos( $html, '</form>' );
		}

		if ( $pos === false ) {
			return $html;
		}

		return substr( $html, 0, $pos ) . $captcha . substr( $html, $pos );
	}

	/**
	 * Check the captcha and the IP ban when a protected Bricks form is sent
	 *
	 * @param array  $errors The Bricks validation errors.
	 * @param object $form   The Bricks form.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function validateCaptcha( $errors, $form ) {
		if ( ! is_array( $errors ) || ! is_object( $form ) || ! method_exists( $form, 'get_settings' ) ) {
			return $errors;
		}

		$settings = (array) $form->get_settings();
		if ( empty( $settings['actions'] ) || ! $this->getProtectedTypes( $settings['actions'] ) ) {
			return $errors;
		}

		/** @var HMWP_Models_Brute $bruteForceModel */
		$bruteForceModel = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' );

		// A banned IP gets the ban message in the form, not the block page
		$response = $bruteForceModel->processIp();
		if ( isset( $response['status'] ) && $response['status'] == 'whitelist' ) {
			return $errors;
		}
		if ( isset( $response['status'] ) && $response['status'] == 'blocked' ) {
			$errors[] = wp_strip_all_tags( (string) HMWP_Classes_Tools::getOption( 'hmwp_brute_message' ) );

			return $errors;
		}

		// The form's own captcha is checked by Bricks
		if ( $this->hasOwnCaptcha( $settings ) ) {
			return $errors;
		}

		$result = $bruteForceModel->getInstance()->authenticate( null, $response );
		if ( is_wp_error( $result ) ) {
			$errors[] = wp_strip_all_tags( $result->get_error_message() );
		}

		return $errors;
	}


	/**
	 * The Bricks answer that sends the browser to the 2FA step
	 *
	 * @param array|null $response Another module's answer.
	 * @param string     $url      The 2FA step address.
	 *
	 * @return array|null
	 */
	public function twoFactorResponse( $response, $url ) {
		if ( HMWP_Classes_Tools::getValue( 'action' ) !== 'bricks_form_submit' ) {
			return $response;
		}

		return array( 'type' => 'success', 'message' => '', 'redirectTo' => $url, 'redirectTimeout' => 0 );
	}

}
