<?php
/**
 * Brute Force Protection Model
 * Called from Brute Force Class
 *
 * @file  The Brute Force Math Recaptcha file
 * @package HMWP/BruteForce/Math
 * @since 8.1
 */

defined( 'ABSPATH' ) || die( 'Cheating uh?' );

class HMWP_Models_Bruteforce_Database {

	private $prefix = 'hmwp_brute_';

	/**
	 * Save the transient with the blocked IP in database
	 *
	 * @param  string  $ip  The verified IP address
	 * @param string $value Info about the login attempts, headers
	 *
	 * @return bool
	 */
	public function save( $ip, $value ) {

		if ( isset( $ip ) ) {

			$transient = $this->prefix . md5( $ip );

			$expiration = apply_filters( 'hmwp_brute_max_timeout', (int) HMWP_Classes_Tools::getOption( 'brute_max_timeout' ) );

			if ( HMWP_Classes_Tools::isMultisites() && ! is_main_site() ) {
				switch_to_blog( $this->getMainBlogId() );
				$return = set_transient( $transient, $value, $expiration );
				restore_current_blog();

				return $return;
			}

			return set_transient( $transient, $value, $expiration );

		}

		return false;

	}


	/**
	 * Get the saved transient from database
	 *
	 * @param string $ip The verified IP address
	 *
	 * @return mixed
	 */
	public function get( $ip ) {

		if ( isset( $ip ) ) {

			$transient = $this->prefix . md5( $ip );

			if ( HMWP_Classes_Tools::isMultisites() && ! is_main_site() ) {
				switch_to_blog( $this->getMainBlogId() );
				$return = get_transient( $transient );
				restore_current_blog();

				return $return;
			}

			return get_transient( $transient );

		}

		return false;
	}

	/**
	 * Delete the transient from database
	 *
	 * @param  string  $ip  The verified IP address
	 *
	 * @return bool
	 */
	public function delete( $ip ) {

		$transient = $this->prefix . md5( $ip );

		if ( HMWP_Classes_Tools::isMultisites() && ! is_main_site() ) {
			switch_to_blog( $this->getMainBlogId() );
			$return = delete_transient( $transient );
			restore_current_blog();

			return $return;
		}

		return delete_transient( $transient );
	}

	/**
	 * Read the saved record of an IP again, without the copy kept in this request
	 *
	 * @param string $ip The verified IP address
	 *
	 * @return mixed
	 */
	public function getFresh( $ip ) {

		if ( ! isset( $ip ) ) {
			return false;
		}

		$transient = $this->prefix . md5( $ip );
		$switch    = ( HMWP_Classes_Tools::isMultisites() && ! is_main_site() );

		if ( $switch ) {
			switch_to_blog( $this->getMainBlogId() );
		}

		if ( wp_using_ext_object_cache() ) {
			// Ask the cache server, not the copy kept in this request
			$return = wp_cache_get( $transient, 'transient', true );
		} else {
			// Forget the copies kept in this request, so the next read comes from the database
			$options = array( '_transient_' . $transient, '_transient_timeout_' . $transient );

			foreach ( array( 'notoptions', 'alloptions' ) as $list ) {
				$cached = wp_cache_get( $list, 'options' );

				if ( is_array( $cached ) && array_intersect_key( $cached, array_flip( $options ) ) ) {
					wp_cache_set( $list, array_diff_key( $cached, array_flip( $options ) ), 'options' );
				}
			}

			foreach ( $options as $option ) {
				wp_cache_delete( $option, 'options' );
			}

			$return = get_transient( $transient );
		}

		if ( $switch ) {
			restore_current_blog();
		}

		return $return;
	}

	/**
	 * Take a short database lock, so one request at a time works on the same record
	 *
	 * @param string $key The IP address or the name of the record
	 *
	 * @return string|false The lock name, or false when the database gave no lock
	 */
	public function lock( $key ) {
		global $wpdb;

		// Lock names are shared by all sites on the database server, keep this one for this site only
		$name = 'hmwp_' . md5( ( defined( 'DB_NAME' ) ? DB_NAME : '' ) . $wpdb->base_prefix . $key );

		// Wait 2 seconds at most. Without a lock the caller goes on as before.
		$errors = $wpdb->suppress_errors( true );
		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, 2 ) ); //phpcs:ignore
		$wpdb->suppress_errors( $errors );

		return ( (string) $locked === '1' ? $name : false );
	}

	/**
	 * Release the lock taken with lock()
	 *
	 * @param string|false $name The lock name returned by lock()
	 *
	 * @return void
	 */
	public function unlock( $name ) {
		global $wpdb;

		if ( $name ) {
			$errors = $wpdb->suppress_errors( true );
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); //phpcs:ignore
			$wpdb->suppress_errors( $errors );
		}
	}

	/**
	 * If we're in a multisite network, return the blog ID of the primary blog
	 *
	 * @return int
	 */
	public function getMainBlogId() {

		if ( defined( 'BLOG_ID_CURRENT_SITE' ) ) {
			return BLOG_ID_CURRENT_SITE;
		}

		return 1;
	}

	/**
	 * Get all blocked IPs
	 *
	 * @return array
	 */
	public function getBlockedIps() {
		global $wpdb;
		$ips = array();

		$pattern = '_transient_' . $this->prefix;

		//phpcs:ignore
		if ( ! $rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT `option_value` FROM `' . esc_sql( $wpdb->options ) . '` WHERE (`option_name` LIKE %s) ORDER BY `option_id` DESC',
				$pattern . '%'
			)
		) ) {
			return $ips;
		}

		// First pass: use $row->option_value directly — it IS the transient value already fetched above.
		// No need to call $this->get() which would re-query the same data via get_transient().
		$blocked = array(); // timeout_key => transient_value
		foreach ( $rows as $row ) {
			$row->option_value = maybe_unserialize( $row->option_value );
			if ( empty( $row->option_value ) || ! isset( $row->option_value['ip'] ) ) {
				continue;
			}
			if ( isset( $row->option_value['status'] ) && $row->option_value['status'] === 'blocked' ) {
				$timeout_key             = '_transient_timeout_' . $this->prefix . md5( $row->option_value['ip'] );
				$blocked[ $timeout_key ] = $row->option_value;
			}
		}

		if ( empty( $blocked ) ) {
			return $ips;
		}

		// Fetch all timeout values in a single query instead of one get_option() per IP
		$placeholders = implode( ', ', array_fill( 0, count( $blocked ), '%s' ) );

		$sql = 'SELECT `option_name`, `option_value` 
				FROM `' . esc_sql( $wpdb->options ) . '` 
				WHERE `option_name` IN (' . $placeholders . ')';

		$timeout_rows = $wpdb->get_results( $wpdb->prepare( $sql, array_keys( $blocked ) ) ); //phpcs:ignore

		$timeouts = array();
		foreach ( (array) $timeout_rows as $t ) {
			$timeouts[ $t->option_name ] = $t->option_value;
		}

		// Build result using pre-fetched timeouts — skip expired entries
		$now = $this->gtmTimestamp();
		foreach ( $blocked as $timeout_key => $transient_value ) {
			$expires = isset( $timeouts[ $timeout_key ] ) ? (int) $timeouts[ $timeout_key ] : null;
			if ( ! $expires || $expires <= $now ) {
				continue;
			}
			$transient_value['remaining'] = $this->timeRemained( $expires );
			$ips[]                        = $transient_value;
		}

		return $ips;
	}


	/**
	 * Get current GMT date time
	 *
	 * @return false|int
	 * @since 7.0
	 *
	 */
	public function gtmTimestamp() {
		return strtotime( gmdate( 'Y-m-d H:i:s', time() ) );
	}

	public function timeRemained( $time ) {

		if ( is_numeric( $time ) ) {

			$etime = $time - $this->gtmTimestamp();

			if ( $etime >= 1 ) {

				$a = [
					365 * 24 * 60 * 60 => 'year',
					30 * 24 * 60 * 60 => 'month',
					24 * 60 * 60 => 'day',
					60 * 60      => 'hour',
					60           => 'minute',
					1            => 'second',
				];

				$a_plural = array(
					'year'   => 'years',
					'month'  => 'months',
					'day'    => 'days',
					'hour'   => 'hours',
					'minute' => 'minutes',
					'second' => 'seconds',
				);

				foreach ( $a as $secs => $str ) {
					$d = $etime / $secs;

					if ( $d >= 1 ) {
						$r = round( $d );

						$time_string = ( $r > 1 ) ? $a_plural[ $str ] : $str;

						return sprintf( '%d %s', $r, $time_string );
					}
				}
			}

		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );

	}

	/**
	 * Clear the block IP table
	 */
	public function clearBlockedIPs() {
		$ips = $this->getBlockedIps();

		if ( ! empty( $ips ) ) {
			foreach ( $ips as $ip ) {
				$this->delete( $ip['ip'] );
			}
		}
	}
}
