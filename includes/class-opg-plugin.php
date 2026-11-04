<?php
/**
 * Bootstrap: activation, database tables, cron, module wiring.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Plugin {

	const CRON_SCAN    = 'opg_weekly_scan';
	const CRON_REPORT  = 'opg_monthly_report';
	const CRON_CLEANUP = 'opg_daily_cleanup';

	/**
	 * Wire every module.
	 */
	public static function init() {
		load_plugin_textdomain( 'ofnoa-privacy-guard', false, dirname( OPG_BASENAME ) . '/languages' );

		if ( get_option( 'opg_db_version' ) !== OPG_DB_VERSION ) {
			self::install_tables();
		}

		// 1.0.3: footer privacy links are on by default - switch them on once for existing installs.
		if ( ! get_option( 'opg_migrated_103' ) ) {
			OPG_Settings::update( array( 'auto_footer_links' => 1 ) );
			update_option( 'opg_migrated_103', 1, false );
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
		add_action( self::CRON_REPORT, array( __CLASS__, 'run_monthly_report' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_doc_content' ), 20 );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
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
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		self::ensure_cron();
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
		wp_clear_scheduled_hook( self::CRON_REPORT );
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
	 * Monthly cron schedule.
	 *
	 * @param array $s Schedules.
	 * @return array
	 */
	public static function cron_schedules( $s ) {
		$s['opg_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => 'Once a month (Ofnoa Privacy Guard)',
		);
		return $s;
	}

	/**
	 * Make sure the monthly report is scheduled (also after an update - no activation hook runs then).
	 * First report: the 1st of next month, 09:00 site time.
	 */
	public static function ensure_cron() {
		if ( wp_next_scheduled( self::CRON_REPORT ) ) {
			return;
		}
		$tz    = wp_timezone();
		$first = new DateTime( 'first day of next month 09:00', $tz );
		wp_schedule_event( $first->getTimestamp(), 'opg_monthly', self::CRON_REPORT );
	}

	/**
	 * Weekly scan - silent, only keeps the report fresh (no email).
	 */
	public static function run_weekly_scan() {
		OPG_Scanner::run();
	}

	/**
	 * Monthly email report.
	 *
	 * @param bool $force Send even if disabled (manual "send now").
	 * @return bool Sent.
	 */
	public static function run_monthly_report( $force = false ) {
		$previous = get_option( 'opg_last_report_score' );
		$result   = OPG_Scanner::run();
		if ( ! $force && ! OPG_Settings::get( 'weekly_scan' ) ) {
			return false;
		}
		$to = OPG_Settings::get( 'scan_email' );
		if ( ! is_email( $to ) ) {
			return false;
		}
		global $wpdb;
		$since    = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$consents = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}opg_consent_log WHERE created_at > %s", $since ) ); // phpcs:ignore
		$requests = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}opg_requests WHERE created_at > %s", $since ) ); // phpcs:ignore
		$failed   = OPG_Audit_Log::failed_logins( 30 * 24 );
		$site     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$lines   = array();
		$lines[] = 'דוח פרטיות חודשי - ' . $site . ' (' . home_url() . ')';
		$lines[] = wp_date( 'F Y' );
		$lines[] = '';
		$lines[] = sprintf( 'ציון ציות: %d/100', $result['score'] ) . ( is_numeric( $previous ) ? sprintf( ' (בחודש הקודם: %d)', (int) $previous ) : '' );
		$lines[] = sprintf( 'תקין: %d · לשיפור: %d · קריטי: %d', $result['counts']['pass'], $result['counts']['warn'], $result['counts']['fail'] );
		$lines[] = '';
		$lines[] = 'פעילות ב-30 הימים האחרונים:';
		$lines[] = sprintf( '- הסכמות שנרשמו: %d', $consents );
		$lines[] = sprintf( '- בקשות זכויות חדשות: %d (פתוחות כעת: %d, באיחור: %d)', $requests, OPG_Requests::count_open(), OPG_Requests::count_overdue() );
		$lines[] = sprintf( '- ניסיונות התחברות כושלים: %d', $failed );
		foreach ( array( 'fail' => 'ממצאים קריטיים:', 'warn' => 'לשיפור:' ) as $status => $title ) {
			$items = array();
			foreach ( $result['checks'] as $check ) {
				if ( $status === $check['status'] ) {
					$items[] = '- ' . $check['title'] . ': ' . trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $check['message'] ) ) );
				}
			}
			if ( $items ) {
				$lines[] = '';
				$lines[] = $title;
				$lines   = array_merge( $lines, $items );
			}
		}
		$lines[] = '';
		$lines[] = 'לדוח המלא: ' . admin_url( 'admin.php?page=opg-scan' );
		update_option( 'opg_last_report_score', $result['score'], false );
		return wp_mail( $to, sprintf( '[%s] דוח פרטיות חודשי - %d/100', $site, $result['score'] ), implode( "\n", $lines ) );
	}

	/**
	 * Front end of pages generated by the plugin: hide the "review before publishing" note
	 * and wrap the content so links/tables stay readable on any theme.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function filter_doc_content( $content ) {
		if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() ) {
			return $content;
		}
		$id = get_the_ID();
		if ( ! $id || ! get_post_meta( $id, '_opg_doc', true ) ) {
			return $content;
		}
		$content = preg_replace( '#<!--\s*opg-review-note\s*-->.*?<!--\s*/opg-review-note\s*-->#s', '', (string) $content );
		return '<div class="opg-doc">' . $content . '</div>';
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
