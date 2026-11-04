<?php
/**
 * Compliance scanner - Amendment 13 checklist.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Scanner {

	const OPTION = 'opg_last_scan';

	/** @var array */
	private static $checks = array();

	/**
	 * Group labels (order = report order).
	 *
	 * @return array
	 */
	public static function groups() {
		return array(
			'mapping'  => 'מיפוי מאגרים, רישום וממונה',
			'policy'   => 'מדיניות הפרטיות',
			'forms'    => 'טפסי איסוף מידע והסכמה',
			'rights'   => 'מימוש זכויות נושאי מידע',
			'cookies'  => 'Cookie וכלי מדידה',
			'vendors'  => 'בקרה על צדדים שלישיים',
			'security' => 'אבטחת מידע',
			'ops'      => 'תפעול, תיעוד וביקורת',
		);
	}

	/**
	 * Add a check result.
	 *
	 * @param string $group   Group.
	 * @param string $id      Id.
	 * @param string $title   Title.
	 * @param string $status  pass|warn|fail|info.
	 * @param string $message Message HTML.
	 * @param string $fix     Fix HTML.
	 * @param int    $weight  Weight 1-3.
	 * @param array  $action  [label, url].
	 */
	private static function add( $group, $id, $title, $status, $message, $fix = '', $weight = 2, $action = array() ) {
		self::$checks[] = compact( 'group', 'id', 'title', 'status', 'message', 'fix', 'weight', 'action' );
	}

	/**
	 * Run all checks and store the result.
	 *
	 * @return array
	 */
	public static function run() {
		self::$checks = array();
		$s            = OPG_Settings::all();

		$home        = OPG_Detector::fetch( home_url( '/' ), true );
		$home_html   = $home['body'];
		$policy_id   = (int) get_option( 'wp_page_for_privacy_policy' );
		$policy_url  = OPG_Plugin::policy_url();
		$policy_text = self::policy_text( $policy_id, $policy_url );
		$third       = OPG_Detector::third_parties( $home_html );
		$forms       = OPG_Detector::forms();
		$docs        = (array) get_option( 'opg_docs', array() );
		$gen_url     = admin_url( 'admin.php?page=opg-documents' );
		$set_url     = admin_url( 'admin.php?page=opg-settings' );

		self::check_mapping( $s, $set_url );
		self::check_policy( $s, $policy_id, $policy_url, $policy_text, $home_html, $home, $third, $gen_url );
		self::check_forms( $s, $forms, $third, $set_url, $home_html );
		self::check_rights( $s, $policy_text, $gen_url );
		self::check_cookies( $s, $third, $home_html, $set_url );
		self::check_vendors( $s, $third, $docs, $gen_url, $set_url );
		self::check_security( $home );
		self::check_ops( $s, $docs, $gen_url );

		$total = 0;
		$got   = 0;
		$count = array(
			'pass' => 0,
			'warn' => 0,
			'fail' => 0,
			'info' => 0,
		);
		foreach ( self::$checks as $c ) {
			++$count[ $c['status'] ];
			if ( 'info' === $c['status'] ) {
				continue;
			}
			$total += $c['weight'];
			if ( 'pass' === $c['status'] ) {
				$got += $c['weight'];
			} elseif ( 'warn' === $c['status'] ) {
				$got += $c['weight'] / 2;
			}
		}
		$result = array(
			'time'          => time(),
			'score'         => $total ? (int) round( 100 * $got / $total ) : 0,
			'counts'        => $count,
			'checks'        => self::$checks,
			'third_parties' => $third,
			'forms'         => $forms,
			'home_error'    => $home['error'],
		);
		update_option( self::OPTION, $result, false );
		return $result;
	}

	/**
	 * Last stored result.
	 *
	 * @return array|null
	 */
	public static function last() {
		$r = get_option( self::OPTION );
		return is_array( $r ) ? $r : null;
	}

	/**
	 * Plain lowercase text of the policy (post content, falls back to rendered page).
	 *
	 * @param int    $id  Page.
	 * @param string $url URL.
	 * @return string
	 */
	public static function policy_text( $id, $url ) {
		if ( ! $id ) {
			return '';
		}
		$post = get_post( $id );
		$text = $post ? wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) : '';
		if ( strlen( trim( $text ) ) < 1200 && $url ) {
			$page = OPG_Detector::fetch( $url, true );
			$html = preg_replace( '#<(script|style|noscript|svg)[^>]*>.*?</\1>#is', ' ', $page['body'] );
			// Prefer <main> / <article> when present to avoid header/footer noise.
			if ( preg_match( '#<(main|article)[^>]*>(.*)</\1>#is', (string) $html, $m ) ) {
				$html = $m[2];
			}
			$rendered = wp_strip_all_tags( (string) $html );
			if ( strlen( $rendered ) > strlen( $text ) ) {
				$text = $rendered;
			}
		}
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = str_replace( array( '״', '”', '“' ), '"', $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return OPG_Plugin::lower( (string) $text );
	}

	/**
	 * Does the text contain any of the terms?
	 *
	 * @param string $text  Text.
	 * @param array  $terms Terms.
	 * @return bool
	 */
	private static function has_any( $text, array $terms ) {
		foreach ( $terms as $t ) {
			if ( '' !== $t && false !== strpos( $text, OPG_Plugin::lower( $t ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Forms in raw HTML that collect email/phone and are not handled by a known form plugin.
	 *
	 * @param string $html HTML.
	 * @return string[] Form labels.
	 */
	public static function custom_forms( $html ) {
		$out = array();
		if ( ! $html || ! preg_match_all( '#<form\b([^>]*)>(.*?)</form>#is', $html, $m, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( $m as $f ) {
			$attrs = $f[1];
			$body  = $f[2];
			if ( preg_match( '/wpcf7|elementor-form|wpforms|gform|fluent|comment-form|commentform|woocommerce|cart|checkout|search|opg-rights-form|opg-skip/i', $attrs ) ) {
				continue;
			}
			if ( false !== strpos( $body, 'opg-consent' ) || preg_match( '/type=["\']?(search|password)/i', $body ) ) {
				continue;
			}
			if ( ! preg_match( '/type=["\']?(email|tel)\b|name=["\'][^"\']*(email|phone)/i', $body ) ) {
				continue;
			}
			$label = preg_match( '/\bid=["\']([^"\']+)/', $attrs, $id ) ? '#' . $id[1] : ( preg_match( '/\bclass=["\']([^"\']+)/', $attrs, $cl ) ? '.' . strtok( $cl[1], ' ' ) : 'form' );
			$out[] = $label;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Registration / DPO obligations from the questionnaire.
	 *
	 * @param array $s Settings.
	 * @return array{register:bool,notify:bool,dpo:bool,reasons:array}
	 */
	public static function obligations( $s = null ) {
		$s       = $s ? $s : OPG_Settings::all();
		$big     = in_array( $s['q_subjects'], array( '10k-100k', 'gt100k' ), true );
		$huge    = 'gt100k' === $s['q_subjects'];
		$reasons = array();

		$register = false;
		if ( $s['q_public_body'] ) {
			$register  = true;
			$reasons[] = 'גוף ציבורי - חובת רישום מאגר.';
		}
		if ( $s['q_data_broker'] && $big ) {
			$register  = true;
			$reasons[] = 'מאגר שעיקר מטרתו מסירת מידע לאחרים כדרך עיסוק (כולל שירותי דיוור ישיר) עם מעל 10,000 נושאי מידע - חובת רישום.';
		}
		$notify = ! $register && $s['sensitive_data'] && $huge;
		if ( $notify ) {
			$reasons[] = 'מאגר עם מידע בעל רגישות מיוחדת על יותר מ-100,000 אנשים - חובת הודעה לרשות תוך 30 יום, בצירוף מסמך הגדרות המאגר.';
		}
		$dpo = $s['q_public_body'] || ( $s['q_data_broker'] && $big ) || $s['q_monitoring'] || ( $s['sensitive_data'] && $big );
		if ( $dpo ) {
			$reasons[] = 'לפי הנתונים שסימנתם קיימת כנראה חובה למנות ממונה על הגנת הפרטיות (DPO).';
		}
		return compact( 'register', 'notify', 'dpo', 'reasons' );
	}

	/* ------------------------------------------------------------------ */

	private static function check_mapping( $s, $set_url ) {
		$filled = $s['business_name'] && is_email( $s['contact_email'] ) && ! empty( $s['data_types'] ) && ! empty( $s['purposes'] );
		self::add(
			'mapping',
			'data_map',
			'מיפוי המידע הנאסף (סוגים, מטרות, משך שמירה)',
			$filled ? 'pass' : 'fail',
			$filled ? sprintf( 'הוגדרו %d סוגי מידע ו-%d מטרות.', count( $s['data_types'] ), count( $s['purposes'] ) ) : 'לא הוגדרו פרטי העסק, סוגי המידע הנאספים או מטרות השימוש.',
			'השלימו את לשונית "מיפוי מידע" בהגדרות. הנתונים משמשים גם להפקת מדיניות הפרטיות ומסמך הגדרות המאגר.',
			3,
			array( 'להגדרות', $set_url . '#tab-map' )
		);

		$ob = self::obligations( $s );
		if ( $ob['register'] ) {
			self::add( 'mapping', 'registration', 'חובת רישום מאגר', 'warn', implode( '<br>', array_map( 'esc_html', $ob['reasons'] ) ), 'הגישו בקשת רישום באתר הרשות להגנת הפרטיות (פרטי בעל השליטה, ממונה אם קיים, מטרות, סוגי מידע, מספר נושאי מידע ומקורות). שמרו את אישור ההגשה.', 3, array( 'אתר הרשות', 'https://www.gov.il/he/departments/the_privacy_protection_authority/govil-landing-page' ) );
		} else {
			self::add( 'mapping', 'registration', 'חובת רישום מאגר', 'pass', 'לפי השאלון, המאגר אינו חייב רישום. החובות המהותיות (שקיפות, הגבלת מטרה, זכויות ואבטחה) עדיין חלות.', 'אם המאגר רשום מהעבר ואינו חייב עוד רישום - אפשר לשקול בקשה למחיקתו מהמרשם.', 1 );
		}
		if ( $ob['notify'] ) {
			self::add( 'mapping', 'notification', 'חובת הודעה לרשות', 'warn', 'מאגר עם מידע רגיש על יותר מ-100,000 אנשים.', 'יש להודיע לרשות תוך 30 יום מהיום שבו התקיים התנאי, ולצרף מסמך הגדרות מאגר עדכני (ניתן להפיק בלשונית המסמכים).', 3 );
		}

		if ( $ob['dpo'] && ! $s['officer_is_dpo'] ) {
			self::add( 'mapping', 'dpo', 'ממונה על הגנת הפרטיות (DPO)', 'fail', 'לפי השאלון קיימת כנראה חובה למנות ממונה, ולא סומן שמונה.', 'מנו ממונה על הגנת הפרטיות, עדכנו את פרטיו בהגדרות וסמנו "ממונה רשמי". מומלץ להתייעץ עם עו"ד לגבי היקף החובה.', 3, array( 'להגדרות', $set_url . '#tab-business' ) );
		} elseif ( ! $s['officer_name'] ) {
			self::add( 'mapping', 'dpo', 'גורם אחראי להגנת הפרטיות', 'warn', 'לא הוגדר אדם אחראי לנושא הפרטיות.', 'גם כשאין חובה משפטית - מנו גורם אחראי וציינו דרך ליצור איתו קשר.', 2, array( 'להגדרות', $set_url . '#tab-business' ) );
		} else {
			self::add( 'mapping', 'dpo', 'גורם אחראי להגנת הפרטיות', 'pass', 'הוגדר: ' . esc_html( $s['officer_name'] ), '', 2 );
		}
	}

	/* ------------------------------------------------------------------ */

	private static function check_policy( $s, $policy_id, $policy_url, $text, $home_html, $home, $third, $gen_url ) {
		if ( ! $policy_url ) {
			self::add( 'policy', 'policy_exists', 'קיים עמוד מדיניות פרטיות מפורסם', 'fail', $policy_id ? 'עמוד מדיניות הפרטיות מוגדר אך אינו מפורסם.' : 'לא הוגדר עמוד מדיניות פרטיות באתר.', 'הפיקו מדיניות מותאמת לאתר בלשונית "מסמכים", עברו עליה ופרסמו. התוסף יגדיר אותה כעמוד המדיניות של וורדפרס.', 15, array( 'הפקת מדיניות', $gen_url ) );
			return;
		}
		self::add( 'policy', 'policy_exists', 'קיים עמוד מדיניות פרטיות מפורסם', 'pass', '<a href="' . esc_url( $policy_url ) . '" target="_blank">' . esc_html( get_the_title( $policy_id ) ) . '</a>', '', 3 );

		// Linked from every page (footer check on the home page).
		if ( $home_html ) {
			// Only real <a href> links count - drop scripts (our own JS config carries the URL) and our consent boxes.
			$links_html = preg_replace( '#<script\b[^>]*>.*?</script>#is', ' ', $home_html );
			$links_html = preg_replace( '#<div class="opg-consent.*?</div>#is', ' ', (string) $links_html );
			preg_match_all( '#<a\b[^>]*\bhref=(["\'])(.*?)\1#is', (string) $links_html, $hrefs );
			$home_html_links = implode( "\n", array_map( 'html_entity_decode', $hrefs[2] ) );
			$path   = wp_parse_url( $policy_url, PHP_URL_PATH );
			$linked = false !== strpos( $home_html_links, $policy_url ) || ( $path && strlen( $path ) > 2 && false !== strpos( $home_html_links, 'href="' . $path ) ) || false !== strpos( $home_html_links, rawurldecode( $policy_url ) ) || false !== stripos( $home_html_links, 'page_id=' . $policy_id );
			if ( ! $linked && $path ) {
				$linked = false !== strpos( $home_html_links, rtrim( $path, '/' ) ) || false !== strpos( rawurldecode( $home_html_links ), rawurldecode( rtrim( $path, '/' ) ) );
			}
			self::add( 'policy', 'policy_linked', 'קישור למדיניות מכל עמוד (פוטר)', $linked ? 'pass' : 'fail', $linked ? 'נמצא קישור למדיניות בדף הבית.' : 'לא נמצא קישור למדיניות הפרטיות בדף הבית.', 'הוסיפו קישור בפוטר / בתפריט התחתון לכל העמודים. אפשר להשתמש בשורטקוד <code>[opg_policy_link]</code>.', 3 );
		} else {
			self::add( 'policy', 'policy_linked', 'קישור למדיניות מכל עמוד (פוטר)', 'info', 'לא ניתן היה לטעון את דף הבית לבדיקה' . ( $home['error'] ? ': ' . esc_html( $home['error'] ) : '' ) . '.', 'בדקו ידנית שיש קישור למדיניות בפוטר.', 3 );
		}

		// Generic WordPress template?
		$generic = self::has_any( $text, array( 'suggested text:', 'הטקסט המוצע:', 'our website address is', 'כתובת האתר שלנו היא', 'who we are', 'מי אנחנו' ) ) && self::has_any( $text, array( 'gravatar', 'embedded content from other websites', 'תוכן מוטמע מאתרים אחרים' ) );
		if ( $generic ) {
			self::add( 'policy', 'policy_generic', 'נוסח מותאם לאתר (לא תבנית גנרית)', 'fail', 'המדיניות נראית כמו התבנית האוטומטית של וורדפרס, שאינה משקפת את האתר.', 'הפיקו מדיניות חדשה שמבוססת על מיפוי המידע והכלים שזוהו באתר.', 3, array( 'הפקת מדיניות', $gen_url ) );
		}

		$sections = array(
			'collected'  => array( 'פירוט סוגי המידע הנאסף', array( 'סוגי המידע', 'המידע שאנו אוספים', 'מידע שנאסף', 'מידע הנאסף', 'איסוף מידע', 'אילו נתונים', 'איזה מידע', 'עשוי להיאסף', 'לאסוף', 'information we collect', 'data we collect', 'personal data we' ), 3 ),
			'purposes'   => array( 'מטרות השימוש במידע', array( 'מטרות', 'מטרת', 'לצורך', 'purpose' ), 3 ),
			'recipients' => array( 'מי מקבל את המידע (צדדים שלישיים)', array( 'צד שלישי', 'צדדים שלישיים', 'ספקי שירות', 'מסירת מידע', 'העברת מידע', 'שיתוף מידע', 'משותף', 'third part', 'share your', 'service provider', 'we share' ), 3 ),
			'retention'  => array( 'משך שמירת המידע', array( 'תקופת שמירה', 'תקופת השמירה', 'משך השמירה', 'משך שמירת', 'נשמר למשך', 'יישמר', 'שמירת המידע', 'שמירת מידע', 'נשמור', 'תשמור', 'ישמור', 'למשך התקופה', 'retention', 'retain' ), 2 ),
			'abroad'     => array( 'העברת מידע לחו"ל', array( 'מחוץ לישראל', 'מחוץ למדינת ישראל', 'בחו"ל', 'חו"ל', 'לחול', 'מחוץ לגבולות', 'מחוץ למדינה', 'outside israel', 'abroad', 'international transfer' ), 2 ),
			'cookies'    => array( 'שימוש ב-Cookie', array( 'cookie', 'קוקי', 'עוגיות', 'עוגיה' ), 2 ),
			'security'   => array( 'אבטחת המידע', array( 'אבטחת מידע', 'אבטחת המידע', 'אמצעי אבטחה', 'security' ), 1 ),
			'obligation' => array( 'הודעה שאין חובה חוקית למסור מידע (סעיף 11)', array( 'חובה חוקית', 'אינך חייב', 'אינכם חייבים', 'אין חובה', 'לא חלה עליך חובה', 'לא חלה עליכם חובה', 'not legally required', 'not required by law' ), 2 ),
		);
		$missing = array();
		foreach ( $sections as $key => $sec ) {
			$ok = self::has_any( $text, $sec[1] );
			self::add(
				'policy',
				'sec_' . $key,
				'מדיניות: ' . $sec[0],
				$ok ? 'pass' : 'fail',
				$ok ? 'נמצא.' : 'לא נמצא סעיף מתאים בטקסט המדיניות.',
				$ok ? '' : 'הוסיפו סעיף "' . $sec[0] . '" - או הפיקו מדיניות מעודכנת שכוללת אותו.',
				$sec[2],
				$ok ? array() : array( 'הפקת מדיניות', $gen_url )
			);
			if ( ! $ok ) {
				$missing[] = $key;
			}
		}

		// Rights: access + correction + deletion.
		$rights = array(
			'עיון'  => array( 'עיון', 'לעיין', 'access' ),
			'תיקון' => array( 'תיקון', 'לתקן', 'correct', 'rectif' ),
			'מחיקה' => array( 'מחיקה', 'למחוק', 'delete', 'erase' ),
		);
		$miss_r = array();
		foreach ( $rights as $label => $terms ) {
			if ( ! self::has_any( $text, $terms ) ) {
				$miss_r[] = $label;
			}
		}
		self::add( 'policy', 'sec_rights', 'מדיניות: זכויות המשתמש (עיון, תיקון, מחיקה)', $miss_r ? 'fail' : 'pass', $miss_r ? 'חסרות: ' . implode( ', ', $miss_r ) . '.' : 'שלוש הזכויות מופיעות.', 'פרטו את הזכות לעיין במידע, לבקש תיקון או מחיקה של מידע שגוי, חסר, לא ברור או לא מעודכן - ואת הדרך לממש אותן.', 3 );

		// How to exercise rights: an email address or a link to the requests page.
		$req_page = (int) $s['requests_page_id'];
		$has_way  = (bool) preg_match( '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text ) || ( $req_page && false !== strpos( $text, OPG_Plugin::lower( get_the_title( $req_page ) ) ) );
		self::add( 'policy', 'sec_contact', 'מדיניות: פרטי קשר למימוש זכויות', $has_way ? 'pass' : 'fail', $has_way ? 'נמצאו פרטי קשר.' : 'לא נמצאה כתובת דוא"ל או קישור לטופס בקשות.', 'ציינו כתובת דוא"ל של האחראי על הפרטיות וקישור לטופס מימוש הזכויות.', 2 );

		// Identity of the controller.
		$who = ( $s['business_name'] && false !== strpos( $text, OPG_Plugin::lower( $s['business_name'] ) ) ) || ( $s['business_id'] && false !== strpos( $text, $s['business_id'] ) );
		self::add( 'policy', 'sec_controller', 'מדיניות: זהות בעל השליטה במאגר', $who ? 'pass' : 'warn', $who ? 'שם העסק מופיע.' : 'שם העסק / מספר ח.פ. מההגדרות לא מופיעים במדיניות.', 'ציינו את שם העסק, מספר ח.פ./ע.מ. וכתובת.', 1 );

		// Direct marketing section.
		$mailing = isset( $third['mailchimp'] ) || isset( $third['mailpoet'] ) || isset( $third['fluentcrm'] ) || isset( $third['hubspot'] ) || $s['direct_marketing'];
		if ( $mailing ) {
			$ok = self::has_any( $text, array( 'דיוור', 'שיווק', 'direct mail', 'marketing' ) ) && self::has_any( $text, array( 'הסרה', 'להסיר', 'הסר', 'unsubscribe', 'opt-out', 'opt out', 'ביטול' ) );
			self::add( 'policy', 'sec_marketing', 'מדיניות: דיוור ישיר, הסכמה והסרה', $ok ? 'pass' : 'fail', $ok ? 'נמצא.' : 'האתר עושה דיוור/שיווק, אך המדיניות לא מסבירה את ההסכמה ואת אופן ההסרה.', 'הוסיפו הסבר על ההסכמה לדיוור, על אופן ההסרה מרשימת התפוצה ועל קישור ההסרה בכל הודעה.', 2 );
		}

		// Third parties named in the policy.
		$unnamed = array();
		foreach ( $third as $tp ) {
			if ( ! self::has_any( $text, $tp['terms'] ) ) {
				$unnamed[] = $tp['name'];
			}
		}
		if ( $third ) {
			self::add( 'policy', 'sec_vendors', 'מדיניות: הכלים והספקים שזוהו באתר מוזכרים', $unnamed ? 'warn' : 'pass', $unnamed ? 'לא מוזכרים במדיניות: ' . esc_html( implode( ', ', $unnamed ) ) : 'כל ' . count( $third ) . ' הכלים שזוהו מוזכרים.', 'פרטו עם מי המידע משותף ולאיזו מטרה. המחולל מכניס אוטומטית את כל הכלים שזוהו.', 2 );
		}

		// Freshness.
		$modified = get_post_modified_time( 'U', true, $policy_id );
		$age_days = $modified ? (int) floor( ( time() - $modified ) / DAY_IN_SECONDS ) : 9999;
		$after13  = $modified && $modified >= strtotime( '2025-08-14' );
		$status   = ( $age_days > 365 || ! $after13 ) ? 'warn' : 'pass';
		self::add( 'policy', 'policy_fresh', 'עדכניות המדיניות', $status, sprintf( 'עודכנה לאחרונה לפני %d ימים (%s).', $age_days, $modified ? esc_html( wp_date( 'd/m/Y', $modified ) ) : '-' ) . ( $after13 ? '' : ' לא עודכנה מאז כניסת תיקון 13 לתוקף (14.8.2025).' ), 'עדכנו את המדיניות בכל שינוי מהותי באיסוף, בשימוש, בספקים או בטכנולוגיה, ולפחות פעם בשנה.', 2 );

		// Languages.
		$locale = get_locale();
		if ( 0 !== strpos( $locale, 'he' ) ) {
			$en = (int) $s['policy_page_en_id'];
			self::add( 'policy', 'policy_lang', 'מדיניות בשפת משתמשי האתר', $en ? 'pass' : 'warn', 'שפת האתר: ' . esc_html( $locale ) . '.', 'פרסמו מדיניות גם בשפה שבה פונים למשתמשים (המחולל מפיק גם גרסה באנגלית).', 1, array( 'הפקת מדיניות באנגלית', $gen_url ) );
		}
	}

	/* ------------------------------------------------------------------ */

	private static function check_forms( $s, $forms, $third, $set_url, $home_html = '' ) {
		$map = array(
			'woocommerce' => array( 'consent_wc_checkout', 'consent_wc_register' ),
			'wp_register' => array( 'consent_wp_register' ),
			'comments'    => array( 'consent_comments' ),
			'cf7'         => array( 'consent_cf7' ),
			'wpforms'     => array( 'consent_wpforms' ),
			'gravity'     => array( 'consent_gravity' ),
			'fluent'      => array( 'consent_fluent' ),
			'elementor'   => array( 'consent_elementor' ),
		);
		if ( ! $forms ) {
			self::add( 'forms', 'forms_none', 'טפסים באתר', 'info', 'לא זוהו תוספי טפסים. טפסים אחרים מכוסים בשכבה הכללית (JS).', '', 1 );
		}
		foreach ( $forms as $key => $f ) {
			list( $label, $count, $supported ) = $f;
			$title = 'צ\'קבוקס הסכמה: ' . $label . ( $count ? " ({$count})" : '' );
			if ( $supported && isset( $map[ $key ] ) ) {
				$on = true;
				foreach ( $map[ $key ] as $opt ) {
					$on = $on && ! empty( $s[ $opt ] );
				}
				self::add( 'forms', 'form_' . $key, $title, $on ? 'pass' : 'fail', $on ? 'מוזרק ונאכף בצד השרת, ההסכמות נרשמות ביומן.' : 'ההזרקה כבויה בהגדרות.', 'הפעילו את האפשרות בלשונית "טפסים והסכמה".', 3, $on ? array() : array( 'להגדרות', $set_url . '#tab-forms' ) );
			} else {
				$on = ! empty( $s['consent_universal'] );
				self::add( 'forms', 'form_' . $key, $title, $on ? 'warn' : 'fail', $on ? 'מכוסה בשכבה הכללית (JS) - נאכף בדפדפן בלבד.' : 'אין כיסוי - השכבה הכללית כבויה.', 'הוסיפו לטופס את השורטקוד <code>[opg_consent_box]</code> או שדה הסכמה מובנה של התוסף, ו/או הפעילו את השכבה הכללית.', 2 );
			}
		}

		// Custom HTML forms on the home page (theme / snippet / page builder HTML widget).
		$custom = self::custom_forms( $home_html );
		if ( $custom ) {
			$on = ! empty( $s['consent_universal'] );
			self::add( 'forms', 'custom_forms', 'טפסים מותאמים בדף הבית (' . count( $custom ) . ')', $on ? 'warn' : 'fail', ( $on ? 'מכוסים בשכבה הכללית (JS) - הצ\'קבוקס מתווסף ונאכף בדפדפן: ' : 'אין כיסוי - השכבה הכללית כבויה: ' ) . esc_html( implode( ', ', $custom ) ), 'לאכיפה גם בצד השרת: הוסיפו לקוד הטופס את <code>[opg_consent_box source="custom"]</code>, או העבירו אותו לתוסף טפסים נתמך. בטופס שנשלח ב-JS (למשל לוואטסאפ) השכבה הכללית מספיקה.', 2 );
		}

		self::add( 'forms', 'collection_notice', 'הודעה על מטרת האיסוף בעת איסוף המידע', $s['show_collection_notice'] ? 'pass' : 'warn', $s['show_collection_notice'] ? 'מוצגת הודעה קצרה מעל הצ\'קבוקס בכל טופס.' : 'ההודעה כבויה - יש רק צ\'קבוקס.', 'הפעילו "הודעת איסוף" - מטרת האיסוף, האם חלה חובה למסור, ולמי יימסר המידע (סעיף 11).', 2, array( 'להגדרות', $set_url . '#tab-forms' ) );

		$mailing_tool = isset( $third['mailchimp'] ) || isset( $third['mailpoet'] ) || isset( $third['fluentcrm'] ) || isset( $third['hubspot'] ) || isset( $third['onesignal'] );
		if ( $mailing_tool && ! $s['direct_marketing'] ) {
			self::add( 'forms', 'marketing_consent', 'הסכמה נפרדת לדיוור שיווקי', 'fail', 'זוהה כלי דיוור/CRM, אבל בהגדרות לא סומן שהאתר שולח דיוור שיווקי - לכן אין צ\'קבוקס הסכמה לשיווק.', 'סמנו "האתר שולח דיוור ישיר" ו"צ\'קבוקס הסכמה לשיווק". אין להניח שדוא"ל שנמסר לחשבונית מתיר שיווק.', 3, array( 'להגדרות', $set_url . '#tab-map' ) );
		} elseif ( $s['direct_marketing'] ) {
			$ok = (bool) $s['show_marketing_box'];
			self::add( 'forms', 'marketing_consent', 'הסכמה נפרדת לדיוור שיווקי', $ok ? 'pass' : 'fail', $ok ? 'צ\'קבוקס שיווק נפרד, לא מסומן מראש.' : 'האתר שולח דיוור אך הצ\'קבוקס לשיווק כבוי.', 'הפעילו צ\'קבוקס נפרד ולא מסומן מראש לדיוור שיווקי.', 3 );
		}

		$stats = OPG_Consent_Log::stats();
		self::add( 'forms', 'consent_log', 'יומן הסכמות (הוכחה לבעל האתר)', 'pass', sprintf( 'נרשמו %d הסכמות, %d ב-30 הימים האחרונים.', $stats['total'], $stats['last30'] ), '', 2 );
	}

	/* ------------------------------------------------------------------ */

	private static function check_rights( $s, $policy_text, $gen_url ) {
		$page = (int) $s['requests_page_id'];
		$ok   = $page && 'publish' === get_post_status( $page ) && has_shortcode( (string) get_post_field( 'post_content', $page ), 'opg_rights_request' );
		self::add( 'rights', 'rights_page', 'טופס מקוון לבקשות עיון / תיקון / מחיקה', $ok ? 'pass' : 'fail', $ok ? '<a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank">' . esc_html( get_the_title( $page ) ) . '</a> - עם אימות דוא"ל ומעקב מועדים.' : 'אין עמוד פעיל לבקשות מימוש זכויות.', 'לחצו "יצירת עמוד, פרסום וקישור אוטומטי" - העמוד יפורסם, יקושר מסוף המדיניות ויופיע בשורת הקישורים בפוטר.', 3, $ok ? array() : array( 'יצירת עמוד', $gen_url ) );

		$overdue = OPG_Requests::count_overdue();
		$open    = OPG_Requests::count_open();
		self::add( 'rights', 'rights_sla', 'מענה לבקשות בזמן (עד 30 יום)', $overdue ? 'fail' : 'pass', $overdue ? sprintf( '%d בקשות חרגו מהמועד!', $overdue ) : sprintf( '%d בקשות פתוחות, אף אחת לא באיחור.', $open ), 'טפלו בבקשות הפתוחות ותעדו את ההחלטה.', 3, $overdue ? array( 'לבקשות', admin_url( 'admin.php?page=opg-requests' ) ) : array() );

		if ( $ok && $policy_text ) {
			$linked = false !== strpos( $policy_text, OPG_Plugin::lower( get_the_title( $page ) ) );
			$policy = get_post( (int) get_option( 'wp_page_for_privacy_policy' ) );
			if ( ! $linked && $policy ) {
				$linked = false !== strpos( $policy->post_content, (string) get_permalink( $page ) ) || false !== strpos( $policy->post_content, 'page_id=' . $page );
			}
			self::add( 'rights', 'rights_linked', 'המדיניות מפנה לטופס הבקשות', $linked ? 'pass' : 'warn', $linked ? 'נמצא קישור.' : 'המדיניות לא מקשרת לטופס הבקשות.', 'הוסיפו למדיניות קישור לעמוד הבקשות (המדיניות שהמחולל מפיק כוללת אותו).', 1 );
		}
	}

	/* ------------------------------------------------------------------ */

	private static function check_cookies( $s, $third, $html, $set_url ) {
		$trackers = array_filter(
			$third,
			function ( $t ) {
				return in_array( $t['category'], array( 'analytics', 'marketing' ), true );
			}
		);
		$other    = OPG_Detector::other_cookie_plugin();
		$ours     = OPG_Cookie_Banner::enabled();

		if ( $other ) {
			self::add( 'cookies', 'cookie_banner', 'באנר הסכמה ל-Cookie', 'pass', 'פעיל: ' . esc_html( $other ) . ' (הבאנר של התוסף כבוי אוטומטית כדי לא לכפול).', 'ודאו שהבאנר מאפשר גם דחייה וגם שינוי בחירה בהמשך.', 2 );
		} elseif ( $ours ) {
			self::add( 'cookies', 'cookie_banner', 'באנר הסכמה ל-Cookie', 'pass', 'הבאנר של Ofnoa Privacy Guard פעיל - אישור, דחייה והגדרות לפי קטגוריה, כולל כפתור לשינוי הבחירה.', '', 2 );
		} else {
			self::add( 'cookies', 'cookie_banner', 'באנר הסכמה ל-Cookie', $trackers ? 'fail' : 'warn', $trackers ? 'פועלים כלי מדידה/פרסום (' . esc_html( implode( ', ', wp_list_pluck( $trackers, 'name' ) ) ) . ') ואין באנר הסכמה.' : 'אין באנר הסכמה.', 'הפעילו את באנר ה-Cookie בהגדרות.', 2, array( 'להגדרות', $set_url . '#tab-cookies' ) );
		}

		if ( $trackers && ! $other ) {
			$cm = $ours && $s['consent_mode_v2'];
			self::add( 'cookies', 'consent_mode', 'עיכוב מדידה ופרסום עד הסכמה', $cm ? 'pass' : 'warn', $cm ? 'Google Consent Mode v2 מוגדר כ"נדחה" עד אישור, ותגיות שסומנו ב-data-opg-category מופעלות רק אחרי הסכמה.' : 'כלי המדידה רצים לפני שהמשתמש בחר.', 'הפעילו Consent Mode v2. לתגיות שאינן של גוגל (למשל Meta Pixel) - שנו ל-<code>type="text/plain" data-opg-category="marketing"</code>, או הגדירו ב-GTM טריגר לפי האירוע <code>opg_consent</code>.', 2 );
			if ( isset( $trackers['meta'] ) && $html && false !== strpos( $html, 'fbq(' ) && false === strpos( $html, 'data-opg-category' ) && ! $other ) {
				self::add( 'cookies', 'meta_before_consent', 'Meta Pixel נטען לפני הסכמה', 'warn', 'Meta Pixel לא מכבד את Consent Mode של גוגל ונטען מיד.', 'השתמשו ב-<code>fbq(\'consent\',\'revoke\')</code> כברירת מחדל - התוסף שולח <code>grant</code> כשהמשתמש מאשר שיווק - או החזיקו את הסקריפט עם <code>data-opg-category="marketing"</code>.', 1 );
			}
		}
		$cookie_page = (int) $s['cookie_page_id'];
		self::add( 'cookies', 'cookie_policy', 'מדיניות Cookie', $cookie_page && 'publish' === get_post_status( $cookie_page ) ? 'pass' : 'info', $cookie_page ? 'קיים עמוד מדיניות Cookie.' : 'אין עמוד נפרד (אפשר להסתפק בסעיף במדיניות הפרטיות).', '', 1 );
	}

	/* ------------------------------------------------------------------ */

	private static function check_vendors( $s, $third, $docs, $gen_url, $set_url ) {
		if ( ! $third ) {
			self::add( 'vendors', 'vendors_none', 'ספקים וכלים חיצוניים', 'info', 'לא זוהו שירותי צד שלישי. אם יש ספקים שלא זוהו (CRM, הנה"ח, אחסון) - הוסיפו אותם ידנית.', '', 1, array( 'הוספה ידנית', $set_url . '#tab-map' ) );
			return;
		}
		$list = array();
		foreach ( $third as $t ) {
			$list[] = '<li><strong>' . esc_html( $t['name'] ) . '</strong> - ' . esc_html( $t['data'] ) . ( $t['location'] ? ' <em>(' . esc_html( $t['location'] ) . ')</em>' : '' ) . '</li>';
		}
		self::add( 'vendors', 'vendors_list', 'מיפוי ספקים שמקבלים מידע אישי', 'pass', 'זוהו ' . count( $third ) . ' ספקים/כלים:<ul class="opg-ul">' . implode( '', $list ) . '</ul>', 'השלימו ידנית ספקים שלא זוהו אוטומטית (שירותי הנה"ח, אחסון ענן, תמיכה).', 2 );

		$abroad = array_filter(
			$third,
			function ( $t ) {
				return $t['location'] && false === strpos( $t['location'], 'ישראל' ) && false === strpos( $t['location'], 'אתר זה' );
			}
		);
		if ( $abroad && ! $s['transfer_abroad'] ) {
			self::add( 'vendors', 'abroad', 'גילוי על העברת מידע לחו"ל', 'fail', 'ספקים בחו"ל: ' . esc_html( implode( ', ', wp_list_pluck( $abroad, 'name' ) ) ) . ' - אבל בהגדרות סומן שאין העברה לחו"ל.', 'סמנו "מידע מועבר לחו"ל" והפיקו מחדש את המדיניות.', 3, array( 'להגדרות', $set_url . '#tab-map' ) );
		} elseif ( $abroad ) {
			self::add( 'vendors', 'abroad', 'גילוי על העברת מידע לחו"ל', 'pass', 'מוגדר ומופיע במדיניות שהמחולל מפיק.', '', 2 );
		}

		$dpa = isset( $docs['vendor_dpa'] ) && $docs['vendor_dpa'] > strtotime( '-12 months' );
		self::add( 'vendors', 'vendor_agreements', 'הסכמי סודיות ואבטחת מידע עם ספקים', $dpa ? 'pass' : 'warn', $dpa ? 'הופק נספח אבטחת מידע לספקים.' : 'לא תועדו הסכמים עם הספקים שמעבדים מידע מטעמכם.', 'לכל ספק שמעבד מידע בשמכם: הסכם סודיות ואבטחה, הגבלת שימוש, חובת דיווח על אירוע. הפיקו נספח והחתימו ספקים מקומיים; לספקים גלובליים - שמרו עותק של ה-DPA שלהם.', 2, array( 'הפקת נספח', $gen_url ) );

		if ( isset( $third['ai'] ) ) {
			self::add( 'vendors', 'ai', 'שירותי בינה מלאכותית', 'warn', 'זוהו רכיבי AI שעשויים לשלוח תוכן משתמשים לספק חיצוני.', 'ודאו: מה נשלח (להימנע ממידע מזהה כשאפשר), מדיניות שמירת הנתונים אצל הספק (Zero Data Retention אם זמין), שהשימוש מוזכר במדיניות, ושיש הסכם עם הספק.', 2 );
		}
	}

	/* ------------------------------------------------------------------ */

	private static function check_security( $home ) {
		$https = 0 === strpos( home_url(), 'https://' ) && 0 === strpos( site_url(), 'https://' );
		self::add( 'security', 'https', 'HTTPS / TLS בכל האתר', $https ? 'pass' : 'fail', $https ? 'כתובות האתר מוגדרות ב-HTTPS.' : 'כתובת האתר או כתובת וורדפרס אינן HTTPS.', 'התקינו תעודת SSL ועדכנו את כתובות האתר ל-https.', 3 );

		$headers = array_change_key_case( (array) $home['headers'], CASE_LOWER );
		if ( $headers ) {
			$want    = array(
				'strict-transport-security' => 'HSTS',
				'x-content-type-options'    => 'X-Content-Type-Options',
				'referrer-policy'           => 'Referrer-Policy',
			);
			$missing = array();
			foreach ( $want as $h => $label ) {
				if ( empty( $headers[ $h ] ) ) {
					$missing[] = $label;
				}
			}
			if ( empty( $headers['x-frame-options'] ) && ( empty( $headers['content-security-policy'] ) || false === stripos( (string) ( is_array( $headers['content-security-policy'] ) ? implode( ' ', $headers['content-security-policy'] ) : $headers['content-security-policy'] ), 'frame-ancestors' ) ) ) {
				$missing[] = 'X-Frame-Options';
			}
			self::add( 'security', 'headers', 'כותרות אבטחה (Security Headers)', $missing ? 'warn' : 'pass', $missing ? 'חסרות: ' . implode( ', ', $missing ) : 'כל הכותרות הבסיסיות קיימות.', 'הוסיפו את הכותרות ברמת השרת / Cloudflare (Transform Rules) או בתוסף אבטחה.', 1 );
		}

		require_once ABSPATH . 'wp-admin/includes/update.php';
		$core   = get_core_updates();
		$core_u = is_array( $core ) && isset( $core[0]->response ) && 'upgrade' === $core[0]->response;
		self::add( 'security', 'core', 'וורדפרס מעודכן', $core_u ? 'warn' : 'pass', $core_u ? 'קיימת גרסה חדשה של וורדפרס.' : 'גרסה ' . esc_html( get_bloginfo( 'version' ) ) . '.', 'עדכנו את וורדפרס.', 2 );

		$upd   = get_site_transient( 'update_plugins' );
		$count = ( is_object( $upd ) && ! empty( $upd->response ) ) ? count( $upd->response ) : 0;
		self::add( 'security', 'plugins', 'תוספים מעודכנים', $count ? 'warn' : 'pass', $count ? sprintf( '%d תוספים ממתינים לעדכון.', $count ) : 'כל התוספים מעודכנים.', 'עדכנו תוספים ותבניות, והסירו תוספים שאינם בשימוש.', 2, $count ? array( 'לעדכונים', admin_url( 'update-core.php' ) ) : array() );

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'fields' => array( 'user_login' ),
			)
		);
		$names  = wp_list_pluck( $admins, 'user_login' );
		self::add( 'security', 'admin_user', 'אין משתמש בשם admin', in_array( 'admin', $names, true ) ? 'warn' : 'pass', in_array( 'admin', $names, true ) ? 'קיים מנהל בשם "admin" - יעד מועדף לתקיפות.' : 'תקין.', 'צרו מנהל חדש עם שם ייחודי והעבירו אליו את התוכן.', 1 );
		self::add( 'security', 'admins', 'הרשאה מזערית - מספר מנהלים', count( $names ) > 3 ? 'warn' : 'pass', sprintf( '%d מנהלים: %s', count( $names ), esc_html( implode( ', ', array_slice( $names, 0, 10 ) ) ) ), 'צמצמו מנהלים לנדרש בלבד, השתמשו בחשבונות אישיים ובטלו גישה מיד כשעובד/ספק עוזב.', 2 );

		$tfa = OPG_Detector::twofa_plugin();
		self::add( 'security', 'twofa', 'אימות דו-שלבי (MFA) למנהלים', $tfa ? 'pass' : 'warn', $tfa ? 'זוהה: ' . esc_html( $tfa ) : 'לא זוהה תוסף אימות דו-שלבי.', 'התקינו תוסף 2FA (למשל Two Factor / WP 2FA) וחייבו אותו למנהלים.', 2 );

		$backup = OPG_Detector::backup_plugin();
		self::add( 'security', 'backups', 'גיבויים', $backup ? 'pass' : 'warn', $backup ? 'זוהה: ' . esc_html( $backup ) : 'לא זוהה תוסף גיבוי (ייתכן שהגיבוי ברמת האחסון).', 'ודאו גיבוי אוטומטי מוצפן מחוץ לשרת, ובדקו שחזור לפחות פעם ברבעון.', 2 );

		$sec = OPG_Detector::security_plugin();
		self::add( 'security', 'security_plugin', 'הגנה וניטור (WAF / סריקה)', $sec ? 'pass' : 'warn', $sec ? 'זוהה: ' . esc_html( $sec ) : 'לא זוהה תוסף אבטחה.', 'התקינו פתרון אבטחה (או Cloudflare WAF) לניטור וחסימת תקיפות.', 1 );

		$file_edit = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
		self::add( 'security', 'file_edit', 'עריכת קבצים מלוח הבקרה חסומה', $file_edit ? 'pass' : 'warn', $file_edit ? 'DISALLOW_FILE_EDIT פעיל.' : 'ניתן לערוך קוד תוספים/תבנית מתוך וורדפרס.', "הוסיפו ל-wp-config.php: <code>define( 'DISALLOW_FILE_EDIT', true );</code>", 1 );

		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY );
		self::add( 'security', 'debug', 'שגיאות לא מוצגות לגולשים', $debug ? 'fail' : 'pass', $debug ? 'WP_DEBUG_DISPLAY פעיל - שגיאות ונתיבים נחשפים.' : 'תקין.', "ב-wp-config.php: <code>define( 'WP_DEBUG_DISPLAY', false );</code>", 2 );

		$php_ok = version_compare( PHP_VERSION, '8.1', '>=' );
		self::add( 'security', 'php', 'גרסת PHP נתמכת', $php_ok ? 'pass' : 'warn', 'PHP ' . esc_html( PHP_VERSION ), 'שדרגו ל-PHP 8.1 ומעלה דרך לוח הבקרה של האחסון.', 1 );

		$audit = OPG_Settings::get( 'audit_log' );
		self::add( 'security', 'audit', 'תיעוד אירועי גישה ושינויים', $audit ? 'pass' : 'warn', $audit ? sprintf( 'יומן אבטחה פעיל. %d ניסיונות התחברות כושלים ב-24 שעות.', OPG_Audit_Log::failed_logins( 24 ) ) : 'יומן האבטחה כבוי.', 'הפעילו את יומן האבטחה בהגדרות.', 2 );
	}

	/* ------------------------------------------------------------------ */

	private static function check_ops( $s, $docs, $gen_url ) {
		$year = strtotime( '-12 months' );
		$need = array(
			'db_definitions' => array( 'מסמך הגדרות מאגר (תקנות אבטחת מידע 2017)', 3 ),
			'security_policy' => array( 'נוהל אבטחת מידע', 2 ),
			'incident_plan'  => array( 'תוכנית תגובה לאירוע אבטחה', 2 ),
			'data_map'       => array( 'מסמך מיפוי מאגרים וזרימת מידע', 2 ),
		);
		foreach ( $need as $key => $d ) {
			$ok = isset( $docs[ $key ] ) && $docs[ $key ] > $year;
			self::add( 'ops', 'doc_' . $key, $d[0], $ok ? 'pass' : 'warn', $ok ? 'הופק ב-' . esc_html( wp_date( 'd/m/Y', $docs[ $key ] ) ) . '.' : 'לא הופק ב-12 החודשים האחרונים.', 'הפיקו את המסמך, השלימו את החלקים הידניים, חתמו ושמרו.', $d[1], $ok ? array() : array( 'הפקה', $gen_url ) );
		}
		$last = $s['last_review'] ? strtotime( $s['last_review'] ) : 0;
		$ok   = $last && $last > $year;
		self::add( 'ops', 'annual_review', 'ביקורת שנתית', $ok ? 'pass' : 'warn', $ok ? 'בוצעה ב-' . esc_html( wp_date( 'd/m/Y', $last ) ) . '.' : 'לא תועדה ביקורת שנתית.', 'פעם בשנה: מפת מאגרים, מדיניות, הרשאות, ספקים, גיבויים ותוכנית התגובה. סמנו "ביקורת בוצעה" בדוח.', 2 );
	}
}
