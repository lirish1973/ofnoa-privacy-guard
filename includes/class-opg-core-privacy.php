<?php
/**
 * Integration with the WordPress core privacy tools + footer links.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Core_Privacy {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'suggest_text' ) );
		add_shortcode( 'opg_policy_link', array( __CLASS__, 'shortcode_policy_link' ) );
		add_shortcode( 'opg_privacy_links', array( __CLASS__, 'shortcode_links' ) );
		if ( OPG_Settings::get( 'auto_footer_links' ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'footer_links' ), 5 );
		}
	}

	/**
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['ofnoa-privacy-guard'] = array(
			'exporter_friendly_name' => 'Ofnoa Privacy Guard - הסכמות ובקשות',
			'callback'               => array( __CLASS__, 'exporter' ),
		);
		return $exporters;
	}

	/**
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['ofnoa-privacy-guard'] = array(
			'eraser_friendly_name' => 'Ofnoa Privacy Guard - הסכמות ובקשות',
			'callback'             => array( __CLASS__, 'eraser' ),
		);
		return $erasers;
	}

	/**
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function exporter( $email, $page = 1 ) {
		$items = array();
		foreach ( OPG_Consent_Log::rows_for_email( $email ) as $row ) {
			$items[] = array(
				'group_id'    => 'opg-consents',
				'group_label' => 'תיעוד הסכמות',
				'item_id'     => 'opg-consent-' . $row['id'],
				'data'        => array(
					array( 'name' => 'תאריך', 'value' => get_date_from_gmt( $row['created_at'], 'd/m/Y H:i' ) ),
					array( 'name' => 'מקור', 'value' => $row['source'] . ' ' . $row['form_ref'] ),
					array( 'name' => 'הסכמה למדיניות', 'value' => $row['privacy'] ? 'כן' : 'לא' ),
					array( 'name' => 'הסכמה לשיווק', 'value' => $row['marketing'] ? ( $row['withdrawn_at'] ? 'בוטלה' : 'כן' ) : 'לא' ),
					array( 'name' => 'גרסת מדיניות', 'value' => $row['policy_version'] ),
					array( 'name' => 'IP (מקוצר)', 'value' => $row['ip'] ),
				),
			);
		}
		global $wpdb;
		$reqs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . OPG_Requests::table() . ' WHERE email = %s', $email ), ARRAY_A ); // phpcs:ignore
		$types = OPG_Requests::types();
		foreach ( (array) $reqs as $r ) {
			$items[] = array(
				'group_id'    => 'opg-requests',
				'group_label' => 'בקשות פרטיות',
				'item_id'     => 'opg-request-' . $r['id'],
				'data'        => array(
					array( 'name' => 'תאריך', 'value' => get_date_from_gmt( $r['created_at'], 'd/m/Y' ) ),
					array( 'name' => 'סוג', 'value' => isset( $types[ $r['type'] ] ) ? $types[ $r['type'] ] : $r['type'] ),
					array( 'name' => 'שם', 'value' => $r['name'] ),
					array( 'name' => 'פרטים', 'value' => $r['details'] ),
					array( 'name' => 'סטטוס', 'value' => $r['status'] ),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase: consent rows are anonymized (evidence kept without identity), requests anonymized.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function eraser( $email, $page = 1 ) {
		global $wpdb;
		$n  = OPG_Consent_Log::anonymize_email( $email );
		$n += (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . OPG_Requests::table() . " SET name = '[נמחק]', email = '', phone = '', details = '' WHERE email = %s AND status IN ('done','rejected')", $email ) ); // phpcs:ignore
		$open = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . OPG_Requests::table() . " WHERE email = %s AND status NOT IN ('done','rejected')", $email ) ); // phpcs:ignore
		return array(
			'items_removed'  => $n > 0,
			'items_retained' => $open > 0,
			'messages'       => $open ? array( 'קיימות בקשות פרטיות פתוחות - נשמרו עד סגירתן.' ) : array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested text for Settings → Privacy → policy guide.
	 */
	public static function suggest_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'Ofnoa Privacy Guard',
			'<p>כאשר את/ה ממלא/ת טופס באתר, אנו שומרים תיעוד של ההסכמה: מועד, סוג ההסכמה, גרסת המדיניות, כתובת IP מקוצרת וכתובת הדוא"ל, לצורך הוכחת ההסכמה. בבאנר ה-Cookie נשמר קובץ opg_consent עם הבחירה שלך למשך 180 יום. בקשות עיון, תיקון ומחיקה נשמרות לצורך טיפול ותיעוד כנדרש בחוק.</p>'
		);
	}

	/**
	 * [opg_policy_link text="..."]
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public static function shortcode_policy_link( $atts ) {
		$atts = shortcode_atts( array( 'text' => 'מדיניות פרטיות' ), $atts, 'opg_policy_link' );
		$url  = OPG_Plugin::policy_url();
		return $url ? '<a href="' . esc_url( $url ) . '" class="opg-policy-link">' . esc_html( $atts['text'] ) . '</a>' : '';
	}

	/** @var bool Links already printed on this page (shortcode in the theme footer). */
	private static $links_printed = false;

	/**
	 * Every public page the plugin knows about, published only, in a fixed order.
	 *
	 * @return array[] [ 'url' => ..., 'label' => ... ]
	 */
	public static function public_links() {
		$order  = array(
			'privacy_he'    => 'מדיניות פרטיות',
			'privacy_en'    => 'Privacy Policy',
			'cookie_policy' => 'מדיניות Cookie',
			'rights_page'   => 'בקשות פרטיות (עיון / תיקון / מחיקה)',
		);
		$ids    = array(
			'privacy_he'    => (int) get_option( 'wp_page_for_privacy_policy' ),
			'privacy_en'    => (int) OPG_Settings::get( 'policy_page_en_id' ),
			'cookie_policy' => (int) OPG_Settings::get( 'cookie_page_id' ),
			'rights_page'   => (int) OPG_Settings::get( 'requests_page_id' ),
		);
		// Any other published page generated by the plugin (e.g. created before the ids were stored).
		$generated = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'meta_key'       => '_opg_doc', // phpcs:ignore
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( (array) $generated as $pid ) {
			$doc = get_post_meta( $pid, '_opg_doc', true );
			if ( isset( $ids[ $doc ] ) && ( ! $ids[ $doc ] || 'publish' !== get_post_status( $ids[ $doc ] ) ) ) {
				$ids[ $doc ] = (int) $pid;
			}
		}
		$out  = array();
		$seen = array();
		foreach ( $order as $key => $label ) {
			$id = $ids[ $key ];
			if ( ! $id || isset( $seen[ $id ] ) || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$out[]       = array(
				'url'   => get_permalink( $id ),
				'label' => $label,
			);
		}
		return $out;
	}

	/**
	 * [opg_privacy_links] - links to all the privacy pages + cookie settings.
	 *
	 * @return string
	 */
	public static function shortcode_links() {
		$links = array();
		foreach ( self::public_links() as $l ) {
			$links[] = '<a href="' . esc_url( $l['url'] ) . '">' . esc_html( $l['label'] ) . '</a>';
		}
		if ( OPG_Cookie_Banner::enabled() ) {
			$links[] = '<a href="#" data-opg-cookie-open="1">הגדרות Cookie</a>';
		}
		if ( ! $links ) {
			return '';
		}
		self::$links_printed = true;
		return '<nav class="opg-privacy-links" aria-label="פרטיות">' . implode( ' <span class="opg-sep" aria-hidden="true">|</span> ', $links ) . '</nav>';
	}

	/**
	 * Automatic footer bar (skipped when the theme already prints [opg_privacy_links]).
	 */
	public static function footer_links() {
		if ( self::$links_printed || is_admin() ) {
			return;
		}
		$html = self::shortcode_links();
		if ( $html ) {
			echo '<div class="opg-footer-links" dir="rtl">' . $html . '</div>'; // phpcs:ignore
		}
	}
}
