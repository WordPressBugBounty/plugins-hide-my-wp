<?php
/**
 * Compatibility Class
 *
 * @file The Others Model file
 * @package HMWP/Compatibility/Others
 * @since 7.0.0
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Compatibility_Others extends HMWP_Models_Compatibility_Abstract {

	public function __construct() {
		parent::__construct();

		//Compatibility with iThemes security plugin
		if ( HMWP_Classes_Tools::isPluginActive( 'wps-hide-login/wps-hide-login.php' ) ) {
			if ( $whl_page = get_option( 'whl_page' ) ) {
				defined( 'HMWP_DEFAULT_LOGIN' ) || define( 'HMWP_DEFAULT_LOGIN', $whl_page );
				HMWP_Classes_Tools::$options['hmwp_login_url'] = HMWP_Classes_Tools::$default['hmwp_login_url'];
			}
		}

		//Add Compatibility with PPress plugin
		//Load the post from Ppress for the login page
		if ( HMWP_Classes_Tools::isPluginActive( 'ppress/profilepress.php' ) ) {

			if ( 'logout' <> HMWP_Classes_Tools::getValue( 'action' ) ) {

				add_action( 'hmwp_login_init', function() {
					//Add compatibility with PPress plugin
					$data = get_option( 'pp_settings_data' );
					if ( class_exists( 'WP_Query' ) && isset( $data['set_login_url'] ) && (int) $data['set_login_url'] > 0 ) {
						$query = new WP_Query( array( 'p' => $data['set_login_url'], 'post_type' => 'any' ) );
						if ( $query->have_posts() ) {
							$query->the_post();
							get_header();
							the_content();
							get_footer();
						}
						exit();
					}

				} );

			}

		}

		//Compatibility with Smart Slider
		if ( HMWP_Classes_Tools::isPluginActive( 'smart-slider-3/smart-slider-3.php' ) || HMWP_Classes_Tools::isPluginActive( 'nextend-smart-slider3-pro/nextend-smart-slider3-pro.php' ) ) {
			add_filter( 'hmwp_priority_buffer', '__return_true' );
		}

		//Compatibility with Fluent CRM - tested 11162021
		if ( HMWP_Classes_Tools::isPluginActive( 'fluent-crm/fluent-crm.php' ) || HMWP_Classes_Tools::isPluginActive( 'fluent-smtp/fluent-smtp.php' ) ) {
			add_filter( 'hmwp_option_hmwp_hideajax_paths', '__return_false' );
		}

		// Tutor LMS prints admin addresses in its front-end script data; visitors don't need them
		if ( HMWP_Classes_Tools::isPluginActive( 'tutor/tutor.php' ) ) {
			add_filter( 'tutor_localize_data', array( $this, 'fixTutorAdminUrls' ) );
		}

		// Theme My Login serves its form on /login, which WP Ghost hides; use the custom login path instead
		if ( HMWP_Classes_Tools::isPluginActive( 'theme-my-login/theme-my-login.php' ) ) {
			add_filter( 'tml_get_action_slug', array( $this, 'fixThemeMyLoginSlug' ), 10, 2 );
		}

		//Check if login auth check is required
		add_filter( 'hmwp_preauth_captcha_check', array( $this, 'fixRecaptchaCheck' ) );

		// Once after an update: rebuild the Yoast data of the "login" page
		if ( get_transient( 'hmwp_refresh_login_page' ) ) {
			add_action( 'wp_loaded', array( $this, 'refreshLoginPageSeo' ) );
		}

	}

	/**
	 * Rebuild the Yoast SEO data (canonical, schema) of a page named "login" after an update
	 *
	 * @return void
	 */
	public function refreshLoginPageSeo() {
		delete_transient( 'hmwp_refresh_login_page' );

		$page = get_page_by_path( 'login' );
		if ( ! $page || ! function_exists( 'YoastSEO' ) || ! class_exists( '\Yoast\WP\SEO\Builders\Indexable_Builder' ) || ! class_exists( '\Yoast\WP\SEO\Repositories\Indexable_Repository' ) ) {
			return;
		}

		try {
			$indexable = YoastSEO()->classes->get( 'Yoast\WP\SEO\Repositories\Indexable_Repository' )->find_by_id_and_type( $page->ID, 'post' );
			if ( $indexable ) {
				$indexable = YoastSEO()->classes->get( 'Yoast\WP\SEO\Builders\Indexable_Builder' )->build_for_id_and_type( $page->ID, 'post', $indexable );
				if ( $indexable ) {
					$indexable->save();
				}
			}
		} catch ( Throwable $e ) {
		}
	}

	public function hookAdmin() {

		//Compatibility with Breakdance plugin
		if ( HMWP_Classes_Tools::isAjax() ) {
			if ( HMWP_Classes_Tools::getValue( 'action' ) == 'query-attachments' || HMWP_Classes_Tools::getValue( 'action' ) == 'breakdance_load_document' || HMWP_Classes_Tools::getValue( 'action' ) == 'breakdance_image_metadata' || HMWP_Classes_Tools::getValue( 'action' ) == 'breakdance_image_sizes' ) {
				//Stop the plugin from loading while on editor
				add_filter( 'hmwp_process_buffer', '__return_false' );
			}
		}

	}

	public function hookFrontend() {

		//don't hide login path on CloudPanel and WP Engine
		if ( HMWP_Classes_Tools::isCloudPanel() || HMWP_Classes_Tools::isWpengine() ) {
			add_filter( 'hmwp_option_hmwp_hide_login', '__return_false' );
		}

		//If in preview mode of the front page
		if ( HMWP_Classes_Tools::getValue( 'hmwp_preview' ) == HMWP_Classes_Tools::getOption( 'hmwp_disable_name' ) ) {
			$_COOKIE = array();
			@header_remove( "Cookie" );
			add_filter( 'show_admin_bar', '__return_false' ); //phpcs:ignore
		}

		//Hook the Hide URLs before the plugin
		//Check params and compatibilities
		add_action( 'plugins_loaded', array( $this, 'fixHideUrls' ), 10 );


		//Compatibility with CDN Enabler - tested 01102021
		if ( HMWP_Classes_Tools::isPluginActive( 'cdn-enabler/cdn-enabler.php' ) ) {
			add_filter( 'hmwp_laterload', '__return_true' );
		}

		//Compatibility with Comet Cache - tested 01102021
		if ( HMWP_Classes_Tools::isPluginActive( 'comet-cache/comet-cache.php' ) ) {
			if ( ! defined( 'COMET_CACHE_DEBUGGING_ENABLE' ) ) {
				define( 'COMET_CACHE_DEBUGGING_ENABLE', false );
			}
		}

		//Compatibility with Hyper Cache plugin - tested 01102021
		if ( HMWP_Classes_Tools::isPluginActive( 'hyper-cache/plugin.php' ) ) {
			add_filter( 'cache_buffer', array( $this, 'findReplaceCache' ), PHP_INT_MAX );
		}

		//compatibility with Wp Maintenance plugin - tested 01102021
		if ( HMWP_Classes_Tools::isPluginActive( 'wp-maintenance-mode/wp-maintenance-mode.php' ) ) {
			add_filter( 'wpmm_footer', array( $this, 'findReplaceBuffer' ) );
		}

		//Compatibility with Oxygen - tested 01102021
		if ( HMWP_Classes_Tools::isPluginActive( 'oxygen/functions.php' ) ) {
			add_filter( 'hmwp_laterload', '__return_true' );
		}

		//compatibility with Wp Bakery - tested 01102021
		if ( HMWP_Classes_Tools::isPluginActive( 'js_composer/js_composer.php' ) ) {
			add_filter( 'hmwp_option_hmwp_hide_styleids', '__return_false' );
		}

		//Patch for WOT Cache plugin
		if ( defined( 'WOT_VERSION' ) ) {
			add_filter( 'wot_cache', array( $this, 'findReplaceCache' ), PHP_INT_MAX );
		}

		//For woo-global-cart plugin
		if ( defined( 'WOOGC_VERSION' ) ) {
			remove_all_actions( 'shutdown', 1 );
			//Hook the cached buffer
			add_filter( 'hmwp_buffer', array( $this, 'fix_woogc_shutdown' ) );
		}

		//Compatibility with XMl Sitemap - tested 12042023
		//Hide the author in other sitemap plugins
		if ( HMWP_Classes_Tools::getOption( 'hmwp_hide_in_sitemap' ) && HMWP_Classes_Tools::getOption( 'hmwp_hide_author_in_sitemap' ) && isset( $_SERVER['REQUEST_URI'] ) ) {

			//XML Sitemap
			if ( HMWP_Classes_Tools::isPluginActive( 'google-sitemap-generator/sitemap.php' ) ) {
				add_action( 'sm_build_index', array(
					HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' ),
					'findReplaceXML'
				) );
				add_action( 'sm_build_content', array(
					HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' ),
					'findReplaceXML'
				) );
			}

			if ( HMWP_Classes_Tools::isPluginActive( 'squirrly-seo/squirrly.php' ) ) {
				add_filter( "sq_sitemap_style", "__return_false", 11 );
			}

			//Yoast sitemap
			if ( HMWP_Classes_Tools::isPluginActive( 'wordpress-seo/wp-seo.php' ) ) {
				add_filter( "wpseo_stylesheet_url", "__return_false" );
			}

			//Rank Math sitemap
			if ( HMWP_Classes_Tools::isPluginActive( 'seo-by-rank-math/rank-math.php' ) ) {
				if ( strpos( wp_unslash( $_SERVER['REQUEST_URI'] ), '.xsl' ) === false ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					if ( $type = str_replace( array( 'sitemap', '-', '_', '.xml', '/' ), '', strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
						if ( $type == 'index' ) {
							$type = 1;
						}
						add_filter( "rank_math/sitemap/{$type}_stylesheet_url", "__return_false" );
						add_filter( 'rank_math/sitemap/remove_credit', array(
							HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' ),
							'findReplaceXML'
						) );
					}
				}
				add_filter( "rank_math/sitemap/remove_credit", "__return_true" );
			}

			//SeoPress
			if ( HMWP_Classes_Tools::isPluginActive( 'wp-seopress/seopress.php' ) ) {
				add_filter( "seopress_sitemaps_xml_index", array( $this, 'findReplaceCache' ), PHP_INT_MAX );
				add_filter( "seopress_sitemaps_xml_author", array( $this, 'findReplaceCache' ), PHP_INT_MAX );
				add_filter( "seopress_sitemaps_xml_single_term", array( $this, 'findReplaceCache' ), PHP_INT_MAX );
				add_filter( "seopress_sitemaps_xml_single", array( $this, 'findReplaceCache' ), PHP_INT_MAX );
			}

			//WordPress default sitemap
			add_filter( "wp_sitemaps_stylesheet_url", "__return_false" );
			add_filter( "wp_sitemaps_stylesheet_index_url", "__return_false" );

		}

		//Change the template directory URL in themes
		if ( ! HMWP_Classes_Tools::isCachePlugin() ) {
			if ( ( HMWP_Classes_Tools::isThemeActive( 'Avada' ) || HMWP_Classes_Tools::isThemeActive( 'WpRentals' ) ) && ! HMWP_Classes_Tools::getOption( 'hmwp_mapping_file' ) ) {
				add_filter( 'template_directory_uri', array( HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' ), 'find_replace_url' ), PHP_INT_MAX );
			}
		}

		//Gravity Form security fix
		add_filter( 'wp_redirect', function( $redirect, $status = '' ) {
			//prevent redirect to new login
			if ( HMWP_Classes_Tools::getDefault( 'hmwp_login_url' ) <> HMWP_Classes_Tools::getOption( 'hmwp_login_url' ) ) {
				if ( HMWP_Classes_Tools::getValue( 'gf_page' ) ) {
					if ( strpos( $redirect, '/' . HMWP_Classes_Tools::getOption( 'hmwp_login_url' ) ) !== false ) {
						if ( ! HMWP_Classes_Tools::isLoggedInUser() ) {
							$redirect = home_url();
						}
					}
				}
			}

			return $redirect;
		}, PHP_INT_MAX, 2 );

		// Prevent Kadence from loading the script rules in frontend
		if ( HMWP_Classes_Tools::isPluginActive( 'kadence-blocks/kadence-blocks.php' ) || HMWP_Classes_Tools::isPluginActive( 'kadence-blocks-pro/kadence-blocks-pro.php' ) ) {
			add_filter( 'hmwp_buffer', function( $buffer ) {
				return preg_replace('/<script type="speculationrules">.*?<\/script>/s', '', $buffer);
			} );
		}

		// Add Riode theme compatibility on comment recaptcha
		if (  HMWP_Classes_Tools::getOption( 'hmwp_bruteforce' ) && HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_comments' ) ) {
			add_filter( 'riode_filter_comment_form_args',  array(HMWP_Classes_ObjController::getClass( 'HMWP_Models_Bruteforce_Comments' ), 'formArgs'), 99 );

			if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_woocommerce' ) ) {
				// Load brute force comments on Woocommerce reviews
				add_filter( 'woocommerce_product_review_comment_form_args', array(HMWP_Classes_ObjController::getClass( 'HMWP_Models_Bruteforce_Comments' ), 'formArgs'), 99 );
			}
		}

		// Add compatibility with Debloat
		if ( HMWP_Classes_Tools::isPluginActive( 'debloat/debloat.php' ) ) {
			add_filter( 'hmwp_priority_hook', function( $priority ) { return -1000; } );
		}

		// Add compatibility with Photo Gallery
		if ( HMWP_Classes_Tools::isPluginActive( 'photo-gallery/photo-gallery.php' ) ) {
			add_action('wp_enqueue_scripts', function () {
				// main script that generates twbbwg-global-js-extra
				wp_dequeue_script('twbbwg-global');
				wp_deregister_script('twbbwg-global');

			}, 999);
		}

		// Add compatibility with Blocksy theme
		if ( HMWP_Classes_Tools::isThemeActive( 'Blocksy' ) ) {

			add_action( 'wp_enqueue_scripts', function () {
				global $wp_scripts;

				if ( ! isset( $wp_scripts->registered['ct-scripts'] ) ) {
					return;
				}

				$data = $wp_scripts->registered['ct-scripts']->extra['data'] ?? '';

				if ( empty( $data ) ) {
					return;
				}

				// Remove the variable assignment wrapper to get pure JSON
				$json_str = preg_replace( '/^var ct_localizations=/', '', $data );
				$json_str = rtrim( $json_str, ';' );

				$decoded = json_decode( $json_str, true );

				if ( ! $decoded ) {
					return;
				}

				// Walk through dynamic_js_chunks and remove wp-admin refs from deps_data
				if ( isset( $decoded['dynamic_js_chunks'] ) ) {
					foreach ( $decoded['dynamic_js_chunks'] as &$chunk ) {
						if ( isset( $chunk['deps_data'] ) ) {
							foreach ( $chunk['deps_data'] as $key => $url ) {
								if ( strpos( $url, HMWP_Classes_Tools::getOption( 'hmwp_admin_url' ) ) !== false ||
								     strpos( $url, HMWP_Classes_Tools::getDefault( 'hmwp_admin_url' ) ) !== false  ) {
									// Replace with the actual bundled version or remove
									unset( $chunk['deps_data'][ $key ] );
								}
							}
						}
					}
				}

				// Re-encode and replace
				$wp_scripts->registered['ct-scripts']->extra['data'] =
					'var ct_localizations=' . json_encode( $decoded ) . ';';

			}, 999 );
		}
	}

	/**
	 * Check if WordPress serves the request as a front-end page
	 *
	 * @param string $url Request path
	 *
	 * @return bool
	 */
	public function isFrontPageRequest( $url ) {
		$script = isset( $_SERVER['SCRIPT_FILENAME'] ) ? wp_normalize_path( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) : ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path   = str_replace( '\\', '/', rawurldecode( rawurldecode( $url ) ) );

		// Served by the front index.php, and no dot segment that the server resolves (/login/../wp-login.php)
		if ( basename( $script ) !== 'index.php' || strpos( $path, '/.' ) !== false ) {
			return false;
		}

		foreach ( array( HMWP_Classes_Tools::getDefault( 'hmwp_admin_url' ), 'wp-includes', 'wp-content' ) as $dir ) {
			if ( strpos( $script, '/' . $dir . '/' ) !== false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check the URL params and compatibilities before hiding the paths
	 *
	 * @return void
	 */
	public function fixHideUrls() {

		$url = ( isset( $_SERVER['REQUEST_URI'] ) ? untrailingslashit( strtok( wp_unslash( $_SERVER["REQUEST_URI"] ), '?' ) ) : false ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		//Compatibility with iThemes Security, Temporary Login Plugin, LoginPress, Wordfence
		if ( $url && ! HMWP_Classes_Tools::isLoggedInUser() ) {

			// /login, /dashboard and /admin are hidden as WordPress shortcuts, but a published page with that
			// address (membership, LMS, shop account pages) has to load
			if ( $this->isFrontPageRequest( $url ) ) {
				foreach ( array( 'login', 'dashboard', 'admin' ) as $slug ) {
					if ( strpos( $url . '/', home_url( $slug, 'relative' ) . '/' ) === 0 && ( $page = get_page_by_path( $slug ) ) && $page->post_status === 'publish' ) {
						add_filter( 'hmwp_process_hide_urls', '__return_false' );
					}
				}
			}

			// Only a real temporary login token lifts the hiding
			$wtlwp_token = (string) HMWP_Classes_Tools::getValue( 'wtlwp_token' );
			if ( $wtlwp_token !== '' && HMWP_Classes_Tools::isPluginActive( 'temporary-login-without-password/temporary-login-without-password.php' ) ) {
				if ( get_users( array( 'meta_key' => '_wtlwp_token', 'meta_value' => $wtlwp_token, 'number' => 1, 'fields' => 'ID' ) ) ) {
					add_filter( 'hmwp_process_hide_urls', '__return_false' );
				}
			}

			// These tokens can't be checked here, so they never unhide the default login and admin paths
			$default_path = HMWP_Classes_Tools::searchInString( $url, array(
				home_url( HMWP_Classes_Tools::getDefault( 'hmwp_login_url' ), 'relative' ),
				site_url( HMWP_Classes_Tools::getDefault( 'hmwp_login_url' ), 'relative' ),
				home_url( HMWP_Classes_Tools::getDefault( 'hmwp_admin_url' ), 'relative' ),
				site_url( HMWP_Classes_Tools::getDefault( 'hmwp_admin_url' ), 'relative' ),
			) );

			//?aam-jwt=value
			if ( ! $default_path && HMWP_Classes_Tools::getValue( 'aam-jwt' ) && HMWP_Classes_Tools::isPluginActive( 'advanced-access-manager/aam.php' ) ) {
				add_filter( 'hmwp_process_hide_urls', '__return_false' );
			}

			if ( ! $default_path && HMWP_Classes_Tools::getValue( 'loginpress_code' ) && HMWP_Classes_Tools::isPluginActive( 'loginpress/loginpress.php' ) ) {
				add_filter( 'hmwp_process_hide_urls', '__return_false' );
			}

			//AI assistant sign-in (MCP OAuth with PKCE, any plugin on the common /oauth/authorize path) sends the visitor to the login page
			if ( $url == home_url( 'oauth/authorize', 'relative' ) && HMWP_Classes_Tools::isMcpAuthorizeRequest() ) {
				add_filter( 'hmwp_process_hide_urls', '__return_false' );
			}

		}
	}

	/**
	 * Keep the custom admin path out of Tutor LMS data on pages for logged-out visitors
	 *
	 * @param array $data Tutor script data
	 *
	 * @return array
	 */
	public function fixTutorAdminUrls( $data ) {
		if ( ! is_array( $data ) || is_admin() || HMWP_Classes_Tools::isLoggedInUser() ||
		     HMWP_Classes_Tools::getDefault( 'hmwp_admin_url' ) == HMWP_Classes_Tools::getOption( 'hmwp_admin_url' ) ) {
			return $data;
		}

		if ( isset( $data['loading_icon_url'] ) ) {
			$data['loading_icon_url'] = includes_url( 'images/spinner.gif' );
		}
		if ( isset( $data['course_list_page_url'] ) ) {
			$data['course_list_page_url'] = '';
		}

		return $data;
	}

	/**
	 * Theme My Login keeps its default /login slug, which WP Ghost hides; use the custom login path instead
	 *
	 * @param string $slug The action slug
	 * @param string $name The action name
	 *
	 * @return string
	 */
	public function fixThemeMyLoginSlug( $slug, $name = '' ) {
		if ( $name === 'login' && $slug === 'login' && HMWP_Classes_Tools::getOption( 'hmwp_hide_login' ) &&
		     HMWP_Classes_Tools::getDefault( 'hmwp_login_url' ) <> HMWP_Classes_Tools::getOption( 'hmwp_login_url' ) ) {
			return HMWP_Classes_Tools::getOption( 'hmwp_login_url' );
		}

		return $slug;
	}

	/**
	 * Fix compatibility on brute force recaptcha
	 *
	 * @param $check
	 *
	 * @return bool
	 */
	public function fixRecaptchaCheck( $check ) {
		global $hmwp_bruteforce;

		//check if the shortcode was called
		if ( isset( $hmwp_bruteforce ) && $hmwp_bruteforce ) {
			return true;
		}

		$url       = ( isset( $_SERVER['REQUEST_URI'] ) ? untrailingslashit( strtok( wp_unslash( $_SERVER["REQUEST_URI"] ), '?' ) ) : false ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// AJAX login forms have no captcha field; the lockout and ban checks still apply to them
		if ( HMWP_Classes_Tools::isAjax() ) {
			return false;
		}

		$http_post = ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' == $_SERVER['REQUEST_METHOD'] );

		//check the brute force
		if ( $check && $url && $http_post && ! HMWP_Classes_Tools::getIsset( 'brute_ck' ) && ! HMWP_Classes_Tools::getIsset( 'g-recaptcha-response' ) ) {

			if ( HMWP_Classes_Tools::getDefault( 'hmwp_login_url' ) <> HMWP_Classes_Tools::getOption( 'hmwp_login_url' ) ) {
				$paths   = array();
				$paths[] = '/' . HMWP_Classes_Tools::getDefault( 'hmwp_login_url' );
				$paths[] = '/' . HMWP_Classes_Tools::getOption( 'hmwp_login_url' );

				//add lostpass path
				if ( HMWP_Classes_Tools::getOption( 'hmwp_lostpassword_url' ) <> '' ) {
					$paths[] = '/' . HMWP_Classes_Tools::getOption( 'hmwp_lostpassword_url' );
				}

				//add register path
				if ( HMWP_Classes_Tools::getOption( 'hmwp_register_url' ) <> '' ) {
					$paths[] = '/' . HMWP_Classes_Tools::getOption( 'hmwp_register_url' );
				}

                //integrate with woocommerce only when Lite Mode or ghost Mode
                if ( HMWP_Classes_Tools::getOption( 'hmwp_mode' ) <> 'default' ) {
                    if ( $post_id = get_option( 'woocommerce_myaccount_page_id' ) ) {
                        if ( $post = get_post( $post_id ) ) {
                            $paths[] = '/' . $post->post_name;
                        }
                    }
                }

				if ( ! HMWP_Classes_Tools::searchInString( $url, $paths ) ) {
					return false;
				}

			}

		}

		return $check;
	}

	/**
	 * Fix compatibility with WooGC plugin
	 *
	 * @param $buffer
	 *
	 * @return mixed
	 */
	public function fix_woogc_shutdown( $buffer ) {
		global $blog_id, $woocommerce, $WooGC;

		if ( ! class_exists( 'WooGC' ) ) {
			return $buffer;
		}

		if ( ! is_object( $woocommerce->cart ) ) {
			return $buffer;
		}

		if ( class_exists( 'WooGC' ) ) {
			if ( $WooGC && ! $WooGC instanceof WooGC ) {
				return $buffer;
			}
		}

		$options      = $WooGC->functions->get_options();
		$blog_details = get_blog_details( $blog_id );

		//replace any checkout links
		if ( ! empty( $options['cart_checkout_location'] ) && $options['cart_checkout_location'] != $blog_id ) {
			$checkout_url = $woocommerce->cart->get_checkout_url();
			$checkout_url = str_replace( array( 'http:', 'https:' ), "", $checkout_url );
			$checkout_url = trailingslashit( $checkout_url );

			$buffer = str_replace( $blog_details->domain . "/checkout/", $checkout_url, $buffer );

		}

		return $buffer;
	}


}
