<?php
/**
 * Files Handle Model
 * Called to handle the files when they are not found
 *
 * @file  The Files Handle file
 * @package HMWP/FilesModel
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Files {

	protected $_files = array();
	protected $_safe_files = array();
	protected $_replace = array();
	protected $_rewrites = array();
	/**
	 * @var bool True when the last mapped address held a changed path
	 */
	protected $_mapped = false;
	protected $_site_url = null;
	protected $_site_hosts = array();
	protected $_fingerprint = null;
	protected $_cachedir = null;
	protected $_request = array();

	public function __construct() {
		//The list of handled file extensions
		$this->_files = array(
			'jpg',
			'jpeg',
			'png',
			'bmp',
			'gif',
			'jp2',
			'weba',
			'webp',
			'webm',
			'css',
			'scss',
			'js',
			'woff',
			'woff2',
			'ttf',
			'otf',
			'pfb',
			'pfm',
			'tfil',
			'eot',
			'svg',
			'pdf',
			'doc',
			'docx',
			'csv',
			'xls',
			'xslx',
			'mp2',
			'mp3',
			'mp4',
			'mpeg',
			'zip',
			'rar',
			'map'
		);

		//the safe extensions for static files
		$this->_safe_files = array_map( function ( $file ) {
			return $file . 'h';
		}, $this->_files );

		//init the replacement array
		$this->_replace = array( 'from' => [], 'to' => [] );
	}

	/**
	 * Show the file if in the list of extensions
	 *
	 * @throws Exception
	 */
	public function maybeShowFile() {
		//If WordPress handles the file
		//Show it if was changed by HMWP
		if ( $this->isFile( $this->getCurrentURL() ) ) {
			$this->showFile( $this->getCurrentURL() );
		}

	}

	/**
	 * Check if the current URL is a file
	 *
	 * @throws Exception
	 */
	public function maybeShowNotFound() {
		// If the file doesn't exist,
		// show the file content
		if ( is_404() ) {
			$this->showFile( $this->getCurrentURL() );
		} else {
			$this->maybeShowLogin( $this->getCurrentURL() );
		}

	}

	/**
	 * Check if the current path is the login path
	 *
	 * @param $url
	 *
	 * @return void
	 */
	public function maybeShowLogin( $url ) {
		//Remove the query from URL
		$url_no_query = ( ( strpos( $url, '?' ) !== false ) ? substr( $url, 0, strpos( $url, '?' ) ) : $url );

		if ( strpos( trailingslashit( $url_no_query ), '/' . HMWP_Classes_Tools::getOption( 'hmwp_login_url' ) . '/' ) || strpos( trailingslashit( $url_no_query ), '/' . HMWP_Classes_Tools::getDefault( 'hmwp_login_url' ) . '/' ) ) {

			add_filter( 'hmwp_option_hmwp_remove_third_hooks', '__return_true' );

			header( "HTTP/1.1 200 OK" );

			$this->handleLogin( $url );
		}
	}

	/**
	 * If the rewrite config is not set
	 * If there is a new file path, change it back to real path and show the file
	 * Prevents errors when the paths are chnged but the rewrite config is not set up correctly
	 *
	 * @param $url
	 *
	 * @return bool|string
	 */
	public function isFile( $url ) {

		if ( $url <> '' ) {
			if ( strpos( $url, '?' ) !== false ) {
				$url = substr( $url, 0, strpos( $url, '?' ) );
			}
			if ( strrpos( $url, '.' ) !== false ) {
				$ext = substr( $url, strrpos( $url, '.' ) + 1 );
				if ( in_array( $ext, $this->_files ) || in_array( $ext, $this->_safe_files ) ) {
					return $ext;
				}
			}
		}

		return false;
	}

	/**
	 * Get the current URL
	 *
	 * @return string
	 */
	public function getCurrentURL() {
		$url = '';

		// Use the site host, the Host header is set by the client
		if ( ! isset( $this->_site_url ) ) {
			$home            = wp_parse_url( home_url() );
			$this->_site_url = ( isset( $home['host'] ) && $home['host'] <> '' ? set_url_scheme( 'http://' . $home['host'] . ( isset( $home['port'] ) && $home['port'] ? ':' . (int) $home['port'] : '' ) ) : '' );
		}

		if ( $this->_site_url <> '' ) {
			// build the URL in the address bar
			$url = $this->_site_url;
			// The server rules send only the CSS and JS files through hmwp_url
			if ( HMWP_Classes_Tools::getOption( 'hmwp_mapping_text_show' ) &&
			     HMWP_Classes_Tools::getOption( 'hmwp_mapping_file' ) &&
			     preg_match( '/^[^?#]+\.(js|css|scss)$/', (string) HMWP_Classes_Tools::getValue( 'hmwp_url' ) ) ) {
				$url .= HMWP_Classes_Tools::getValue( 'hmwp_url' );
			} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$url .= rawurldecode( wp_unslash( $_SERVER['REQUEST_URI']) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
		}

		return $url;
	}

	/**
	 * Get the host and the port of an URL in a comparable format
	 *
	 * @param  string  $url  The URL to extract the authority from
	 *
	 * @return string The lowercase host, with the port appended when it's not the default one
	 */
	public function getUrlAuthority( $url ) {

		$parse_url = wp_parse_url( $url );

		if ( ! isset( $parse_url['host'] ) || $parse_url['host'] == '' ) {
			return '';
		}

		$authority = strtolower( $parse_url['host'] );

		if ( isset( $parse_url['port'] ) && $parse_url['port'] && ! in_array( (int) $parse_url['port'], array( 80, 443 ), true ) ) {
			$authority .= ':' . (int) $parse_url['port'];
		}

		return $authority;
	}

	/**
	 * Check if the URL points to the current site
	 *
	 * @param  string  $url  The URL to verify
	 *
	 * @return bool
	 */
	public function isSiteUrl( $url ) {

		$authority = $this->getUrlAuthority( $url );

		if ( $authority == '' ) {
			return false;
		}

		// The site hosts can't change during the request
		if ( empty( $this->_site_hosts ) ) {

			$allowed = array();

			foreach ( array( home_url(), site_url(), network_home_url(), network_site_url() ) as $site_url ) {
				if ( $site_authority = $this->getUrlAuthority( $site_url ) ) {
					$allowed[] = $site_authority;
				}
			}

			$this->_site_hosts = (array) apply_filters( 'hmwp_files_allowed_hosts', array_unique( $allowed ) );
		}

		return in_array( $authority, $this->_site_hosts, true );
	}

	/**
	 * Get the current request headers which can be forwarded to the site
	 *
	 * @return array
	 */
	public function getForwardHeaders() {

		$headers = array();

		if ( function_exists( 'getallheaders' ) ) {
			$headers = (array) getallheaders();
		} else {
			foreach ( $_SERVER as $key => $value ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( strpos( $key, 'HTTP_' ) === 0 ) {
					$name             = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $key, 5 ) ) ) ) );
					$headers[ $name ] = wp_unslash( $value );
				}
			}
		}

		// Drop the Host header so the client can't pick the vhost of the internal request
		$skip = array(
			'host',
			'connection',
			'keep-alive',
			'proxy-authenticate',
			'proxy-authorization',
			'te',
			'trailer',
			'transfer-encoding',
			'upgrade',
			'content-length',
			'accept-encoding',
		);

		foreach ( $headers as $name => $value ) {
			if ( in_array( strtolower( $name ), $skip, true ) ) {
				unset( $headers[ $name ] );
			}
		}

		// Mark the loopback so a nested showFile() won't proxy it again and loop
		$headers['X-HMWP-Loopback'] = '1';

		return apply_filters( 'hmwp_files_request_headers', $headers );
	}

	/**
	 * Check if a response header can be sent back to the browser
	 *
	 * @param  string  $name  The header name
	 *
	 * @return bool
	 */
	public function isAllowedHeader( $name ) {

		// The body is already decoded by WordPress so the hop-by-hop headers would break the response
		$skip = apply_filters( 'hmwp_files_skip_headers', array(
			'content-encoding',
			'content-length',
			'transfer-encoding',
			'connection',
			'keep-alive',
			'te',
			'trailer',
			'upgrade',
			'proxy-authenticate',
			'proxy-authorization',
		) );

		return ! in_array( strtolower( $name ), (array) $skip, true );
	}

	/**
	 * Get the response headers which can be sent back to the browser
	 *
	 * @param  array|WP_Error  $response  The remote response
	 *
	 * @return array
	 */
	public function getResponseHeaders( $response ) {

		$headers = array();

		foreach ( wp_remote_retrieve_headers( $response ) as $key => $value ) {

			if ( ! $this->isAllowedHeader( $key ) ) {
				continue;
			}

			if ( ! is_array( $value ) ) {
				$headers[] = "$key: $value";
			} else {
				foreach ( $value as $v ) {
					$headers[] = "$key: $v";
				}
			}
		}

		return apply_filters( 'hmwp_files_response_headers', $headers, $response );
	}

	/**
	 * Fingerprint of everything that changes the mapped file content
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getMappingFingerprint() {

		if ( ! isset( $this->_fingerprint ) ) {

			/** @var HMWP_Models_Rewrite $rewriteModel */
			$rewriteModel = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' );

			// Build the map the same way find_replace_url does it
			if ( empty( $rewriteModel->_replace ) ) {
				$rewriteModel->buildRedirect();
				$rewriteModel->prepareFindReplace();
			}

			// The map holds the CDN and the third party changes, so it covers more than the plugin options
			$this->_fingerprint = md5( wp_json_encode( array(
				$rewriteModel->_replace,
				HMWP_Classes_Tools::getTextMapping(),
				HMWP_Classes_Tools::getOption( 'hmwp_mapping_classes' ),
				HMWP_Classes_Tools::getOption( 'hmwp_mapping_text_show' ),
				HMWP_Classes_Tools::getOption( 'hmwp_mapping_file' ),
				HMWP_Classes_Tools::isLoggedInUser(),
				( is_multisite() ? get_current_blog_id() : 0 ),
			) ) );
		}

		return $this->_fingerprint;
	}

	/**
	 * Build the cache validator for a file
	 *
	 * @param  string  $path  The file path on the server
	 * @param  bool  $mapped  True when the content is changed before it's sent
	 *
	 * @return array The etag and the last modified time
	 * @throws Exception
	 */
	public function getFileValidator( $path, $mapped = false ) {

		// Initialize WordPress Filesystem
		$wp_filesystem = HMWP_Classes_ObjController::initFilesystem();

		$mtime = (int) $wp_filesystem->mtime( $path );
		$size  = (int) $wp_filesystem->size( $path );

		// The mapped content changes with the settings, add them in the validator
		$fingerprint = ( $mapped ? $this->getMappingFingerprint() : '' );

		return array(
			'etag'  => md5( $path . '|' . $mtime . '|' . $size . '|' . $fingerprint ),
			'mtime' => $mtime,
			'size'  => $size,
		);
	}

	/**
	 * Check if the browser already has the current version of the file
	 *
	 * @param  string  $etag  The current etag
	 * @param  int  $mtime  The last modified time
	 *
	 * @return bool
	 */
	public function isNotModified( $etag, $mtime ) {

		// The etag has priority over the modified time
		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$match = trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( $match <> '' ) {
				// The browser can send more etags and the W/ prefix for the weak validators
				foreach ( explode( ',', $match ) as $value ) {
					$value = trim( str_replace( array( 'W/', '"' ), '', $value ) );
					if ( $value <> '' && $value === $etag ) {
						return true;
					}
				}

				return false;
			}
		}

		if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && $mtime > 0 ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$since = strtotime( trim( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( $since !== false && $mtime <= $since ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the compression accepted by the browser
	 *
	 * @return string br, gzip or empty when the content is sent as it is
	 */
	public function getAcceptedEncoding() {

		// Don't compress twice when the server already does it
		if ( ini_get( 'zlib.output_compression' ) ) {
			return '';
		}

		if ( ! isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return '';
		}

		$accepted = array();

		foreach ( explode( ',', strtolower( wp_unslash( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ) ) as $value ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$parts  = explode( ';', $value );
			$name   = trim( $parts[0] );
			$weight = 1;

			// Skip the encodings the browser refuses with q=0
			if ( isset( $parts[1] ) && strpos( $parts[1], 'q=' ) !== false ) {
				$weight = (float) str_replace( 'q=', '', trim( $parts[1] ) );
			}

			if ( $name <> '' && $weight > 0 ) {
				$accepted[] = $name;
			}
		}

		if ( in_array( 'br', $accepted, true ) && function_exists( 'brotli_compress' ) ) {
			return 'br';
		}

		if ( in_array( 'gzip', $accepted, true ) && function_exists( 'gzencode' ) ) {
			return 'gzip';
		}

		return '';
	}

	/**
	 * Get the directory where the changed CSS and JS files are kept
	 *
	 * It's never under wp-content/cache, that directory is scanned and rewritten
	 * by checkCacheFiles when no known cache plugin is installed.
	 *
	 * @return string The cache directory with trailing slash, empty when it can't be used
	 */
	public function getCacheDir() {

		if ( isset( $this->_cachedir ) ) {
			return $this->_cachedir;
		}

		$this->_cachedir = '';

		$uploads = wp_upload_dir( null, false );

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return $this->_cachedir;
		}

		$dir = trailingslashit( $uploads['basedir'] ) . 'wp-ghost-cache/';
		$dir = apply_filters( 'hmwp_files_cache_dir', $dir );

		// Initialize WordPress Filesystem
		$wp_filesystem = HMWP_Classes_ObjController::initFilesystem();

		if ( ! $wp_filesystem->is_dir( $dir ) ) {

			if ( ! $wp_filesystem->mkdir( $dir, HMW_DIR_PERMISSION ) ) {
				return $this->_cachedir;
			}

			// Keep the directory private
			$wp_filesystem->put_contents( $dir . 'index.php', '<?php // Silence is golden', HMW_FILE_PERMISSION );
			$deny = "<IfModule mod_authz_core.c>" . PHP_EOL . "Require all denied" . PHP_EOL . "</IfModule>" . PHP_EOL;
			$deny .= "<IfModule !mod_authz_core.c>" . PHP_EOL . "Order Allow,Deny" . PHP_EOL . "Deny from all" . PHP_EOL . "</IfModule>" . PHP_EOL;

			$wp_filesystem->put_contents( $dir . '.htaccess', $deny, HMW_FILE_PERMISSION );
		}

		if ( ! $wp_filesystem->is_writable( $dir ) ) {
			return $this->_cachedir;
		}

		$this->_cachedir = $dir;

		return $this->_cachedir;
	}

	/**
	 * Check if the changed content of a file can be kept on disk
	 *
	 * @return bool
	 */
	public function isCacheable() {

		// The content is not changed for logged in users, caching it would
		// send the original paths to the visitors
		if ( HMWP_Classes_Tools::isLoggedInUser() ) {
			return false;
		}

		// A random text mapping is generated on every request by design
		$mapping = json_decode( HMWP_Classes_Tools::getOption( 'hmwp_text_mapping' ), true );

		if ( isset( $mapping['to'] ) && is_array( $mapping['to'] ) ) {
			foreach ( $mapping['to'] as $value ) {
				if ( strpos( (string) $value, '{rand}' ) !== false ) {
					return false;
				}
			}
		}

		return (bool) apply_filters( 'hmwp_files_cache', true );
	}

	/**
	 * Get the file which holds the changed content
	 *
	 * @param  string  $etag  The validator of the original file
	 * @param  string  $encoding  The compression used for the content
	 *
	 * @return string The path of the cache file, empty when it can't be used
	 */
	public function getCacheFile( $etag, $encoding ) {

		if ( ! $dir = $this->getCacheDir() ) {
			return '';
		}

		// The extension is never css or js, so the cache rewriting never picks these files up
		return $dir . md5( $etag . '|' . $encoding ) . '.cache';
	}

	/**
	 * Save the changed content so it's not rebuilt on every request
	 *
	 * @param  string  $file  The cache file path
	 * @param  string  $content  The content to save
	 *
	 * @return void
	 */
	public function saveCacheFile( $file, $content ) {

		// Initialize WordPress Filesystem
		$wp_filesystem = HMWP_Classes_ObjController::initFilesystem();

		// Write in a temporary file first so a visitor never reads half a file
		$temp = $file . '.' . wp_rand( 100000, 999999 ) . '.tmp';

		if ( $wp_filesystem->put_contents( $temp, $content, HMW_FILE_PERMISSION ) ) {
			//Overwrite: a missing file for a moment is a cache miss, never half a file
			if ( ! $wp_filesystem->move( $temp, $file, true ) ) {
				$wp_filesystem->delete( $temp );
			}
		}

		$this->cleanCacheDir();
	}

	/**
	 * Remove the cache files left behind when a file or a setting changed
	 *
	 * @return void
	 */
	public function cleanCacheDir() {

		if ( ! $dir = $this->getCacheDir() ) {
			return;
		}

		// Initialize WordPress Filesystem
		$wp_filesystem = HMWP_Classes_ObjController::initFilesystem();

		$marker = $dir . 'last-clean';
		$lifetime = (int) apply_filters( 'hmwp_files_cache_lifetime', WEEK_IN_SECONDS );

		// Only look through the directory once a day
		if ( $wp_filesystem->exists( $marker ) && ( time() - (int) $wp_filesystem->mtime( $marker ) ) < DAY_IN_SECONDS ) {
			return;
		}

		$wp_filesystem->put_contents( $marker, (string) time(), HMW_FILE_PERMISSION );

		if ( ! function_exists( 'glob' ) ) {
			return;
		}

		if ( $files = glob( $dir . '*.cache' ) ) {
			foreach ( $files as $file ) {
				if ( ( time() - (int) $wp_filesystem->mtime( $file ) ) > $lifetime ) {
					$wp_filesystem->delete( $file );
				}
			}
		}

		// Clean up the temporary files left by an interrupted write
		if ( $files = glob( $dir . '*.tmp' ) ) {
			foreach ( $files as $file ) {
				if ( ( time() - (int) $wp_filesystem->mtime( $file ) ) > HOUR_IN_SECONDS ) {
					$wp_filesystem->delete( $file );
				}
			}
		}
	}

	/**
	 * Close all the output buffers before sending a file
	 *
	 * @return void
	 */
	public function closeBuffers() {

		while ( ob_get_level() ) {
			ob_end_clean();
		}
	}

	/**
	 * Build the redirects array
	 *
	 * @throws Exception
	 */
	public function buildRedirect() {
		$rewriteModel = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' );
		//build the rules paths to change back the hidden paths
		$rewriteModel->clearRedirect()->buildRedirect();

		//URL Mapping
		$hmwp_url_mapping = json_decode( HMWP_Classes_Tools::getOption( 'hmwp_url_mapping' ), true );
		if ( isset( $hmwp_url_mapping['from'] ) && ! empty( $hmwp_url_mapping['from'] ) ) {
			foreach ( $hmwp_url_mapping['from'] as $index => $row ) {
				if ( substr( $hmwp_url_mapping['from'][ $index ], - 1 ) == '/' ) {
					$this->_rewrites['from'][]  = '#^/' . str_replace( array( home_url() . '/' ), '', ltrim( $hmwp_url_mapping['to'][ $index ], '/' ) ) . '(.*)' . '#i';
					$this->_rewrites['exact'][] = '#^/' . preg_quote( str_replace( array( home_url() . '/' ), '', ltrim( $hmwp_url_mapping['to'][ $index ], '/' ) ), '#' ) . '(.*)' . '#';
					$this->_rewrites['to'][]   = '/' . str_replace( array( home_url() . '/' ), '', ltrim( $hmwp_url_mapping['from'][ $index ], '/' ) ) . "$1";
				} else {
					$this->_rewrites['from'][]  = '#^/' . str_replace( array( home_url() . '/' ), '', ltrim( $hmwp_url_mapping['to'][ $index ], '/' ) ) . '$' . '#i';
					$this->_rewrites['exact'][] = '#^/' . preg_quote( str_replace( array( home_url() . '/' ), '', ltrim( $hmwp_url_mapping['to'][ $index ], '/' ) ), '#' ) . '$' . '#';
					$this->_rewrites['to'][]   = '/' . str_replace( array( home_url() . '/' ), '', ltrim( $hmwp_url_mapping['from'][ $index ], '/' ) );
				}
			}
		}

		if ( ! empty( $rewriteModel->_replace['from'] ) && ! empty( $rewriteModel->_replace['to'] ) ) {
			foreach ( $rewriteModel->_replace['from'] as $index => $row ) {
				$this->_rewrites['from'][]  = '#^/' . $rewriteModel->_replace['to'][ $index ] . ( substr( $rewriteModel->_replace['to'][ $index ], - 1 ) == '/' ? "(.*)" : "" ) . '#i';
				$this->_rewrites['exact'][] = '#^/' . preg_quote( $rewriteModel->_replace['to'][ $index ], '#' ) . ( substr( $rewriteModel->_replace['to'][ $index ], - 1 ) == '/' ? "(.*)" : "" ) . '#';
				$this->_rewrites['to'][]   = '/' . $rewriteModel->_replace['from'][ $index ] . ( substr( $rewriteModel->_replace['to'][ $index ], - 1 ) == '/' ? "$1" : "" );
			}
		}

	}

	/**
	 * Retrieves the original URL by applying rewrite rules and constructing the URL from parsed components.
	 *
	 * @param  string  $url  The redirected URL which needs to be converted back to the original URL.
	 * @param  bool  $exact  Match the changed paths letter by letter, as the files are served.
	 *
	 * @return string The original URL reconstructed from the given URL based on rewrite rules.
	 * @throws Exception
	 */
	public function getOriginalUrl( $url, $exact = false ) {
		$this->_mapped = false;

		// Build the rewrite rules if they are not already built
		if ( empty( $this->_rewrites ) ) {
			$this->buildRedirect();
		}

		// Parse the URL components
		$parse_url = wp_parse_url( $url );

		// Only if there is a path to change
		if ( ! isset( $parse_url['host'] ) || ! isset( $parse_url['path'] )) {
			return $url;
		}

		// Never map an URL which doesn't belong to this site
		if ( ! $this->isSiteUrl( $url ) ) {
			return $url;
		}

		// Get the home root path
		$path = wp_parse_url( home_url(), PHP_URL_PATH );

		// Backslash the paths
		if ( $path <> '' ) {
			$parse_url['path'] = preg_replace( '/^' . preg_quote( $path, '/' ) . '/', '', $parse_url['path'] );
		}

		// Replace paths to original based on rewrite rules
		if ( isset( $this->_rewrites['from'] ) && isset( $this->_rewrites['to'] ) && ! empty( $this->_rewrites['from'] ) && ! empty( $this->_rewrites['to'] ) ) {
			$from = ( $exact && isset( $this->_rewrites['exact'] ) ? $this->_rewrites['exact'] : $this->_rewrites['from'] );

			$old_path          = $parse_url['path'];
			$parse_url['path'] = preg_replace( $from, $this->_rewrites['to'], $parse_url['path'], 1 );

			// Remember if a changed path was found in this address
			$this->_mapped = ( $old_path !== $parse_url['path'] );
		}

		// Default to https if the scheme is not set
		if ( ! isset( $parse_url['scheme'] ) ) {
			$parse_url['scheme'] = 'https';
		}

		// Reconstruct the URL
		if ( isset( $parse_url['port'] ) && $parse_url['port'] <> 80 ) {
			$new_url = $parse_url['scheme'] . '://' . $parse_url['host'] . ':' . $parse_url['port'] . $path . $parse_url['path'];
		} else {
			$new_url = $parse_url['scheme'] . '://' . $parse_url['host'] . $path . $parse_url['path'];
		}

		// Append query string if present
		if ( isset( $parse_url['query'] ) && ! empty( $parse_url['query'] ) ) {
			$query   = $parse_url['query'];
			$query   = str_replace( array( '?', '%3F' ), '&', $query );
			$new_url .= ( ! strpos( $new_url, '?' ) ? '?' : '&' ) . $query;
		}

		// Return the constructed URL
		return sanitize_url( $new_url );

	}

	/**
	 * Get the original path from url
	 *
	 * @param  $new_url
	 *
	 * @return string
	 */
	public function getOriginalPath( $new_url, $resolve = true ) {
		//remove domain from path
		$new_path = str_replace( home_url(), '', $new_url );

		//remove queries from path
		if ( strpos( $new_path, '?' ) !== false ) {
			$new_path = substr( $new_path, 0, strpos( $new_path, '?' ) );
		}

		$new_path = HMWP_Classes_Tools::getRootPath() . ltrim( $new_path, '/' );

		// The path as written keeps a link to another folder in it
		if ( $resolve ) {
			$new_path = realpath( $new_path );
		}

		$new_path = str_replace( '\\', '/', (string) $new_path );

		if ( strpos( $new_path, HMWP_Classes_Tools::getRootPath() ) === false ) {
			return false;
		}

		return $new_path;
	}

	/**
	 * Check if the server rules refuse this file to the current visitor
	 *
	 * @param  string  $path  The file path on the server
	 *
	 * @return bool
	 */
	public function isDeniedPath( $path ) {
		$path   = str_replace( '\\', '/', (string) $path );
		$root   = rtrim( HMWP_Classes_Tools::getRootPath(), '/' );
		$denied = false;

		// Only a file under the website root has rules to read
		if ( strpos( $path, $root . '/' ) === 0 ) {
			$denied = $this->isClosedByRules( $path, $root );
		}

		// For folders closed in the server config, where PHP can't read the rule
		return (bool) apply_filters( 'hmwp_denied_path', $denied, $path );
	}

	/**
	 * Read the .htaccess files from the website root to the folder of the file, the way the server does for a direct request
	 *
	 * @param  string  $path  The file path on the server
	 * @param  string  $root  The website root, without the ending slash
	 *
	 * @return bool
	 */
	protected function isClosedByRules( $path, $root ) {
		$relative = ltrim( substr( $path, strlen( $root ) ), '/' );
		$uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$verb     = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$verb     = ( '' === $verb ? 'GET' : $verb );

		// The server gives a file to these methods only, the others get an error or an empty answer
		if ( ! in_array( $verb, array( 'GET', 'HEAD', 'POST' ), true ) ) {
			return true;
		}

		// The request as the server would see it on the real address of the file
		$this->_request = array(
			'root'   => $root,
			'path'   => $path,
			'uri'    => HMWP_Classes_Tools::getHomeRootPath() . $relative,
			'verb'   => $verb,
			'method' => ( 'HEAD' === $verb ? 'GET' : $verb ),
			'query'  => ( strpos( $uri, '?' ) !== false ? substr( $uri, strpos( $uri, '?' ) + 1 ) : '' ),
			'env'    => array(),
		);

		$names = explode( '/', $relative );
		$file  = array_pop( $names );
		array_unshift( $names, '' );

		$folders = array();
		$dir     = $root;

		foreach ( $names as $index => $name ) {
			if ( $index > 0 ) {
				if ( '' === $name ) {
					continue;
				}
				$dir .= '/' . $name;
			}

			$is_root  = ( 0 === $index );
			$htaccess = $dir . '/.htaccess';

			if ( ! is_file( $htaccess ) ) {
				continue;
			}

			// The server refuses a folder when it can't read its rules
			if ( ! is_readable( $htaccess ) || filesize( $htaccess ) > 262144 ) {
				if ( ! $is_root ) {
					return true;
				}
				if ( ! is_readable( $htaccess ) ) {
					continue;
				}
			}

			$rules = $this->getFolderRules( (string) file_get_contents( $htaccess, false, null, 0, 262144 ), $file, $is_root ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $rules || $rules['closed'] ) {
				return true;
			}

			$rules['dir']  = $dir;
			$rules['root'] = $is_root;
			$folders[]     = $rules;
		}

		// Folder rules first, then the rules written for the file name, then the If blocks
		$scopes = array();
		foreach ( array( 'folder', 'files', 'if' ) as $type ) {
			foreach ( $folders as $folder ) {
				foreach ( $folder[ $type ] as $lines ) {
					$scopes[] = $lines;
				}
			}
		}

		if ( $this->isAccessClosed( $scopes ) ) {
			return true;
		}

		foreach ( $folders as $folder ) {
			if ( $this->isRedirectClosed( $folder ) ) {
				return true;
			}
		}

		return $this->isRewriteClosed( $folders );
	}

	/**
	 * Sort the lines of a .htaccess file by the block they belong to, for this file name and request
	 *
	 * @param  string  $content  The .htaccess content
	 * @param  string  $file  The file name
	 * @param  bool  $is_root  True for the .htaccess in the website root
	 *
	 * @return array|false False when the server would not accept the file
	 */
	protected function getFolderRules( $content, $file, $is_root ) {
		$rules  = array( 'folder' => array( array() ), 'files' => array(), 'if' => array(), 'rewrite' => array(), 'redirect' => array(), 'options' => array(), 'closed' => false );
		$stack  = array();
		$scopes = array( array( 'folder', 0 ) );
		$skip   = $off = $unsure = $dead = $blind = 0;
		$if_met = null;

		// Below the root Apache answers with an error for a file it can't read to the end
		$strict = ( ! $is_root && HMWP_Classes_Tools::isApache() && ! HMWP_Classes_Tools::isLitespeed() );

		// The blocks Apache reads only when their test holds, and the other blocks of a .htaccess file
		$tests  = array( 'ifmodule', 'ifversion', 'ifdefine', 'iffile', 'ifdirective', 'ifsection' );
		$blocks = array( 'files', 'filesmatch', 'if', 'elseif', 'else', 'limit', 'limitexcept', 'requireall', 'requireany', 'requirenone' );

		// The plugin's own rules are made for the changed paths, the server applied them already
		if ( $is_root ) {
			foreach ( array( 'HMWP_RULES', 'HMWP_VULNERABILITY' ) as $marker ) {
				$start = strpos( $content, '# BEGIN ' . $marker );
				$end   = strpos( $content, '# END ' . $marker );
				if ( false !== $start && false !== $end && $end > $start ) {
					$content = substr( $content, 0, $start ) . substr( $content, $end );
				}
			}
		}

		// The server stops on a file that starts with a byte order mark
		if ( strpos( $content, "\xEF\xBB\xBF" ) === 0 ) {
			if ( ! $is_root ) {
				return false;
			}
			$content = substr( $content, 3 );
		}

		// A line that ends with one backslash goes on in the next line
		$content = (string) preg_replace( '/(?<!\\\\)\\\\\r?\n/', '', $content );

		foreach ( explode( "\n", $content ) as $line ) {
			$line = trim( $line );

			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}

			// The server stops on a line this long
			if ( strlen( $line ) > 8190 ) {
				if ( $is_root ) {
					continue;
				}

				return false;
			}

			$scope = end( $scopes );

			if ( '<' === $line[0] ) {
				// A closing tag ends every block opened inside it
				if ( isset( $line[1] ) && '/' === $line[1] ) {
					$word  = substr( $line, 2, strcspn( $line, " \t" ) - 2 );
					$tag   = strtolower( rtrim( $word, '>' ) );
					$entry = end( $stack );

					// Apache takes only the closing tag of the block opened last
					if ( $strict && ( '>' !== substr( $word, - 1 ) || ! $entry || $entry['tag'] !== $tag ) ) {
						return false;
					}

					if ( in_array( $tag, array_column( $stack, 'tag' ), true ) ) {
						do {
							$entry  = array_pop( $stack );
							$skip   -= $entry['skip'];
							$off    -= $entry['off'];
							$unsure -= $entry['unsure'];
							$dead   -= $entry['dead'];
							$blind  -= $entry['blind'];

							if ( $entry['scope'] ) {
								array_pop( $scopes );
							}

							if ( $entry['require'] ) {
								$scope                             = end( $scopes );
								$rules[ $scope[0] ][ $scope[1] ][] = array( '>', '' );
							}
						} while ( $entry['tag'] !== $tag );
					}

					continue;
				}

				// The tag ends at its last bracket
				$end    = strrpos( $line, '>' );
				$line   = trim( substr( $line, 1, ( false === $end ? strlen( $line ) : $end - 1 ) ) );
				$length = strcspn( $line, " \t" );
				$tag    = strtolower( substr( $line, 0, $length ) );
				$args   = trim( substr( $line, $length ) );
				$entry  = array( 'tag' => $tag, 'skip' => 0, 'off' => 0, 'unsure' => 0, 'dead' => 0, 'blind' => 0, 'scope' => false, 'require' => false );
				$met    = true;
				$type   = false;
				$test   = in_array( $tag, $tests, true );
				$sure   = true;

				// Inside a block Apache does not read, a tag only opens a block that has to be closed
				if ( $dead ) {
					$stack[] = $entry;
					continue;
				}

				if ( $strict && ! $blind ) {
					// Apache stops on a tag without its bracket and on a block it does not take in a .htaccess file
					if ( false === $end || ( ! $test && ! in_array( $tag, $blocks, true ) ) ) {
						return false;
					}

					// Only these blocks are written without a test
					if ( '' === $args && ! in_array( $tag, array( 'else', 'requireall', 'requireany', 'requirenone' ), true ) ) {
						return false;
					}

					// The modules that read these blocks
					if ( $this->isModuleMissing( 'ifversion' === $tag ? 'mod_version' : ( strpos( $tag, 'require' ) === 0 ? 'mod_authz_core' : '' ) ) ) {
						return false;
					}
				}

				if ( ! $skip || $test ) {
					switch ( $tag ) {
						case 'ifmodule':
							$met = $this->isModuleActive( trim( $args, "\"' " ), $sure );
							break;

						case 'ifversion':
							// Without the version from the server, a current Apache 2.4 is taken
							$sure = false;

							if ( preg_match( '/^(!?)\s*(=|==|<|<=|>|>=)?\s*(\d+(?:\.\d+){0,2})$/', $args, $part ) ) {
								$sure    = ( function_exists( 'apache_get_version' ) && preg_match( '#Apache/(\d+\.\d+\.\d+)#', (string) apache_get_version(), $found ) );
								$version = ( $sure ? $found[1] : '2.4.60' );
								$met     = ( version_compare( $version, $part[3], ( in_array( $part[2], array( '', '=' ), true ) ? '==' : $part[2] ) ) !== ( '!' === $part[1] ) );
							}
							break;

						case 'ifdefine':
							// A name given to the server at start is taken as not set
							$met  = ( strpos( trim( $args, "\"' " ), '!' ) === 0 );
							$sure = false;
							break;

						case 'iffile':
						case 'ifdirective':
						case 'ifsection':
							$word = trim( $args, "\"' " );
							$path = ltrim( $word, '!' );

							if ( 'iffile' === $tag && isset( $path[0] ) && '/' === $path[0] ) {
								$met = ( @file_exists( $path ) === ( $word === $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
								break;
							}

							// A test PHP can't make: the rules inside close a folder below the root
							$entry['unsure'] = ( $is_root ? 0 : 1 );
							$met             = ( $is_root ? false : null );
							$sure            = false;
							break;

						case 'files':
						case 'filesmatch':
							$words = $this->getRuleWords( $args );
							$regex = ( 'filesmatch' === $tag );

							if ( isset( $words[0] ) && '~' === $words[0] ) {
								$regex = true;
								array_shift( $words );
							}

							$name = ( isset( $words[0] ) ? $words[0] : '' );

							if ( $regex ) {
								$met = $this->isPatternMatch( $name, $file );

								// Apache stops on a pattern it can't read
								if ( null === $met && $strict && ! $blind ) {
									return false;
								}

								// A pattern PHP can't read counts as a match below the root
								$met = ( null === $met ? ! $is_root : $met );
							} else {
								$met = ( function_exists( 'fnmatch' ) ? fnmatch( $name, $file, FNM_PATHNAME ) : ( $name === $file || '*' === $name ) );
							}

							$type = 'files';
							break;

						case 'if':
						case 'elseif':
						case 'else':
							// The server works out these tests before it sets the variables of the folder rules
							$variables              = $this->_request['env'];
							$this->_request['env'] = array();

							if ( 'if' === $tag ) {
								$met = $this->isExpressionMet( $args );
							} elseif ( true === $if_met ) {
								$met = false;
							} elseif ( null === $if_met ) {
								$met = null;
							} else {
								$met = ( 'else' === $tag ? true : $this->isExpressionMet( $args ) );
							}

							$if_met                = ( ( 'if' !== $tag && true === $if_met ) ? true : $met );
							$this->_request['env'] = $variables;

							// A test PHP can't read: its access rules close a folder below the root, in the root they are left out
							if ( null === $met ) {
								$entry['unsure'] = ( $is_root ? 0 : 1 );
								$met             = ( $is_root ? false : null );
							}

							$type = 'if';
							break;

						case 'limit':
						case 'limitexcept':
							$methods = str_replace( 'HEAD', 'GET', array_map( 'strtoupper', $this->getRuleWords( $args ) ) );

							// Rules for other methods are counted as present, without their content
							if ( in_array( $this->_request['method'], $methods, true ) !== ( 'limit' === $tag ) ) {
								$entry['off'] = 1;
							}
							break;

						case 'requireall':
						case 'requireany':
						case 'requirenone':
							if ( $unsure ) {
								$rules['closed'] = true;
							} elseif ( $off ) {
								$rules[ $scope[0] ][ $scope[1] ][] = array( 'require', false );
							} else {
								$rules[ $scope[0] ][ $scope[1] ][] = array( '<', $tag );
								$entry['require']                  = true;
							}
							break;
					}

					if ( $test ) {
						// Apache does not read a block whose test fails. A block with a guessed test is read without the checks
						$entry['dead']  = ( false === $met ? 1 : 0 );
						$entry['blind'] = ( $sure ? 0 : 1 );
					} elseif ( false === $met ) {
						$entry['skip'] = 1;
					} elseif ( $type && true === $met ) {
						$rules[ $type ][] = array();
						$scopes[]         = array( $type, count( $rules[ $type ] ) - 1 );
						$entry['scope']   = true;
					}
				}

				$skip    += $entry['skip'];
				$off     += $entry['off'];
				$unsure  += $entry['unsure'];
				$dead    += $entry['dead'];
				$blind   += $entry['blind'];
				$stack[] = $entry;

				continue;
			}

			if ( $dead ) {
				continue;
			}

			$length = strcspn( $line, " \t" );
			$name   = strtolower( substr( $line, 0, $length ) );
			$args   = trim( substr( $line, $length ) );

			// Apache checks every line it reads, also in a block that is not for this file
			if ( $strict && ! $blind && ! $this->isRuleValid( $name, $args ) ) {
				return false;
			}

			if ( $skip ) {
				continue;
			}

			switch ( $name ) {
				case 'order':
				case 'allow':
				case 'deny':
				case 'satisfy':
				case 'require':
				case 'authmerging':
					if ( $unsure ) {
						$rules['closed'] = true;
						break;
					}

					// The server applied the folder rules of the root to this request already
					$rules[ $scope[0] ][ $scope[1] ][] = array( $name, ( ( $off || ( $is_root && 'folder' === $scope[0] ) ) ? false : $this->getRuleWords( $args ) ) );
					break;

				case 'setenvif':
				case 'setenvifnocase':
				case 'setenvifexpr':
				case 'browsermatch':
				case 'browsermatchnocase':
					// A variable from a block PHP can't read is not known
					if ( $unsure ) {
						$rules['closed'] = true;
						break;
					}

					$this->setRuleVariables( $name, $this->getRuleWords( $args ) );
					break;

				case 'rewriteengine':
				case 'rewritebase':
				case 'rewriteoptions':
				case 'rewritecond':
				case 'rewriterule':
					$words = $this->getRuleWords( $args, true );

					// A rewrite rule inside a block is read only for what it forbids
					if ( $unsure || 'folder' !== $scope[0] ) {
						if ( 'rewriterule' === $name && ( $unsure || $this->isForbidFlags( $this->getRuleFlags( isset( $words[2] ) ? $words[2] : '' ) ) ) ) {
							$rules['closed'] = true;
						}
						break;
					}

					$rules['rewrite'][] = array( substr( $name, 7 ), $words );
					break;

				case 'redirect':
				case 'redirectmatch':
				case 'redirectpermanent':
				case 'redirecttemp':
					if ( $unsure ) {
						$rules['closed'] = true;
						break;
					}

					$rules['redirect'][] = array( $name, $this->getRuleWords( $args ) );
					break;

				case 'options':
					if ( $unsure ) {
						$rules['closed'] = true;
						break;
					}

					$rules['options'][] = array_map( 'strtolower', $this->getRuleWords( $args ) );
					break;

				case 'sslrequiressl':
				case 'sslrequire':
				case 'action':
					// The server already applied the folder lines of the root, a secure request passes the first one
					if ( ( ! $is_root || 'folder' !== $scope[0] ) && ( 'sslrequiressl' !== $name || ! is_ssl() ) ) {
						$rules['closed'] = true;
					}
					break;

				case 'sethandler':
				case 'addhandler':
				case 'addtype':
				case 'forcetype':
					$words = array_map( 'strtolower', $this->getRuleWords( $args ) );
					$value = (string) array_shift( $words );

					if ( 'sethandler' === $name || 'addhandler' === $name ) {
						$found = ! in_array( $value, array( '', 'none', 'default-handler' ), true );
					} else {
						$found = (bool) preg_match( '/php|x-httpd/', $value );
					}

					// A line with extensions counts for a file that has one of them
					if ( $found && ( 'addhandler' === $name || 'addtype' === $name ) ) {
						$found = (bool) array_intersect( array_map( function ( $word ) {
							return ltrim( $word, '.' );
						}, $words ), array_slice( explode( '.', strtolower( $file ) ), 1 ) );
					}

					// The server runs the file as a script, it does not send it
					if ( $found ) {
						$rules['closed'] = true;
					}
					break;
			}
		}

		// Apache stops on a block left open, but not inside a block it reads on a test
		if ( $strict && $stack && ( $dead || ! in_array( $stack[0]['tag'], $tests, true ) ) ) {
			return false;
		}

		if ( $is_root ) {
			$rules['folder'] = array( array() );
		}

		return $rules;
	}

	/**
	 * Check if the rules of an IfModule block apply
	 *
	 * @param  string  $name  The module as written in the block, with ! for a module that is not loaded
	 * @param  bool  $sure  Set to false when the answer is a guess
	 *
	 * @return bool
	 */
	protected function isModuleActive( $name, &$sure = true ) {
		$negate = ( isset( $name[0] ) && '!' === $name[0] );
		$name   = trim( substr( $name, (int) $negate ) );
		$list   = ( function_exists( 'apache_get_modules' ) ? (array) apache_get_modules() : array() );
		$apache = ( HMWP_Classes_Tools::isApache() && ! HMWP_Classes_Tools::isLitespeed() );
		$file   = ( '.c' === substr( $name, - 2 ) );
		$sure   = true;

		// The block written for LiteSpeed servers
		if ( 'litespeed' === strtolower( $name ) ) {
			return ( HMWP_Classes_Tools::isLitespeed() !== $negate );
		}

		// The file name and the identifier of a module, both as mod_name
		if ( $file ) {
			$name = substr( $name, 0, - 2 );
		} elseif ( '_module' === substr( $name, - 7 ) ) {
			$name = 'mod_' . substr( $name, 0, - 7 );
		} elseif ( $apache ) {
			// Apache finds a module only by its file name or its identifier
			return $negate;
		}

		// Apache with PHP as a module lists every loaded module by its file name
		if ( $apache && $list && ( $file || in_array( $name, $list, true ) ) ) {
			return ( in_array( $name, $list, true ) !== $negate );
		}

		// The identifiers of the server core and of its process modules, listed without mod_
		if ( $apache && ! $file && in_array( preg_replace( '/^mod_(mpm_)?/', '', $name ), array_intersect( $list, array( 'core', 'prefork', 'worker', 'event' ) ), true ) ) {
			return ! $negate;
		}

		$sure = false;

		if ( $list && strpos( $name, 'mod_' ) === 0 && strpos( $name, 'mod_mpm_' ) !== 0 && ! in_array( $name, array( 'mod_core', 'mod_http' ), true ) ) {
			return ( in_array( $name, $list, true ) !== $negate );
		}

		// Without the list every block is read, but not the one made for a server without the access or the rewrite module
		return ! ( $negate && in_array( strtolower( $name ), array( 'mod_authz_core', 'mod_rewrite' ), true ) );
	}

	/**
	 * Check if a module is not in the list of loaded modules that Apache 2.4 gives to PHP
	 *
	 * @param  string  $module  The module as mod_name
	 *
	 * @return bool False also without the list
	 */
	protected function isModuleMissing( $module ) {
		$list = ( function_exists( 'apache_get_modules' ) ? (array) apache_get_modules() : array() );

		return ( '' !== $module && in_array( 'mod_authz_core', $list, true ) && ! in_array( $module, $list, true ) );
	}

	/**
	 * Check if Apache takes a line of a .htaccess file: a directive it knows, from a loaded module, with the words it needs
	 *
	 * @param  string  $name  The directive name in lower case
	 * @param  string  $args  The text after the name
	 *
	 * @return bool
	 */
	protected function isRuleValid( $name, $args ) {
		// Directives of modules PHP can't check, by the start of their name
		if ( preg_match( '/^(modpagespeed|sec|mod_gzip_|suphp_|lsapi_|passenger|fcgi|perl)/', $name ) ) {
			return true;
		}

		if ( strpos( $name, 'session' ) === 0 ) {
			return ! $this->isModuleMissing( 'mod_session' );
		}

		// The directives a .htaccess file may hold, by module, with the least number of words
		$known = array(
			'core'                => array( 'acceptpathinfo' => 1, 'adddefaultcharset' => 1, 'cgipassauth' => 1, 'contentdigest' => 1, 'defaulttype' => 1, 'enablemmap' => 1, 'enablesendfile' => 1, 'errordocument' => 2, 'fileetag' => 0, 'forcetype' => 1, 'limitrequestbody' => 1, 'limitxmlrequestbody' => 1, 'options' => 0, 'php_flag' => 2, 'php_value' => 2, 'rlimitcpu' => 1, 'rlimitmem' => 1, 'rlimitnproc' => 1, 'serversignature' => 1, 'sethandler' => 1, 'setinputfilter' => 1, 'setoutputfilter' => 1 ),
			'mod_access_compat'   => array( 'allow' => 2, 'deny' => 2, 'order' => 1, 'satisfy' => 1 ),
			'mod_actions'         => array( 'action' => 2 ),
			'mod_alias'           => array( 'redirect' => 1, 'redirectmatch' => 1, 'redirectpermanent' => 1, 'redirecttemp' => 1 ),
			'mod_auth_basic'      => array( 'authbasicauthoritative' => 1, 'authbasicfake' => 1, 'authbasicprovider' => 1, 'authbasicusedigestalgorithm' => 1 ),
			'mod_auth_digest'     => array( 'authdigestalgorithm' => 1, 'authdigestdomain' => 1, 'authdigestnoncelifetime' => 1, 'authdigestprovider' => 1, 'authdigestqop' => 1 ),
			'mod_authn_core'      => array( 'authname' => 1, 'authtype' => 1 ),
			'mod_authn_dbm'       => array( 'authdbmtype' => 1, 'authdbmuserfile' => 1 ),
			'mod_authn_file'      => array( 'authuserfile' => 1 ),
			'mod_authz_core'      => array( 'authmerging' => 1, 'authzsendforbiddenonfailure' => 1, 'require' => 1 ),
			'mod_authz_groupfile' => array( 'authgroupfile' => 1 ),
			'mod_autoindex'       => array( 'addalt' => 2, 'addaltbyencoding' => 2, 'addaltbytype' => 2, 'adddescription' => 2, 'addicon' => 2, 'addiconbyencoding' => 2, 'addiconbytype' => 2, 'defaulticon' => 1, 'headername' => 1, 'indexheadinsert' => 1, 'indexignore' => 1, 'indexignorereset' => 1, 'indexoptions' => 0, 'indexorderdefault' => 2, 'indexstylesheet' => 1, 'readmename' => 1 ),
			'mod_dir'             => array( 'directorycheckhandler' => 1, 'directoryindex' => 0, 'directoryindexredirect' => 1, 'directoryslash' => 1, 'fallbackresource' => 1 ),
			'mod_env'             => array( 'passenv' => 1, 'setenv' => 1, 'unsetenv' => 1 ),
			'mod_expires'         => array( 'expiresactive' => 1, 'expiresbytype' => 2, 'expiresdefault' => 1 ),
			'mod_filter'          => array( 'addoutputfilterbytype' => 2, 'filterchain' => 1, 'filterdeclare' => 1, 'filterprotocol' => 2, 'filterprovider' => 3 ),
			'mod_headers'         => array( 'header' => 2, 'requestheader' => 2 ),
			'mod_http2'           => array( 'h2push' => 1, 'h2pushresource' => 1, 'h2upgrade' => 1 ),
			'mod_include'         => array( 'ssierrormsg' => 1, 'ssilegacyexprparser' => 1, 'ssitimeformat' => 1, 'ssiundefinedecho' => 1, 'xbithack' => 1 ),
			'mod_mime'            => array( 'addcharset' => 2, 'addencoding' => 2, 'addhandler' => 2, 'addinputfilter' => 2, 'addlanguage' => 2, 'addoutputfilter' => 2, 'addtype' => 2, 'defaultlanguage' => 1, 'multiviewsmatch' => 1, 'removecharset' => 1, 'removeencoding' => 1, 'removehandler' => 1, 'removeinputfilter' => 1, 'removelanguage' => 1, 'removeoutputfilter' => 1, 'removetype' => 1 ),
			'mod_negotiation'     => array( 'forcelanguagepriority' => 1, 'languagepriority' => 1 ),
			'mod_rewrite'         => array( 'rewritebase' => 1, 'rewritecond' => 2, 'rewriteengine' => 1, 'rewriteoptions' => 1, 'rewriterule' => 2 ),
			'mod_setenvif'        => array( 'browsermatch' => 2, 'browsermatchnocase' => 2, 'setenvif' => 3, 'setenvifexpr' => 2, 'setenvifnocase' => 3 ),
			'mod_speling'         => array( 'checkcaseonly' => 1, 'checkspelling' => 1 ),
			'mod_ssl'             => array( 'sslciphersuite' => 1, 'ssloptions' => 1, 'sslrenegbuffersize' => 1, 'sslrequire' => 1, 'sslrequiressl' => 0, 'sslusername' => 1, 'sslverifyclient' => 1, 'sslverifydepth' => 1 ),
			'mod_substitute'      => array( 'substitute' => 1, 'substituteinheritbefore' => 1, 'substitutemaxlinelength' => 1 ),
			'mod_xsendfile'       => array( 'xsendfile' => 1 ),
		);

		$words = $this->getRuleWords( $args, ( strpos( $name, 'rewrite' ) === 0 ) );
		$least = null;

		foreach ( $known as $module => $list ) {
			if ( isset( $list[ $name ] ) ) {
				$least = ( ( 'core' !== $module && $this->isModuleMissing( $module ) ) ? null : $list[ $name ] );
				break;
			}
		}

		if ( null === $least || count( $words ) < $least ) {
			return false;
		}

		$first = strtolower( isset( $words[0] ) ? $words[0] : '' );

		switch ( $name ) {
			case 'checkspelling':
			case 'contentdigest':
			case 'directoryslash':
			case 'enablemmap':
			case 'enablesendfile':
			case 'expiresactive':
			case 'rewriteengine':
				return in_array( $first, array( 'on', 'off' ), true );

			case 'satisfy':
				return in_array( $first, array( 'any', 'all' ), true );

			case 'authmerging':
				return in_array( $first, array( 'off', 'and', 'or' ), true );

			case 'order':
				return ( 1 === count( $words ) && in_array( $first, array( 'allow,deny', 'deny,allow', 'mutual-failure' ), true ) );

			case 'allow':
			case 'deny':
				return ( 'from' === $first );

			case 'errordocument':
				return (bool) preg_match( '/^[1-5]\d\d/', $first );

			case 'expiresbytype':
			case 'expiresdefault':
				// The time starts from the access, the change of the file or now
				return (bool) preg_match( '/^[amn]/i', $words[ ( 'expiresdefault' === $name ? 0 : 1 ) ] );

			case 'options':
				$signed = $whole = false;

				foreach ( $words as $index => $word ) {
					$sign   = ( isset( $word[0] ) && in_array( $word[0], array( '+', '-' ), true ) );
					$option = strtolower( substr( $word, (int) $sign ) );

					// All the options have a sign, or none of them. Only after All or None the next ones may have it
					if ( $index && ! $whole && $sign !== $signed ) {
						return false;
					}

					// A sign may not be followed by an option without it
					if ( $signed && ! $sign ) {
						return false;
					}

					if ( in_array( $option, array( 'all', 'none' ), true ) ) {
						// All and None come first and without a sign
						if ( $index || $sign ) {
							return false;
						}

						$whole = true;
					} elseif ( ! in_array( $option, array( 'execcgi', 'followsymlinks', 'includes', 'includesnoexec', 'indexes', 'multiviews', 'symlinksifownermatch' ), true ) ) {
						return false;
					}

					$signed = $sign;
				}

				return true;

			case 'authbasicprovider':
				$modules = array( 'file' => 'mod_authn_file', 'dbm' => 'mod_authn_dbm', 'dbd' => 'mod_authn_dbd', 'ldap' => 'mod_authnz_ldap', 'socache' => 'mod_authn_socache', 'anon' => 'mod_authn_anon' );

				foreach ( $words as $word ) {
					if ( ! isset( $modules[ strtolower( $word ) ] ) || $this->isModuleMissing( $modules[ strtolower( $word ) ] ) ) {
						return false;
					}
				}

				return true;

			case 'rewritebase':
				return ( '/' === substr( $first, 0, 1 ) );

			case 'rewriteoptions':
				foreach ( $words as $word ) {
					$word = strtolower( $word );

					if ( strpos( $word, 'maxredirects=' ) !== 0 && ! in_array( $word, array( 'inherit', 'inheritbefore', 'inheritdown', 'inheritdownbefore', 'ignoreinherit', 'allownoslash', 'allowanyuri', 'mergebase', 'ignorecontextinfo', 'legacyprefixdocroot', 'longurloptimization' ), true ) ) {
						return false;
					}
				}

				return true;

			case 'rewriterule':
			case 'rewritecond':
				$pattern = $words[ ( 'rewriterule' === $name ? 0 : 1 ) ];
				$pattern = ( strpos( $pattern, '!' ) === 0 ? substr( $pattern, 1 ) : $pattern );

				// A condition may hold a test in place of a pattern
				if ( 'rewritecond' === $name && ( 'expr' === $first || preg_match( '/^(-[a-zA-Z]|[=<>])/', $pattern ) ) ) {
					return true;
				}

				return ( null !== $this->isPatternMatch( $pattern, '' ) );

			case 'setenvif':
			case 'setenvifnocase':
			case 'browsermatch':
			case 'browsermatchnocase':
				return ( null !== $this->isPatternMatch( $words[ ( strpos( $name, 'setenvif' ) === 0 ? 1 : 0 ) ], '' ) );
		}

		return true;
	}

	/**
	 * Keep the variables a SetEnvIf or BrowserMatch line sets for the current request
	 *
	 * @param  string  $name  The directive name in lower case
	 * @param  array  $words  The words after the directive
	 *
	 * @return void
	 */
	protected function setRuleVariables( $name, $words ) {
		if ( 'setenvifexpr' === $name ) {
			$found = $this->isExpressionMet( (string) array_shift( $words ) );
		} else {
			$value = ( strpos( $name, 'browsermatch' ) === 0 ? $this->getServerValue( 'HTTP_USER_AGENT' ) : $this->getAttributeValue( (string) array_shift( $words ) ) );
			$found = ( null !== $value && $this->isPatternMatch( (string) array_shift( $words ), $value, ( 'nocase' === substr( $name, - 6 ) ) ) );
		}

		// A test PHP can't read leaves the variables as they are
		if ( true !== $found ) {
			return;
		}

		foreach ( $words as $word ) {
			$word = strtolower( (string) $word );

			if ( '' === $word ) {
				continue;
			}

			if ( '!' === $word[0] ) {
				unset( $this->_request['env'][ substr( $word, 1 ) ] );
			} else {
				$part                                 = explode( '=', $word, 2 );
				$this->_request['env'][ $part[0] ] = ( isset( $part[1] ) ? $part[1] : '1' );
			}
		}
	}

	/**
	 * Return the request value a SetEnvIf line tests
	 *
	 * @param  string  $name  A request header, a variable set before, or a name like Request_URI
	 *
	 * @return string|null Null for a value PHP can't know
	 */
	protected function getAttributeValue( $name ) {
		$name = strtolower( $name );

		switch ( $name ) {
			case 'request_uri':
				return $this->_request['uri'];
			case 'request_method':
				return $this->_request['verb'];
			case 'request_protocol':
				return $this->getServerValue( 'SERVER_PROTOCOL' );
			case 'remote_addr':
				return $this->getServerValue( 'REMOTE_ADDR' );
			case 'server_addr':
				return $this->getServerValue( 'SERVER_ADDR' );
			case 'remote_host':
				return null;
		}

		// A header name written as a pattern is not read
		if ( ! preg_match( '/^[a-z0-9_-]+$/', $name ) ) {
			return null;
		}

		$header = strtoupper( str_replace( '-', '_', $name ) );

		foreach ( array( 'HTTP_' . $header, ( strpos( $header, 'CONTENT_' ) === 0 ? $header : '' ) ) as $key ) {
			if ( $key && isset( $_SERVER[ $key ] ) ) {
				return (string) wp_unslash( $_SERVER[ $key ] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
		}

		return ( isset( $this->_request['env'][ $name ] ) ? (string) $this->_request['env'][ $name ] : '' );
	}

	/**
	 * Split the arguments of a .htaccess line into words
	 *
	 * @param  string  $text  The line without the directive name
	 * @param  bool  $raw  True for the rewrite lines, which keep their backslashes
	 *
	 * @return array
	 */
	protected function getRuleWords( $text, $raw = false ) {
		$words = array();

		if ( $raw ) {
			$pattern = '/"([^"]*)"|\'([^\']*)\'|((?:\\\\\s|\S)+)/';
		} else {
			$pattern = '/"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'|(\S+)/';
		}

		if ( preg_match_all( $pattern, (string) $text, $found, PREG_SET_ORDER ) ) {
			foreach ( $found as $word ) {
				// The last group holds the word: 4 parts for a bare word, less for a quoted one
				$value = end( $word );

				if ( ! $raw ) {
					$value = ( count( $word ) < 4 ? preg_replace( '/\\\\(["\'\\\\])/', '$1', $value ) : str_replace( '\\\\', '\\', $value ) );
				}

				$words[] = (string) $value;
			}
		}

		return $words;
	}

	/**
	 * Read the flags of a rewrite line as name => value
	 *
	 * @param  string  $flags  The flags, with or without the brackets
	 *
	 * @return array
	 */
	protected function getRuleFlags( $flags ) {
		$list  = array();
		$names = array( 'FORBIDDEN' => 'F', 'GONE' => 'G', 'REDIRECT' => 'R', 'NOCASE' => 'NC', 'ORNEXT' => 'OR', 'LAST' => 'L', 'SKIP' => 'S', 'CHAIN' => 'C', 'HANDLER' => 'H', 'TYPE' => 'T' );

		foreach ( explode( ',', trim( (string) $flags, "[] \t" ) ) as $flag ) {
			$flag = explode( '=', trim( $flag ), 2 );
			$name = strtoupper( $flag[0] );

			if ( '' !== $name ) {
				$list[ ( isset( $names[ $name ] ) ? $names[ $name ] : $name ) ] = ( isset( $flag[1] ) ? $flag[1] : '' );
			}
		}

		return $list;
	}

	/**
	 * Check if the flags of a rewrite rule refuse the request
	 *
	 * @param  array  $flags  The flags from getRuleFlags
	 *
	 * @return bool
	 */
	protected function isForbidFlags( $flags ) {
		return ( isset( $flags['F'] ) || isset( $flags['G'] ) || ( isset( $flags['R'] ) && is_numeric( $flags['R'] ) && ( $flags['R'] < 300 || $flags['R'] > 399 ) ) );
	}

	/**
	 * Apply the access rules block by block: the last block with rules of a kind replaces the ones before it
	 *
	 * @param  array  $scopes  The lines of every block that applies to the file, in the order of the server
	 *
	 * @return bool
	 */
	protected function isAccessClosed( $scopes ) {
		$host = $user = $any = false;

		foreach ( $scopes as $lines ) {
			$order    = 'deny,allow';
			$merging  = 'off';
			$require  = array();
			$allow    = $deny = $satisfy = $has_host = $has_user = false;

			foreach ( $lines as $line ) {
				list( $name, $words ) = $line;

				switch ( $name ) {
					case 'require':
					case '<':
					case '>':
						$has_user = true;
						if ( false !== $words ) {
							$require[] = $line;
						}
						break;

					case 'authmerging':
						$merging = ( $words ? strtolower( $words[0] ) : $merging );
						break;

					case 'order':
						$has_host = true;
						$order    = ( $words ? strtolower( implode( '', $words ) ) : $order );
						break;

					case 'satisfy':
						$has_host = true;
						$satisfy  = ( $words ? ( 'any' === strtolower( $words[0] ) ) : $satisfy );
						break;

					case 'allow':
					case 'deny':
						$has_host = true;
						if ( $words && 'from' === strtolower( $words[0] ) && $this->isAddressListed( array_slice( $words, 1 ), true ) ) {
							if ( 'allow' === $name ) {
								$allow = true;
							} else {
								$deny = true;
							}
						}
						break;
				}
			}

			// Allow and Deny lines
			if ( $has_host ) {
				$any  = $satisfy;
				$host = ( 'deny,allow' === $order ? ( $deny && ! $allow ) : ( ! $allow || $deny ) );
			}

			// Require lines. With none for this request method the block lets the request in
			if ( $has_user ) {
				$closed = ( count( $require ) && $this->getRequireResult( $require ) < 1 );

				if ( 'and' === $merging ) {
					$user = ( $user || $closed );
				} elseif ( 'or' === $merging ) {
					$user = ( $user && $closed );
				} else {
					$user = $closed;
				}
			}
		}

		// With Satisfy Any one open side is enough
		return ( $any ? ( $host && $user ) : ( $host || $user ) );
	}

	/**
	 * Work out a group of Require lines: 1 granted, 0 no decision, -1 denied
	 *
	 * @param  array  $lines  The Require lines, with the markers of the RequireAll, RequireAny and RequireNone blocks
	 * @param  int  $index  The line to start from
	 * @param  string  $type  The block type
	 *
	 * @return int
	 */
	protected function getRequireResult( $lines, &$index = 0, $type = 'requireany' ) {
		$granted = $denied = false;

		for ( $count = count( $lines ); $index < $count; $index ++ ) {
			list( $name, $words ) = $lines[ $index ];

			if ( '>' === $name ) {
				break;
			}

			if ( '<' === $name ) {
				$index ++;
				$result = $this->getRequireResult( $lines, $index, $words );
			} else {
				$not    = ( isset( $words[0] ) && 'not' === strtolower( $words[0] ) );
				$met    = $this->isRequireMet( $not ? array_slice( $words, 1 ) : $words );
				$result = ( $not ? ( $met ? - 1 : 0 ) : ( $met ? 1 : - 1 ) );
			}

			$granted = ( $granted || 1 === $result );
			$denied  = ( $denied || - 1 === $result );
		}

		if ( 'requireall' === $type ) {
			return ( $denied ? - 1 : ( $granted ? 1 : 0 ) );
		}

		if ( 'requirenone' === $type ) {
			return ( $granted ? - 1 : 0 );
		}

		return ( $granted ? 1 : ( $denied ? - 1 : 0 ) );
	}

	/**
	 * Check one Require line for the current visitor
	 *
	 * @param  array  $words  The words after Require
	 *
	 * @return bool False also for a login, a group, a host name or a rule PHP can't check
	 */
	protected function isRequireMet( $words ) {
		$type = strtolower( (string) array_shift( $words ) );

		switch ( $type ) {
			case 'all':
				return ( isset( $words[0] ) && 'granted' === strtolower( $words[0] ) );
			case 'method':
				return in_array( $this->_request['method'], str_replace( 'HEAD', 'GET', array_map( 'strtoupper', $words ) ), true );
			case 'ip':
				return $this->isAddressListed( $words );
			case 'local':
				return $this->isAddressListed( array( '127', '::1', (string) $this->getServerValue( 'SERVER_ADDR' ) ) );
			case 'expr':
				return ( true === $this->isExpressionMet( implode( ' ', $words ) ) );
			case 'env':
				// One variable set by the folder rules is enough
				return (bool) array_intersect_key( array_flip( array_map( 'strtolower', $words ) ), $this->_request['env'] );
		}

		return false;
	}

	/**
	 * Check if the visitor's IP address is in a list of addresses
	 *
	 * @param  array  $entries  Full or partial IP addresses and IPv4 ranges
	 * @param  bool  $host  True for Allow and Deny lines, where "all" is a valid entry
	 *
	 * @return bool
	 */
	protected function isAddressListed( $entries, $host = false ) {
		$ip = strtolower( (string) $this->getServerValue( 'REMOTE_ADDR' ) );

		foreach ( $entries as $entry ) {
			$entry = strtolower( trim( (string) $entry ) );

			if ( '' === $entry ) {
				continue;
			}

			if ( $host && 'all' === $entry ) {
				return true;
			}

			// A variable counts as set only when the folder rules set it for this request
			if ( $host && strpos( $entry, 'env=' ) === 0 ) {
				$name = ltrim( substr( $entry, 4 ), '!' );

				if ( isset( $this->_request['env'][ $name ] ) === ( '!' !== substr( $entry, 4, 1 ) ) ) {
					return true;
				}

				continue;
			}

			if ( $entry === $ip ) {
				return true;
			}

			// The first numbers of an address stand for the whole range
			if ( preg_match( '/^\d{1,3}(\.\d{1,3}){0,2}\.?$/', $entry ) && strpos( $ip, rtrim( $entry, '.' ) . '.' ) === 0 ) {
				return true;
			}

			if ( preg_match( '#^(\d{1,3}(?:\.\d{1,3}){0,3})/(\d{1,2})$#', $entry, $match ) && $match[2] <= 32 ) {
				$network = ip2long( implode( '.', array_pad( explode( '.', $match[1] ), 4, '0' ) ) );
				$address = ip2long( $ip );
				$mask    = ( $match[2] > 0 ? ( - 1 << ( 32 - (int) $match[2] ) ) : 0 );

				if ( false !== $network && false !== $address && ( $network & $mask ) === ( $address & $mask ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if a Redirect line of a folder sends the visitor away from the file
	 *
	 * @param  array  $folder  The rules of the folder
	 *
	 * @return bool
	 */
	protected function isRedirectClosed( $folder ) {
		foreach ( $folder['redirect'] as $line ) {
			list( $name, $words ) = $line;
			$status = 302;
			$known  = false;

			if ( in_array( $name, array( 'redirect', 'redirectmatch' ), true ) && isset( $words[0] ) && preg_match( '/^(\d{3}|permanent|temp|seeother|gone)$/i', $words[0] ) ) {
				$status = strtolower( array_shift( $words ) );
				$known  = true;
			}

			$error = ( 'gone' === $status || ( is_numeric( $status ) && ( $status < 300 || $status > 399 ) ) );
			$count = count( $words );

			// A line without a path counts for every file of the folder
			if ( $count < 2 && ( ! $known || 0 === $count || ! $error ) ) {
				if ( $error || ! $folder['root'] ) {
					return true;
				}

				continue;
			}

			// An error status counts in every folder, a move to another address only below the root
			if ( '' === $words[0] || ( ! $error && $folder['root'] ) ) {
				continue;
			}

			if ( 'redirectmatch' === $name ) {
				$found = $this->isPatternMatch( $words[0], $this->_request['uri'] );
				$found = ( null === $found ? ! $folder['root'] : $found );
			} else {
				$found = ( '/' === $words[0][0] && ( $this->_request['uri'] === $words[0] || strpos( $this->_request['uri'], rtrim( $words[0], '/' ) . '/' ) === 0 ) );
			}

			if ( $found ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Run the rewrite rules the server would run for a direct request to the file
	 *
	 * @param  array  $folders  The rules of the folders, from the website root down
	 *
	 * @return bool
	 */
	protected function isRewriteClosed( $folders ) {
		$engine = true;
		$lines  = $base = array();
		$dir    = '';
		$links  = array( 'followsymlinks' => true, 'symlinksifownermatch' => false );

		foreach ( $folders as $folder ) {
			// The options of the root let this request through already
			foreach ( ( $folder['root'] ? array() : $folder['options'] ) as $words ) {
				// A line without signs replaces the options of the folders above
				if ( preg_grep( '/^[^+-]/', $words ) ) {
					$links = array_fill_keys( array_keys( $links ), false );
				}

				foreach ( $words as $word ) {
					$name = ltrim( $word, '+-' );
					$name = ( 'all' === $name ? 'followsymlinks' : $name );

					if ( isset( $links[ $name ] ) ) {
						$links[ $name ] = ( '-' !== $word[0] );
					}
				}
			}

			if ( empty( $folder['rewrite'] ) ) {
				continue;
			}

			$own = $conds = array();
			$set = null;

			foreach ( $folder['rewrite'] as $line ) {
				list( $type, $words ) = $line;

				if ( 'engine' === $type ) {
					$engine = ( isset( $words[0] ) && 'on' === strtolower( $words[0] ) );
				} elseif ( 'options' === $type ) {
					$set = array_merge( (array) $set, array_map( 'strtolower', $words ) );
				} elseif ( 'cond' === $type ) {
					$conds[] = $words;
				} elseif ( 'rule' === $type ) {
					$own[] = array( $words, $conds, $folder['root'] );
					$conds = array();
				}
			}

			// The deepest folder with rewrite lines gives the rules, the ones from above stay only with an inherit option
			$options = ( null === $set ? $base : $set );
			$keep    = ! in_array( 'ignoreinherit', $options, true );

			if ( in_array( 'inherit', $options, true ) || ( $keep && in_array( 'inheritdown', $base, true ) ) ) {
				$lines = array_merge( $own, $lines );
			} elseif ( in_array( 'inheritbefore', $options, true ) || ( $keep && in_array( 'inheritdownbefore', $base, true ) ) ) {
				$lines = array_merge( $lines, $own );
			} else {
				$lines = $own;
			}

			$base = $options;
			$dir  = $folder['dir'];
		}

		if ( ! $engine || '' === $dir ) {
			return false;
		}

		// The rewrite engine refuses a folder where the server may not follow links
		if ( ! array_filter( $links ) ) {
			return true;
		}

		// Every rule is matched on the path below the folder that gives the rules
		$local = ltrim( substr( $this->_request['path'], strlen( $dir ) ), '/' );
		$jump  = 0;
		$chain = false;

		foreach ( $lines as $line ) {
			list( $words, $rule_conds, $is_root ) = $line;
			$flags = $this->getRuleFlags( isset( $words[2] ) ? $words[2] : '' );

			if ( count( $words ) < 2 ) {
				continue;
			}

			// Rules jumped over by the S flag or by a broken chain
			if ( $jump > 0 || $chain ) {
				$jump  = max( 0, $jump - 1 );
				$chain = ( $chain && isset( $flags['C'] ) );
				continue;
			}

			$match  = array();
			$negate = ( strpos( $words[0], '!' ) === 0 );
			$found  = $this->isPatternMatch( ( $negate ? substr( $words[0], 1 ) : $words[0] ), $local, isset( $flags['NC'] ), $match );

			// A pattern or a condition PHP can't read leaves the rule in doubt
			$doubt   = ( null === $found );
			$applies = ( $doubt || $found !== $negate );

			if ( $applies && $rule_conds ) {
				$applies = $this->areConditionsMet( $rule_conds, $match, true );
				$doubt   = ( $doubt || ( $applies && ! $this->areConditionsMet( $rule_conds, $match, false ) ) );
			}

			if ( ! $applies ) {
				$chain = isset( $flags['C'] );
				continue;
			}

			// The variables the rule sets for the conditions of the rules after it
			foreach ( explode( ',', trim( ( isset( $words[2] ) ? $words[2] : '' ), "[] \t" ) ) as $flag ) {
				if ( ! preg_match( '/^\s*E(?:NV)?=(!?)([^:]+)(?::(.*))?$/is', $flag, $part ) ) {
					continue;
				}

				$name = strtolower( trim( $part[2] ) );

				if ( $doubt || ( isset( $part[3] ) && preg_match( '/%\{|[$%]\d/', $part[3] ) ) ) {
					// A value PHP can't know
					$this->_request['env'][ $name ] = null;
				} elseif ( '!' === $part[1] ) {
					unset( $this->_request['env'][ $name ] );
				} else {
					$this->_request['env'][ $name ] = ( isset( $part[3] ) ? $part[3] : '' );
				}
			}

			$result = $this->getRewriteResult( $words[1], $flags, $match, $is_root, $rule_conds, $dir );

			// In doubt, a rule counts only when it closes a folder below the root
			if ( $doubt ) {
				if ( 'closed' === $result && ! $is_root ) {
					return true;
				}
				continue;
			}

			if ( 'closed' === $result ) {
				return true;
			}

			if ( 'stop' === $result ) {
				return false;
			}

			$jump = ( isset( $flags['S'] ) ? (int) $flags['S'] : 0 );
		}

		return false;
	}

	/**
	 * Tell what a rewrite rule that applies does to the request for the file
	 *
	 * @param  string  $target  The new address in the rule
	 * @param  array  $flags  The flags of the rule
	 * @param  array  $match  The matches of the rule pattern
	 * @param  bool  $is_root  True for a rule in the website root
	 * @param  array  $conds  The conditions of the rule
	 * @param  string  $dir  The folder that gives the rules
	 *
	 * @return string closed, stop or next
	 */
	protected function getRewriteResult( $target, $flags, $match, $is_root, $conds, $dir ) {
		$next = ( ( isset( $flags['L'] ) || isset( $flags['END'] ) ) ? 'stop' : 'next' );

		// Also a rule that gives the file to a script handler
		if ( $this->isForbidFlags( $flags ) || isset( $flags['H'] ) || ( isset( $flags['T'] ) && preg_match( '/php|x-httpd/i', $flags['T'] ) ) ) {
			return 'closed';
		}

		if ( '-' === $target ) {
			return $next;
		}

		list( $target ) = $this->getRequestValue( $target, $match );
		$redirect = ( isset( $flags['R'] ) || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $target ) );
		$target   = (string) preg_replace( '/\?.*$/s', '', $target );
		$name     = strtolower( basename( $target ) );
		$file     = strtolower( basename( $this->_request['path'] ) );
		$asset    = (bool) preg_match( '/\.(css|js|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|otf|eot)$/', $file );
		$home     = HMWP_Classes_Tools::getHomeRootPath();

		// WordPress decides who reads the file
		if ( in_array( $name, array( 'index.php', 'wp-login.php' ), true ) || rtrim( strtolower( $target ), '/' ) === rtrim( strtolower( $home ), '/' ) ) {
			return 'closed';
		}

		// A script that picks the image format the browser accepts gives the file to everyone, another script may not
		if ( '.php' === substr( $name, - 4 ) ) {
			return ( ( $asset && preg_grep( '/%\{HTTP(_|:)ACCEPT\}/i', array_column( $conds, 0 ) ) ) ? 'stop' : 'closed' );
		}

		// A redirect of the whole website in the root keeps the file open, any other one sends the visitor away
		if ( $redirect ) {
			return ( ( ! $is_root || $conds ) ? 'closed' : 'stop' );
		}

		// The file the server sends in place of the asked one
		if ( strpos( $target, $this->_request['root'] . '/' ) === 0 ) {
			$full = $target;
		} elseif ( isset( $target[0] ) && '/' === $target[0] ) {
			$full = $this->_request['root'] . '/' . ltrim( ( strpos( $target, $home ) === 0 ? substr( $target, strlen( $home ) ) : $target ), '/' );
		} else {
			$full = $dir . '/' . $target;
		}

		if ( $full === $this->_request['path'] ) {
			return $next;
		}

		// Another copy of an image or style file: packed, or in a newer image format
		if ( $asset ) {
			$copy = ( strpos( $name, $file . '.' ) === 0 && in_array( substr( $name, strlen( $file ) + 1 ), array( 'gz', 'br', 'webp', 'avif' ), true ) );
			$copy = ( $copy || ( preg_match( '/^(.+)\.(jpe?g|png|gif)$/', $file, $part ) && in_array( $name, array( $part[1] . '.webp', $part[1] . '.avif' ), true ) ) );
			$copy = ( $copy || in_array( $name, array( $file . '_gzip', $file . '_br' ), true ) );

			if ( $is_root || ( ( $copy || $name === $file ) && is_file( $full ) ) ) {
				return $next;
			}
		}

		return 'closed';
	}

	/**
	 * Check the conditions of a rewrite rule, in the order and with the OR logic of the server
	 *
	 * @param  array  $conds  The RewriteCond lines as words
	 * @param  array  $match  The matches of the rule pattern
	 * @param  bool  $default  The answer for a condition PHP can't check
	 *
	 * @return bool
	 */
	protected function areConditionsMet( $conds, $match, $default ) {
		for ( $i = 0, $count = count( $conds ); $i < $count; $i ++ ) {
			$flags = $this->getRuleFlags( isset( $conds[ $i ][2] ) ? $conds[ $i ][2] : '' );
			$met   = $this->isConditionMet( $conds[ $i ], $match, $default );

			if ( isset( $flags['OR'] ) ) {
				if ( ! $met ) {
					continue;
				}

				// One true condition is enough: jump to the last one of the OR group
				while ( $i < $count - 1 ) {
					$flags = $this->getRuleFlags( isset( $conds[ $i ][2] ) ? $conds[ $i ][2] : '' );
					if ( ! isset( $flags['OR'] ) ) {
						break;
					}
					$i ++;
				}
			} elseif ( ! $met ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check one rewrite condition for the current request
	 *
	 * @param  array  $words  Test string, pattern and flags
	 * @param  array  $match  The matches of the rule pattern
	 * @param  bool  $default  The answer for a condition PHP can't check
	 *
	 * @return bool
	 */
	protected function isConditionMet( $words, $match, $default ) {
		if ( count( $words ) < 2 ) {
			return $default;
		}

		// The condition is written as a server expression
		if ( 'expr' === strtolower( $words[0] ) ) {
			$found = $this->isExpressionMet( $words[1] );

			return ( null === $found ? $default : $found );
		}

		list( $value, $known ) = $this->getRequestValue( $words[0], $match );
		if ( ! $known ) {
			return $default;
		}

		$flags   = $this->getRuleFlags( isset( $words[2] ) ? $words[2] : '' );
		$negate  = ( strpos( $words[1], '!' ) === 0 );
		$pattern = ( $negate ? substr( $words[1], 1 ) : $words[1] );

		if ( in_array( $pattern, array( '-f', '-F', '-d', '-s', '-l', '-L', '-h' ), true ) ) {
			// Only a path inside the website can be looked up
			if ( strpos( $value, $this->_request['root'] . '/' ) !== 0 || strpos( $value, '..' ) !== false || strpos( $value, "\0" ) !== false ) {
				return $default;
			}

			if ( '-d' === $pattern ) {
				$found = is_dir( $value );
			} elseif ( '-s' === $pattern ) {
				$found = ( is_file( $value ) && filesize( $value ) > 0 );
			} elseif ( in_array( $pattern, array( '-l', '-L', '-h' ), true ) ) {
				$found = is_link( $value );
			} else {
				$found = is_file( $value );
			}
		} elseif ( preg_match( '/^(<|>|-(eq|ge|gt|le|lt|ne|U|x)$)/', $pattern ) ) {
			return $default;
		} elseif ( strpos( $pattern, '=' ) === 0 ) {
			$text  = trim( substr( $pattern, 1 ), '"' );
			$found = ( isset( $flags['NC'] ) ? ( strcasecmp( $value, $text ) === 0 ) : ( $value === $text ) );
		} else {
			$found = $this->isPatternMatch( $pattern, $value, isset( $flags['NC'] ) );
			if ( null === $found ) {
				return $default;
			}
		}

		return ( $found !== $negate );
	}

	/**
	 * Fill the server variables and the rule matches into a test string or a new address
	 *
	 * @param  string  $string  The text from the rule
	 * @param  array  $match  The matches of the rule pattern
	 *
	 * @return array The value and true when every part of it is known
	 */
	protected function getRequestValue( $string, $match = array() ) {
		$known = true;
		$value = preg_replace_callback( '/%\{([^}]*)\}|\$(\d)|%(\d)/', function ( $found ) use ( &$known, $match ) {
			// A match of an earlier condition is not kept
			if ( isset( $found[3] ) ) {
				$known = false;

				return '';
			}

			if ( isset( $found[2] ) ) {
				return ( isset( $match[ (int) $found[2] ] ) ? $match[ (int) $found[2] ] : '' );
			}

			$server = $this->getServerValue( $found[1] );
			$known  = ( $known && null !== $server );

			return (string) $server;
		}, (string) $string );

		return array( (string) $value, $known );
	}

	/**
	 * Return a server variable as the server would have it on a direct request to the file
	 *
	 * @param  string  $name  The variable name
	 *
	 * @return string|null Null for a variable PHP can't know
	 */
	protected function getServerValue( $name ) {
		$name = strtoupper( trim( (string) $name ) );

		if ( strpos( $name, 'HTTP:' ) === 0 ) {
			$name = 'HTTP_' . str_replace( '-', '_', substr( $name, 5 ) );
		}

		switch ( $name ) {
			case 'REQUEST_URI':
				return $this->_request['uri'];
			case 'REQUEST_FILENAME':
			case 'SCRIPT_FILENAME':
				return $this->_request['path'];
			case 'QUERY_STRING':
				return $this->_request['query'];
			case 'REQUEST_METHOD':
				return $this->_request['verb'];
			case 'THE_REQUEST':
				return $this->_request['verb'] . ' ' . $this->_request['uri'] . ( '' !== $this->_request['query'] ? '?' . $this->_request['query'] : '' ) . ' HTTP/1.1';
			case 'HTTPS':
				return ( is_ssl() ? 'on' : 'off' );
			case 'REQUEST_SCHEME':
				return ( is_ssl() ? 'https' : 'http' );
			case 'IS_SUBREQ':
				return 'false';
			case 'REMOTE_USER':
			case 'REMOTE_IDENT':
			case 'AUTH_TYPE':
			case 'PATH_INFO':
				return '';
		}

		// Only the variables set by the folder rules for this request are known
		if ( strpos( $name, 'ENV:' ) === 0 ) {
			$name = strtolower( substr( $name, 4 ) );

			if ( ! array_key_exists( $name, $this->_request['env'] ) ) {
				return '';
			}

			// A variable set by a rewrite rule may hold a value PHP can't know
			return ( null === $this->_request['env'][ $name ] ? null : (string) $this->_request['env'][ $name ] );
		}

		$header = ( strpos( $name, 'HTTP_' ) === 0 );

		if ( $header || in_array( $name, array( 'DOCUMENT_ROOT', 'REMOTE_ADDR', 'SERVER_NAME', 'SERVER_PORT', 'SERVER_PROTOCOL', 'SERVER_ADDR' ), true ) ) {
			if ( isset( $_SERVER[ $name ] ) ) {
				return (string) wp_unslash( $_SERVER[ $name ] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}

			return ( $header ? '' : null );
		}

		return null;
	}

	/**
	 * Work out the test of an If block, a Require expr line or a rewrite condition written as an expression
	 *
	 * @param  string  $expression  Comparisons of one server variable each, joined by && and ||
	 *
	 * @return bool|null Null for a test PHP can't read
	 */
	protected function isExpressionMet( $expression ) {
		$expression = trim( (string) $expression );

		if ( strlen( $expression ) > 1 && ( '"' === $expression[0] || "'" === $expression[0] ) && substr( $expression, - 1 ) === $expression[0] ) {
			$expression = substr( $expression, 1, - 1 );
		}

		$met = false;

		// One group that holds is enough, a group holds when all its comparisons do
		foreach ( explode( '||', $expression ) as $group ) {
			$all = true;

			foreach ( explode( '&&', $group ) as $part ) {
				$found = $this->isComparisonMet( $part );

				if ( false === $found ) {
					$all = false;
					break;
				}

				$all = ( null === $found ? null : $all );
			}

			if ( true === $all ) {
				return true;
			}

			$met = ( null === $all ? null : $met );
		}

		return $met;
	}

	/**
	 * Work out one comparison of a server variable with a pattern or a text
	 *
	 * @param  string  $text  The comparison
	 *
	 * @return bool|null Null for a comparison PHP can't read
	 */
	protected function isComparisonMet( $text ) {
		if ( preg_match( '/^(!?)\s*(true|false)$/i', trim( $text ), $part ) ) {
			return ( ( 'true' === strtolower( $part[2] ) ) !== ( '!' === $part[1] ) );
		}

		if ( ! preg_match( '/^(!?)\s*%\{([^}]+)\}\s*(=~|!~|==|!=|=)\s*(.+)$/s', trim( $text ), $part ) ) {
			return null;
		}

		$value = $this->getServerValue( $part[2] );
		if ( null === $value ) {
			return null;
		}

		if ( '=~' === $part[3] || '!~' === $part[3] ) {
			// The pattern is written as /text/ or m#text#, with i for any letter case
			if ( ! preg_match( '/^(?:\/(.*)\/|m(.)(.*)\2)(i?)$/s', trim( $part[4] ), $regex ) ) {
				return null;
			}

			$found = $this->isPatternMatch( ( '' !== $regex[2] ? $regex[3] : $regex[1] ), $value, ( 'i' === $regex[4] ) );
			if ( null === $found ) {
				return null;
			}

			$met = ( $found === ( '=~' === $part[3] ) );
		} else {
			if ( ! preg_match( '/^([\'"])(.*)\1$/s', trim( $part[4] ), $text ) ) {
				return null;
			}

			$met = ( ( $value === $text[2] ) === ( '!=' !== $part[3] ) );
		}

		return ( '!' === $part[1] ? ! $met : $met );
	}

	/**
	 * Match a pattern from a server rule the way the server does
	 *
	 * @param  string  $pattern  The pattern, without delimiters
	 * @param  string  $subject  The text to look in
	 * @param  bool  $nocase  True to ignore the letter case
	 * @param  array  $match  The matches
	 *
	 * @return bool|null Null for a pattern PHP can't read
	 */
	protected function isPatternMatch( $pattern, $subject, $nocase = false, &$match = array() ) {
		$found = @preg_match( "\x01" . $pattern . "\x01sD" . ( $nocase ? 'i' : '' ), (string) $subject, $match ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return ( false === $found ? null : (bool) $found );
	}

	/**
	 * Return the file mime based on extension
	 *
	 * @param $ext
	 *
	 * @return false|string
	 */
	private function getMime( $ext ) {

		switch ( $ext ) {
			case "scss":
			case "csv":
			case "css":
				return "text/css";
			case "js":
			case "mjs":
				return "text/javascript";
			case "svg":
				return "image/svg+xml";
			case "jpg":
				return "image/jpeg";
			case "jpeg":
			case "png":
			case "bmp":
			case "gif":
			case "jp2":
			case "tiff":
			case "webp":
			case "avif":
				return "image/" . $ext;
			case "ico":
			case "icon":
				return "image/vnd.microsoft.icon";
			case "woff":
			case "woff2":
			case "ttf":
			case "otf":
				return "font/" . $ext;
			case "eot":
				return "application/vnd.ms-fontobject";
			case "avi":
				return "video/x-msvideo";
			case "mp4":
			case "mpeg":
			case "webm":
				return "video/" . $ext;
			case "doc":
				return "application/msword";
			case "xls":
				return "application/vnd.ms-excel";
			case "json":
				return "application/json";
			case "docx":
				return "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
			case "xlsx":
				return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
			case "xml":
			case "zip":
				return "application/" . $ext;
		}

		return false;
	}

	/**
	 * Show the file when the server rewrite is not added
	 *
	 * @param  string  $url  broken URL
	 *
	 * @throws Exception
	 */
	public function showFile( $url ) {

		// Initialize WordPress Filesystem
		$wp_filesystem = HMWP_Classes_ObjController::initFilesystem();

		// Remove the redirect hook
		remove_filter( 'wp_redirect', array(
			HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' ),
			'sanitize_redirect'
		), PHP_INT_MAX );
		remove_filter( 'template_directory_uri', array(
			HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' ),
			'find_replace_url'
		), PHP_INT_MAX );

		// Build the rewrite rules
		if ( empty( $this->_rewrites ) ) {
			$this->buildRedirect();
		}

		//Get the original URL and path based on rewrite rules
		$url_no_query     = ( ( strpos( $url, '?' ) !== false ) ? substr( $url, 0, strpos( $url, '?' ) ) : $url );
		$new_url          = $this->getOriginalUrl( $url );
		$mapped           = $this->_mapped;
		$new_url_no_query = ( ( strpos( $new_url, '?' ) !== false ) ? substr( $new_url, 0, strpos( $new_url, '?' ) ) : $new_url );
		$new_path         = $this->getOriginalPath( $new_url );
		$mime             = false;

		//hook the original url/path when handles by WP
		do_action( 'hmwp_files_show_file', $new_url, $new_path );

		// If there is a mapping in the current URL
		if ( $url <> $new_url ) {

			// If it's a file type
			if ( $ext = $this->isFile( $new_url ) ) {

				// Only a changed path, in its own letters, maps to a file: a quote, a backslash or a dot segment in the address is a broken link
				if ( $url_no_query === $new_url_no_query || $new_url !== $this->getOriginalUrl( $url, true ) || ! HMWP_Classes_Tools::isCleanUrlPath( $url_no_query ) ) {
					return;
				}

				// A folder closed to visitors in its .htaccess stays closed
				if ( $new_path && ( $this->isDeniedPath( $new_path ) || ( ( $link_path = $this->getOriginalPath( $new_url, false ) ) && $link_path !== $new_path && $this->isDeniedPath( $link_path ) ) ) ) {
					return;
				}

				//if the file exists on the server
				if ( $new_path && $wp_filesystem->exists( $new_path ) ) {

					//If the plugin is not set to map all the files dynamically
					if ( ! HMWP_Classes_Tools::getOption( 'hmwp_mapping_file' ) ) {
						//if file is loaded through WordPress rewrites and not through config file
						if ( wp_parse_url( $url ) && in_array( $ext, array( 'png', 'jpg', 'jpeg', 'webp', 'gif', 'css', 'js', 'svg', 'woff', 'woff2' ) ) ) {
							// Only an admin's own visit may switch on the safe fallback, never a visitor's request
							if ( stripos( $new_url, 'wp-admin' ) === false && function_exists( 'current_user_can' ) && HMWP_Classes_Tools::userCan( HMWP_CAPABILITY ) ) {
								//if it's a valid URL and not from admin
								//add the url in the WP rewrite list
								$mappings = (array) HMWP_Classes_Tools::getOption( 'file_mappings' );
								if ( count( $mappings ) < 10 ) {
									$mappings[ md5( $url ) ] = $url;
									HMWP_Classes_Tools::saveOptions( 'file_mappings', $mappings );
								}

								//for debug
								do_action( 'hmwp_debug_files', $url );
							}
						}

					}

					//////////////////////////////////////////////////////////////////////////

					if ( ! $mime = $this->getMime( $ext ) ) {
						if ( function_exists( 'mime_content_type' ) ) {
							$mime = @mime_content_type( $new_path );
						} else {
							$mime = 'text/plain';
						}
					}

					//////////////////////////////////////////////////////////////////////////

					// Only the CSS, JS and SCSS content is changed before it's sent
					$ismapped = ( strpos( $new_url, '.js' ) || strpos( $new_url, '.css' ) || strpos( $new_url, '.scss' ) );

					// Build the validator before the file is read
					$validator = $this->getFileValidator( $new_path, $ismapped );

					header( "Cache-Control: max-age=2592000, must-revalidate" );
					header( "Expires: " . gmdate( 'r', strtotime( "+1 month" ) ) );
					header( "Vary: Accept-Encoding" );
					header( "Pragma: public" );
					header( 'Etag: "' . $validator['etag'] . '"' );

					if ( $validator['mtime'] > 0 ) {
						header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $validator['mtime'] ) . ' GMT' );
					}

					// Answer the conditional request without reading the file
					if ( $this->isNotModified( $validator['etag'], $validator['mtime'] ) ) {

						$this->closeBuffers();

						if ( function_exists( 'http_response_code' ) ) {
							http_response_code( 304 );
						}

						header( "HTTP/1.1 304 Not Modified" );
						exit();
					}

					if ( function_exists( 'http_response_code' ) ) {
						http_response_code( 200 );
					}

					header( "HTTP/1.1 200 OK" );

					if ( $mime ) {
						header( 'Content-Type: ' . $mime . '; charset=UTF-8' );
					}

					//////////////////////////////////////////////////////////////////////////
					// The images, fonts and media need no change, send them without loading them in memory
					if ( ! $ismapped ) {

						$this->closeBuffers();

						if ( $validator['size'] > 0 ) {
							header( 'Content-Length: ' . $validator['size'] );
						}

						if ( readfile( $new_path ) === false ) { //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams the file, WP_Filesystem would load it in memory
							echo $wp_filesystem->get_contents( $new_path ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}

						exit();
					}

					//////////////////////////////////////////////////////////////////////////
					// If CSS, JS or SCSS
					$encoding = $this->getAcceptedEncoding();

					if ( $encoding ) {
						header( 'Content-Encoding: ' . $encoding );
					}

					// The changed content is the same for every visitor, build it once
					$cachefile = ( $this->isCacheable() ? $this->getCacheFile( $validator['etag'], $encoding ) : '' );

					if ( $cachefile && $wp_filesystem->exists( $cachefile ) ) {

						$this->closeBuffers();

						if ( $cachesize = (int) $wp_filesystem->size( $cachefile ) ) {
							header( 'Content-Length: ' . $cachesize );
						}

						if ( readfile( $cachefile ) !== false ) { //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams the file, WP_Filesystem would load it in memory
							exit();
						}
					}

					ob_clean(); //clear the buffer
					$content = $wp_filesystem->get_contents( $new_path );

					// URL Mapping for all css and js files
					$content = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' )->find_replace_url( $content );
					// Text Mapping for all css and js files
					$content = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' )->replaceTextMapping( $content, true );

					// Compress the CSS and JS only with what the browser accepts
					switch ( $encoding ) {
						case 'br':
							$content = brotli_compress( $content, 1 );
							break;
						case 'gzip':
							$content = gzencode( $content );
							break;
					}

					// Keep the built content for the next request
					if ( $cachefile ) {
						$this->saveCacheFile( $cachefile, $content );
					}

					// Send the body past the other plugins' buffers, as the cached path does,
					// so none of them can change or cut the compressed content
					$this->closeBuffers();

					// Show the file html content
					header( 'Content-Length: ' . strlen( $content ) );

					echo $content; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					exit();
				}

			} elseif ( ! $mapped ) {

				// No changed path in the address: it only looks like an old path once it is cleaned
				return;

			} elseif ( stripos( trailingslashit( $new_url_no_query ), '/' . HMWP_Classes_Tools::getDefault( 'hmwp_wp-json' ) . '/' ) !== false ) {

					// Don't re-proxy a request we already looped back ourselves, or the
					// legacy /wp-json/ proxy can pile up and take the site down
					if ( ! empty( $_SERVER['HTTP_X_HMWP_LOOPBACK'] ) ) {
						return;
					}

					$response = false;

					if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
						$response = $this->postRequest( $new_url );
					} elseif ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'GET' ) {
						$response = $this->getRequest( $new_url );
					}

					if ( $response ) {
						header( "HTTP/1.1 200 OK" );

						if ( ! empty( $response['headers'] ) ) {
							foreach ( $response['headers'] as $header ) {
								header( $header );
							}
						}

						//Echo the html file content
						echo $response['body']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						exit();
					}

					exit();

			} elseif ( stripos( trailingslashit( $url_no_query ), '/' . HMWP_Classes_Tools::getOption( 'hmwp_login_url' ) . '/' ) !== false ||
			           ( HMWP_Classes_Tools::getOption( 'hmwp_logout_url' ) <> '' && stripos( trailingslashit( $url_no_query ), '/' . HMWP_Classes_Tools::getOption( 'hmwp_logout_url' ) . '/' ) !== false ) ||
			           ( HMWP_Classes_Tools::getOption( 'hmwp_lostpassword_url' ) <> '' && stripos( trailingslashit( $url_no_query ), '/' . HMWP_Classes_Tools::getOption( 'hmwp_lostpassword_url' ) . '/' ) !== false ) ||
			           ( HMWP_Classes_Tools::getOption( 'hmwp_register_url' ) <> '' && stripos( trailingslashit( $url_no_query ), '/' . HMWP_Classes_Tools::getOption( 'hmwp_register_url' ) . '/' ) !== false ) ||
			           stripos( trailingslashit( $new_url_no_query ), '/' . HMWP_Classes_Tools::getDefault( 'hmwp_login_url' ) . '/' ) !== false ) {

				add_filter( 'hmwp_option_hmwp_remove_third_hooks', '__return_true' );

				header( "HTTP/1.1 200 OK" );

				$this->handleLogin( $new_url );

			} elseif ( stripos( trailingslashit( $url_no_query ), '/' . HMWP_Classes_Tools::getOption( 'hmwp_admin_url' ) . '/' ) !== false ||
			           stripos( trailingslashit( $new_url_no_query ), '/' . HMWP_Classes_Tools::getDefault( 'hmwp_admin_url' ) . '/' ) !== false ) {

				wp_safe_redirect( $new_url, 301 );
				exit();

			} elseif ( HMWP_Classes_Tools::isMultisites() && stripos( trailingslashit( $url_no_query ), '/' . HMWP_Classes_Tools::getOption( 'hmwp_activate_url' ) . '/' ) !== false ) {

				if ( $new_path && strpos( $new_path, 'wp-activate.php' ) && $wp_filesystem->exists( $new_path ) ) {
					header( "HTTP/1.1 200 OK" );

					ob_start();
					global $wp_object_cache, $wp_query;
					require_once $new_path;
					$content = ob_get_clean();

					//Echo the html file content
					echo $content; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					die();
				}

			}

		}
	}

	/**
	 * Request a page of this site and follow its redirects only while they stay on this site
	 *
	 * wp_safe_remote_request() also refuses loopback and private addresses on any other host.
	 *
	 * @param  string  $url  The site URL to request
	 * @param  array  $options  The request options
	 *
	 * @return array|WP_Error
	 */
	public function siteRequest( $url, $options ) {

		$options['redirection'] = 0;
		if ( ! isset( $options['sslverify'] ) ) {
			$options['sslverify'] = true;
		}

		for ( $i = 0; $i <= 5; $i ++ ) {

			if ( ! $this->isSiteUrl( $url ) ) {
				return new WP_Error( 'hmwp_files_external', 'Only requests to this site are allowed.' );
			}

			$response = wp_safe_remote_request( $url, $options );

			// The site proxies to itself, so retry once without SSL verification when the
			// server can't validate its own certificate on loopback
			if ( is_wp_error( $response ) && ! empty( $options['sslverify'] ) ) {
				$options['sslverify'] = false;
				$response = wp_safe_remote_request( $url, $options );
			}
			$code     = (int) wp_remote_retrieve_response_code( $response );
			$location = wp_remote_retrieve_header( $response, 'location' );

			if ( $code < 300 || $code > 399 || ! is_string( $location ) || $location === '' ) {
				return $response;
			}

			$url = WP_Http::make_absolute_url( $location, $url );

			//A redirected form post continues as a GET, the same as in the browser
			if ( in_array( $code, array( 301, 302, 303 ), true ) ) {
				$options['method'] = 'GET';
				unset( $options['body'] );
			}
		}

		return new WP_Error( 'hmwp_files_redirects', 'Too many redirects.' );
	}

	/**
	 * Do a Post request
	 *
	 * @param  $url
	 *
	 * @return array
	 */
	public function postRequest( $url ) {
		$return = array();

		// Only requests to this site are allowed
		if ( ! $this->isSiteUrl( $url ) ) {
			return array();
		}

		$headers = $this->getForwardHeaders();
		$options = array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => $_POST, //phpcs:ignore WordPress.Security.NonceVerification.Missing
			'timeout'   => 30,
			'sslverify' => true,
		);

		do_action( 'hmwp_files_post_request_before', $url, $options );

		$response = $this->siteRequest( $url, $options );

		$return['body']    = wp_remote_retrieve_body( $response );
		$return['headers'] = $this->getResponseHeaders( $response );

		do_action( 'hmwp_files_post_request_after', $url, $return );

		return $return;
	}

	/**
	 * Do a Get request
	 *
	 * @param  $url
	 *
	 * @return array
	 */
	public function getRequest( $url ) {
		$return = array();

		// Only requests to this site are allowed
		if ( ! $this->isSiteUrl( $url ) ) {
			return array();
		}

		$headers = $this->getForwardHeaders();
		$options = array(
			'method'    => 'GET',
			'headers'   => $headers,
			'timeout'   => 30,
			'sslverify' => true,
		);

		do_action( 'hmwp_files_get_request_before', $url, $options );

		$response = $this->siteRequest( $url, $options );

		$return['body']    = wp_remote_retrieve_body( $response );
		$return['headers'] = $this->getResponseHeaders( $response );

		do_action( 'hmwp_files_get_request_after', $url, $return );

		return $return;
	}

	/**
	 * Look into array of actions
	 *
	 * @param $haystack
	 * @param  array  $needles
	 * @param  int  $offset
	 *
	 * @return bool|mixed
	 */
	function strposa( $haystack, $needles = array(), $offset = 0 ) {
		foreach ( $needles as $needle ) {
			if ( strpos( $haystack, $needle, $offset ) !== false ) {
				return $needle;
			}
		}

		return false;
	}

	/**
	 * Handle the Login if the rules were not added in the config file
	 *
	 * @param $url
	 *
	 * @return void
	 */
	public function handleLogin( $url ) {
		$url = rawurldecode( $url );

		//If it's the login page
		if ( strpos( $url, '/' . HMWP_Classes_Tools::getOption( 'hmwp_login_url' ) ) || strpos( $url, '/' . HMWP_Classes_Tools::getDefault( 'hmwp_login_url' ) ) || ( HMWP_Classes_Tools::getOption( 'hmwp_lostpassword_url' ) && strpos( $url, '/' . HMWP_Classes_Tools::getOption( 'hmwp_lostpassword_url' ) ) ) || ( HMWP_Classes_Tools::getOption( 'hmwp_register_url' ) && strpos( $url, '/' . HMWP_Classes_Tools::getOption( 'hmwp_register_url' ) ) ) ) {

			do_action( 'hmwp_files_handle_login', $url );

			//Get the action if exists in params
			$params = array();
			$query  = wp_parse_url( $url, PHP_URL_QUERY );
			if ( $query <> '' ) {
				parse_str( $query, $params );
			}

			if ( isset( $params['action'] ) ) {
				$actions            = array(
					'postpass',
					'logout',
					'lostpassword',
					'retrievepassword',
					'resetpass',
					'rp',
					'register',
					'login',
					'confirmaction',
					'validate_2fa',
					'itsec-2fa',
				);
				$_REQUEST['action'] = $this->strposa( $params['action'], $actions );
			}

			$urled_redirect_to = HMWP_Classes_Tools::getValue( 'redirect_to', '' );

			//if user is logged in
			if ( HMWP_Classes_Tools::isLoggedInUser() ) {
				if ( HMWP_Classes_Tools::getOption( 'hmwp_logged_users_redirect' ) ) {
					/** @var HMWP_Models_Rewrite $rewriteModel */
					$rewriteModel = HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' );
					$rewriteModel->dashboard_redirect();
				}
			}

			global $wp_query, $error, $interim_login, $action, $user_login;

			$wp_query->is_404 = false;
			if ( ! empty( $error ) ) {
				$error = false;
			}

			require_once ABSPATH . 'wp-login.php';
			die();

		} elseif ( HMWP_Classes_Tools::getOption( 'hmwp_logout_url' ) <> '' && strpos( $url, '/' . HMWP_Classes_Tools::getOption( 'hmwp_logout_url' ) ) ) {

			check_admin_referer( 'log-out' );

			do_action( 'hmwp_files_handle_logout', $url );

			$user = wp_get_current_user();

			wp_logout();

			$redirect_to = $requested_redirect_to = HMWP_Classes_Tools::getValue( 'redirect_to' );

			if ( ! $redirect_to ) {
				$redirect_to           = add_query_arg( array(
					'loggedout' => 'true',
					'wp_lang'   => get_user_locale( $user ),
				), wp_login_url() );
				$requested_redirect_to = '';
			}

			$redirect_to = apply_filters( 'logout_redirect', $redirect_to, $requested_redirect_to, $user );

			wp_safe_redirect( $redirect_to );
			exit;
		}

	}

}
