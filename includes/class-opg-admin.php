<?php
/**
 * Admin UI: report, documents, requests, consent log, audit log, settings.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Admin {

	const CAP = 'manage_options';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'activation_redirect' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_widget' ) );

		$actions = array( 'run_scan', 'save_settings', 'create_page', 'download_doc', 'view_doc', 'export_consents', 'request_update', 'request_core', 'mark_review', 'export_settings', 'import_settings', 'quick_fix' );
		foreach ( $actions as $a ) {
			add_action( 'admin_post_opg_' . $a, array( __CLASS__, 'action_' . $a ) );
		}
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		$open  = OPG_Requests::count_open();
		$badge = $open ? ' <span class="awaiting-mod">' . (int) $open . '</span>' : '';
		add_menu_page( 'Ofnoa Privacy Guard', 'הגנת פרטיות' . $badge, self::CAP, 'opg-scan', array( __CLASS__, 'page_scan' ), 'dashicons-shield', 81 );
		add_submenu_page( 'opg-scan', 'דוח ציות', 'דוח ציות', self::CAP, 'opg-scan', array( __CLASS__, 'page_scan' ) );
		add_submenu_page( 'opg-scan', 'מסמכים וטפסים', 'מסמכים וטפסים', self::CAP, 'opg-documents', array( __CLASS__, 'page_documents' ) );
		add_submenu_page( 'opg-scan', 'בקשות זכויות', 'בקשות זכויות' . $badge, self::CAP, 'opg-requests', array( __CLASS__, 'page_requests' ) );
		add_submenu_page( 'opg-scan', 'יומן הסכמות', 'יומן הסכמות', self::CAP, 'opg-consents', array( __CLASS__, 'page_consents' ) );
		add_submenu_page( 'opg-scan', 'יומן אבטחה', 'יומן אבטחה', self::CAP, 'opg-audit', array( __CLASS__, 'page_audit' ) );
		add_submenu_page( 'opg-scan', 'הגדרות', 'הגדרות', self::CAP, 'opg-settings', array( __CLASS__, 'page_settings' ) );
	}

	/**
	 * Assets on our pages.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'opg-' ) && 'index.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'opg-admin', OPG_URL . 'assets/css/admin.css', array(), OPG_VERSION );
		$deps = array();
		if ( false !== strpos( (string) $hook, 'opg-settings' ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			$deps = array( 'jquery', 'wp-color-picker' );
		}
		wp_enqueue_script( 'opg-admin', OPG_URL . 'assets/js/admin.js', $deps, OPG_VERSION, true );
	}

	/**
	 * After activation → run a scan and open the report.
	 */
	public static function activation_redirect() {
		if ( ! get_transient( 'opg_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'opg_activation_redirect' );
		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) || ! current_user_can( self::CAP ) ) { // phpcs:ignore
			return;
		}
		OPG_Scanner::run();
		wp_safe_redirect( admin_url( 'admin.php?page=opg-scan&welcome=1' ) );
		exit;
	}

	/**
	 * Admin notices (dashboard + plugins screen only).
	 */
	public static function notices() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}
		$overdue = OPG_Requests::count_overdue();
		if ( $overdue ) {
			printf( '<div class="notice notice-error"><p><strong>הגנת פרטיות:</strong> %d בקשות מימוש זכויות חרגו מ-30 יום. <a href="%s">לטיפול</a></p></div>', (int) $overdue, esc_url( admin_url( 'admin.php?page=opg-requests' ) ) );
		}
		$last = OPG_Scanner::last();
		if ( $last && $last['score'] < 70 ) {
			printf( '<div class="notice notice-warning"><p><strong>הגנת פרטיות:</strong> ציון הציות לאתר הוא %d/100 עם %d ממצאים קריטיים. <a href="%s">לדוח</a></p></div>', (int) $last['score'], (int) $last['counts']['fail'], esc_url( admin_url( 'admin.php?page=opg-scan' ) ) );
		}
	}

	/**
	 * Dashboard widget.
	 */
	public static function dashboard_widget() {
		if ( current_user_can( self::CAP ) ) {
			wp_add_dashboard_widget( 'opg_widget', 'הגנת פרטיות - תיקון 13', array( __CLASS__, 'render_widget' ) );
		}
	}

	public static function render_widget() {
		$last  = OPG_Scanner::last();
		$stats = OPG_Consent_Log::stats();
		echo '<div class="opg-widget">';
		if ( $last ) {
			echo self::gauge( $last['score'] ); // phpcs:ignore
			printf( '<p>%d תקין · %d לשיפור · <strong>%d קריטי</strong><br><small>נסרק: %s</small></p>', (int) $last['counts']['pass'], (int) $last['counts']['warn'], (int) $last['counts']['fail'], esc_html( wp_date( 'd/m/Y H:i', $last['time'] ) ) );
		} else {
			echo '<p>עדיין לא בוצעה סריקה.</p>';
		}
		printf( '<p>בקשות פתוחות: <strong>%d</strong> · הסכמות ב-30 יום: <strong>%d</strong></p>', (int) OPG_Requests::count_open(), (int) $stats['last30'] );
		printf( '<p><a class="button button-primary" href="%s">לדוח המלא</a></p>', esc_url( admin_url( 'admin.php?page=opg-scan' ) ) );
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Nonce'd admin-post URL.
	 *
	 * @param string $action Action.
	 * @param array  $args   Args.
	 * @return string
	 */
	public static function post_url( $action, array $args = array() ) {
		$args['action'] = 'opg_' . $action;
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'opg_' . $action );
	}

	/**
	 * Guard for admin-post handlers.
	 *
	 * @param string $action Action.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'אין הרשאה.' );
		}
		check_admin_referer( 'opg_' . $action );
	}

	/**
	 * Redirect back with a message.
	 *
	 * @param string $page Page slug.
	 * @param string $msg  Message key.
	 * @param array  $args Extra.
	 */
	private static function back( $page, $msg = '', array $args = array() ) {
		$args['page'] = $page;
		if ( $msg ) {
			$args['opg_msg'] = $msg;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Flash message.
	 */
	private static function flash() {
		$msgs = array(
			'scanned'  => 'הסריקה הושלמה.',
			'saved'    => 'ההגדרות נשמרו.',
			'created'  => 'נוצרה טיוטה. עברו עליה ופרסמו.',
			'updated'  => 'הבקשה עודכנה.',
			'core'     => 'נוצרה בקשה בכלי הפרטיות של וורדפרס - המשיכו משם לייצוא/מחיקה.',
			'reviewed' => 'הביקורת השנתית תועדה.',
			'imported' => 'ההגדרות יובאו.',
			'badjson'  => 'קובץ ההגדרות לא תקין.',
			'fixed'    => 'בוצע.',
			'report_sent'   => 'הדוח החודשי נשלח לכתובת שבהגדרות.',
			'rights_ok'     => 'עמוד בקשות הפרטיות נוצר ופורסם, ונוסף אליו קישור בסוף מדיניות הפרטיות ובשורת הקישורים בפוטר.',
			'rights_footer' => 'עמוד בקשות הפרטיות נוצר ופורסם ונוסף לשורת הקישורים בפוטר. את הקישור במדיניות לא הוספתי אוטומטית (המדיניות בנויה באלמנטור או לא מוגדרת) - הוסיפו אותו ידנית.',
			'error'    => 'אירעה שגיאה.',
		);
		$key  = isset( $_GET['opg_msg'] ) ? sanitize_key( wp_unslash( $_GET['opg_msg'] ) ) : ''; // phpcs:ignore
		if ( isset( $msgs[ $key ] ) ) {
			$type = in_array( $key, array( 'badjson', 'error' ), true ) ? 'error' : 'success';
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $msgs[ $key ] ) );
		}
	}

	/**
	 * Score gauge HTML.
	 *
	 * @param int $score Score.
	 * @return string
	 */
	private static function gauge( $score ) {
		$score = max( 0, min( 100, (int) $score ) );
		$cls   = $score >= 85 ? 'good' : ( $score >= 60 ? 'mid' : 'bad' );
		return '<div class="opg-gauge opg-' . $cls . '" style="--p:' . $score . '"><span>' . $score . '</span><small>/100</small></div>';
	}

	/**
	 * Page header.
	 *
	 * @param string $title Title.
	 */
	private static function header( $title ) {
		echo '<div class="wrap opg-wrap" dir="rtl"><h1 class="opg-title"><span class="dashicons dashicons-shield"></span> ' . esc_html( $title ) . '</h1>';
		self::flash();
	}

	/* ------------------------------------------------------------------
	 * Pages
	 * ------------------------------------------------------------------ */

	public static function page_scan() {
		$last = OPG_Scanner::last();
		self::header( 'דוח ציות לחוק הגנת הפרטיות' );

		if ( ! empty( $_GET['welcome'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-info"><p><strong>ברוכים הבאים!</strong> הסריקה הראשונה הושלמה. מומלץ להתחיל ב<a href="' . esc_url( admin_url( 'admin.php?page=opg-settings' ) ) . '">הגדרות</a> (פרטי עסק ומיפוי מידע), ואז להפיק את המסמכים ולהריץ סריקה חוזרת.</p></div>';
		}

		echo '<div class="opg-top">';
		if ( $last ) {
			echo self::gauge( $last['score'] ); // phpcs:ignore
			echo '<div class="opg-top-txt">';
			printf( '<p class="opg-counts"><span class="opg-b opg-b-pass">%d תקין</span> <span class="opg-b opg-b-warn">%d לשיפור</span> <span class="opg-b opg-b-fail">%d קריטי</span> <span class="opg-b opg-b-info">%d מידע</span></p>', (int) $last['counts']['pass'], (int) $last['counts']['warn'], (int) $last['counts']['fail'], (int) $last['counts']['info'] );
			$next = wp_next_scheduled( OPG_Plugin::CRON_REPORT );
			printf( '<p>סריקה אחרונה: %s · דוח חודשי במייל: %s</p>', esc_html( wp_date( 'd/m/Y H:i', $last['time'] ) ), OPG_Settings::get( 'weekly_scan' ) ? 'פעיל' . ( $next ? ' (הבא: ' . esc_html( wp_date( 'd/m/Y', $next ) ) . ')' : '' ) : 'כבוי' );
			if ( $last['home_error'] ) {
				printf( '<p class="opg-muted">לא ניתן היה לטעון את דף הבית לבדיקת קוד (%s) - חלק מהבדיקות מבוססות על התוספים בלבד.</p>', esc_html( $last['home_error'] ) );
			}
			echo '</div>';
		} else {
			echo '<p>עדיין לא בוצעה סריקה.</p>';
		}
		echo '<div class="opg-top-actions">';
		printf( '<a class="button button-primary button-hero" href="%s">סריקה עכשיו</a> ', esc_url( self::post_url( 'run_scan' ) ) );
		printf( '<a class="button" href="%s">ביקורת שנתית בוצעה</a> ', esc_url( self::post_url( 'mark_review' ) ) );
		printf( '<a class="button" href="%s">שליחת הדוח החודשי עכשיו</a> ', esc_url( self::post_url( 'quick_fix', array( 'fix' => 'report' ) ) ) );
		echo '<button type="button" class="button" onclick="window.print()">הדפסת הדוח</button>';
		echo '</div></div>';

		echo '<p class="opg-disclaimer">הדוח הוא כלי עזר טכני ואינו מהווה ייעוץ משפטי. הוא בודק את מה שאפשר לבדוק אוטומטית באתר; חלק מהחובות (הסכמים, הדרכות, החלטות) דורשות פעולה ותיעוד ידני.</p>';

		if ( ! $last ) {
			echo '</div>';
			return;
		}

		$filter = '<div class="opg-filter"><label><input type="checkbox" data-opg-hide-pass> הסתרת בדיקות תקינות</label></div>';
		echo $filter; // phpcs:ignore

		$by = array();
		foreach ( $last['checks'] as $c ) {
			$by[ $c['group'] ][] = $c;
		}
		$labels = array(
			'pass' => 'תקין',
			'warn' => 'לשיפור',
			'fail' => 'קריטי',
			'info' => 'מידע',
		);
		foreach ( OPG_Scanner::groups() as $gk => $gl ) {
			if ( empty( $by[ $gk ] ) ) {
				continue;
			}
			$fails = count( wp_list_filter( $by[ $gk ], array( 'status' => 'fail' ) ) );
			printf( '<h2 class="opg-group">%s %s</h2>', esc_html( $gl ), $fails ? '<span class="opg-b opg-b-fail">' . (int) $fails . '</span>' : '' );
			echo '<div class="opg-checks">';
			foreach ( $by[ $gk ] as $c ) {
				echo '<div class="opg-check opg-s-' . esc_attr( $c['status'] ) . '">';
				echo '<div class="opg-check-head"><span class="opg-dot"></span><strong>' . esc_html( $c['title'] ) . '</strong><span class="opg-b opg-b-' . esc_attr( $c['status'] ) . '">' . esc_html( $labels[ $c['status'] ] ) . '</span></div>';
				echo '<div class="opg-check-msg">' . wp_kses_post( $c['message'] ) . '</div>';
				if ( $c['fix'] && 'pass' !== $c['status'] ) {
					echo '<div class="opg-check-fix"><strong>מה לעשות:</strong> ' . wp_kses_post( $c['fix'] ) . '</div>';
				}
				if ( 'rights_page' === $c['id'] && 'pass' !== $c['status'] ) {
					printf( '<a class="button button-primary button-small" href="%s">יצירת עמוד, פרסום וקישור אוטומטי</a> ', esc_url( self::post_url( 'quick_fix', array( 'fix' => 'rights' ) ) ) );
					$c['action'] = array();
				}
				if ( ! empty( $c['action'] ) ) {
					printf( '<a class="button button-small" href="%s"%s>%s</a>', esc_url( $c['action'][1] ), 0 === strpos( $c['action'][1], 'http' ) && false === strpos( $c['action'][1], admin_url() ) ? ' target="_blank" rel="noopener"' : '', esc_html( $c['action'][0] ) );
				}
				if ( 'policy_linked' === $c['id'] && 'fail' === $c['status'] && ! OPG_Settings::get( 'auto_footer_links' ) ) {
					printf( ' <a class="button button-small" href="%s">הוספת שורת קישורי פרטיות לפוטר אוטומטית</a>', esc_url( self::post_url( 'quick_fix', array( 'fix' => 'footer' ) ) ) );
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	public static function page_documents() {
		self::header( 'מסמכים וטפסים לפי החוק' );
		echo '<p>כל המסמכים נבנים מנתוני האתר (הגדרות + הכלים והטפסים שזוהו בסריקה). <strong>מסמכים ציבוריים</strong> נוצרים כטיוטת עמוד - לא דורסים עמוד קיים. <strong>מסמכים פנימיים</strong> יורדים כקובץ Word לעריכה וחתימה, או נפתחים להדפסה/PDF.</p>';
		$docs = (array) get_option( 'opg_docs', array() );
		$cat  = OPG_Templates::catalogue();
		$s    = OPG_Settings::all();
		$ids  = array(
			'privacy_he'    => (int) get_option( 'wp_page_for_privacy_policy' ),
			'privacy_en'    => (int) $s['policy_page_en_id'],
			'cookie_policy' => (int) $s['cookie_page_id'],
			'rights_page'   => (int) $s['requests_page_id'],
		);
		foreach ( array( 'public' => 'מסמכים ציבוריים (עמודים באתר)', 'internal' => 'מסמכים פנימיים (לשמירה ולחתימה)' ) as $type => $label ) {
			echo '<h2>' . esc_html( $label ) . '</h2><table class="widefat striped opg-docs"><thead><tr><th>מסמך</th><th>תיאור</th><th>מצב</th><th>פעולות</th></tr></thead><tbody>';
			foreach ( $cat as $key => $d ) {
				if ( $d[1] !== $type ) {
					continue;
				}
				$state = isset( $docs[ $key ] ) ? 'הופק ' . wp_date( 'd/m/Y', $docs[ $key ] ) : 'לא הופק';
				echo '<tr><td><strong>' . esc_html( $d[0] ) . '</strong></td><td>' . esc_html( $d[2] ) . '</td><td>';
				if ( 'public' === $type && ! empty( $ids[ $key ] ) && get_post( $ids[ $key ] ) ) {
					$st = get_post_status( $ids[ $key ] );
					printf( '<a href="%s">%s</a> (%s)<br>', esc_url( get_edit_post_link( $ids[ $key ] ) ), esc_html( get_the_title( $ids[ $key ] ) ), 'publish' === $st ? 'מפורסם' : esc_html( $st ) );
				}
				echo esc_html( $state ) . '</td><td>';
				if ( 'rights_page' === $key ) {
					printf( '<a class="button button-primary" href="%s">יצירה, פרסום וקישור</a> ', esc_url( self::post_url( 'quick_fix', array( 'fix' => 'rights' ) ) ) );
				} elseif ( 'public' === $type ) {
					printf( '<a class="button button-primary" href="%s">יצירת טיוטה</a> ', esc_url( self::post_url( 'create_page', array( 'doc' => $key ) ) ) );
					printf( '<a class="button" target="_blank" href="%s">תצוגה</a>', esc_url( self::post_url( 'view_doc', array( 'doc' => $key ) ) ) );
				} else {
					printf( '<a class="button button-primary" href="%s">הורדה (Word)</a> ', esc_url( self::post_url( 'download_doc', array( 'doc' => $key ) ) ) );
					printf( '<a class="button" target="_blank" href="%s">הדפסה / PDF</a>', esc_url( self::post_url( 'view_doc', array( 'doc' => $key ) ) ) );
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2>שורטקודים</h2><table class="widefat striped"><tbody>';
		$codes = array(
			'[opg_rights_request]'  => 'טופס בקשות עיון / תיקון / מחיקה / הסרה מדיוור',
			'[opg_consent_box source="newsletter"]' => 'צ\'קבוקס הסכמה + הודעת איסוף לכל טופס מותאם אישית',
			'[opg_privacy_links]'   => 'שורת קישורים: מדיניות | Cookie | בקשות פרטיות (לפוטר)',
			'[opg_policy_link]'     => 'קישור למדיניות הפרטיות',
			'[opg_cookie_settings]' => 'קישור לפתיחה מחדש של הגדרות ה-Cookie',
		);
		foreach ( $codes as $code => $desc ) {
			echo '<tr><td><code>' . esc_html( $code ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
		}
		echo '</tbody></table><p class="opg-muted">להחרגת טופס מהזרקה אוטומטית: הוסיפו לו את המחלקה <code>opg-skip</code> (או <code>data-opg-skip</code>).</p></div>';
	}

	public static function page_requests() {
		self::header( 'בקשות מימוש זכויות' );
		$types    = OPG_Requests::types();
		$statuses = OPG_Requests::statuses();
		$view     = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore

		if ( $view ) {
			$r = OPG_Requests::get( $view );
			if ( ! $r ) {
				echo '<p>הבקשה לא נמצאה.</p></div>';
				return;
			}
			$due     = strtotime( $r['due_at'] . ' UTC' );
			$overdue = in_array( $r['status'], array( 'new', 'in_progress' ), true ) && $due < time();
			printf( '<p><a href="%s">→ לכל הבקשות</a></p>', esc_url( admin_url( 'admin.php?page=opg-requests' ) ) );
			echo '<div class="opg-card"><h2>בקשה #' . (int) $r['id'] . ' - ' . esc_html( isset( $types[ $r['type'] ] ) ? $types[ $r['type'] ] : $r['type'] ) . '</h2><table class="form-table">';
			$rows = array(
				'סטטוס'       => isset( $statuses[ $r['status'] ] ) ? $statuses[ $r['status'] ] : $r['status'],
				'שם'          => $r['name'],
				'דוא"ל'       => $r['email'],
				'טלפון'       => $r['phone'],
				'התקבלה'      => get_date_from_gmt( $r['created_at'], 'd/m/Y H:i' ),
				'אומתה'       => $r['verified_at'] ? get_date_from_gmt( $r['verified_at'], 'd/m/Y H:i' ) : 'לא',
				'מועד אחרון'  => get_date_from_gmt( $r['due_at'], 'd/m/Y' ) . ( $overdue ? ' - באיחור!' : '' ),
				'נסגרה'       => $r['closed_at'] ? get_date_from_gmt( $r['closed_at'], 'd/m/Y H:i' ) : '-',
			);
			foreach ( $rows as $k => $v ) {
				echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
			}
			echo '<tr><th>פרטים</th><td>' . nl2br( esc_html( (string) $r['details'] ) ) . '</td></tr></table>';

			$user = get_user_by( 'email', $r['email'] );
			echo '<h3>כלים</h3><p>';
			if ( $user ) {
				printf( 'קיים משתמש באתר: <a href="%s">%s</a>. ', esc_url( get_edit_user_link( $user->ID ) ), esc_html( $user->user_login ) );
			}
			printf( '<a class="button" href="%s">יצירת בקשת ייצוא מידע (וורדפרס)</a> ', esc_url( self::post_url( 'request_core', array( 'id' => $r['id'], 'what' => 'export' ) ) ) );
			printf( '<a class="button" href="%s">יצירת בקשת מחיקה (וורדפרס)</a> ', esc_url( self::post_url( 'request_core', array( 'id' => $r['id'], 'what' => 'erase' ) ) ) );
			printf( '<a class="button" href="%s">הסכמות של כתובת זו</a> ', esc_url( admin_url( 'admin.php?page=opg-consents&s=' . rawurlencode( $r['email'] ) ) ) );
			printf( '<a class="button" target="_blank" href="%s">נוסחי מענה</a>', esc_url( self::post_url( 'view_doc', array( 'doc' => 'response_letters' ) ) ) );
			echo '</p>';
			if ( class_exists( 'WooCommerce' ) ) {
				echo '<p class="opg-muted">הזמנות WooCommerce וחשבוניות חייבים להישמר לפי דיני המס - במחיקה, וורדפרס מתממת אותן לפי הגדרת "פרטיות" של WooCommerce.</p>';
			}

			echo '<h3>עדכון</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'opg_request_update' );
			echo '<input type="hidden" name="action" value="opg_request_update"><input type="hidden" name="id" value="' . (int) $r['id'] . '">';
			echo '<p><select name="status">';
			foreach ( $statuses as $k => $l ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $r['status'], $k, false ), esc_html( $l ) );
			}
			echo '</select></p><p><textarea name="note" rows="3" class="large-text" placeholder="תיעוד הטיפול / נימוק לסירוב"></textarea></p><p><button class="button button-primary">שמירה</button></p></form>';

			$notes = json_decode( (string) $r['notes'], true );
			if ( $notes ) {
				echo '<h3>היסטוריית טיפול</h3><ul class="opg-timeline">';
				foreach ( array_reverse( $notes ) as $n ) {
					printf( '<li><span>%s · %s</span> %s</li>', esc_html( get_date_from_gmt( $n['at'], 'd/m/Y H:i' ) ), esc_html( $n['author'] ), esc_html( $n['text'] ) );
				}
				echo '</ul>';
			}
			echo '</div></div>';
			return;
		}

		$filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore
		echo '<ul class="subsubsub"><li><a href="' . esc_url( admin_url( 'admin.php?page=opg-requests' ) ) . '"' . ( $filter ? '' : ' class="current"' ) . '>הכל</a></li>';
		foreach ( $statuses as $k => $l ) {
			echo ' | <li><a href="' . esc_url( admin_url( 'admin.php?page=opg-requests&status=' . $k ) ) . '"' . ( $filter === $k ? ' class="current"' : '' ) . '>' . esc_html( $l ) . '</a></li>';
		}
		echo '</ul><br class="clear">';

		$page = (int) OPG_Settings::get( 'requests_page_id' );
		if ( ! $page ) {
			printf( '<div class="notice notice-warning inline"><p>עדיין אין עמוד בקשות באתר. <a href="%s">צרו, פרסמו וקשרו אותו בלחיצה</a>.</p></div>', esc_url( self::post_url( 'quick_fix', array( 'fix' => 'rights' ) ) ) );
		}

		$rows = OPG_Requests::all( $filter );
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>התקבלה</th><th>סוג</th><th>שם / דוא"ל</th><th>סטטוס</th><th>מועד אחרון</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">אין בקשות.</td></tr>';
		}
		foreach ( $rows as $r ) {
			$due     = strtotime( $r['due_at'] . ' UTC' );
			$open    = in_array( $r['status'], array( 'new', 'in_progress' ), true );
			$days    = (int) ceil( ( $due - time() ) / DAY_IN_SECONDS );
			$due_txt = get_date_from_gmt( $r['due_at'], 'd/m/Y' ) . ( $open ? ( $days < 0 ? ' <span class="opg-b opg-b-fail">באיחור ' . abs( $days ) . ' ימים</span>' : ' <span class="opg-b opg-b-' . ( $days <= 7 ? 'warn' : 'info' ) . '">עוד ' . $days . ' ימים</span>' ) : '' );
			printf(
				'<tr><td><a href="%s">#%d</a></td><td>%s</td><td>%s</td><td><a href="%s">%s</a><br><small>%s</small></td><td>%s</td><td>%s</td></tr>',
				esc_url( admin_url( 'admin.php?page=opg-requests&view=' . $r['id'] ) ),
				(int) $r['id'],
				esc_html( get_date_from_gmt( $r['created_at'], 'd/m/Y' ) ),
				esc_html( isset( $types[ $r['type'] ] ) ? $types[ $r['type'] ] : $r['type'] ),
				esc_url( admin_url( 'admin.php?page=opg-requests&view=' . $r['id'] ) ),
				esc_html( $r['name'] ),
				esc_html( $r['email'] ),
				esc_html( isset( $statuses[ $r['status'] ] ) ? $statuses[ $r['status'] ] : $r['status'] ),
				wp_kses_post( $due_txt )
			);
		}
		echo '</tbody></table></div>';
	}

	public static function page_consents() {
		self::header( 'יומן הסכמות' );
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore
		$paged  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore
		$stats  = OPG_Consent_Log::stats();
		$labels = OPG_Consent_Log::source_labels();
		$q      = OPG_Consent_Log::query(
			array(
				'search' => $search,
				'source' => $source,
				'page'   => $paged,
			)
		);

		printf( '<p>סה"כ %d רשומות · %d ב-30 יום · %d כתובות עם הסכמה פעילה לשיווק. כל רשומה כוללת מועד, מקור, גרסת מדיניות, גיבוב של נוסח ההסכמה ו-IP מקוצר - <strong>זו ההוכחה שלך שההסכמה ניתנה</strong>.</p>', (int) $stats['total'], (int) $stats['last30'], (int) $stats['marketing'] );
		echo '<form method="get" class="opg-search"><input type="hidden" name="page" value="opg-consents">';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="חיפוש לפי דוא&quot;ל / טופס"> <select name="source"><option value="">כל המקורות</option>';
		foreach ( $stats['sources'] as $src ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $src ), selected( $source, $src, false ), esc_html( isset( $labels[ $src ] ) ? $labels[ $src ] : $src ) );
		}
		echo '</select> <button class="button">סינון</button> ';
		printf( '<a class="button" href="%s">ייצוא CSV</a></form>', esc_url( self::post_url( 'export_consents' ) ) );

		echo '<table class="widefat striped"><thead><tr><th>מועד</th><th>מקור</th><th>טופס</th><th>דוא"ל</th><th>מדיניות</th><th>שיווק</th><th>Cookie</th><th>גרסת מדיניות</th><th>IP</th></tr></thead><tbody>';
		if ( ! $q['rows'] ) {
			echo '<tr><td colspan="9">אין רשומות.</td></tr>';
		}
		foreach ( $q['rows'] as $r ) {
			$mk = $r['marketing'] ? ( $r['withdrawn_at'] ? 'בוטל' : '✔' ) : '-';
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( get_date_from_gmt( $r['created_at'], 'd/m/Y H:i' ) ),
				esc_html( isset( $labels[ $r['source'] ] ) ? $labels[ $r['source'] ] : $r['source'] ),
				esc_html( $r['form_ref'] ),
				esc_html( $r['email'] ? $r['email'] : ( $r['email_hash'] ? substr( $r['email_hash'], 0, 10 ) . '…' : '-' ) ),
				$r['privacy'] ? '✔' : '-',
				esc_html( $mk ),
				esc_html( $r['cookies'] ),
				esc_html( $r['policy_version'] ),
				esc_html( $r['ip'] )
			);
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $q['total'] / 50 );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo paginate_links( // phpcs:ignore
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			);
			echo '</div></div>';
		}
		echo '</div>';
	}

	public static function page_audit() {
		self::header( 'יומן אבטחה' );
		$event  = isset( $_GET['event'] ) ? sanitize_key( wp_unslash( $_GET['event'] ) ) : ''; // phpcs:ignore
		$labels = OPG_Audit_Log::labels();
		echo '<p>תיעוד גישה ושינויים לפי תקנות אבטחת מידע: התחברויות, ניסיונות כושלים, שינויי הרשאות, תוספים, ייצוא ומחיקת מידע. נשמר 24 חודשים.</p>';
		echo '<form method="get"><input type="hidden" name="page" value="opg-audit"><select name="event"><option value="">כל האירועים</option>';
		foreach ( $labels as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $event, $k, false ), esc_html( $l ) );
		}
		echo '</select> <button class="button">סינון</button></form><br>';
		$rows = OPG_Audit_Log::recent( 300, $event );
		echo '<table class="widefat striped"><thead><tr><th>מועד</th><th>אירוע</th><th>משתמש</th><th>אובייקט</th><th>פרטים</th><th>IP</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">אין אירועים.</td></tr>';
		}
		foreach ( $rows as $r ) {
			printf(
				'<tr class="%s"><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				'login_failed' === $r['event'] ? 'opg-row-warn' : '',
				esc_html( get_date_from_gmt( $r['created_at'], 'd/m/Y H:i:s' ) ),
				esc_html( isset( $labels[ $r['event'] ] ) ? $labels[ $r['event'] ] : $r['event'] ),
				esc_html( $r['user_login'] ),
				esc_html( $r['object'] ),
				esc_html( (string) $r['details'] ),
				esc_html( $r['ip'] )
			);
		}
		echo '</tbody></table></div>';
	}

	/* ------------------------------------------------------------------
	 * Settings page
	 * ------------------------------------------------------------------ */

	/**
	 * Field helpers.
	 */
	private static function f_text( $key, $label, $help = '', $type = 'text' ) {
		$v = OPG_Settings::get( $key );
		printf( '<tr><th><label for="opg-%1$s">%2$s</label></th><td><input type="%4$s" class="regular-text" id="opg-%1$s" name="opg[%1$s]" value="%3$s">%5$s</td></tr>', esc_attr( $key ), esc_html( $label ), esc_attr( (string) $v ), esc_attr( $type ), $help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : '' );
	}

	private static function f_area( $key, $label, $help = '', $rows = 3 ) {
		$v = OPG_Settings::get( $key );
		printf( '<tr><th><label for="opg-%1$s">%2$s</label></th><td><textarea class="large-text" rows="%5$d" id="opg-%1$s" name="opg[%1$s]">%3$s</textarea>%4$s</td></tr>', esc_attr( $key ), esc_html( $label ), esc_textarea( (string) $v ), $help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : '', (int) $rows );
	}

	private static function f_bool( $key, $label, $help = '' ) {
		$v = OPG_Settings::get( $key );
		printf( '<tr><th>%2$s</th><td><label><input type="checkbox" name="opg[%1$s]" value="1"%3$s> %4$s</label></td></tr>', esc_attr( $key ), esc_html( $label ), checked( (bool) $v, true, false ), $help ? wp_kses_post( $help ) : 'פעיל' );
	}

	private static function f_color( $key, $label, $help = '' ) {
		$v = OPG_Settings::get( $key );
		printf( '<tr><th><label for="opg-%1$s">%2$s</label></th><td><input type="text" class="opg-color" id="opg-%1$s" name="opg[%1$s]" value="%3$s" data-default-color="%4$s">%5$s</td></tr>', esc_attr( $key ), esc_html( $label ), esc_attr( (string) $v ), esc_attr( (string) OPG_Settings::defaults()[ $key ] ), $help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : '' );
	}

	private static function f_select( $key, $label, array $choices, $help = '' ) {
		$v = OPG_Settings::get( $key );
		echo '<tr><th>' . esc_html( $label ) . '</th><td><select name="opg[' . esc_attr( $key ) . ']">';
		foreach ( $choices as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $v, $k, false ), esc_html( $l ) );
		}
		echo '</select>' . ( $help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : '' ) . '</td></tr>';
	}

	private static function f_checks( $key, $label, array $choices ) {
		$v = (array) OPG_Settings::get( $key );
		echo '<tr><th>' . esc_html( $label ) . '</th><td><fieldset class="opg-checks-list">';
		foreach ( $choices as $k => $l ) {
			printf( '<label><input type="checkbox" name="opg[%s][]" value="%s"%s> %s</label><br>', esc_attr( $key ), esc_attr( $k ), checked( in_array( $k, $v, true ), true, false ), esc_html( $l ) );
		}
		echo '</fieldset></td></tr>';
	}

	public static function page_settings() {
		self::header( 'הגדרות הגנת פרטיות' );
		$tabs = array(
			'business' => 'פרטי העסק',
			'map'      => 'מיפוי מידע',
			'quest'    => 'שאלון רישום וממונה',
			'forms'    => 'טפסים והסכמה',
			'cookies'  => 'Cookie',
			'ops'      => 'תפעול',
			'transfer' => 'ייבוא / ייצוא',
		);
		echo '<nav class="nav-tab-wrapper opg-tabs">';
		foreach ( $tabs as $k => $l ) {
			echo '<a href="#tab-' . esc_attr( $k ) . '" class="nav-tab" data-opg-tab="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'opg_save_settings' );
		echo '<input type="hidden" name="action" value="opg_save_settings">';

		echo '<div class="opg-tab" id="tab-business"><table class="form-table">';
		self::f_text( 'business_name', 'שם העסק / בעל השליטה' );
		self::f_text( 'business_id', 'ח.פ. / ע.מ.' );
		self::f_text( 'business_address', 'כתובת' );
		self::f_text( 'contact_email', 'דוא"ל ליצירת קשר', '', 'email' );
		self::f_text( 'contact_phone', 'טלפון' );
		self::f_text( 'site_domain', 'שם האתר / דומיין' );
		self::f_text( 'officer_name', 'אחראי הגנת הפרטיות', 'גם כשאין חובה משפטית - מומלץ למנות אחראי.' );
		self::f_text( 'officer_email', 'דוא"ל האחראי', '', 'email' );
		self::f_bool( 'officer_is_dpo', 'ממונה רשמי (DPO)', 'האחראי מונה כממונה על הגנת הפרטיות לפי סעיף 17ב1' );
		echo '</table></div>';

		echo '<div class="opg-tab" id="tab-map"><table class="form-table">';
		self::f_checks( 'data_types', 'סוגי מידע שנאספים', OPG_Settings::data_type_labels() );
		self::f_checks( 'purposes', 'מטרות השימוש', OPG_Settings::purpose_labels() );
		self::f_bool( 'direct_marketing', 'דיוור ישיר', 'האתר שולח דיוור / הודעות שיווקיות (מפעיל צ\'קבוקס הסכמה נפרד לשיווק)' );
		self::f_bool( 'sensitive_data', 'מידע רגיש', 'נאסף מידע בעל רגישות מיוחדת (בריאות, אמונות, ביומטרי, מצב כלכלי וכו\')' );
		self::f_area( 'retention_text', 'מדיניות שמירה' );
		self::f_bool( 'transfer_abroad', 'העברה לחו"ל', 'מידע נשמר אצל ספקים מחוץ לישראל' );
		self::f_area( 'abroad_text', 'פירוט העברה לחו"ל' );
		self::f_area( 'third_parties_manual', 'ספקים נוספים (ידני)', 'שורה לכל ספק: <code>שם|מטרה|סוג מידע|מדינה</code> - למשל <code>רווחית|הנהלת חשבונות|שם, ח.פ., חשבוניות|ישראל</code>', 5 );
		echo '</table></div>';

		echo '<div class="opg-tab" id="tab-quest"><p>השאלון קובע אם חלה חובת רישום / הודעה / מינוי ממונה. אם אינכם בטוחים - התייעצו עם עו"ד.</p><table class="form-table">';
		self::f_select(
			'q_subjects',
			'מספר נושאי מידע (אנשים) במאגר',
			array(
				'lt10k'    => 'פחות מ-10,000',
				'10k-100k' => '10,000 - 100,000',
				'gt100k'   => 'יותר מ-100,000',
			)
		);
		self::f_bool( 'q_data_broker', 'סחר במידע', 'עיקר מטרת המאגר היא מסירת מידע לאחרים כדרך עיסוק או בתמורה (כולל שירותי דיוור ישיר לאחרים)' );
		self::f_bool( 'q_public_body', 'גוף ציבורי', 'בעל השליטה הוא גוף ציבורי' );
		self::f_bool( 'q_monitoring', 'ניטור שיטתי', 'עיסוק מרכזי הכולל ניטור שוטף ושיטתי של אנשים בהיקף ניכר (למשל פרסום ממוקד, מעקב מיקום)' );
		echo '</table>';
		$ob = OPG_Scanner::obligations();
		echo '<div class="opg-card"><h3>תוצאה לפי ההגדרות השמורות</h3><ul class="opg-ul">';
		echo '<li>חובת רישום: <strong>' . ( $ob['register'] ? 'כן' : 'לא' ) . '</strong></li><li>חובת הודעה לרשות: <strong>' . ( $ob['notify'] ? 'כן' : 'לא' ) . '</strong></li><li>חובת מינוי ממונה: <strong>' . ( $ob['dpo'] ? 'ככל הנראה כן' : 'לא' ) . '</strong></li>';
		foreach ( $ob['reasons'] as $r ) {
			echo '<li>' . esc_html( $r ) . '</li>';
		}
		echo '</ul></div></div>';

		echo '<div class="opg-tab" id="tab-forms"><table class="form-table">';
		self::f_bool( 'consent_wc_checkout', 'WooCommerce - קופה', 'צ\'קבוקס בקופה (רגילה ובלוקים) + שמירה בהזמנה' );
		self::f_bool( 'consent_wc_register', 'WooCommerce - הרשמה' );
		self::f_bool( 'consent_wp_register', 'הרשמה לוורדפרס' );
		self::f_bool( 'consent_comments', 'תגובות וביקורות' );
		self::f_bool( 'consent_cf7', 'Contact Form 7' );
		self::f_bool( 'consent_elementor', 'Elementor Pro Forms' );
		self::f_bool( 'consent_wpforms', 'WPForms' );
		self::f_bool( 'consent_gravity', 'Gravity Forms' );
		self::f_bool( 'consent_fluent', 'Fluent Forms' );
		self::f_bool( 'consent_universal', 'שכבה כללית (JS)', 'כל טופס אחר עם שדה דוא"ל/טלפון יקבל צ\'קבוקס בדפדפן (טפסי חיפוש והתחברות מוחרגים)' );
		self::f_bool( 'show_collection_notice', 'הודעת איסוף', 'להציג הודעה קצרה מעל הצ\'קבוקס (סעיף 11)' );
		self::f_bool( 'show_marketing_box', 'צ\'קבוקס שיווק', 'צ\'קבוקס נפרד, לא מסומן מראש, להסכמה לדיוור (מוצג רק כש"דיוור ישיר" פעיל)' );
		self::f_area( 'consent_text_privacy', 'נוסח צ\'קבוקס המדיניות', '<code>{privacy_link}</code> = קישור למדיניות, <code>{business}</code> = שם העסק', 2 );
		self::f_area( 'consent_text_marketing', 'נוסח צ\'קבוקס השיווק', '', 2 );
		self::f_area( 'collection_notice', 'נוסח הודעת האיסוף', '', 3 );
		self::f_text( 'consent_error', 'הודעת שגיאה' );
		self::f_select(
			'log_email_mode',
			'שמירת דוא"ל ביומן',
			array(
				'full' => 'כתובת מלאה + גיבוב (מומלץ - הוכחה מלאה)',
				'hash' => 'גיבוב בלבד (צמצום מידע)',
			)
		);
		self::f_text( 'log_retention_months', 'שמירת יומן (חודשים)', 'ברירת מחדל 84 (7 שנים - תקופת התיישנות). מינימום 12.', 'number' );
		echo '</table></div>';

		echo '<div class="opg-tab" id="tab-cookies"><table class="form-table">';
		$other = OPG_Detector::other_cookie_plugin();
		self::f_select(
			'cookie_banner',
			'באנר Cookie',
			array(
				'auto' => 'אוטומטי - פעיל רק אם אין תוסף Cookie אחר' . ( $other ? ' (זוהה: ' . $other . ' → כבוי)' : '' ),
				'on'   => 'תמיד פעיל',
				'off'  => 'כבוי',
			)
		);
		self::f_select(
			'cookie_position',
			'מיקום',
			array(
				'bottom' => 'פס תחתון',
				'top'    => 'פס עליון',
				'box'    => 'חלונית בפינה',
			)
		);
		self::f_area( 'cookie_text', 'נוסח הבאנר', '', 3 );
		echo '<tr><th colspan="2"><h3 style="margin:10px 0 0">כפתור הגדרות ה-Cookie (הכפתור הצף)</h3></th></tr>';
		self::f_select(
			'fab_position',
			'מיקום',
			array(
				'bottom-left'  => 'למטה משמאל',
				'bottom-right' => 'למטה מימין',
				'top-left'     => 'למעלה משמאל',
				'top-right'    => 'למעלה מימין',
				'middle-left'  => 'באמצע משמאל',
				'middle-right' => 'באמצע מימין',
				'hidden'       => 'מוסתר (אז חובה קישור [opg_cookie_settings] או שורת הקישורים בפוטר)',
			),
			'בחרו צד שלא מתנגש עם כפתור וואטסאפ / צ\'אט / חזרה למעלה.'
		);
		self::f_text( 'fab_offset_x', 'מרחק מהצד (px)', '', 'number' );
		self::f_text( 'fab_offset_y', 'מרחק מלמעלה / מלמטה (px)', 'לא חל במיקום "באמצע".', 'number' );
		self::f_text( 'fab_size', 'גודל (px)', 'בין 24 ל-80.', 'number' );
		self::f_color( 'fab_bg', 'צבע רקע', 'אפשר לבחור בבוחר, או להקליד קוד כמו #ff6600 / ff6600 / rgb(255,102,0).' );
		self::f_color( 'fab_color', 'צבע האייקון' );
		self::f_color( 'fab_border', 'צבע מסגרת', 'ריק = בלי מסגרת.' );
		echo '<tr><th>תצוגה מקדימה</th><td><div class="opg-fab-preview" id="opg-fab-preview"><span class="opg-fab-demo" id="opg-fab-demo"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5zm-3.5 7a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm2 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm5-1a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg></span></div></td></tr>';
		self::f_bool( 'consent_mode_v2', 'Google Consent Mode v2', 'ברירת מחדל "נדחה" לגוגל אנליטיקס/אדס עד אישור; שולח <code>fbq(\'consent\')</code> ל-Meta ואירוע <code>opg_consent</code> ל-dataLayer' );
		echo '</table><p class="opg-muted">להשהיית סקריפט עד הסכמה: <code>&lt;script type="text/plain" data-opg-category="analytics"&gt;…&lt;/script&gt;</code> (קטגוריות: analytics, marketing, functional).</p></div>';

		echo '<div class="opg-tab" id="tab-ops"><table class="form-table">';
		self::f_text( 'requests_notify', 'דוא"ל להתראות על בקשות', '', 'email' );
		self::f_text( 'requests_due_days', 'ימים למענה', 'לפי החוק - עד 30 יום.', 'number' );
		self::f_bool( 'weekly_scan', 'דוח חודשי במייל', 'פעם בחודש (ב-1 לחודש): ציון, ממצאים, הסכמות, בקשות וניסיונות התחברות. אין מיילים על כל שינוי - חוץ מחריג אחד: בקשת זכויות שחרגה מ-30 יום שולחת התראה מיידית (פעם אחת לכל בקשה) לכתובת ההתראות על בקשות. הסריקה רצה בשקט פעם בשבוע.' );
		self::f_text( 'scan_email', 'דוא"ל לדוח החודשי', '', 'email' );
		self::f_bool( 'audit_log', 'יומן אבטחה', 'תיעוד התחברויות, ניסיונות כושלים, הרשאות, תוספים, ייצוא ומחיקה' );
		self::f_bool( 'auto_footer_links', 'קישורי פרטיות בפוטר', 'שורת קישורים אוטומטית בתחתית כל עמוד לכל העמודים שהתוסף הפיק ופורסמו (מדיניות פרטיות, Privacy Policy, מדיניות Cookie, בקשות פרטיות) + הגדרות Cookie. אם הוספתם את <code>[opg_privacy_links]</code> לפוטר של התבנית - השורה האוטומטית לא תוצג פעמיים.' );
		echo '</table></div>';

		echo '<p class="submit opg-save"><button type="submit" class="button button-primary button-hero">שמירת הגדרות</button></p></form>';

		echo '<div class="opg-tab" id="tab-transfer"><h2>העתקת הגדרות בין האתרים</h2><p>ייצאו את ההגדרות מאתר אחד וייבאו באתר אחר (עמודים ופרטים ייחודיים לאתר לא מועתקים).</p>';
		printf( '<p><a class="button" href="%s">ייצוא הגדרות (JSON)</a></p>', esc_url( self::post_url( 'export_settings' ) ) );
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'opg_import_settings' );
		echo '<input type="hidden" name="action" value="opg_import_settings"><input type="file" name="opg_file" accept=".json,application/json"> <button class="button">ייבוא</button></form></div>';
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	 * Actions
	 * ------------------------------------------------------------------ */

	public static function action_run_scan() {
		self::guard( 'run_scan' );
		OPG_Scanner::run();
		self::back( 'opg-scan', 'scanned' );
	}

	public static function action_save_settings() {
		self::guard( 'save_settings' );
		$raw = isset( $_POST['opg'] ) && is_array( $_POST['opg'] ) ? wp_unslash( $_POST['opg'] ) : array(); // phpcs:ignore
		// Keep values that are not on the form (page ids etc.).
		$keep = array( 'requests_page_id', 'policy_page_en_id', 'cookie_page_id', 'last_review' );
		foreach ( $keep as $k ) {
			unset( $raw[ $k ] );
		}
		OPG_Settings::update( $raw, false );
		OPG_Audit_Log::log( 'settings_saved', 'opg_settings' );
		self::back( 'opg-settings', 'saved' );
	}

	public static function action_create_page() {
		self::guard( 'create_page' );
		$doc = isset( $_GET['doc'] ) ? sanitize_key( wp_unslash( $_GET['doc'] ) ) : '';
		$id  = OPG_Generator::create_page( $doc );
		if ( is_wp_error( $id ) ) {
			self::back( 'opg-documents', 'error' );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $id . '&action=edit' ) );
		exit;
	}

	public static function action_download_doc() {
		self::guard( 'download_doc' );
		$doc = isset( $_GET['doc'] ) ? sanitize_key( wp_unslash( $_GET['doc'] ) ) : '';
		if ( ! isset( OPG_Templates::catalogue()[ $doc ] ) ) {
			self::back( 'opg-documents', 'error' );
		}
		OPG_Generator::download( $doc );
	}

	public static function action_view_doc() {
		self::guard( 'view_doc' );
		$doc = isset( $_GET['doc'] ) ? sanitize_key( wp_unslash( $_GET['doc'] ) ) : '';
		if ( ! isset( OPG_Templates::catalogue()[ $doc ] ) ) {
			self::back( 'opg-documents', 'error' );
		}
		OPG_Generator::view( $doc );
	}

	public static function action_export_consents() {
		self::guard( 'export_consents' );
		OPG_Consent_Log::export_csv();
	}

	public static function action_request_update() {
		self::guard( 'request_update' );
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$note   = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		OPG_Requests::admin_update( $id, $status, $note );
		self::back( 'opg-requests', 'updated', array( 'view' => $id ) );
	}

	public static function action_request_core() {
		self::guard( 'request_core' );
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$what = isset( $_GET['what'] ) && 'erase' === $_GET['what'] ? 'erase' : 'export';
		$res  = OPG_Requests::create_core_request( $id, $what );
		if ( is_wp_error( $res ) ) {
			self::back( 'opg-requests', 'error', array( 'view' => $id ) );
		}
		wp_safe_redirect( admin_url( 'erase' === $what ? 'erase-personal-data.php' : 'export-personal-data.php' ) );
		exit;
	}

	public static function action_mark_review() {
		self::guard( 'mark_review' );
		OPG_Settings::update( array( 'last_review' => gmdate( 'Y-m-d' ) ) );
		OPG_Audit_Log::log( 'settings_saved', 'annual review' );
		OPG_Scanner::run();
		self::back( 'opg-scan', 'reviewed' );
	}

	public static function action_export_settings() {
		self::guard( 'export_settings' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ofnoa-privacy-guard-settings-' . gmdate( 'Y-m-d' ) . '.json' );
		echo OPG_Settings::export_json(); // phpcs:ignore
		exit;
	}

	public static function action_import_settings() {
		self::guard( 'import_settings' );
		if ( empty( $_FILES['opg_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['opg_file']['tmp_name'] ) ) { // phpcs:ignore
			self::back( 'opg-settings', 'badjson' );
		}
		$json = file_get_contents( $_FILES['opg_file']['tmp_name'] ); // phpcs:ignore
		self::back( 'opg-settings', OPG_Settings::import_json( (string) $json ) ? 'imported' : 'badjson' );
	}

	public static function action_quick_fix() {
		self::guard( 'quick_fix' );
		$fix = isset( $_GET['fix'] ) ? sanitize_key( wp_unslash( $_GET['fix'] ) ) : '';
		$msg = 'fixed';
		if ( 'footer' === $fix ) {
			OPG_Settings::update( array( 'auto_footer_links' => 1 ) );
		}
		if ( 'report' === $fix ) {
			self::back( 'opg-scan', OPG_Plugin::run_monthly_report( true ) ? 'report_sent' : 'error' );
		}
		if ( 'rights' === $fix ) {
			$res = OPG_Generator::setup_rights_page();
			if ( is_wp_error( $res ) ) {
				self::back( 'opg-scan', 'error' );
			}
			OPG_Settings::update( array( 'auto_footer_links' => 1 ) );
			$msg = $res['policy_linked'] ? 'rights_ok' : 'rights_footer';
		}
		delete_transient( 'opg_fetch_' . md5( home_url( '/' ) ) );
		OPG_Scanner::run();
		self::back( 'opg-scan', $msg );
	}
}
