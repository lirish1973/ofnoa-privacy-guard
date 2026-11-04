<?php
/**
 * Document templates (Hebrew + English privacy policy, and internal compliance documents).
 *
 * IMPORTANT: the texts are a professional starting point built from the site's real data,
 * not legal advice. Every document is marked for review by the site owner / a lawyer.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Templates {

	/**
	 * Escape helper.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	private static function e( $s ) {
		return esc_html( (string) $s );
	}

	/**
	 * <ul> from a list.
	 *
	 * @param array $items Items (already safe HTML or plain).
	 * @param bool  $raw   Items are HTML.
	 * @return string
	 */
	private static function ul( array $items, $raw = false ) {
		if ( ! $items ) {
			return '';
		}
		$out = '<ul>';
		foreach ( $items as $i ) {
			$out .= '<li>' . ( $raw ? $i : self::e( $i ) ) . '</li>';
		}
		return $out . '</ul>';
	}

	/**
	 * Third-party table.
	 *
	 * @param array $third Third parties.
	 * @param bool  $en    English headers.
	 * @return string
	 */
	private static function vendor_table( array $third, $en = false ) {
		if ( ! $third ) {
			return $en ? '<p>We do not currently share personal data with third-party tools beyond our hosting provider.</p>' : '<p>נכון למועד עדכון המדיניות, איננו משתפים מידע אישי עם כלים חיצוניים מלבד ספק האחסון של האתר.</p>';
		}
		$cats = array(
			'analytics'  => $en ? 'Analytics' : 'מדידה וניתוח',
			'marketing'  => $en ? 'Marketing' : 'שיווק ופרסום',
			'functional' => $en ? 'Functionality' : 'פונקציונליות',
			'necessary'  => $en ? 'Security / necessary' : 'אבטחה / חיוני',
			'service'    => $en ? 'Service provider' : 'ספק שירות',
		);
		$h    = $en ? array( 'Provider', 'Purpose', 'Data', 'Location' ) : array( 'ספק / כלי', 'מטרה', 'סוג המידע', 'מיקום המידע' );
		$out  = '<table class="opg-table"><thead><tr><th>' . implode( '</th><th>', $h ) . '</th></tr></thead><tbody>';
		foreach ( $third as $t ) {
			$purpose = ! empty( $t['purpose'] ) ? $t['purpose'] : ( isset( $cats[ $t['category'] ] ) ? $cats[ $t['category'] ] : '' );
			$out    .= '<tr><td>' . self::e( $t['name'] ) . '</td><td>' . self::e( $purpose ) . '</td><td>' . self::e( $t['data'] ) . '</td><td>' . self::e( $t['location'] ) . '</td></tr>';
		}
		return $out . '</tbody></table>';
	}

	/**
	 * Review banner placed at the top of generated drafts.
	 *
	 * @return string
	 */
	public static function review_note() {
		return '<!-- opg-review-note --><p style="background:#fff8e1;border:1px solid #f0c36d;padding:10px;border-radius:6px"><strong>טיוטה שהופקה אוטומטית על ידי Ofnoa Privacy Guard.</strong> יש לעבור על הנוסח, להשלים פרטים ולהתאים למציאות העסק לפני פרסום. מומלץ בדיקה של עורך/ת דין. מחקו פסקה זו לפני הפרסום.</p><!-- /opg-review-note -->';
	}

	/* ==================================================================
	 * Privacy policy - Hebrew
	 * ================================================================== */

	/**
	 * @param array $c Context.
	 * @return string
	 */
	public static function privacy_policy_he( array $c ) {
		$o = array();

		$o[] = '<p><strong>עודכן לאחרונה: ' . self::e( $c['date'] ) . '</strong></p>';
		$o[] = '<p>' . self::e( $c['name'] ) . ( $c['id'] ? ' (' . self::e( $c['id'] ) . ')' : '' ) . ' ("<strong>אנחנו</strong>", "<strong>העסק</strong>") מכבד את פרטיות המשתמשים באתר ' . self::e( $c['domain'] ) . ' ("<strong>האתר</strong>"). מדיניות זו מסבירה איזה מידע אנו אוספים, לאיזו מטרה, עם מי הוא משותף, כמה זמן הוא נשמר, ומהן זכויותיך. המדיניות נכתבה בהתאם לחוק הגנת הפרטיות, התשמ"א-1981 ולתקנות שהותקנו מכוחו, לרבות תיקון מס\' 13 לחוק. השימוש בלשון זכר או נקבה הוא לנוחות בלבד ומתייחס לכל המגדרים.</p>';

		$o[] = '<h2>1. מי אחראי על המידע</h2>';
		$who = array( 'בעל השליטה במאגר: ' . $c['name'] . ( $c['id'] ? ', ' . $c['id'] : '' ) );
		if ( $c['address'] ) {
			$who[] = 'כתובת: ' . $c['address'];
		}
		$who[] = 'דוא"ל: ' . $c['email'];
		if ( $c['phone'] ) {
			$who[] = 'טלפון: ' . $c['phone'];
		}
		if ( $c['officer_name'] ) {
			$who[] = ( $c['officer_is_dpo'] ? 'הממונה על הגנת הפרטיות: ' : 'האחראי על נושא הפרטיות: ' ) . $c['officer_name'] . ' - ' . $c['officer_email'];
		}
		$o[] = self::ul( $who );

		$o[] = '<h2>2. איזה מידע אנו אוספים</h2>';
		$o[] = '<p>אנו אוספים רק מידע הנחוץ למטרות המפורטות במדיניות זו:</p>';
		$o[] = self::ul( $c['data_types'] );
		$o[] = '<p><strong>מקורות המידע:</strong> מידע שמסרת לנו בעצמך (בטפסים, בהזמנה, בהרשמה, בצ\'אט או בפנייה), מידע שנאסף אוטומטית בעת השימוש באתר (באמצעות קובצי Cookie וטכנולוגיות דומות), ומידע שמתקבל מספקי השירות שלנו (למשל אישור תשלום מחברת הסליקה).</p>';
		if ( $c['sensitive'] ) {
			$o[] = '<p><strong>מידע בעל רגישות מיוחדת:</strong> במסגרת השירות אנו עשויים לעבד מידע בעל רגישות מיוחדת. מידע זה נאסף רק בהסכמתך המפורשת או כשהדבר נדרש לשם מתן השירות, ומוגן ברמת אבטחה מוגברת.</p>';
		}
		$o[] = '<p><strong>אינך חייב/ת על פי חוק למסור לנו מידע.</strong> מסירת המידע תלויה ברצונך ובהסכמתך, אך ללא פרטים מסוימים (המסומנים כשדות חובה) לא נוכל לספק את השירות, לבצע הזמנה או להשיב לפנייה.</p>';

		$o[] = '<h2>3. מטרות השימוש במידע</h2>';
		$o[] = '<p>אנו משתמשים במידע אך ורק למטרות שלשמן נמסר, ובכלל זה:</p>';
		$o[] = self::ul( $c['purposes'] );
		$o[] = '<p>לא נשתמש במידע למטרה שונה מהמטרה שלשמה נמסר, אלא בהסכמתך או כשהדבר מותר או נדרש על פי דין. כתובת דוא"ל שנמסרה לצורך הזמנה או משלוח חשבונית <strong>לא</strong> תשמש לדיוור שיווקי ללא הסכמה נפרדת.</p>';

		$o[] = '<h2>4. דיוור ישיר ופרסומות</h2>';
		if ( $c['marketing'] ) {
			$o[] = '<p>נשלח אליך עדכונים, מבצעים ודברי פרסומת (בדוא"ל, SMS או WhatsApp) רק אם סימנת הסכמה מפורשת לכך. ההסכמה אינה תנאי לקבלת השירות ואינה מסומנת מראש. בכל הודעה שיווקית יופיע קישור הסרה פעיל, ובאפשרותך לבטל את ההסכמה בכל עת גם באמצעות <a href="' . esc_url( $c['requests_url'] ) . '">טופס הבקשות</a> או בפנייה אלינו בדוא"ל. הסרה תבוצע בהקדם ולא יאוחר מהמועד הקבוע בדין. אנו פועלים בהתאם לסעיף 30א לחוק התקשורת (בזק ושידורים) ולסעיף 17ו לחוק הגנת הפרטיות.</p>';
		} else {
			$o[] = '<p>איננו שולחים דיוור שיווקי. אם נתחיל לעשות זאת בעתיד - הדבר ייעשה רק בהסכמתך המפורשת ומדיניות זו תעודכן.</p>';
		}

		$o[] = '<h2>5. עם מי אנו משתפים את המידע</h2>';
		$o[] = '<p>איננו מוכרים מידע אישי. אנו מעבירים מידע רק לספקי שירות הפועלים מטעמנו ולצורך המטרות שלעיל, בהיקף הנדרש בלבד ובכפוף להתחייבות לסודיות ולאבטחת מידע:</p>';
		$o[] = self::vendor_table( $c['third'] );
		$o[] = '<p>בנוסף, נמסור מידע אם נידרש לכך על פי דין או צו שיפוטי, לשם הגנה על זכויותינו בהליך משפטי, או במסגרת מיזוג או העברת פעילות - ובלבד שהגוף המקבל יתחייב לפעול לפי מדיניות זו.</p>';

		$o[] = '<h2>6. העברת מידע אל מחוץ לישראל</h2>';
		if ( $c['abroad'] ) {
			$o[] = '<p>' . self::e( $c['abroad_text'] ) . ' העברת המידע נעשית בהתאם לתקנות הגנת הפרטיות (העברת מידע אל מאגרי מידע שמחוץ לגבולות המדינה), התשס"א-2001, ולספקים המחויבים לרמת הגנה נאותה.</p>';
		} else {
			$o[] = '<p>המידע נשמר בשרתים בישראל ואינו מועבר אל מחוץ לישראל.</p>';
		}

		$o[] = '<h2>7. קובצי Cookie וטכנולוגיות מעקב</h2>';
		$o[] = '<p>האתר משתמש בקובצי Cookie: <strong>חיוניים</strong> (נדרשים להפעלת האתר, עגלת קניות והתחברות - פועלים תמיד), <strong>סטטיסטיקה</strong> (מדידת שימוש ושיפור האתר) ו<strong>שיווק</strong> (פרסום מותאם ומדידת קמפיינים). Cookie שאינם חיוניים מופעלים רק לאחר שאישרת זאת בבאנר ה-Cookie. ניתן לשנות את הבחירה בכל עת דרך הקישור "הגדרות Cookie" שבתחתית האתר, או לחסום Cookie בהגדרות הדפדפן.' . ( $c['cookie_url'] ? ' פירוט נוסף ב<a href="' . esc_url( $c['cookie_url'] ) . '">מדיניות ה-Cookie</a>.' : '' ) . '</p>';

		$o[] = '<h2>8. משך שמירת המידע</h2>';
		$o[] = '<p>' . self::e( $c['retention'] ) . '</p>';
		$o[] = self::ul(
			array(
				'פניות וטפסי יצירת קשר - עד 24 חודשים מהטיפול האחרון בפנייה, אלא אם נוצרה התקשרות.',
				'הזמנות, חשבוניות ורשומות כספיות - 7 שנים, כנדרש בדיני המס.',
				'הסכמות לדיוור ותיעוד הסכמות - כל עוד ההסכמה בתוקף ולתקופת ההתיישנות לאחר ביטולה, לצורך הוכחה.',
				'נתוני סטטיסטיקה - לפי הגדרות כלי המדידה (בדרך כלל עד 14 חודשים).',
				'לוגים טכניים ואבטחה - עד 24 חודשים.',
			)
		);
		$o[] = '<p>בתום התקופה המידע יימחק או יעבור התממה כך שלא ניתן יהיה לזהות אותך.</p>';

		$o[] = '<h2>9. אבטחת המידע</h2>';
		$o[] = '<p>אנו נוקטים אמצעים מקובלים להגנה על המידע, בהתאם לתקנות הגנת הפרטיות (אבטחת מידע), התשע"ז-2017: הצפנת התקשורת (HTTPS/TLS), בקרת גישה לפי הרשאה מזערית, אימות דו-שלבי לגישה מנהלית, עדכוני אבטחה שוטפים, גיבויים, תיעוד אירועי גישה, והתקשרות רק עם ספקים המחויבים לאבטחת מידע. פרטי כרטיס אשראי אינם נשמרים באתר ומעובדים ישירות אצל ספק הסליקה. עם זאת, אין מערכת חסינה לחלוטין. במקרה של אירוע אבטחה חמור נפעל כנדרש בדין, לרבות דיווח לרשות להגנת הפרטיות והודעה לנושאי המידע כשהדבר נדרש.</p>';

		$o[] = '<h2>10. הזכויות שלך</h2>';
		$o[] = self::ul(
			array(
				'<strong>זכות עיון</strong> - לעיין במידע שנשמר עליך במאגר (סעיף 13 לחוק).',
				'<strong>זכות לתיקון ומחיקה</strong> - לבקש לתקן או למחוק מידע שאינו נכון, שלם, ברור או מעודכן (סעיף 14 לחוק).',
				'<strong>הסרה מדיוור</strong> - לבקש בכל עת להפסיק לקבל דיוור שיווקי ולהימחק ממאגר הדיוור (סעיף 17ו לחוק).',
				'<strong>ביטול הסכמה</strong> - לבטל הסכמה שנתת, בלי שהדבר יפגע בחוקיות השימוש שנעשה לפני הביטול.',
			),
			true
		);
		$o[] = '<p><strong>איך מממשים:</strong> באמצעות <a href="' . esc_url( $c['requests_url'] ) . '">טופס בקשות הפרטיות</a> באתר, או בדוא"ל אל ' . self::e( $c['officer_email'] ? $c['officer_email'] : $c['email'] ) . '. לצורך הגנה על פרטיותך נבקש לאמת את זהותך לפני מסירת מידע. נשיב לבקשה בתוך 30 יום. אם נסרב לבקשה - נודיע על כך בכתב ובצירוף נימוק, ותוכל/י לפנות לבית המשפט או לרשות להגנת הפרטיות.</p>';

		$o[] = '<h2>11. קטינים</h2>';
		$o[] = '<p>האתר אינו מיועד לילדים מתחת לגיל 18 שלא בהסכמת הורה. אם נודע לנו שנאסף מידע על קטין ללא הסכמה - נמחק אותו.</p>';

		$o[] = '<h2>12. שינויים במדיניות</h2>';
		$o[] = '<p>נעדכן מדיניות זו בכל שינוי מהותי באיסוף המידע, בשימוש בו, בספקים או בטכנולוגיה. מועד העדכון האחרון מופיע בראש העמוד. במקרה של שינוי מהותי נודיע על כך באתר או בדוא"ל.</p>';

		$o[] = '<h2>13. יצירת קשר</h2>';
		$o[] = '<p>בכל שאלה בנושא פרטיות: ' . self::e( $c['officer_email'] ? $c['officer_email'] : $c['email'] ) . ( $c['phone'] ? ' | ' . self::e( $c['phone'] ) : '' ) . ( $c['address'] ? ' | ' . self::e( $c['address'] ) : '' ) . '. ניתן גם לפנות לרשות להגנת הפרטיות במשרד המשפטים.</p>';

		return implode( "\n", $o );
	}

	/* ==================================================================
	 * Privacy policy - English
	 * ================================================================== */

	/**
	 * @param array $c Context.
	 * @return string
	 */
	public static function privacy_policy_en( array $c ) {
		$o   = array();
		$o[] = '<div dir="ltr" style="text-align:left">';
		$o[] = '<p><strong>Last updated: ' . self::e( $c['date'] ) . '</strong></p>';
		$o[] = '<p>' . self::e( $c['name'] ) . ' ("we") respects the privacy of users of ' . self::e( $c['domain'] ) . '. This policy explains what we collect, why, who receives it, how long we keep it and what your rights are, in accordance with the Israeli Protection of Privacy Law, 5741-1981 (including Amendment 13) and its regulations.</p>';
		$o[] = '<h2>1. Controller</h2>' . self::ul( array_filter( array( $c['name'] . ( $c['id'] ? ', ' . $c['id'] : '' ), $c['address'], 'Email: ' . $c['email'], $c['officer_name'] ? 'Privacy contact: ' . $c['officer_name'] . ' - ' . $c['officer_email'] : '' ) ) );
		$o[] = '<h2>2. Data we collect</h2>' . self::ul( $c['data_types_en'] );
		$o[] = '<p>Sources: information you give us (forms, orders, registration, chat), information collected automatically (cookies and similar technologies) and information from our service providers (e.g. payment confirmation). <strong>You are not legally required to provide personal data</strong>; without required fields we cannot provide the service.</p>';
		$o[] = '<h2>3. Purposes</h2>' . self::ul( $c['purposes_en'] );
		$o[] = '<p>We do not use data for a purpose other than the one it was given for without your consent. An email given for an order or invoice is not used for marketing without separate consent.</p>';
		$o[] = '<h2>4. Direct marketing</h2><p>' . ( $c['marketing'] ? 'We send marketing messages only with your explicit, separate, opt-in consent. Every message contains a working unsubscribe link and you may withdraw consent at any time.' : 'We do not send marketing messages.' ) . '</p>';
		$o[] = '<h2>5. Who receives the data</h2><p>We never sell personal data. We share it only with service providers acting on our behalf, under confidentiality and security obligations:</p>' . self::vendor_table( $c['third'], true );
		$o[] = '<h2>6. International transfers</h2><p>' . ( $c['abroad'] ? 'Some providers store data outside Israel (including the US and the EU), in accordance with the Privacy Protection (Transfer of Data to Databases Abroad) Regulations, 2001.' : 'Data is stored in Israel and is not transferred abroad.' ) . '</p>';
		$o[] = '<h2>7. Cookies</h2><p>Necessary cookies always run. Analytics and marketing cookies run only after you accept them in the cookie banner; you can change your choice at any time via "Cookie settings" at the bottom of the site.</p>';
		$o[] = '<h2>8. Retention</h2><p>We keep data only as long as needed for its purpose and as required by law (tax records - 7 years; contact inquiries - up to 24 months; consent records - while valid and for the limitation period). Then it is deleted or anonymized.</p>';
		$o[] = '<h2>9. Security</h2><p>HTTPS/TLS, least-privilege access control, multi-factor authentication for administrators, updates, backups, access logs and vetted vendors, in line with the Privacy Protection (Data Security) Regulations, 2017. Card details are processed by the payment provider and not stored on the site.</p>';
		$o[] = '<h2>10. Your rights</h2><p>You may access your data, request correction or deletion of data that is inaccurate, incomplete, unclear or outdated, unsubscribe from marketing and withdraw consent. Use our <a href="' . esc_url( $c['requests_url'] ) . '">privacy request form</a> or email ' . self::e( $c['officer_email'] ? $c['officer_email'] : $c['email'] ) . '. We verify your identity and respond within 30 days.</p>';
		$o[] = '<h2>11. Changes and contact</h2><p>We update this policy on any material change. Questions: ' . self::e( $c['officer_email'] ? $c['officer_email'] : $c['email'] ) . '.</p>';
		$o[] = '</div>';
		return implode( "\n", $o );
	}

	/* ==================================================================
	 * Cookie policy
	 * ================================================================== */

	/**
	 * @param array $c Context.
	 * @return string
	 */
	public static function cookie_policy( array $c ) {
		$groups = array(
			'necessary'  => 'חיוניים',
			'functional' => 'פונקציונליים',
			'analytics'  => 'סטטיסטיקה',
			'marketing'  => 'שיווק',
		);
		$o      = array();
		$o[]    = '<p><strong>עודכן לאחרונה: ' . self::e( $c['date'] ) . '</strong></p>';
		$o[]    = '<p>קובץ Cookie הוא קובץ טקסט קטן שנשמר בדפדפן בעת הביקור באתר. מסמך זה משלים את <a href="' . esc_url( $c['policy_url'] ) . '">מדיניות הפרטיות</a>.</p>';
		$o[]    = '<h2>סוגי ה-Cookie באתר</h2>';
		$o[]    = self::ul(
			array(
				'<strong>חיוניים</strong> - התחברות, עגלת קניות, אבטחה ושמירת בחירת ה-Cookie שלך. פועלים תמיד.',
				'<strong>פונקציונליים</strong> - סרטונים מוטמעים, מפות, צ\'אט והעדפות תצוגה.',
				'<strong>סטטיסטיקה</strong> - מדידת שימוש אנונימית לשיפור האתר. מופעלים רק באישורך.',
				'<strong>שיווק</strong> - פרסום מותאם ומדידת קמפיינים. מופעלים רק באישורך.',
			),
			true
		);
		$rows = array();
		foreach ( $c['third'] as $t ) {
			if ( in_array( $t['category'], array( 'analytics', 'marketing', 'functional', 'necessary' ), true ) ) {
				$rows[] = '<tr><td>' . self::e( $t['name'] ) . '</td><td>' . self::e( $groups[ $t['category'] ] ) . '</td><td>' . self::e( $t['data'] ) . '</td><td>' . self::e( $t['location'] ) . '</td></tr>';
			}
		}
		$rows[] = '<tr><td>' . self::e( $c['domain'] ) . ' (opg_consent)</td><td>חיוניים</td><td>שמירת בחירת ה-Cookie</td><td>הדפדפן שלך, 180 יום</td></tr>';
		$o[]    = '<h2>הכלים שבשימוש</h2><table class="opg-table"><thead><tr><th>כלי</th><th>קטגוריה</th><th>מידע</th><th>מיקום / תוקף</th></tr></thead><tbody>' . implode( '', $rows ) . '</tbody></table>';
		$o[]    = '<h2>איך משנים את הבחירה</h2><p>[opg_cookie_settings text="לחצו כאן לשינוי הגדרות ה-Cookie"] - או מחקו Cookie בהגדרות הדפדפן.</p>';
		return implode( "\n", $o );
	}

	/* ==================================================================
	 * Rights request page
	 * ================================================================== */

	/**
	 * @param array $c Context.
	 * @return string
	 */
	public static function rights_page( array $c ) {
		return '<p>לפי חוק הגנת הפרטיות, באפשרותך לעיין במידע שנשמר עליך, לבקש לתקן או למחוק מידע שגוי, חסר, לא ברור או לא מעודכן, לבקש הסרה מרשימת הדיוור ולבטל הסכמה שנתת.</p>'
			. '<p>מלאו את הטופס. נשלח קישור לאימות כתובת הדוא"ל, ולאחר האימות הבקשה תטופל בתוך 30 יום לכל היותר. בקשת הסרה מדיוור מבוצעת אוטומטית מיד לאחר האימות.</p>'
			. "\n[opg_rights_request]\n"
			. '<p>אפשר לפנות גם בדוא"ל: ' . self::e( $c['officer_email'] ? $c['officer_email'] : $c['email'] ) . '. פרטים נוספים ב<a href="' . esc_url( $c['policy_url'] ) . '">מדיניות הפרטיות</a>.</p>';
	}

	/* ==================================================================
	 * Internal documents
	 * ================================================================== */

	/**
	 * Header block for internal documents.
	 *
	 * @param string $title Title.
	 * @param array  $c     Context.
	 * @return string
	 */
	private static function doc_head( $title, array $c ) {
		return '<h1>' . self::e( $title ) . '</h1>'
			. '<table class="opg-meta"><tr><td>בעל השליטה במאגר</td><td>' . self::e( $c['name'] ) . ( $c['id'] ? ' - ' . self::e( $c['id'] ) : '' ) . '</td></tr>'
			. '<tr><td>אתר</td><td>' . self::e( $c['site_url'] ) . '</td></tr>'
			. '<tr><td>תאריך הפקה</td><td>' . self::e( $c['date'] ) . '</td></tr>'
			. '<tr><td>אחראי</td><td>' . self::e( $c['officer_name'] ? $c['officer_name'] : '____________' ) . '</td></tr>'
			. '<tr><td>תאריך סקירה הבא</td><td>' . self::e( $c['next_review'] ) . '</td></tr></table>'
			. '<p class="opg-warn">מסמך פנימי - אין לפרסם. הופק אוטומטית מנתוני האתר; שדות ____ יש להשלים ידנית. מומלץ אישור עו"ד / יועץ אבטחת מידע.</p>';
	}

	/**
	 * Signature block.
	 *
	 * @return string
	 */
	private static function signature() {
		return '<p style="margin-top:40px">אושר על ידי: ____________________ &nbsp; תפקיד: ______________ &nbsp; חתימה: ______________ &nbsp; תאריך: ____________</p>';
	}

	/**
	 * Data map / database definitions - shared table of the databases on the site.
	 *
	 * @param array $c Context.
	 * @return array[]
	 */
	private static function databases( array $c ) {
		$dbs = array();
		$dbs[] = array( 'פניות ולידים', 'טפסי יצירת קשר, צ\'אט, טלפון', 'שם, טלפון, דוא"ל, תוכן הפנייה', 'מענה לפניות ושירות לקוחות', 'הסכמה / צעדים לקראת התקשרות', '24 חודשים' );
		if ( $c['has_woo'] ) {
			$dbs[] = array( 'לקוחות והזמנות', 'קופה, חשבון לקוח', 'שם, כתובת, טלפון, דוא"ל, מוצרים, סכומים', 'ביצוע הזמנות, משלוח, חשבוניות, שירות', 'ביצוע חוזה, חובה חוקית (מס)', '7 שנים' );
		}
		if ( $c['users_can_register'] || $c['has_woo'] ) {
			$dbs[] = array( 'משתמשים רשומים', 'הרשמה לאתר', 'שם משתמש, דוא"ל, סיסמה מוצפנת, היסטוריית פעילות', 'ניהול חשבון', 'ביצוע חוזה', 'עד מחיקת החשבון + 24 חודשים' );
		}
		if ( $c['marketing'] ) {
			$dbs[] = array( 'דיוור ישיר', 'צ\'קבוקס הסכמה בטפסים', 'שם, דוא"ל, טלפון, תיעוד הסכמה, פעילות דיוור', 'שליחת דיוור שיווקי', 'הסכמה מפורשת', 'עד ביטול ההסכמה + תקופת התיישנות' );
		}
		$dbs[] = array( 'יומן הסכמות ובקשות פרטיות', 'התוסף Ofnoa Privacy Guard', 'דוא"ל, IP מקוצר, מועד וסוג הסכמה, בקשות עיון/תיקון/מחיקה', 'הוכחת הסכמה ועמידה בחובות החוק', 'חובה חוקית / אינטרס לגיטימי', (string) $c['log_months'] . ' חודשים' );
		$dbs[] = array( 'לוגים ואבטחה', 'שרת, תוסף אבטחה, יומן אבטחה', 'IP, משתמש, פעולה, זמן', 'אבטחת מידע, איתור תקלות ותקיפות', 'חובה חוקית (תקנות אבטחת מידע)', '24 חודשים' );
		if ( $c['analytics'] ) {
			$dbs[] = array( 'סטטיסטיקה ושיווק', 'Cookie וכלי מדידה', 'מזהי Cookie, נתוני גלישה', 'מדידה, שיפור ופרסום', 'הסכמה (באנר Cookie)', 'לפי הגדרות הכלי (עד 14 חודשים)' );
		}
		return $dbs;
	}

	/**
	 * @param array $c Context.
	 * @return string
	 */
	public static function data_map( array $c ) {
		$o   = array( self::doc_head( 'מיפוי מאגרי מידע וזרימת מידע', $c ) );
		$o[] = '<h2>1. מאגרים ומקורות איסוף</h2><table class="opg-table"><thead><tr><th>מאגר</th><th>מקור</th><th>סוגי מידע</th><th>מטרה</th><th>בסיס לשימוש</th><th>משך שמירה</th></tr></thead><tbody>';
		foreach ( self::databases( $c ) as $d ) {
			$o[] = '<tr><td>' . implode( '</td><td>', array_map( array( __CLASS__, 'e' ), $d ) ) . '</td></tr>';
		}
		$o[] = '</tbody></table>';
		$o[] = '<h2>2. טפסים שזוהו באתר</h2>';
		$forms = array();
		foreach ( $c['forms'] as $f ) {
			$forms[] = $f[0] . ( $f[1] ? ' (' . $f[1] . ')' : '' ) . ( $f[2] ? ' - הסכמה נאכפת בשרת' : ' - כיסוי JS' );
		}
		$o[] = self::ul( $forms ? $forms : array( 'לא זוהו תוספי טפסים' ) );
		$o[] = '<h2>3. ספקים ומעבדי מידע (צד שלישי)</h2>' . self::vendor_table( $c['third'] );
		$o[] = '<h2>4. נתונים כמותיים וסיווג</h2>';
		$o[] = self::ul(
			array(
				'מספר נושאי מידע משוער: ' . $c['subjects_label'],
				'מידע בעל רגישות מיוחדת: ' . ( $c['sensitive'] ? 'כן' : 'לא' ),
				'משתמשים רשומים באתר כעת: ' . $c['user_count'],
				'מי ניגש למידע: מנהלי האתר (' . $c['admin_count'] . ' מנהלים), ספקי השירות המפורטים לעיל, ____________',
				'חובת רישום: ' . ( $c['obligations']['register'] ? 'כן - יש להגיש בקשת רישום' : 'לא' ) . ' | חובת הודעה: ' . ( $c['obligations']['notify'] ? 'כן' : 'לא' ) . ' | חובת מינוי ממונה: ' . ( $c['obligations']['dpo'] ? 'ככל הנראה כן' : 'לא לפי השאלון' ),
			)
		);
		$o[] = '<h2>5. נוהל עדכון המיפוי</h2><p>המיפוי יעודכן בכל הוספה או הסרה של מערכת, תוסף, ספק, מטרה או סוג מידע, ולפחות פעם בשנה במסגרת הביקורת השנתית. התוסף מריץ סריקה שבועית ומתריע בדוא"ל על שינויים בציון.</p>';
		$o[] = self::signature();
		return implode( "\n", $o );
	}

	/**
	 * Database definitions document (Data Security Regulations 2017, reg. 2).
	 *
	 * @param array $c Context.
	 * @return string
	 */
	public static function db_definitions( array $c ) {
		$o   = array( self::doc_head( 'מסמך הגדרות מאגר - תקנה 2 לתקנות הגנת הפרטיות (אבטחת מידע), התשע"ז-2017', $c ) );
		$o[] = '<h2>1. תיאור כללי של פעילות האיסוף והשימוש</h2><p>' . self::e( $c['name'] ) . ' מפעיל את האתר ' . self::e( $c['site_url'] ) . '. במסגרת פעילותו נאסף מידע אישי מגולשים, לקוחות ופונים, לצורך: ' . self::e( implode( '; ', $c['purposes'] ) ) . '.</p>';
		$o[] = '<h2>2. מטרות השימוש במידע</h2>' . self::ul( $c['purposes'] );
		$o[] = '<h2>3. סוגי המידע הכלולים במאגר</h2>' . self::ul( $c['data_types'] );
		$o[] = '<h2>4. פרטים על העברת המידע</h2><p>העברה לספקים: ראו טבלה. העברה לחו"ל: ' . ( $c['abroad'] ? 'כן - ' . self::e( $c['abroad_text'] ) : 'לא' ) . '.</p>' . self::vendor_table( $c['third'] );
		$o[] = '<h2>5. עיבוד מידע באמצעות מחזיק (ספק)</h2><p>ספקי השירות בטבלה מעבדים מידע מטעם בעל השליטה. עם כל ספק נדרש הסכם הכולל את הנושאים בתקנה 15 (נספח אבטחת מידע לספקים).</p>';
		$o[] = '<h2>6. מאגרי המידע ותשתיות</h2><table class="opg-table"><thead><tr><th>מאגר</th><th>מקור</th><th>סוגי מידע</th><th>מטרה</th><th>בסיס</th><th>שמירה</th></tr></thead><tbody>';
		foreach ( self::databases( $c ) as $d ) {
			$o[] = '<tr><td>' . implode( '</td><td>', array_map( array( __CLASS__, 'e' ), $d ) ) . '</td></tr>';
		}
		$o[] = '</tbody></table>';
		$o[] = self::ul(
			array(
				'מערכת: WordPress ' . $c['wp_version'] . ', PHP ' . PHP_VERSION . ( $c['has_woo'] ? ', WooCommerce' : '' ),
				'שרת / אחסון: ____________ (שם ספק האחסון, מיקום השרתים)',
				'מסד נתונים: MySQL/MariaDB בשרת האחסון, טבלאות עם קידומת ' . $c['db_prefix'],
				'ממשקים חיצוניים: ראו טבלת ספקים',
				'גיבויים: ' . ( $c['backup'] ? $c['backup'] : '____________' ) . ' | מיקום הגיבויים: ____________',
				'הגנה היקפית: ' . ( $c['security'] ? $c['security'] : '____________' ) . ', HTTPS: ' . ( $c['https'] ? 'כן' : 'לא' ),
			)
		);
		$o[] = '<h2>7. רמת האבטחה החלה על המאגר</h2><p>לפי התוספת הראשונה לתקנות: ____________ (בסיסית / בינונית / גבוהה). הערכה ראשונית: ' . self::e( $c['security_level'] ) . '. ההחלטה הסופית צריכה להתבסס על מספר בעלי ההרשאה, מספר נושאי המידע וסוג המידע.</p>';
		$o[] = '<h2>8. בעלי הרשאה וגישה</h2><p>מנהלי מערכת: ' . self::e( implode( ', ', $c['admins'] ) ) . '. עקרון הרשאה מזערית; כל משתמש בחשבון אישי; סקירת הרשאות פעם ברבעון; ביטול מיידי בעזיבה.</p>';
		$o[] = '<h2>9. הסיכונים העיקריים ודרכי ההתמודדות</h2><table class="opg-table"><thead><tr><th>סיכון</th><th>אמצעי התמודדות</th></tr></thead><tbody>'
			. '<tr><td>פריצה לחשבון מנהל</td><td>סיסמאות חזקות, אימות דו-שלבי, הגבלת ניסיונות, יומן התחברויות</td></tr>'
			. '<tr><td>חולשה בתוסף / תבנית</td><td>עדכונים שוטפים, הסרת תוספים לא פעילים, WAF</td></tr>'
			. '<tr><td>דליפת מידע דרך ספק</td><td>הסכם אבטחה, צמצום המידע המועבר, בדיקת הספק</td></tr>'
			. '<tr><td>אובדן מידע</td><td>גיבוי אוטומטי מוצפן מחוץ לשרת ובדיקת שחזור</td></tr>'
			. '<tr><td>יירוט תעבורה</td><td>HTTPS בכל האתר, HSTS</td></tr>'
			. '<tr><td>שימוש למטרה חורגת</td><td>יומן הסכמות, הסכמה נפרדת לשיווק, הדרכת עובדים</td></tr>'
			. '</tbody></table>';
		$o[] = '<h2>10. ממונה / אחראי אבטחה</h2><p>' . self::e( $c['officer_name'] ? $c['officer_name'] . ' - ' . $c['officer_email'] : '____________' ) . '</p>';
		$o[] = '<p>מסמך זה ייבחן ויעודכן לפחות פעם בשנה ובכל שינוי מהותי במאגר.</p>';
		$o[] = self::signature();
		return implode( "\n", $o );
	}

	/**
	 * Security procedure.
	 *
	 * @param array $c Context.
	 * @return string
	 */
	public static function security_policy( array $c ) {
		$o   = array( self::doc_head( 'נוהל אבטחת מידע', $c ) );
		$o[] = '<h2>1. מטרה ותחולה</h2><p>הנוהל קובע את כללי אבטחת המידע במאגרי ' . self::e( $c['name'] ) . ', וחל על כל מי שמקבל גישה למידע - עובדים, פרילנסרים וספקים. הנוהל מותאם לרמת הסיכון ויעודכן לפחות פעם בשנה, לאחר אירוע אבטחה, ובכל שינוי טכנולוגי מהותי.</p>';
		$sections = array(
			'2. בקרת גישה והרשאות' => array( 'כל משתמש בחשבון אישי - אין חשבונות משותפים.', 'עקרון הרשאה מזערית: תפקיד וורדפרס מינימלי הנדרש לעבודה.', 'אימות דו-שלבי (MFA) חובה לכל מנהל ולכל גישה לשרת, לאחסון, ל-DNS ולספקים.', 'סיסמה של 12 תווים לפחות, ייחודית, מנוהלת במנהל סיסמאות.', 'סקירת הרשאות פעם ברבעון ותיעודה.', 'ביטול גישה מיידי (באותו יום) בעזיבת עובד או סיום עבודה עם ספק, כולל החלפת סיסמאות ומפתחות API משותפים.' ),
			'3. הצפנה'              => array( 'HTTPS/TLS בכל האתר, הפניה אוטומטית מ-HTTP ו-HSTS.', 'סיסמאות נשמרות מגובבות (WordPress).', 'גיבויים מוצפנים ונשמרים מחוץ לשרת.', 'מידע רגיש לא נשלח בדוא"ל לא מוצפן; מפתחות API לא נשמרים בקוד פתוח.' ),
			'4. עדכונים ותחזוקה'    => array( 'עדכוני אבטחה לוורדפרס, תוספים ותבנית - תוך 7 ימים מפרסום (קריטי - תוך 48 שעות).', 'הסרת תוספים ותבניות שאינם בשימוש.', 'PHP בגרסה נתמכת.', 'עריכת קבצים מתוך לוח הבקרה חסומה (DISALLOW_FILE_EDIT).' ),
			'5. ניטור ותיעוד'        => array( 'יומן אבטחה: התחברויות, ניסיונות כושלים, שינויי הרשאות, הפעלת/כיבוי תוספים, ייצוא ומחיקת מידע (Ofnoa Privacy Guard).', 'סקירת היומן פעם בחודש; חריגות מתועדות.', 'שמירת לוגים 24 חודשים.' ),
			'6. גיבוי ושחזור'        => array( 'גיבוי אוטומטי יומי של מסד הנתונים ושבועי של הקבצים (' . ( $c['backup'] ? $c['backup'] : 'כלי: ________' ) . ').', 'שמירת עותק מחוץ לשרת האחסון.', 'בדיקת שחזור פעם ברבעון ותיעוד התוצאה.' ),
			'7. ספקים'               => array( 'לפני התקשרות: בדיקת אבטחת הספק, מיקום המידע ומטרת השיתוף.', 'הסכם סודיות ואבטחת מידע (נספח ספקים) או DPA של הספק.', 'העברת המידע המינימלי הנדרש בלבד.', 'סקירה שנתית של רשימת הספקים.' ),
			'8. עובדים'              => array( 'חתימה על התחייבות לסודיות לפני קבלת גישה.', 'הדרכה בנושא פרטיות ואבטחת מידע בקליטה ואחת לשנה.', 'דיווח מיידי על חשד לאירוע אבטחה.' ),
			'9. אירועי אבטחה'        => array( 'טיפול לפי "תוכנית תגובה לאירוע אבטחה".', 'תיעוד כל אירוע ביומן האירועים.' ),
			'10. ביקורת'             => array( 'ביקורת שנתית: מפת מאגרים, מדיניות פרטיות, הרשאות, ספקים, גיבויים ותוכנית התגובה.', 'ככל שהמאגר ברמת אבטחה בינונית/גבוהה - סקר סיכונים ומבדקי חדירות לפי התקנות.' ),
		);
		foreach ( $sections as $title => $items ) {
			$o[] = '<h2>' . self::e( $title ) . '</h2>' . self::ul( $items );
		}
		$o[] = self::signature();
		return implode( "\n", $o );
	}

	/**
	 * Incident response plan + incident log form.
	 *
	 * @param array $c Context.
	 * @return string
	 */
	public static function incident_plan( array $c ) {
		$o   = array( self::doc_head( 'תוכנית תגובה לאירוע אבטחת מידע', $c ) );
		$o[] = '<h2>1. צוות ותפקידים</h2><table class="opg-table"><tbody>'
			. '<tr><td>מוביל האירוע</td><td>' . self::e( $c['officer_name'] ? $c['officer_name'] : '____________' ) . ' | ' . self::e( $c['officer_email'] ) . ' | טלפון: ________</td></tr>'
			. '<tr><td>אחראי טכני (אחסון/פיתוח)</td><td>____________ | טלפון: ________</td></tr>'
			. '<tr><td>ספק אחסון - תמיכה</td><td>____________</td></tr>'
			. '<tr><td>יועץ משפטי</td><td>____________</td></tr></tbody></table>';
		$o[] = '<h2>2. מה נחשב אירוע</h2>' . self::ul( array( 'גישה לא מורשית למידע או לחשבון מנהל.', 'דליפה, חשיפה, שינוי או מחיקה של מידע אישי.', 'השבתה או נעילה (כופרה), השחתת האתר, קוד זדוני.', 'אובדן מכשיר עם גישה למערכות.', 'אירוע אצל ספק שמעבד את המידע שלנו.' ) );
		$o[] = '<h2>3. שלבי טיפול</h2><ol>'
			. '<li><strong>זיהוי ודיווח (מיידי)</strong> - כל מי שמזהה חשד מדווח למוביל. פותחים רשומה בטופס התיעוד.</li>'
			. '<li><strong>הכלה (עד שעתיים)</strong> - ניתוק הגורם: החלפת סיסמאות, ביטול משתמשים חשודים, ביטול מפתחות API, חסימת IP, מצב תחזוקה במידת הצורך. שמירת לוגים וראיות לפני ניקוי.</li>'
			. '<li><strong>הערכה (עד 24 שעות)</strong> - איזה מידע נחשף, של כמה אנשים, האם מידע רגיש, מה הסיכון לנפגעים.</li>'
			. '<li><strong>בדיקת חובת דיווח</strong> - אירוע אבטחה חמור מחייב דיווח לרשות להגנת הפרטיות ללא דיחוי. הרשות רשאית להורות על הודעה לנושאי המידע. מתעדים את ההחלטה ואת נימוקיה.</li>'
			. '<li><strong>הודעה לנושאי מידע</strong> - כשנדרש או כשיש סיכון ממשי: מה קרה, איזה מידע, מה עשינו, מה מומלץ לעשות (למשל החלפת סיסמה), ופרטי קשר.</li>'
			. '<li><strong>התאוששות</strong> - שחזור מגיבוי נקי, עדכון כל הרכיבים, סריקה, בדיקה שהפרצה נסגרה.</li>'
			. '<li><strong>הפקת לקחים (עד 14 יום)</strong> - תחקיר, עדכון נוהל האבטחה ומסמך הגדרות המאגר.</li></ol>';
		$o[] = '<h2>4. אנשי קשר חיצוניים</h2>' . self::ul( array( 'הרשות להגנת הפרטיות - דיווח על אירוע אבטחה דרך אתר הרשות (gov.il).', 'מערך הסייבר הלאומי - מוקד 119.', 'ספק האחסון: ____________', 'Cloudflare / DNS: ____________' ) );
		$o[] = '<h2>נספח - טופס תיעוד אירוע אבטחה</h2><table class="opg-table"><tbody>';
		foreach ( array( 'מספר אירוע', 'תאריך ושעת גילוי', 'מי גילה ואיך', 'תיאור האירוע', 'מערכות שנפגעו', 'סוגי המידע שנחשפו', 'מספר נושאי מידע משוער', 'האם מידע בעל רגישות מיוחדת', 'פעולות הכלה (מה, מתי, מי)', 'האם אירוע חמור? נימוק', 'דווח לרשות? מתי? מספר פנייה', 'הודעה לנושאי מידע? מתי? נוסח', 'פעולות התאוששות', 'לקחים ושינויים בנהלים', 'נסגר בתאריך / חתימת המוביל' ) as $row ) {
			$o[] = '<tr><td style="width:35%">' . self::e( $row ) . '</td><td>&nbsp;</td></tr>';
		}
		$o[] = '</tbody></table>';
		$o[] = self::signature();
		return implode( "\n", $o );
	}

	/**
	 * Vendor data processing / security appendix (reg. 15).
	 *
	 * @param array $c Context.
	 * @return string
	 */
	public static function vendor_dpa( array $c ) {
		$o   = array( self::doc_head( 'נספח סודיות ואבטחת מידע לספק המעבד מידע (מחזיק)', $c ) );
		$o[] = '<p>נספח זה מצורף להסכם בין <strong>' . self::e( $c['name'] ) . '</strong> ("בעל השליטה") לבין ____________________ ח.פ. __________ ("הספק"), ומהווה חלק בלתי נפרד ממנו, בהתאם לתקנה 15 לתקנות הגנת הפרטיות (אבטחת מידע), התשע"ז-2017.</p>';
		$items = array(
			'<strong>המידע ומטרות העיבוד:</strong> הספק יעבד את סוגי המידע הבאים: ____________, אך ורק לצורך: ____________, ולא לכל מטרה אחרת.',
			'<strong>הגבלת שימוש:</strong> הספק לא ישתמש במידע לצרכיו, לא ימכור, לא יעביר ולא יגלה אותו לצד שלישי, אלא לפי הוראה בכתב מבעל השליטה או לפי דין.',
			'<strong>סודיות:</strong> הספק ועובדיו ישמרו על סודיות המידע גם לאחר סיום ההתקשרות. רק עובדים שחייבים בכך לצורך השירות יקבלו גישה, ולאחר שחתמו על התחייבות לסודיות.',
			'<strong>אבטחת מידע:</strong> הספק ינקוט אמצעי אבטחה לפחות ברמה הנדרשת בתקנות למאגר, ובהם: בקרת גישה והרשאה מזערית, אימות דו-שלבי, הצפנה בהעברה ובמנוחה כשנדרש, עדכוני אבטחה, גיבוי ותיעוד גישה.',
			'<strong>קבלני משנה:</strong> הספק לא יעביר עיבוד לקבלן משנה ללא אישור מראש ובכתב, ויחייב אותו בהתחייבויות זהות.',
			'<strong>מיקום המידע:</strong> המידע יישמר ב: ____________. העברה מחוץ לישראל - רק באישור ובהתאם לתקנות העברת מידע לחו"ל.',
			'<strong>דיווח על אירוע:</strong> הספק ידווח לבעל השליטה על כל אירוע אבטחה או חשד לאירוע בתוך 24 שעות מגילויו, וישתף פעולה באופן מלא בבירור, בהכלה ובדיווח לרשות.',
			'<strong>זכויות נושאי מידע:</strong> הספק יסייע לבעל השליטה במענה לבקשות עיון, תיקון ומחיקה, בתוך 7 ימים מבקשה.',
			'<strong>ביקורת:</strong> הספק ימסור, לפי דרישה ולפחות פעם בשנה, דיווח על עמידתו בהוראות הנספח, ויאפשר בקרה סבירה.',
			'<strong>סיום התקשרות:</strong> בסיום ההתקשרות הספק יחזיר או ימחק את המידע, לבחירת בעל השליטה, וימסור אישור בכתב על המחיקה.',
			'<strong>אחריות:</strong> הספק אחראי לכל נזק שייגרם עקב הפרת הנספח על ידיו או על ידי מי מטעמו.',
		);
		$o[] = '<ol><li>' . implode( '</li><li>', $items ) . '</li></ol>';
		if ( $c['third'] ) {
			$o[] = '<h2>ספקים שזוהו באתר - סטטוס הסכם</h2><table class="opg-table"><thead><tr><th>ספק</th><th>מידע</th><th>מיקום</th><th>הסכם / DPA</th></tr></thead><tbody>';
			foreach ( $c['third'] as $t ) {
				$o[] = '<tr><td>' . self::e( $t['name'] ) . '</td><td>' . self::e( $t['data'] ) . '</td><td>' . self::e( $t['location'] ) . '</td><td>☐ נחתם &nbsp; ☐ DPA של הספק נשמר &nbsp; קישור/מיקום: ______</td></tr>';
			}
			$o[] = '</tbody></table><p>לספקים גלובליים (Google, Meta, Mailchimp וכו\') - שמרו עותק או קישור ל-DPA/תנאי עיבוד הנתונים שלהם והפעילו את הגדרות הפרטיות בחשבון (למשל IP anonymization, הגבלת שמירת נתונים).</p>';
		}
		$o[] = '<p style="margin-top:40px">בעל השליטה: ______________ &nbsp;&nbsp; הספק: ______________ &nbsp;&nbsp; תאריך: ____________</p>';
		return implode( "\n", $o );
	}

	/**
	 * Staff confidentiality undertaking.
	 *
	 * @param array $c Context.
	 * @return string
	 */
	public static function staff_nda( array $c ) {
		$o   = array( self::doc_head( 'התחייבות לשמירת סודיות ואבטחת מידע - עובד / נותן שירות', $c ) );
		$o[] = '<p>אני הח"מ ____________________ ת.ז. ______________, תפקיד: ______________, מצהיר/ה ומתחייב/ת כלפי ' . self::e( $c['name'] ) . ' כדלקמן:</p><ol>'
			. '<li>אשמור בסודיות כל מידע אישי על לקוחות, פונים ומשתמשים שיגיע אליי במסגרת עבודתי, ולא אגלה אותו לאיש, גם לאחר סיום עבודתי.</li>'
			. '<li>אשתמש במידע אך ורק לצורך ביצוע תפקידי ובהתאם להרשאות שניתנו לי.</li>'
			. '<li>לא אעתיק, לא אוריד ולא אעביר מידע אל מחוץ למערכות הארגון, לרבות לכלי בינה מלאכותית חיצוניים, ללא אישור.</li>'
			. '<li>אשמור על סיסמאותיי ועל אמצעי האימות שלי, לא אשתף אותם, ואשתמש באימות דו-שלבי.</li>'
			. '<li>אדווח מיד לאחראי על כל חשד לאירוע אבטחה, אובדן מכשיר או גישה לא מורשית.</li>'
			. '<li>בסיום עבודתי אחזיר כל מידע וציוד ואמחק עותקים שבידי.</li>'
			. '<li>ידוע לי שהפרת התחייבות זו עלולה להוות עבירה לפי חוק הגנת הפרטיות.</li></ol>'
			. '<p>קראתי את נוהל אבטחת המידע ואת מדיניות הפרטיות והבנתי אותם.</p>'
			. '<p style="margin-top:40px">תאריך: ____________ &nbsp; חתימה: ____________________</p>';
		return implode( "\n", $o );
	}

	/**
	 * Response letters for rights requests.
	 *
	 * @param array $c Context.
	 * @return string
	 */
	public static function response_letters( array $c ) {
		$sig = '<p>בברכה,<br>' . self::e( $c['officer_name'] ? $c['officer_name'] : $c['name'] ) . '<br>' . self::e( $c['name'] ) . ' | ' . self::e( $c['officer_email'] ? $c['officer_email'] : $c['email'] ) . '</p>';
		$o   = array( self::doc_head( 'נוסחי מענה לבקשות מימוש זכויות', $c ) );
		$o[] = '<h2>א. אישור קבלת בקשה</h2><p>שלום ______,<br>קיבלנו את בקשתך מיום ______ בנושא ______ (מס\' בקשה ____). נשיב לך בתוך 30 יום מקבלתה. לצורך אימות זהותך נבקש ______.</p>' . $sig;
		$o[] = '<h2>ב. מענה לבקשת עיון</h2><p>שלום ______,<br>בהמשך לבקשתך מיום ______, מצורף המידע השמור עליך במאגרי ' . self::e( $c['name'] ) . ': ______. המידע נאסף לצורך ______ ומשותף עם ______. אם לדעתך המידע אינו נכון, שלם, ברור או מעודכן - באפשרותך לבקש לתקנו או למחקו.</p>' . $sig;
		$o[] = '<h2>ג. אישור תיקון / מחיקה</h2><p>שלום ______,<br>בהמשך לבקשתך מיום ______, ביצענו ______ (תיקון/מחיקה) של המידע הבא: ______. נמסור הודעה על התיקון גם למי שקיבל ממנו את המידע בשנה האחרונה, ככל שנדרש. מידע שאנו חייבים לשמור על פי דין (למשל חשבוניות) יישמר עד תום התקופה הנדרשת.</p>' . $sig;
		$o[] = '<h2>ד. סירוב מנומק</h2><p>שלום ______,<br>לאחר בחינת בקשתך מיום ______, איננו יכולים להיעתר לה מהנימוק הבא: ______. באפשרותך לערער על הסירוב בבית משפט השלום בתוך 30 יום מקבלת הודעה זו, וכן לפנות לרשות להגנת הפרטיות.</p>' . $sig;
		$o[] = '<h2>ה. אישור הסרה מדיוור</h2><p>שלום ______,<br>כתובתך ______ הוסרה מרשימת הדיוור שלנו ולא יישלחו אליך עוד הודעות שיווקיות. הודעות תפעוליות הנוגעות להזמנות או לשירות פעיל עשויות להמשיך להישלח.</p>' . $sig;
		return implode( "\n", $o );
	}

	/**
	 * Catalogue of available documents.
	 *
	 * @return array key => [title, type(public|internal), description]
	 */
	public static function catalogue() {
		return array(
			'privacy_he'       => array( 'מדיניות פרטיות (עברית)', 'public', 'מותאמת לנתוני האתר ולכלים שזוהו - נוצרת כטיוטה, לא דורסת את הקיימת.' ),
			'privacy_en'       => array( 'Privacy Policy (English)', 'public', 'גרסה באנגלית למשתמשים דוברי אנגלית.' ),
			'cookie_policy'    => array( 'מדיניות Cookie', 'public', 'טבלת הכלים שזוהו + קישור לשינוי הבחירה.' ),
			'rights_page'      => array( 'עמוד בקשות פרטיות (עיון/תיקון/מחיקה)', 'public', 'עמוד עם טופס, אימות דוא"ל ומעקב 30 יום.' ),
			'data_map'         => array( 'מיפוי מאגרים וזרימת מידע', 'internal', 'כל המאגרים, מקורות, ספקים, נתונים כמותיים.' ),
			'db_definitions'   => array( 'מסמך הגדרות מאגר (תקנה 2)', 'internal', 'נדרש בתקנות אבטחת מידע ומצורף להודעה/רישום לרשות.' ),
			'security_policy'  => array( 'נוהל אבטחת מידע', 'internal', 'גישה, הצפנה, עדכונים, ניטור, גיבוי, ספקים, ביקורת.' ),
			'incident_plan'    => array( 'תוכנית תגובה לאירוע אבטחה + טופס תיעוד', 'internal', 'מי מוביל, הכלה, בדיקת חובת דיווח, הודעה לנושאי מידע.' ),
			'vendor_dpa'       => array( 'נספח אבטחת מידע לספקים (תקנה 15)', 'internal', 'כולל טבלת הספקים שזוהו לסימון סטטוס ההסכם.' ),
			'staff_nda'        => array( 'התחייבות לסודיות - עובדים ונותני שירות', 'internal', 'לחתימה לפני מתן גישה למידע.' ),
			'response_letters' => array( 'נוסחי מענה לבקשות זכויות', 'internal', 'אישור קבלה, עיון, תיקון/מחיקה, סירוב מנומק, הסרה מדיוור.' ),
		);
	}
}
