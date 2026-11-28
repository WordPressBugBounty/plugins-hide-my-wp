<?php
/**
 * Compatibility Class
 *
 * @file The Essential Addons for Elementor Model file
 * @package HMWP/Compatibility/EssentialAddons
 * @since 9.1.15
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Compatibility_EssentialAddons extends HMWP_Models_Compatibility_Abstract {

	public function __construct() {
		parent::__construct();

		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce' ) ) {
			// Show the WP Ghost captcha in the Login | Register Form widget
			add_action( 'eael/login-register/before-login-footer', array( $this, 'loginCaptcha' ) );
			add_action( 'eael/login-register/before-lostpassword-footer', array( $this, 'lostPasswordCaptcha' ) );
			add_action( 'eael/login-register/before-register-footer', array( $this, 'registerCaptcha' ) );
			add_filter( 'eael/login-register/register-form-allowed-html', array( $this, 'allowCaptchaHtml' ) );

			// Check it on submit: login and lost password through WordPress, registration before the widget handles it
			add_filter( 'hmwp_preauth_captcha_check', array( $this, 'captchaCheck' ), 20 );
			add_filter( 'lostpassword_errors', array( $this, 'lostPasswordError' ), PHP_INT_MAX );
			add_action( 'init', array( $this, 'checkRegister' ), 9 );
		}

		// The widget's email code is its own second factor
		add_filter( 'hmwp_2fa_skip_login', array( $this, 'skipTwoFactor' ) );
	}

	/**
	 * Is the WP Ghost protection on for this form
	 *
	 * @param string $form login, lostpassword or register.
	 *
	 * @return bool
	 */
	private function isProtected( $form ) {
		$options = array( 'login' => 'hmwp_bruteforce_login', 'lostpassword' => 'hmwp_bruteforce_lostpassword', 'register' => 'hmwp_bruteforce_register' );

		return isset( $options[ $form ] ) && HMWP_Classes_Tools::getOption( $options[ $form ] );
	}

	/**
	 * The widget shows its own reCAPTCHA or Turnstile on this form: WP Ghost adds none
	 *
	 * @param array  $settings The widget settings.
	 * @param string $form     login, lostpassword or register.
	 *
	 * @return bool
	 */
	private function hasOwnCaptcha( $settings, $form ) {
		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return false;
		}

		$on = function ( $key, $default = '' ) use ( $settings ) {
			return ( isset( $settings[ $key ] ) ? $settings[ $key ] : $default ) === 'yes';
		};

		if ( $on( 'enable_login_register_recaptcha', 'yes' ) ) {
			if ( isset( $settings['login_register_recaptcha_version'] ) && $settings['login_register_recaptcha_version'] === 'v3' ) {
				if ( get_option( 'eael_recaptcha_sitekey_v3' ) ) {
					return true;
				}
			} elseif ( $on( 'enable_' . $form . '_recaptcha' ) && get_option( 'eael_recaptcha_sitekey' ) ) {
				return true;
			}
		}

		return $on( 'enable_cloudflare_turnstile' ) && $on( 'enable_cloudflare_turnstile_on_' . $form ) && get_option( 'eael_cloudflare_turnstile_sitekey' ) && get_option( 'eael_cloudflare_turnstile_secretkey' );
	}

	/**
	 * Print the captcha in a widget form
	 *
	 * @param object $widget The widget.
	 * @param string $form   login, lostpassword or register.
	 *
	 * @return void
	 */
	private function printCaptcha( $widget, $form ) {
		if ( ! $this->isProtected( $form ) || ! is_object( $widget ) || ! method_exists( $widget, 'get_settings_for_display' ) ) {
			return;
		}

		if ( $this->hasOwnCaptcha( $widget->get_settings_for_display(), $form ) ) {
			return;
		}

		echo '<div class="eael-lr-form-group hmwp-ea-captcha">';
		HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->getInstance()->form();
		echo '</div>';
	}

	public function loginCaptcha( $widget ) {
		$this->printCaptcha( $widget, 'login' );
	}

	public function lostPasswordCaptcha( $widget ) {
		$this->printCaptcha( $widget, 'lostpassword' );
	}

	public function registerCaptcha( $widget ) {
		$this->printCaptcha( $widget, 'register' );
	}

	/**
	 * The register form is printed through an allow-list: let the captcha fields through
	 *
	 * @param array $allowed The allowed HTML.
	 *
	 * @return array
	 */
	public function allowCaptchaHtml( $allowed ) {
		$allowed = (array) $allowed;
		$attrs   = array( 'class' => true, 'id' => true, 'style' => true );

		$allowed['div']    = array_merge( isset( $allowed['div'] ) ? (array) $allowed['div'] : array(), $attrs, array( 'data-sitekey' => true, 'data-theme' => true ) );
		$allowed['span']   = array_merge( isset( $allowed['span'] ) ? (array) $allowed['span'] : array(), $attrs );
		$allowed['strong'] = isset( $allowed['strong'] ) ? $allowed['strong'] : array();
		$allowed['input']  = array_merge( isset( $allowed['input'] ) ? (array) $allowed['input'] : array(), $attrs, array(
			'type' => true, 'name' => true, 'value' => true, 'size' => true, 'inputmode' => true, 'autocomplete' => true, 'aria-labelledby' => true,
		) );

		return $allowed;
	}

	/**
	 * The widget form being sent in this request
	 *
	 * @return string login, lostpassword, register or empty
	 */
	private function getSubmitForm() {
		foreach ( array( 'login' => 'eael-login-submit', 'lostpassword' => 'eael-lostpassword-submit', 'register' => 'eael-register-submit' ) as $form => $field ) {
			if ( HMWP_Classes_Tools::getIsset( $field ) ) {
				return $form;
			}
		}

		return '';
	}

	/**
	 * The settings of the widget that sent the form, read by Essential Addons itself
	 *
	 * @return array Empty when the widget can't be read
	 */
	private function getSubmitSettings() {
		$page_id   = (int) HMWP_Classes_Tools::getValue( 'page_id' );
		$widget_id = (string) HMWP_Classes_Tools::getValue( 'widget_id' );

		if ( ! $page_id || $widget_id === '' || ! class_exists( '\Essential_Addons_Elementor\Classes\Bootstrap' ) ) {
			return array();
		}

		$addons = \Essential_Addons_Elementor\Classes\Bootstrap::instance();

		return method_exists( $addons, 'lr_get_widget_settings' ) ? (array) $addons->lr_get_widget_settings( $page_id, $widget_id ) : array();
	}

	/**
	 * Login and lost password from the widget: check the WP Ghost captcha the form shows, not over the widget's own
	 *
	 * @param bool $check Check the captcha.
	 *
	 * @return bool
	 */
	public function captchaCheck( $check ) {
		$form = $this->getSubmitForm();

		if ( ! in_array( $form, array( 'login', 'lostpassword' ), true ) ) {
			return $check;
		}

		return ! $this->hasOwnCaptcha( $this->getSubmitSettings(), $form );
	}

	/**
	 * The widget shows a lost password error only under its own codes: pass the captcha message under one of them
	 *
	 * @param WP_Error $errors The lost password errors.
	 *
	 * @return WP_Error
	 */
	public function lostPasswordError( $errors ) {
		if ( $this->getSubmitForm() !== 'lostpassword' || ! is_wp_error( $errors ) || ! $errors->get_error_message( 'authentication_failed' ) ) {
			return $errors;
		}

		return new WP_Error( 'invalidcombo', $errors->get_error_message( 'authentication_failed' ) );
	}

	/**
	 * Registration from the widget: check the captcha before the widget creates the user
	 *
	 * @return void
	 * @throws Exception
	 */
	public function checkRegister() {
		if ( $this->getSubmitForm() !== 'register' || ! $this->isProtected( 'register' ) || HMWP_Classes_Tools::isLoggedInUser() ) {
			return;
		}

		// Only a real widget, as the widget itself checks
		$settings = $this->getSubmitSettings();
		if ( empty( $settings ) || $this->hasOwnCaptcha( $settings, 'register' ) ) {
			return;
		}

		/** @var HMWP_Models_Brute $bruteForceModel */
		$bruteForceModel = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' );
		$response        = $bruteForceModel->bruteForceCheck();

		if ( isset( $response['status'] ) && $response['status'] == 'whitelist' ) {
			return;
		}

		$error = $bruteForceModel->getInstance()->authenticate( null, $response );
		if ( ! is_wp_error( $error ) ) {
			return;
		}

		$message = wp_strip_all_tags( $error->get_error_message() );

		if ( wp_doing_ajax() ) {
			wp_send_json_error( $message );
		}

		// The widget shows its registration errors from this option, then sends the visitor back like the widget does
		$widget_id = (string) HMWP_Classes_Tools::getValue( 'widget_id' );
		update_option( 'eael_register_errors_' . $widget_id, array( 'captcha' => $message ), false );

		$page_id  = (int) HMWP_Classes_Tools::getValue( 'page_id' );
		$redirect = wp_get_referer() ? wp_get_referer() : get_permalink( $page_id );
		wp_safe_redirect( $redirect ? $redirect : home_url( '/' ) );
		exit();
	}

	/**
	 * The widget's email code login: WP Ghost doesn't ask for its own 2FA on top
	 *
	 * @param bool $skip Skip the WP Ghost 2FA.
	 *
	 * @return bool
	 */
	public function skipTwoFactor( $skip ) {
		if ( wp_doing_ajax() && HMWP_Classes_Tools::getValue( 'action' ) === 'eael_lr_verify_otp' ) {
			return true;
		}

		return $skip;
	}

}
