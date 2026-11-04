<?php
/**
 * Data subject rights: access / correction / deletion / unsubscribe requests.
 *
 * Flow: public form [opg_rights_request] → email verification link → owner notified →
 * handled in admin with a 30-day deadline, notes and a documented outcome.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Requests {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'opg_rights_request', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'handle_submit' ) );
		add_action( 'init', array( __CLASS__, 'handle_verify' ) );
	}

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'opg_requests';
	}

	/**
	 * Request types.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'access'      => 'עיון במידע שנשמר עליי',
			'correct'     => 'תיקון מידע שגוי, חסר או לא מעודכן',
			'delete'      => 'מחיקת המידע שלי',
			'unsubscribe' => 'הסרה מרשימת דיוור / ביטול הסכמה לשיווק',
			'object'      => 'התנגדות לשימוש מסוים במידע',
			'other'       => 'אחר',
		);
	}

	/**
	 * Status labels.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'pending'     => 'ממתין לאימות דוא"ל',
			'new'         => 'חדש - מאומת',
			'in_progress' => 'בטיפול',
			'done'        => 'טופל',
			'rejected'    => 'נדחה (עם נימוק)',
		);
	}

	/**
	 * Public form.
	 *
	 * @return string
	 */
	public static function shortcode() {
		$state = isset( $_GET['opg_req'] ) ? sanitize_key( wp_unslash( $_GET['opg_req'] ) ) : ''; // phpcs:ignore
		$msgs  = array(
			'sent'     => array( 'ok', 'הבקשה התקבלה. שלחנו אליך הודעת דוא"ל עם קישור לאימות - הבקשה תטופל לאחר האימות.' ),
			'verified' => array( 'ok', 'כתובת הדוא"ל אומתה והבקשה הועברה לטיפול. נשיב לך בתוך 30 יום לכל היותר.' ),
			'done'     => array( 'ok', 'ההסרה בוצעה - לא יישלח אליך עוד דיוור שיווקי.' ),
			'invalid'  => array( 'err', 'הקישור אינו תקף או שפג תוקפו. אפשר להגיש בקשה חדשה.' ),
			'error'    => array( 'err', 'יש למלא שם, כתובת דוא"ל תקינה וסוג בקשה, ולאשר את מדיניות הפרטיות.' ),
			'rate'     => array( 'err', 'נשלחו יותר מדי בקשות. נסו שוב מאוחר יותר.' ),
		);

		ob_start();
		echo '<div class="opg-rights">';
		if ( isset( $msgs[ $state ] ) ) {
			printf( '<div class="opg-msg opg-msg-%s" role="status">%s</div>', esc_attr( $msgs[ $state ][0] ), esc_html( $msgs[ $state ][1] ) );
		}
		if ( in_array( $state, array( 'sent', 'verified', 'done' ), true ) ) {
			echo '</div>';
			return ob_get_clean();
		}
		?>
		<form method="post" class="opg-rights-form" data-opg-skip="1">
			<?php wp_nonce_field( 'opg_rights', 'opg_rights_nonce' ); ?>
			<input type="hidden" name="opg_rights_submit" value="1">
			<input type="hidden" name="opg_ts" value="<?php echo esc_attr( (string) time() ); ?>">
			<div class="opg-hp" aria-hidden="true"><label>Website <input type="text" name="opg_website" tabindex="-1" autocomplete="off"></label></div>
			<p><label>שם מלא *<br><input type="text" name="opg_name" required maxlength="190"></label></p>
			<p><label>דוא"ל *<br><input type="email" name="opg_email" required maxlength="190"></label></p>
			<p><label>טלפון (לא חובה)<br><input type="tel" name="opg_phone" maxlength="60"></label></p>
			<p><label>סוג הבקשה *<br>
				<select name="opg_type" required>
					<option value="">בחרו…</option>
					<?php foreach ( self::types() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label>פרטי הבקשה (למשל: איזה מידע לתקן ומה הנוסח הנכון)<br><textarea name="opg_details" rows="5" maxlength="5000"></textarea></label></p>
			<?php echo OPG_Consent::box_html( 'rights', array( 'notice' => false ) ); // phpcs:ignore ?>
			<p class="opg-small">לצורך זיהוי, נשלח קישור אימות לכתובת הדוא"ל שהזנתם. ייתכן שנבקש אמצעי זיהוי נוסף לפני מסירת מידע.</p>
			<p><button type="submit" class="opg-btn">שליחת הבקשה</button></p>
		</form>
		<?php
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Redirect back to the form page with a state.
	 *
	 * @param string $state State.
	 */
	private static function back( $state ) {
		$ref = wp_get_referer();
		$url = $ref ? $ref : home_url( '/' );
		$url = remove_query_arg( array( 'opg_req' ), $url );
		wp_safe_redirect( add_query_arg( 'opg_req', $state, $url ) );
		exit;
	}

	/**
	 * Handle the public form POST.
	 */
	public static function handle_submit() {
		if ( empty( $_POST['opg_rights_submit'] ) ) { // phpcs:ignore
			return;
		}
		if ( ! isset( $_POST['opg_rights_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['opg_rights_nonce'] ) ), 'opg_rights' ) ) {
			self::back( 'invalid' );
		}
		// Honeypot + minimum fill time.
		$ts = isset( $_POST['opg_ts'] ) ? (int) $_POST['opg_ts'] : 0;
		if ( ! empty( $_POST['opg_website'] ) || ( time() - $ts ) < 3 ) {
			self::back( 'sent' ); // Pretend success to bots.
		}
		$rl_key = 'opg_rr_' . md5( OPG_Plugin::client_ip( false ) );
		$count  = (int) get_transient( $rl_key );
		if ( $count >= 5 ) {
			self::back( 'rate' );
		}
		set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

		$name    = isset( $_POST['opg_name'] ) ? sanitize_text_field( wp_unslash( $_POST['opg_name'] ) ) : '';
		$email   = isset( $_POST['opg_email'] ) ? sanitize_email( wp_unslash( $_POST['opg_email'] ) ) : '';
		$phone   = isset( $_POST['opg_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['opg_phone'] ) ) : '';
		$type    = isset( $_POST['opg_type'] ) ? sanitize_key( wp_unslash( $_POST['opg_type'] ) ) : '';
		$details = isset( $_POST['opg_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['opg_details'] ) ) : '';

		if ( ! $name || ! is_email( $email ) || ! isset( self::types()[ $type ] ) || ! OPG_Consent::posted() ) {
			self::back( 'error' );
		}

		global $wpdb;
		$token = wp_generate_password( 32, false, false );
		$days  = max( 1, (int) OPG_Settings::get( 'requests_due_days', 30 ) );
		$wpdb->insert( // phpcs:ignore
			self::table(),
			array(
				'created_at' => current_time( 'mysql', true ),
				'type'       => $type,
				'status'     => 'pending',
				'name'       => $name,
				'email'      => $email,
				'phone'      => $phone,
				'details'    => $details,
				'due_at'     => gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ),
				'token'      => $token,
				'ip'         => OPG_Plugin::client_ip( true ),
				'notes'      => wp_json_encode( array() ),
			)
		);
		$id = (int) $wpdb->insert_id;

		OPG_Consent::log( 'rights', 'request #' . $id, $email, false );

		$verify = add_query_arg(
			array(
				'opg_verify' => $token,
				'rid'        => $id,
			),
			wp_get_referer() ? remove_query_arg( 'opg_req', wp_get_referer() ) : home_url( '/' )
		);
		$site   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$body   = "שלום {$name},\n\nהתקבלה באתר {$site} בקשה בנושא: " . self::types()[ $type ] . ".\n\nכדי לאמת שהבקשה נשלחה על ידך, יש ללחוץ על הקישור:\n{$verify}\n\nאם לא שלחת את הבקשה - אפשר להתעלם מהודעה זו.\n\n{$site}";
		wp_mail( $email, "[{$site}] אימות בקשה בנושא פרטיות", $body );

		self::back( 'sent' );
	}

	/**
	 * Handle the verification link.
	 */
	public static function handle_verify() {
		if ( empty( $_GET['opg_verify'] ) || empty( $_GET['rid'] ) ) { // phpcs:ignore
			return;
		}
		global $wpdb;
		$token = sanitize_text_field( wp_unslash( $_GET['opg_verify'] ) ); // phpcs:ignore
		$id    = absint( $_GET['rid'] ); // phpcs:ignore
		$row   = self::get( $id );
		$back  = remove_query_arg( array( 'opg_verify', 'rid', 'opg_req' ) );

		if ( ! $row || ! hash_equals( (string) $row['token'], $token ) || strtotime( $row['created_at'] . ' UTC' ) < time() - 7 * DAY_IN_SECONDS ) {
			wp_safe_redirect( add_query_arg( 'opg_req', 'invalid', $back ) );
			exit;
		}
		$state = 'verified';
		if ( 'pending' === $row['status'] ) {
			$update = array(
				'status'      => 'new',
				'verified_at' => current_time( 'mysql', true ),
				'token'       => '',
			);
			if ( 'unsubscribe' === $row['type'] ) {
				OPG_Consent_Log::withdraw_marketing( $row['email'] );
				$update['status']    = 'done';
				$update['closed_at'] = current_time( 'mysql', true );
				$state               = 'done';
			}
			$wpdb->update( self::table(), $update, array( 'id' => $id ) ); // phpcs:ignore
			self::add_note( $id, 'system', 'unsubscribe' === $row['type'] ? 'אומת - ההסכמה לשיווק בוטלה אוטומטית.' : 'כתובת הדוא"ל אומתה.' );
			self::notify_owner( self::get( $id ) );
		}
		wp_safe_redirect( add_query_arg( 'opg_req', $state, $back ) );
		exit;
	}

	/**
	 * Email the owner about a verified request.
	 *
	 * @param array $row Request.
	 */
	public static function notify_owner( $row ) {
		$to = OPG_Settings::get( 'requests_notify' );
		if ( ! $row || ! is_email( $to ) ) {
			return;
		}
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$types = self::types();
		$body  = sprintf(
			"בקשת מימוש זכויות חדשה (#%d)\n\nסוג: %s\nשם: %s\nדוא\"ל: %s\nטלפון: %s\nפרטים:\n%s\n\nמועד אחרון למענה: %s\n\nלטיפול: %s",
			$row['id'],
			isset( $types[ $row['type'] ] ) ? $types[ $row['type'] ] : $row['type'],
			$row['name'],
			$row['email'],
			$row['phone'],
			$row['details'],
			get_date_from_gmt( $row['due_at'], 'd/m/Y' ),
			admin_url( 'admin.php?page=opg-requests&view=' . (int) $row['id'] )
		);
		wp_mail( $to, "[{$site}] בקשת פרטיות חדשה #{$row['id']}", $body );
	}

	/**
	 * Get a request.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
		return $row ? $row : null;
	}

	/**
	 * List requests.
	 *
	 * @param string $status Status filter.
	 * @return array
	 */
	public static function all( $status = '' ) {
		global $wpdb;
		if ( $status ) {
			return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE status = %s ORDER BY id DESC LIMIT 500', $status ), ARRAY_A ); // phpcs:ignore
		}
		return (array) $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT 500', ARRAY_A ); // phpcs:ignore
	}

	/**
	 * Open (verified, not closed) requests.
	 *
	 * @return int
	 */
	public static function count_open() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE status IN ('new','in_progress')" ); // phpcs:ignore
	}

	/**
	 * Requests past their due date.
	 *
	 * @return int
	 */
	public static function count_overdue() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE status IN ('new','in_progress') AND due_at < %s", current_time( 'mysql', true ) ) ); // phpcs:ignore
	}

	/**
	 * Append a note.
	 *
	 * @param int    $id     ID.
	 * @param string $author Author.
	 * @param string $text   Text.
	 */
	public static function add_note( $id, $author, $text ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row ) {
			return;
		}
		$notes   = json_decode( (string) $row['notes'], true );
		$notes   = is_array( $notes ) ? $notes : array();
		$notes[] = array(
			'at'     => current_time( 'mysql', true ),
			'author' => $author,
			'text'   => $text,
		);
		$wpdb->update( self::table(), array( 'notes' => wp_json_encode( $notes ) ), array( 'id' => $id ) ); // phpcs:ignore
	}

	/**
	 * Admin update.
	 *
	 * @param int    $id     ID.
	 * @param string $status Status.
	 * @param string $note   Note.
	 */
	public static function admin_update( $id, $status, $note ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row ) {
			return;
		}
		$data = array();
		if ( $status && isset( self::statuses()[ $status ] ) && $status !== $row['status'] ) {
			$data['status'] = $status;
			if ( in_array( $status, array( 'done', 'rejected' ), true ) ) {
				$data['closed_at'] = current_time( 'mysql', true );
			}
		}
		if ( $data ) {
			$wpdb->update( self::table(), $data, array( 'id' => $id ) ); // phpcs:ignore
		}
		$user = wp_get_current_user();
		if ( $note || $data ) {
			$text = $note;
			if ( isset( $data['status'] ) ) {
				$text = 'סטטוס: ' . self::statuses()[ $data['status'] ] . ( $note ? ' | ' . $note : '' );
			}
			self::add_note( $id, $user->user_login, $text );
		}
		OPG_Audit_Log::log( 'request_updated', 'request #' . $id, isset( $data['status'] ) ? $data['status'] : 'note' );
	}

	/**
	 * Create a WP core privacy request (export / erase) so the built-in tools can run.
	 *
	 * @param int    $id     Our request.
	 * @param string $action export|erase.
	 * @return int|WP_Error
	 */
	public static function create_core_request( $id, $action ) {
		$row = self::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'opg_missing', 'Request not found' );
		}
		$name   = 'erase' === $action ? 'remove_personal_data' : 'export_personal_data';
		$result = wp_create_user_request( $row['email'], $name, array(), 'request-confirmed' );
		if ( ! is_wp_error( $result ) ) {
			self::add_note( $id, wp_get_current_user()->user_login, 'נוצרה בקשת ' . ( 'erase' === $action ? 'מחיקה' : 'ייצוא' ) . ' בכלי הפרטיות של וורדפרס (#' . (int) $result . ').' );
		}
		return $result;
	}
}
