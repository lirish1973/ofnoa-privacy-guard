<?php
/**
 * Detects plugins, forms and third-party services on the site.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Detector {

	/** @var array|null */
	private static $plugins = null;

	/**
	 * Active plugin files (single + network).
	 *
	 * @return array
	 */
	public static function active_plugins() {
		if ( null === self::$plugins ) {
			$active = (array) get_option( 'active_plugins', array() );
			if ( is_multisite() ) {
				$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
			}
			self::$plugins = array_unique( $active );
		}
		return self::$plugins;
	}

	/**
	 * Is a plugin active (by slug substring of its file path)?
	 *
	 * @param string|array $needles Substrings.
	 * @return bool
	 */
	public static function has_plugin( $needles ) {
		foreach ( (array) $needles as $needle ) {
			foreach ( self::active_plugins() as $file ) {
				if ( false !== stripos( $file, $needle ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Another cookie-consent plugin active?
	 *
	 * @return string Name or ''.
	 */
	public static function other_cookie_plugin() {
		$map = array(
			'cookie-law-info'         => 'CookieYes',
			'complianz'               => 'Complianz',
			'cookiebot'               => 'Cookiebot',
			'cookie-notice'           => 'Cookie Notice',
			'gdpr-cookie-compliance'  => 'GDPR Cookie Compliance',
			'real-cookie-banner'      => 'Real Cookie Banner',
			'iubenda'                 => 'iubenda',
			'borlabs-cookie'          => 'Borlabs Cookie',
			'cookie-script'           => 'Cookie-Script',
			'termly'                  => 'Termly',
			'uk-cookie-consent'       => 'Termly Cookie Consent',
			'pixelyoursite-cookie'    => 'PYS Consent',
		);
		foreach ( $map as $slug => $name ) {
			if ( self::has_plugin( $slug ) ) {
				return $name;
			}
		}
		return '';
	}

	/**
	 * Third-party service catalogue.
	 * key => [name, category, data, location, plugin-needles, html-patterns, policy terms]
	 *
	 * @return array
	 */
	public static function catalogue() {
		return array(
			'ga'        => array( 'Google Analytics', 'analytics', 'נתוני גלישה, מזהי Cookie, IP מקוצר', 'ארה"ב', array( 'google-site-kit', 'google-analytics', 'woocommerce-google-analytics', 'monsterinsights', 'ga-google-analytics' ), array( 'googletagmanager.com/gtag/js?id=G-', "gtag('config', 'G-", 'gtag("config","G-', 'google-analytics.com/analytics.js' ), array( 'google analytics', 'גוגל אנליטיקס', 'אנליטיקס', 'analytics' ) ),
			'gtm'       => array( 'Google Tag Manager', 'analytics', 'מטעין תגים (לפי התגים שמוגדרים בו)', 'ארה"ב', array( 'duracelltomi-google-tag-manager', 'gtm4wp' ), array( 'googletagmanager.com/gtm.js', 'googletagmanager.com/ns.html' ), array( 'tag manager', 'google', 'גוגל' ) ),
			'gads'      => array( 'Google Ads', 'marketing', 'מזהי Cookie, אירועי המרה', 'ארה"ב', array( 'google-listings-and-ads' ), array( "gtag('config', 'AW-", 'gtag("config","AW-', 'googleadservices.com' ), array( 'google ads', 'גוגל אדס', 'פרסום', 'google' ) ),
			'meta'      => array( 'Meta Pixel (Facebook/Instagram)', 'marketing', 'מזהי Cookie, אירועי גלישה ורכישה, דוא"ל/טלפון מגובבים', 'ארה"ב', array( 'facebook-for-woocommerce', 'official-facebook-pixel', 'pixelyoursite', 'pixel-caffeine' ), array( 'connect.facebook.net', 'fbq(' ), array( 'facebook', 'פייסבוק', 'meta', 'מטא', 'פיקסל', 'pixel' ) ),
			'tiktok'    => array( 'TikTok Pixel', 'marketing', 'מזהי Cookie, אירועי גלישה', 'ארה"ב/סינגפור', array( 'tiktok' ), array( 'analytics.tiktok.com' ), array( 'tiktok', 'טיקטוק' ) ),
			'hotjar'    => array( 'Hotjar', 'analytics', 'הקלטות גלישה, מפות חום', 'האיחוד האירופי', array( 'hotjar' ), array( 'static.hotjar.com' ), array( 'hotjar' ) ),
			'clarity'   => array( 'Microsoft Clarity', 'analytics', 'הקלטות גלישה, מפות חום', 'ארה"ב', array( 'microsoft-clarity' ), array( 'clarity.ms' ), array( 'clarity', 'microsoft', 'מיקרוסופט' ) ),
			'linkedin'  => array( 'LinkedIn Insight', 'marketing', 'מזהי Cookie', 'ארה"ב', array(), array( 'snap.licdn.com' ), array( 'linkedin', 'לינקדאין' ) ),
			'xpixel'    => array( 'X (Twitter) Pixel', 'marketing', 'מזהי Cookie', 'ארה"ב', array(), array( 'static.ads-twitter.com' ), array( 'twitter', 'טוויטר' ) ),
			'recaptcha' => array( 'Google reCAPTCHA', 'necessary', 'IP, נתוני דפדפן ותנועת עכבר', 'ארה"ב', array(), array( 'google.com/recaptcha', 'recaptcha/api.js' ), array( 'recaptcha', 'google', 'גוגל' ) ),
			'gfonts'    => array( 'Google Fonts', 'functional', 'כתובת IP', 'ארה"ב', array(), array( 'fonts.googleapis.com' ), array( 'google', 'גוגל', 'fonts' ) ),
			'youtube'   => array( 'YouTube (סרטונים מוטמעים)', 'functional', 'מזהי Cookie, IP', 'ארה"ב', array(), array( 'youtube.com/embed', 'youtube-nocookie.com' ), array( 'youtube', 'יוטיוב' ) ),
			'maps'      => array( 'Google Maps', 'functional', 'IP, מיקום משוער', 'ארה"ב', array(), array( 'maps.googleapis.com', 'google.com/maps/embed' ), array( 'google', 'גוגל', 'מפות' ) ),
			'tawk'      => array( 'Tawk.to (צ\'אט)', 'functional', 'שם, דוא"ל, תוכן שיחה, IP', 'ארה"ב/האיחוד האירופי', array( 'tawkto' ), array( 'embed.tawk.to' ), array( 'tawk', 'צ\'אט', 'צאט' ) ),
			'tidio'     => array( 'Tidio (צ\'אט)', 'functional', 'שם, דוא"ל, תוכן שיחה', 'האיחוד האירופי/ארה"ב', array( 'tidio' ), array( 'code.tidio.co' ), array( 'tidio', 'צ\'אט' ) ),
			'hubspot'   => array( 'HubSpot (CRM)', 'marketing', 'פרטי קשר, היסטוריית גלישה', 'ארה"ב/האיחוד האירופי', array( 'leadin', 'hubspot' ), array( 'js.hs-scripts.com', 'js.hsforms.net' ), array( 'hubspot', 'crm' ) ),
			'onesignal' => array( 'OneSignal (התראות פוש)', 'marketing', 'מזהה מכשיר', 'ארה"ב', array( 'onesignal' ), array( 'cdn.onesignal.com' ), array( 'onesignal', 'התראות' ) ),
			'mailchimp' => array( 'Mailchimp (דיוור)', 'marketing', 'דוא"ל, שם, פעילות דיוור', 'ארה"ב', array( 'mailchimp-for-wp', 'mailchimp-for-woocommerce', 'mc4wp' ), array( 'chimpstatic.com', 'list-manage.com' ), array( 'mailchimp', 'מיילצ\'ימפ', 'דיוור' ) ),
			'mailpoet'  => array( 'MailPoet (דיוור)', 'marketing', 'דוא"ל, שם, פעילות דיוור', 'אתר זה / ארה"ב', array( 'mailpoet' ), array(), array( 'mailpoet', 'דיוור' ) ),
			'fluentcrm' => array( 'FluentCRM', 'marketing', 'פרטי קשר, פעילות דיוור', 'אתר זה', array( 'fluent-crm' ), array(), array( 'crm', 'דיוור' ) ),
			'ofnoacrm'  => array( 'Ofnoacomps CRM', 'service', 'פרטי לידים ולקוחות', 'אתר זה', array( 'ofnoacomps-crm' ), array(), array( 'crm', 'ניהול לקוחות' ) ),
			'smtp'      => array( 'שירות שליחת דוא"ל (SMTP)', 'service', 'כתובות דוא"ל ותוכן הודעות', 'לפי הספק', array( 'wp-mail-smtp', 'post-smtp', 'fluent-smtp', 'easy-wp-smtp', 'wp-ses' ), array(), array( 'דוא"ל', 'email', 'smtp' ) ),
			'ai'        => array( 'שירותי בינה מלאכותית (OpenAI / Anthropic וכו\')', 'service', 'תוכן שיחות/טפסים שנשלח לעיבוד', 'ארה"ב', array( 'ai-engine', 'openai', 'gpt', 'chatbot', 'ai-assistant', 'seo-content-agent', 'meta-content-agent', 'hoco-main-chatbot', 'claude' ), array( 'api.openai.com' ), array( 'בינה מלאכותית', 'openai', 'chatgpt', 'ai', 'מודל שפה' ) ),
			'payments'  => array( 'ספקי סליקה ותשלום', 'service', 'פרטי תשלום, שם, סכום', 'לפי הספק', array( 'cardcom', 'tranzila', 'payplus', 'meshulam', 'grow-payment', 'woocommerce-payments', 'woocommerce-gateway-stripe', 'woocommerce-paypal-payments', 'pelecard', 'isracard', 'yaad', 'hyp-payment', 'bit-payment' ), array(), array( 'סליקה', 'תשלום', 'payment', 'כרטיס אשראי' ) ),
			'invoices'  => array( 'מערכת חשבוניות / הנהלת חשבונות', 'service', 'שם, כתובת, ח.פ., פרטי רכישה', 'לפי הספק', array( 'icount', 'greeninvoice', 'morning', 'rivhit', 'ezcount', 'invoice4u', 'sumit' ), array(), array( 'חשבונית', 'הנהלת חשבונות', 'invoice' ) ),
			'shipping'  => array( 'חברות שילוח', 'service', 'שם, כתובת, טלפון', 'ישראל', array( 'cargo-shipping', 'chita', 'lionwheel', 'baldar', 'israel-post', 'hfd', 'yango-delivery' ), array(), array( 'משלוח', 'שליח', 'shipping' ) ),
			'whatsapp'  => array( 'WhatsApp (כפתור צ\'אט)', 'functional', 'מספר טלפון ותוכן שיחה (אצל Meta)', 'ארה"ב', array( 'click-to-chat', 'creame-whatsapp-me', 'joinchat', 'wp-whatsapp', 'whatsapp' ), array( 'wa.me/', 'api.whatsapp.com' ), array( 'whatsapp', 'וואטסאפ', 'ווטסאפ' ) ),
		);
	}

	/**
	 * Detect active third parties.
	 *
	 * @param string $html Front page HTML.
	 * @return array key => [name, category, data, location, via, terms]
	 */
	public static function third_parties( $html = '' ) {
		$found = array();
		foreach ( self::catalogue() as $key => $row ) {
			list( $name, $cat, $data, $loc, $plugins, $patterns, $terms ) = $row;
			$via = '';
			if ( $plugins && self::has_plugin( $plugins ) ) {
				$via = 'plugin';
			}
			if ( ! $via && $html && $patterns ) {
				foreach ( $patterns as $p ) {
					if ( false !== stripos( $html, $p ) ) {
						$via = 'frontend';
						break;
					}
				}
			}
			if ( $via ) {
				$found[ $key ] = array(
					'name'     => $name,
					'category' => $cat,
					'data'     => $data,
					'location' => $loc,
					'via'      => $via,
					'terms'    => $terms,
				);
			}
		}

		// Manual list from settings: name|purpose|data|country.
		$manual = (string) OPG_Settings::get( 'third_parties_manual' );
		foreach ( preg_split( '/\r\n|\r|\n/', $manual ) as $i => $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( empty( $parts[0] ) ) {
				continue;
			}
			$found[ 'manual_' . $i ] = array(
				'name'     => $parts[0],
				'category' => 'service',
				'data'     => isset( $parts[2] ) ? $parts[2] : '',
				'location' => isset( $parts[3] ) ? $parts[3] : '',
				'purpose'  => isset( $parts[1] ) ? $parts[1] : '',
				'via'      => 'manual',
				'terms'    => array( OPG_Plugin::lower( $parts[0] ) ),
			);
		}
		return $found;
	}

	/**
	 * Forms on the site.
	 *
	 * @return array plugin => [label, count, supported]
	 */
	public static function forms() {
		global $wpdb;
		$forms = array();

		if ( class_exists( 'WooCommerce' ) ) {
			$forms['woocommerce'] = array( 'WooCommerce (קופה והרשמה)', 2, true );
		}
		if ( get_option( 'users_can_register' ) ) {
			$forms['wp_register'] = array( 'הרשמה לאתר (וורדפרס)', 1, true );
		}
		if ( 'open' === get_default_comment_status() || (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE comment_status = 'open' AND post_status = 'publish' LIMIT 1" ) ) { // phpcs:ignore
			$forms['comments'] = array( 'טופס תגובות', 1, true );
		}
		if ( defined( 'WPCF7_VERSION' ) ) {
			$forms['cf7'] = array( 'Contact Form 7', (int) wp_count_posts( 'wpcf7_contact_form' )->publish, true );
		}
		if ( defined( 'WPFORMS_VERSION' ) ) {
			$forms['wpforms'] = array( 'WPForms', (int) wp_count_posts( 'wpforms' )->publish, true );
		}
		if ( class_exists( 'GFAPI' ) ) {
			$gf               = GFAPI::get_forms();
			$forms['gravity'] = array( 'Gravity Forms', is_array( $gf ) ? count( $gf ) : 0, true );
		}
		if ( defined( 'FLUENTFORM' ) || defined( 'FLUENTFORM_VERSION' ) ) {
			$table           = $wpdb->prefix . 'fluentform_forms';
			$count           = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0; // phpcs:ignore
			$forms['fluent'] = array( 'Fluent Forms', $count, true );
		}
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$count              = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%\"widgetType\":\"form\"%'" ); // phpcs:ignore
			$forms['elementor'] = array( 'Elementor Pro Forms', $count, true );
		}
		if ( self::has_plugin( 'ninja-forms' ) ) {
			$forms['ninja'] = array( 'Ninja Forms', 0, false );
		}
		if ( self::has_plugin( 'formidable' ) ) {
			$forms['formidable'] = array( 'Formidable Forms', 0, false );
		}
		if ( self::has_plugin( array( 'mailchimp-for-wp', 'mc4wp' ) ) ) {
			$forms['mc4wp'] = array( 'Mailchimp for WP (ניוזלטר)', 0, false );
		}
		if ( self::has_plugin( 'jetpack' ) ) {
			$forms['jetpack'] = array( 'Jetpack Forms', 0, false );
		}
		return $forms;
	}

	/**
	 * Fetch a URL from the site itself (loopback), cached.
	 *
	 * @param string $url  URL.
	 * @param bool   $fresh Skip cache.
	 * @return array{code:int,body:string,headers:array,error:string}
	 */
	public static function fetch( $url, $fresh = false ) {
		$key = 'opg_fetch_' . md5( $url );
		if ( ! $fresh ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'sslverify'   => false,
				'user-agent'  => 'OfnoaPrivacyGuard/' . OPG_VERSION . '; ' . home_url(),
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			$out = array(
				'code'    => 0,
				'body'    => '',
				'headers' => array(),
				'error'   => $res->get_error_message(),
			);
		} else {
			$headers = wp_remote_retrieve_headers( $res );
			$out     = array(
				'code'    => (int) wp_remote_retrieve_response_code( $res ),
				'body'    => (string) wp_remote_retrieve_body( $res ),
				'headers' => is_object( $headers ) && method_exists( $headers, 'getAll' ) ? $headers->getAll() : (array) $headers,
				'error'   => '',
			);
		}
		if ( strlen( $out['body'] ) > 2000000 ) {
			$out['body'] = substr( $out['body'], 0, 2000000 );
		}
		set_transient( $key, $out, 6 * HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Backup plugin active?
	 *
	 * @return string
	 */
	public static function backup_plugin() {
		$map = array(
			'updraftplus'               => 'UpdraftPlus',
			'backwpup'                  => 'BackWPup',
			'all-in-one-wp-migration'   => 'All-in-One WP Migration',
			'duplicator'                => 'Duplicator',
			'jetpack-backup'            => 'Jetpack Backup',
			'vaultpress'                => 'VaultPress',
			'blogvault'                 => 'BlogVault',
			'wpvivid'                   => 'WPvivid',
			'backup-backup'             => 'Backup Migration',
			'boldgrid-backup'           => 'Total Upkeep',
		);
		foreach ( $map as $slug => $name ) {
			if ( self::has_plugin( $slug ) ) {
				return $name;
			}
		}
		return '';
	}

	/**
	 * Security plugin active?
	 *
	 * @return string
	 */
	public static function security_plugin() {
		$map = array(
			'wordfence'              => 'Wordfence',
			'ofnoa-shield'           => 'Ofnoa Shield',
			'sucuri'                 => 'Sucuri',
			'better-wp-security'     => 'Solid Security',
			'ithemes-security'       => 'Solid Security',
			'all-in-one-wp-security' => 'AIOS',
			'wp-cerber'              => 'WP Cerber',
			'malcare'                => 'MalCare',
			'defender-security'      => 'Defender',
		);
		foreach ( $map as $slug => $name ) {
			if ( self::has_plugin( $slug ) ) {
				return $name;
			}
		}
		return '';
	}

	/**
	 * 2FA available?
	 *
	 * @return string
	 */
	public static function twofa_plugin() {
		$map = array(
			'two-factor'              => 'Two Factor',
			'wp-2fa'                  => 'WP 2FA',
			'wordfence'               => 'Wordfence (2FA)',
			'google-authenticator'    => 'Google Authenticator',
			'miniorange-2-factor'     => 'miniOrange 2FA',
			'better-wp-security'      => 'Solid Security (2FA)',
			'two-factor-authentication' => 'Two Factor Authentication',
			'loginizer'               => 'Loginizer',
		);
		foreach ( $map as $slug => $name ) {
			if ( self::has_plugin( $slug ) ) {
				return $name;
			}
		}
		return '';
	}
}
