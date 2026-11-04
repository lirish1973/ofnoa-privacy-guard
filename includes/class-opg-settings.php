<?php
/**
 * Settings storage, defaults and sanitizing.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Settings {

	const OPTION = 'opg_settings';

	/** @var array|null */
	private static $cache = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		$site = wp_parse_url( home_url(), PHP_URL_HOST );
		return array(
			// Business details.
			'business_name'          => get_bloginfo( 'name' ),
			'business_id'            => '',
			'business_address'       => '',
			'contact_email'          => get_option( 'admin_email' ),
			'contact_phone'          => '',
			'officer_name'           => '',
			'officer_email'          => get_option( 'admin_email' ),
			'officer_is_dpo'         => 0,
			'site_domain'            => $site ? $site : '',

			// Data map.
			'data_types'             => array( 'contact', 'usage', 'cookies' ),
			'purposes'               => array( 'service', 'support', 'improve' ),
			'retention_text'         => 'המידע יישמר כל עוד הוא נדרש למטרה שלשמה נאסף, ולאחר מכן לתקופה הנדרשת לפי דין (לרבות דיני מס והתיישנות - עד 7 שנים), ואז יימחק או יעבור התממה.',
			'transfer_abroad'        => 1,
			'abroad_text'            => 'חלק מספקי השירות שלנו (כגון שירותי אחסון, ניתוח תעבורה ודיוור) מאחסנים מידע בשרתים מחוץ לישראל, לרבות בארצות הברית ובאיחוד האירופי, בכפוף לתקנות הגנת הפרטיות (העברת מידע אל מאגרי מידע שמחוץ לגבולות המדינה).',
			'direct_marketing'       => 0,
			'third_parties_manual'   => '',
			'sensitive_data'         => 0,

			// Registration / DPO questionnaire.
			'q_data_broker'          => 0,
			'q_public_body'          => 0,
			'q_subjects'             => 'lt10k',
			'q_monitoring'           => 0,

			// Consent on forms.
			'consent_wc_checkout'    => 1,
			'consent_wc_register'    => 1,
			'consent_comments'       => 1,
			'consent_wp_register'    => 1,
			'consent_cf7'            => 1,
			'consent_elementor'      => 1,
			'consent_wpforms'        => 1,
			'consent_gravity'        => 1,
			'consent_fluent'         => 1,
			'consent_universal'      => 1,
			'show_marketing_box'     => 1,
			'show_collection_notice' => 1,
			'consent_text_privacy'   => 'קראתי ואני מאשר/ת את {privacy_link} ואת איסוף המידע ושמירתו לצורך הטיפול בפנייה.',
			'consent_text_marketing' => 'אני מאשר/ת לקבל עדכונים, מבצעים ודיוור שיווקי. ניתן להסיר את ההסכמה בכל עת.',
			'collection_notice'      => 'המידע שתמסרו ישמש את {business} לטיפול בפנייה/בהזמנה בלבד. אינכם חייבים למסור את המידע על פי חוק, אך ללא מסירתו לא נוכל לטפל בבקשה. המידע עשוי להימסר לספקי שירות הפועלים מטעמנו. זכותכם לעיין במידע ולבקש לתקנו או למחקו - פרטים ב{privacy_link}.',
			'consent_error'          => 'יש לאשר את מדיניות הפרטיות כדי להמשיך.',

			// Consent log.
			'log_email_mode'         => 'full',
			'log_retention_months'   => 84,

			// Cookie banner.
			'cookie_banner'          => 'auto',
			'cookie_text'            => 'אנו משתמשים בקובצי Cookie כדי שהאתר יעבוד, למדידת שימוש ולשיווק. קובצי Cookie חיוניים פועלים תמיד; את השאר אפשר לאשר או לדחות.',
			'cookie_position'        => 'bottom',
			'consent_mode_v2'        => 1,

			// Rights requests.
			'requests_page_id'       => 0,
			'requests_notify'        => get_option( 'admin_email' ),
			'requests_due_days'      => 30,

			// Ops.
			'audit_log'              => 1,
			'weekly_scan'            => 1,
			'scan_email'             => get_option( 'admin_email' ),
			'last_review'            => '',
			'policy_page_en_id'      => 0,
			'cookie_page_id'         => 0,
			'auto_footer_links'      => 1,
		);
	}

	/**
	 * Every setting key with its type, used for sanitizing.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'business_name'          => 'text',
			'business_id'            => 'text',
			'business_address'       => 'text',
			'contact_email'          => 'email',
			'contact_phone'          => 'text',
			'officer_name'           => 'text',
			'officer_email'          => 'email',
			'officer_is_dpo'         => 'bool',
			'site_domain'            => 'text',
			'data_types'             => 'list',
			'purposes'               => 'list',
			'retention_text'         => 'textarea',
			'transfer_abroad'        => 'bool',
			'abroad_text'            => 'textarea',
			'direct_marketing'       => 'bool',
			'third_parties_manual'   => 'textarea',
			'sensitive_data'         => 'bool',
			'q_data_broker'          => 'bool',
			'q_public_body'          => 'bool',
			'q_subjects'             => 'choice:lt10k,10k-100k,gt100k',
			'q_monitoring'           => 'bool',
			'consent_wc_checkout'    => 'bool',
			'consent_wc_register'    => 'bool',
			'consent_comments'       => 'bool',
			'consent_wp_register'    => 'bool',
			'consent_cf7'            => 'bool',
			'consent_elementor'      => 'bool',
			'consent_wpforms'        => 'bool',
			'consent_gravity'        => 'bool',
			'consent_fluent'         => 'bool',
			'consent_universal'      => 'bool',
			'show_marketing_box'     => 'bool',
			'show_collection_notice' => 'bool',
			'consent_text_privacy'   => 'html',
			'consent_text_marketing' => 'html',
			'collection_notice'      => 'html',
			'consent_error'          => 'text',
			'log_email_mode'         => 'choice:full,hash',
			'log_retention_months'   => 'int',
			'cookie_banner'          => 'choice:auto,on,off',
			'cookie_text'            => 'html',
			'cookie_position'        => 'choice:bottom,top,box',
			'consent_mode_v2'        => 'bool',
			'requests_page_id'       => 'int',
			'requests_notify'        => 'email',
			'requests_due_days'      => 'int',
			'audit_log'              => 'bool',
			'weekly_scan'            => 'bool',
			'scan_email'             => 'email',
			'last_review'            => 'text',
			'policy_page_en_id'      => 'int',
			'cookie_page_id'         => 'int',
			'auto_footer_links'      => 'bool',
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Single setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Merge and save values.
	 *
	 * @param array $values Raw values.
	 * @param bool  $partial Only the given keys (true) or a full form post where missing bools are 0 (false).
	 */
	public static function update( array $values, $partial = true ) {
		$current = self::all();
		$types   = self::types();
		foreach ( $types as $key => $type ) {
			if ( array_key_exists( $key, $values ) ) {
				$current[ $key ] = self::sanitize( $values[ $key ], $type );
			} elseif ( ! $partial && ( 'bool' === $type || 'list' === $type ) ) {
				$current[ $key ] = 'bool' === $type ? 0 : array();
			}
		}
		update_option( self::OPTION, $current, false );
		self::$cache = null;
	}

	/**
	 * Sanitize a value by type.
	 *
	 * @param mixed  $value Value.
	 * @param string $type  Type.
	 * @return mixed
	 */
	public static function sanitize( $value, $type ) {
		if ( 0 === strpos( $type, 'choice:' ) ) {
			$choices = explode( ',', substr( $type, 7 ) );
			$value   = is_scalar( $value ) ? (string) $value : '';
			return in_array( $value, $choices, true ) ? $value : $choices[0];
		}
		switch ( $type ) {
			case 'bool':
				return empty( $value ) ? 0 : 1;
			case 'int':
				return absint( $value );
			case 'email':
				return sanitize_email( (string) $value );
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'html':
				return wp_kses( (string) $value, self::allowed_html() );
			case 'list':
				$value = is_array( $value ) ? $value : array();
				return array_values( array_map( 'sanitize_key', $value ) );
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Tags allowed inside consent texts.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'br'     => array(),
			'span'   => array( 'class' => true ),
		);
	}

	/**
	 * Export settings as JSON (for copying between sites).
	 *
	 * @return string
	 */
	public static function export_json() {
		$data = self::all();
		// Site-specific values are not portable.
		unset( $data['requests_page_id'], $data['policy_page_en_id'], $data['cookie_page_id'], $data['site_domain'], $data['last_review'] );
		return wp_json_encode(
			array(
				'plugin'   => 'ofnoa-privacy-guard',
				'version'  => OPG_VERSION,
				'exported' => gmdate( 'c' ),
				'settings' => $data,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Import settings from JSON.
	 *
	 * @param string $json JSON.
	 * @return bool
	 */
	public static function import_json( $json ) {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return false;
		}
		self::update( $data['settings'], true );
		return true;
	}

	/**
	 * Labels for data types.
	 *
	 * @return array
	 */
	public static function data_type_labels() {
		return array(
			'contact'   => 'פרטי קשר (שם, טלפון, דוא"ל)',
			'account'   => 'פרטי חשבון משתמש (שם משתמש, סיסמה מוצפנת)',
			'order'     => 'פרטי הזמנה ומשלוח (כתובת, מוצרים שנרכשו)',
			'payment'   => 'פרטי תשלום (מעובדים אצל ספק הסליקה)',
			'usage'     => 'נתוני שימוש וגלישה (עמודים, זמני ביקור)',
			'technical' => 'נתונים טכניים (כתובת IP, סוג דפדפן ומכשיר, לוגים)',
			'cookies'   => 'מזהי Cookie ומזהים פרסומיים',
			'location'  => 'מיקום משוער',
			'content'   => 'תוכן שהמשתמש מעלה (תגובות, קבצים, הודעות צ\'אט)',
			'id'        => 'מספר תעודת זהות / ח.פ. (לצורכי חשבונית)',
			'sensitive' => 'מידע בעל רגישות מיוחדת (בריאות, אמונות, נטייה מינית וכו\')',
		);
	}

	/**
	 * Labels for purposes.
	 *
	 * @return array
	 */
	public static function purpose_labels() {
		return array(
			'service'  => 'מתן השירות, ביצוע הזמנות ומשלוחים',
			'support'  => 'מענה לפניות ושירות לקוחות',
			'account'  => 'ניהול חשבון משתמש',
			'billing'  => 'הנפקת חשבוניות ועמידה בחובות חוקיות',
			'improve'  => 'ניתוח שימוש ושיפור האתר והשירות',
			'marketing'=> 'דיוור ישיר ושיווק (בכפוף להסכמה)',
			'personal' => 'התאמה אישית של תוכן והצעות',
			'ads'      => 'פרסום ממוקד ומדידת קמפיינים',
			'security' => 'אבטחת מידע, מניעת הונאות ושימוש לרעה',
		);
	}
}
