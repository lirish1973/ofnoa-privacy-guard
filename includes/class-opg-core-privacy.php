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

	/**
	 * [opg_privacy_links] - policy | cookie settings | privacy requests.
	 *
	 * @return string
	 */
	public static function shortcode_links() {
		$links = array();
		$url   = OPG_Plugin::policy_url();
		if ( $url ) {
			$links[] = '<a href="' . esc_url( $url ) . '">מדיניות פרטיות</a>';
		}
		$cookie = (int) OPG_Settings::get( 'cookie_page_id' );
		if ( $cookie && 'publish' === get_post_status( $cookie ) ) {
			$links[] = '<a href="' . esc_url( get_permalink( $cookie ) ) . '">מדיניות Cookie</a>';
		}
		if ( OPG_Cookie_Banner::enabled() ) {
			$links[] = '<a href="#" data-opg-cookie-open="1">הגדרות Cookie</a>';
		}
		$req = (int) OPG_Settings::get( 'requests_page_id' );
		if ( $req && 'publish' === get_post_status( $req ) ) {
			$links[] = '<a href="' . esc_url( get_permalink( $req ) ) . '">בקשות פרטיות</a>';
		}
		return $links ? '<nav class="opg-privacy-links" aria-label="פרטיות">' . implode( ' <span aria-hidden="true">|</span> ', $links ) . '</nav>' : '';
	}

	/**
	 * Optional auto footer bar.
	 */
	public static function footer_links() {
		$html = self::shortcode_links();
		if ( $html ) {
			echo '<div class="opg-footer-links">' . $html . '</div>'; // phpcs:ignore
		}
	}
}
