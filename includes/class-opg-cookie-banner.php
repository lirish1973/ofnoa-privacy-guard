<?php
/**
 * Cookie consent banner + Google Consent Mode v2 defaults.
 *
 * Scripts can be held until consent with:
 *   <script type="text/plain" data-opg-category="analytics" src="..."></script>
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Cookie_Banner {

	const COOKIE = 'opg_consent';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'opg_cookie_settings', array( __CLASS__, 'shortcode_link' ) );
		if ( ! self::enabled() ) {
			return;
		}
		if ( OPG_Settings::get( 'consent_mode_v2' ) ) {
			add_action( 'wp_head', array( __CLASS__, 'consent_mode_defaults' ), 1 );
		}
	}

	/**
	 * Is our banner active on this site?
	 *
	 * @return bool
	 */
	public static function enabled() {
		$mode = OPG_Settings::get( 'cookie_banner', 'auto' );
		if ( 'on' === $mode ) {
			return true;
		}
		if ( 'off' === $mode ) {
			return false;
		}
		return ! OPG_Detector::other_cookie_plugin();
	}

	/**
	 * Config for front.js.
	 *
	 * @return array
	 */
	public static function config() {
		$cookie_page = (int) OPG_Settings::get( 'cookie_page_id' );
		$policy      = $cookie_page && 'publish' === get_post_status( $cookie_page ) ? get_permalink( $cookie_page ) : OPG_Plugin::policy_url();
		return array(
			'enabled'  => self::enabled(),
			'cookie'   => self::COOKIE,
			'version'  => '1',
			'text'     => wp_kses( (string) OPG_Settings::get( 'cookie_text' ), OPG_Settings::allowed_html() ),
			'position' => OPG_Settings::get( 'cookie_position', 'bottom' ),
			'policy'   => $policy ? $policy : '',
			'days'     => 180,
			'labels'   => array(
				'accept'     => 'אישור הכל',
				'reject'     => 'דחייה',
				'settings'   => 'הגדרות',
				'save'       => 'שמירת הבחירה',
				'policy'     => 'מדיניות Cookie',
				'title'      => 'פרטיות ו-Cookie',
				'reopen'     => 'הגדרות Cookie',
				'necessary'  => 'חיוניים - נדרשים לפעולת האתר (תמיד פעילים)',
				'analytics'  => 'סטטיסטיקה - מדידת שימוש ושיפור האתר',
				'marketing'  => 'שיווק - פרסום מותאם ומדידת קמפיינים',
				'functional' => 'פונקציונליים - צ\'אט, סרטונים מוטמעים והעדפות',
			),
		);
	}

	/**
	 * Google Consent Mode v2: default denied until the visitor chooses.
	 */
	public static function consent_mode_defaults() {
		$cookie = self::COOKIE;
		?>
<script id="opg-consent-mode">
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
(function(){var d={ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500};
gtag('consent','default',d);gtag('set','ads_data_redaction',true);
var m=document.cookie.match(/(?:^|; )<?php echo esc_js( $cookie ); ?>=([^;]*)/);if(!m)return;
var c=decodeURIComponent(m[1]).split('|')[1]||'';var a=c.indexOf('analytics')>-1?'granted':'denied',k=c.indexOf('marketing')>-1?'granted':'denied';
gtag('consent','update',{analytics_storage:a,ad_storage:k,ad_user_data:k,ad_personalization:k});})();
</script>
		<?php
	}

	/**
	 * [opg_cookie_settings] - link that reopens the banner.
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public static function shortcode_link( $atts ) {
		$atts = shortcode_atts( array( 'text' => 'הגדרות Cookie' ), $atts, 'opg_cookie_settings' );
		return '<a href="#" class="opg-cookie-reopen" data-opg-cookie-open="1">' . esc_html( $atts['text'] ) . '</a>';
	}
}
