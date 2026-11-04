<?php
/**
 * Bootstrap: activation, database tables, cron, module wiring.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Plugin {

	const CRON_SCAN    = 'opg_weekly_scan';
	const CRON_CLEANUP = 'opg_daily_cleanup';

	/**
	 * Wire every module.
	 */
	public static function init() {
		load_plugin_textdomain( 'ofnoa-privacy-guard', false, dirname( OPG_BASENAME ) . '/languages' );

		if ( get_option( 'opg_db_version' ) !== OPG_DB_VERSION ) {
			self::install_tables();
		}

		OPG_Audit_Log::init();
		OPG_Consent::init();
		OPG_Requests::init();
		OPG_Cookie_Banner::init();
		OPG_Core_Privacy::init();
		OPG_Generator::init();
		OPG_Updater::init();

		if ( is_admin() ) {
			OPG_Admin::init();
		}

		add_action( self::CRON_SCAN, array( __CLASS__, 'run_weekly_scan' ) );
		add_action( self::CRON_CLEANUP, array( __CLASS__, 'run_cleanup' ) );
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		self::install_tables();
		if ( ! wp_next_scheduled( self::CRON_SCAN ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_SCAN );
		}
		if ( ! wp_next_scheduled( self::CRON_CLEANUP ) ) {
			wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::CRON_CLEANUP );
		}
		if ( false === get_option( OPG_Settings::OPTION ) ) {
			add_option( OPG_Settings::OPTION, OPG_Settings::defaults(), '', false );
		}
		add_option( 'opg_activated_at', time(), '', false );
		set_transient( 'opg_activation_redirect', 1, 60 );
	}

	/**
	 * Deactivation: keep data, drop cron.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_SCAN );
		wp_clear_scheduled_hook( self::CRON_CLEANUP );
	}

	/**
	 * Create / upgrade custom tables.
	 */
	public static function install_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$consent  = $wpdb->prefix . 'opg_consent_log';
		$requests = $wpdb->prefix . 'opg_requests';
		$audit    = $wpdb->prefix . 'opg_audit_log';

		dbDelta(
			"CREATE TABLE {$consent} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			source varchar(64) NOT NULL DEFAULT '',
			form_ref varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			email_hash char(64) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ip varchar(64) NOT NULL DEFAULT '',
			ua_hash char(64) NOT NULL DEFAULT '',
			privacy tinyint(1) NOT NULL DEFAULT 0,
			marketing tinyint(1) NOT NULL DEFAULT 0,
			cookies varchar(191) NOT NULL DEFAULT '',
			policy_version varchar(32) NOT NULL DEFAULT '',
			text_hash char(64) NOT NULL DEFAULT '',
			page_url varchar(255) NOT NULL DEFAULT '',
			withdrawn_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY email_hash (email_hash),
			KEY created_at (created_at),
			KEY source (source)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$requests} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			type varchar(20) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(64) NOT NULL DEFAULT '',
			details text NULL,
			due_at datetime NULL DEFAULT NULL,
			verified_at datetime NULL DEFAULT NULL,
			closed_at datetime NULL DEFAULT NULL,
			notes longtext NULL,
			token char(32) NOT NULL DEFAULT '',
			ip varchar(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY email (email)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_login varchar(60) NOT NULL DEFAULT '',
			event varchar(64) NOT NULL DEFAULT '',
			object varchar(191) NOT NULL DEFAULT '',
			details text NULL,
			ip varchar(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY event (event)
			) {$charset};"
		);

		update_option( 'opg_db_version', OPG_DB_VERSION, false );
	}

	/**
	 * Weekly scan + email when the score drops or critical issues exist.
	 */
	public static function run_weekly_scan() {
		$previous = get_option( 'opg_last_scan' );
		$result   = OPG_Scanner::run();
		if ( ! OPG_Settings::get( 'weekly_scan' ) ) {
			return;
		}
		$to = OPG_Settings::get( 'scan_email' );
		if ( ! is_email( $to ) ) {
			return;
		}
		$dropped  = is_array( $previous ) && isset( $previous['score'] ) && $result['score'] < $previous['score'];
		$critical = $result['counts']['fail'] > 0;
		$overdue  = OPG_Requests::count_overdue();
		if ( ! $dropped && ! $critical && ! $overdue ) {
			return;
		}
		$lines   = array();
		$lines[] = sprintf( 'ציון ציות לפרטיות באתר %s: %d/100', home_url(), $result['score'] );
		if ( $dropped ) {
			$lines[] = sprintf( 'הציון ירד מ-%d.', (int) $previous['score'] );
		}
		if ( $overdue ) {
			$lines[] = sprintf( 'יש %d בקשות מימוש זכויות שחרגו מהמועד!', $overdue );
		}
		$lines[] = '';
		$lines[] = 'ממצאים שדורשים טיפול:';
		foreach ( $result['checks'] as $check ) {
			if ( 'fail' === $check['status'] ) {
				$lines[] = '✗ ' . $check['title'] . ' - ' . wp_strip_all_tags( $check['message'] );
			}
		}
		$lines[] = '';
		$lines[] = 'לדוח המלא: ' . admin_url( 'admin.php?page=opg-scan' );
		wp_mail( $to, sprintf( '[%s] דוח פרטיות שבועי - %d/100', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $result['score'] ), implode( "\n", $lines ) );
	}

	/**
	 * Daily cleanup of old log rows.
	 */
	public static function run_cleanup() {
		global $wpdb;
		$months = max( 12, (int) OPG_Settings::get( 'log_retention_months', 84 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months" ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}opg_consent_log WHERE created_at < %s", $cutoff ) ); // phpcs:ignore
		$audit_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-24 months' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}opg_audit_log WHERE created_at < %s", $audit_cutoff ) ); // phpcs:ignore
	}

	/**
	 * Client IP, anonymized (last IPv4 octet / last 80 bits of IPv6 zeroed).
	 *
	 * @param bool $anonymize Anonymize.
	 * @return string
	 */
	public static function client_ip( $anonymize = true ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cf = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
				$ip = $cf;
			}
		}
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		if ( $anonymize && function_exists( 'wp_privacy_anonymize_ip' ) ) {
			return wp_privacy_anonymize_ip( $ip );
		}
		return $ip;
	}

	/**
	 * Lowercase that works with or without mbstring (Hebrew has no case anyway).
	 *
	 * @param string $s Text.
	 * @return string
	 */
	public static function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s, 'UTF-8' ) : strtolower( (string) $s );
	}

	/**
	 * Current privacy policy page URL.
	 *
	 * @return string
	 */
	public static function policy_url() {
		$id = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return get_permalink( $id );
		}
		return '';
	}

	/**
	 * Policy version string = last modified date of the policy page.
	 *
	 * @return string
	 */
	public static function policy_version() {
		$id = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( ! $id ) {
			return 'none';
		}
		$mod = get_post_modified_time( 'Y-m-d', true, $id );
		return $mod ? (string) $mod : 'none';
	}
}
