<?php
/**
 * Lightweight security audit log (logins, permissions, plugins, data exports).
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Audit_Log {

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! OPG_Settings::get( 'audit_log' ) ) {
			return;
		}
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( __CLASS__, 'on_login_failed' ) );
		add_action( 'set_user_role', array( __CLASS__, 'on_role' ), 10, 3 );
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ) );
		add_action( 'delete_user', array( __CLASS__, 'on_user_delete' ) );
		add_action( 'activated_plugin', array( __CLASS__, 'on_plugin_activated' ) );
		add_action( 'deactivated_plugin', array( __CLASS__, 'on_plugin_deactivated' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 10, 2 );
		add_action( 'wp_privacy_personal_data_exported', array( __CLASS__, 'on_export' ) );
		add_action( 'wp_privacy_personal_data_erased', array( __CLASS__, 'on_erase' ) );
	}

	/**
	 * Write a row.
	 *
	 * @param string $event   Event key.
	 * @param string $object  Object description.
	 * @param string $details Details.
	 * @param int    $user_id Actor (0 = current user).
	 */
	public static function log( $event, $object = '', $details = '', $user_id = 0 ) {
		global $wpdb;
		if ( ! OPG_Settings::get( 'audit_log' ) ) {
			return;
		}
		$user_id = $user_id ? $user_id : get_current_user_id();
		$login   = '';
		if ( $user_id ) {
			$user  = get_userdata( $user_id );
			$login = $user ? $user->user_login : '';
		}
		$wpdb->insert( // phpcs:ignore
			$wpdb->prefix . 'opg_audit_log',
			array(
				'created_at' => current_time( 'mysql', true ),
				'user_id'    => (int) $user_id,
				'user_login' => substr( (string) $login, 0, 60 ),
				'event'      => substr( sanitize_key( $event ), 0, 64 ),
				'object'     => substr( (string) $object, 0, 191 ),
				'details'    => (string) $details,
				'ip'         => OPG_Plugin::client_ip( false ),
			)
		);
	}

	/** @param string $login Login. @param WP_User $user User. */
	public static function on_login( $login, $user ) {
		self::log( 'login', $login, implode( ',', (array) $user->roles ), $user->ID );
	}

	/** @param string $login Login attempted. */
	public static function on_login_failed( $login ) {
		self::log( 'login_failed', sanitize_user( $login ), '' );
	}

	/** @param int $user_id ID. @param string $role New. @param array $old Old roles. */
	public static function on_role( $user_id, $role, $old ) {
		$user = get_userdata( $user_id );
		self::log( 'role_changed', $user ? $user->user_login : (string) $user_id, implode( ',', (array) $old ) . ' → ' . $role );
	}

	/** @param int $user_id ID. */
	public static function on_user_register( $user_id ) {
		$user = get_userdata( $user_id );
		self::log( 'user_created', $user ? $user->user_login : (string) $user_id, $user ? implode( ',', (array) $user->roles ) : '' );
	}

	/** @param int $user_id ID. */
	public static function on_user_delete( $user_id ) {
		$user = get_userdata( $user_id );
		self::log( 'user_deleted', $user ? $user->user_login : (string) $user_id );
	}

	/** @param string $plugin Plugin file. */
	public static function on_plugin_activated( $plugin ) {
		self::log( 'plugin_activated', $plugin );
	}

	/** @param string $plugin Plugin file. */
	public static function on_plugin_deactivated( $plugin ) {
		self::log( 'plugin_deactivated', $plugin );
	}

	/** @param object $upgrader Upgrader. @param array $extra Data. */
	public static function on_upgrade( $upgrader, $extra ) {
		$type  = isset( $extra['type'] ) ? $extra['type'] : '';
		$items = array();
		if ( ! empty( $extra['plugins'] ) ) {
			$items = (array) $extra['plugins'];
		} elseif ( ! empty( $extra['themes'] ) ) {
			$items = (array) $extra['themes'];
		}
		self::log( 'updated_' . sanitize_key( $type ), implode( ', ', $items ) );
	}

	/** @param int $request_id Request. */
	public static function on_export( $request_id ) {
		self::log( 'data_exported', 'request #' . (int) $request_id );
	}

	/** @param int $request_id Request. */
	public static function on_erase( $request_id ) {
		self::log( 'data_erased', 'request #' . (int) $request_id );
	}

	/**
	 * Event labels.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'login'              => 'התחברות',
			'login_failed'       => 'ניסיון התחברות כושל',
			'role_changed'       => 'שינוי הרשאה',
			'user_created'       => 'משתמש נוצר',
			'user_deleted'       => 'משתמש נמחק',
			'plugin_activated'   => 'תוסף הופעל',
			'plugin_deactivated' => 'תוסף כובה',
			'updated_plugin'     => 'עדכון תוספים',
			'updated_theme'      => 'עדכון תבנית',
			'updated_core'       => 'עדכון וורדפרס',
			'data_exported'      => 'ייצוא מידע אישי',
			'data_erased'        => 'מחיקת מידע אישי',
			'consent_exported'   => 'ייצוא יומן הסכמות',
			'request_updated'    => 'טיפול בבקשת זכויות',
			'settings_saved'     => 'הגדרות פרטיות עודכנו',
			'document_generated' => 'מסמך פרטיות הופק',
			'incident_logged'    => 'אירוע אבטחה תועד',
		);
	}

	/**
	 * Recent rows.
	 *
	 * @param int    $limit Limit.
	 * @param string $event Filter.
	 * @return array
	 */
	public static function recent( $limit = 200, $event = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'opg_audit_log';
		if ( $event ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE event = %s ORDER BY id DESC LIMIT %d", $event, $limit ), ARRAY_A ); // phpcs:ignore
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore
	}

	/**
	 * Count failed logins in the last N hours.
	 *
	 * @param int $hours Hours.
	 * @return int
	 */
	public static function failed_logins( $hours = 24 ) {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}opg_audit_log WHERE event = 'login_failed' AND created_at > %s", $since ) ); // phpcs:ignore
	}
}
