<?php
/**
 * Consent log - the site owner's evidence that consent was given.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Consent_Log {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'opg_consent_log';
	}

	/**
	 * Record a consent event.
	 *
	 * @param array $args source, form_ref, email, user_id, privacy, marketing, cookies, text.
	 * @return int Row id.
	 */
	public static function record( array $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'source'    => 'unknown',
				'form_ref'  => '',
				'email'     => '',
				'user_id'   => get_current_user_id(),
				'privacy'   => 0,
				'marketing' => 0,
				'cookies'   => '',
				'text'      => '',
				'page_url'  => '',
			)
		);

		$email = strtolower( trim( sanitize_email( (string) $args['email'] ) ) );
		$hash  = $email ? hash( 'sha256', $email . wp_salt( 'auth' ) ) : '';
		$store = 'full' === OPG_Settings::get( 'log_email_mode' ) ? $email : '';
		$ua    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$url   = $args['page_url'];
		if ( ! $url && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$url = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		$wpdb->insert( // phpcs:ignore
			self::table(),
			array(
				'created_at'     => current_time( 'mysql', true ),
				'source'         => substr( sanitize_key( $args['source'] ), 0, 64 ),
				'form_ref'       => substr( sanitize_text_field( (string) $args['form_ref'] ), 0, 191 ),
				'email'          => $store,
				'email_hash'     => $hash,
				'user_id'        => (int) $args['user_id'],
				'ip'             => OPG_Plugin::client_ip( true ),
				'ua_hash'        => $ua ? hash( 'sha256', $ua ) : '',
				'privacy'        => $args['privacy'] ? 1 : 0,
				'marketing'      => $args['marketing'] ? 1 : 0,
				'cookies'        => substr( sanitize_text_field( (string) $args['cookies'] ), 0, 191 ),
				'policy_version' => OPG_Plugin::policy_version(),
				'text_hash'      => $args['text'] ? hash( 'sha256', wp_strip_all_tags( (string) $args['text'] ) ) : '',
				'page_url'       => substr( (string) $url, 0, 255 ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Email hash used for lookups.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	public static function hash_email( $email ) {
		$email = strtolower( trim( sanitize_email( (string) $email ) ) );
		return $email ? hash( 'sha256', $email . wp_salt( 'auth' ) ) : '';
	}

	/**
	 * Withdraw marketing consent for an email.
	 *
	 * @param string $email Email.
	 * @return int Rows affected.
	 */
	public static function withdraw_marketing( $email ) {
		global $wpdb;
		$hash = self::hash_email( $email );
		if ( ! $hash ) {
			return 0;
		}
		$rows = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET withdrawn_at = %s WHERE email_hash = %s AND marketing = 1 AND withdrawn_at IS NULL', current_time( 'mysql', true ), $hash ) ); // phpcs:ignore
		self::record(
			array(
				'source'    => 'withdrawal',
				'email'     => $email,
				'privacy'   => 0,
				'marketing' => 0,
				'form_ref'  => 'marketing_withdrawn',
			)
		);
		/**
		 * Fires when marketing consent is withdrawn - hook your mailing tool here.
		 */
		do_action( 'opg_marketing_withdrawn', $email );
		return (int) $rows;
	}

	/**
	 * Does this email currently hold marketing consent?
	 *
	 * @param string $email Email.
	 * @return bool
	 */
	public static function has_marketing_consent( $email ) {
		global $wpdb;
		$hash = self::hash_email( $email );
		if ( ! $hash ) {
			return false;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE email_hash = %s AND marketing = 1 AND withdrawn_at IS NULL LIMIT 1', $hash ) ); // phpcs:ignore
	}

	/**
	 * Query rows.
	 *
	 * @param array $args search, source, page, per_page.
	 * @return array{rows:array,total:int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'source'   => '',
				'page'     => 1,
				'per_page' => 50,
			)
		);
		$where = array( '1=1' );
		$vals  = array();
		if ( $args['search'] ) {
			$where[] = '(email LIKE %s OR email_hash = %s OR form_ref LIKE %s)';
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$vals[]  = $like;
			$vals[]  = self::hash_email( $args['search'] );
			$vals[]  = $like;
		}
		if ( $args['source'] ) {
			$where[] = 'source = %s';
			$vals[]  = $args['source'];
		}
		$sql_where = implode( ' AND ', $where );
		$table     = self::table();
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";
		$total     = (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore
		$offset    = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );
		$sql       = "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows      = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $vals, array( (int) $args['per_page'], $offset ) ) ), ARRAY_A ); // phpcs:ignore
		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Stats for the dashboard.
	 *
	 * @return array
	 */
	public static function stats() {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		return array(
			'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), // phpcs:ignore
			'last30'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at > %s", $since ) ), // phpcs:ignore
			'marketing' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT email_hash) FROM {$table} WHERE marketing = 1 AND withdrawn_at IS NULL AND email_hash <> ''" ), // phpcs:ignore
			'sources'   => $wpdb->get_col( "SELECT DISTINCT source FROM {$table} ORDER BY source" ), // phpcs:ignore
		);
	}

	/**
	 * Stream CSV export.
	 */
	public static function export_csv() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC', ARRAY_A ); // phpcs:ignore
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=consent-log-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM so Excel shows Hebrew.
		fputcsv( $out, array( 'id', 'created_at_utc', 'source', 'form', 'email', 'email_hash', 'user_id', 'ip_anonymized', 'privacy', 'marketing', 'cookies', 'policy_version', 'text_hash', 'page_url', 'withdrawn_at' ) );
		foreach ( (array) $rows as $row ) {
			fputcsv(
				$out,
				array(
					$row['id'],
					$row['created_at'],
					$row['source'],
					$row['form_ref'],
					$row['email'],
					$row['email_hash'],
					$row['user_id'],
					$row['ip'],
					$row['privacy'],
					$row['marketing'],
					$row['cookies'],
					$row['policy_version'],
					$row['text_hash'],
					$row['page_url'],
					$row['withdrawn_at'],
				)
			);
		}
		fclose( $out );
		OPG_Audit_Log::log( 'consent_exported', count( (array) $rows ) . ' rows' );
		exit;
	}

	/**
	 * Rows for an email (for core exporter / eraser).
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function rows_for_email( $email ) {
		global $wpdb;
		$hash = self::hash_email( $email );
		if ( ! $hash ) {
			return array();
		}
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE email_hash = %s ORDER BY id', $hash ), ARRAY_A ); // phpcs:ignore
	}

	/**
	 * Anonymize rows of an email (keeps the evidence row, removes the identifier).
	 *
	 * @param string $email Email.
	 * @return int
	 */
	public static function anonymize_email( $email ) {
		global $wpdb;
		$hash = self::hash_email( $email );
		if ( ! $hash ) {
			return 0;
		}
		return (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET email = '', ip = '', user_id = 0 WHERE email_hash = %s", $hash ) ); // phpcs:ignore
	}

	/**
	 * Source labels.
	 *
	 * @return array
	 */
	public static function source_labels() {
		return array(
			'wc_checkout'  => 'קופה (WooCommerce)',
			'wc_register'  => 'הרשמה (WooCommerce)',
			'comment'      => 'תגובה',
			'wp_register'  => 'הרשמה לאתר',
			'cf7'          => 'Contact Form 7',
			'elementor'    => 'טופס Elementor',
			'wpforms'      => 'WPForms',
			'gravity'      => 'Gravity Forms',
			'fluent'       => 'Fluent Forms',
			'universal'    => 'טופס כללי',
			'cookie'       => 'באנר Cookie',
			'rights'       => 'בקשת מימוש זכויות',
			'withdrawal'   => 'ביטול הסכמה',
		);
	}
}
