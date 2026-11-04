<?php
/**
 * Consent checkboxes + collection notice on every form that collects personal data.
 *
 * Server-side (injected + validated + logged): WooCommerce checkout (classic + blocks),
 * WooCommerce registration, WP registration, comments, Contact Form 7, Elementor Pro,
 * WPForms, Gravity Forms, Fluent Forms. Everything else: the universal JS layer.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Consent {

	const FIELD_PRIVACY   = 'opg_privacy';
	const FIELD_MARKETING = 'opg_marketing';
	const BLOCK_PRIVACY   = 'ofnoa/privacy-consent';
	const BLOCK_MARKETING = 'ofnoa/marketing-consent';

	/** @var bool Prevents double logging when several hooks fire for one user. */
	private static $logged_register = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		$s = OPG_Settings::all();

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_login' ) );
		add_action( 'wp_ajax_opg_log_consent', array( __CLASS__, 'ajax_log' ) );
		add_action( 'wp_ajax_nopriv_opg_log_consent', array( __CLASS__, 'ajax_log' ) );
		add_shortcode( 'opg_consent_box', array( __CLASS__, 'shortcode_box' ) );

		// WooCommerce.
		if ( $s['consent_wc_checkout'] ) {
			add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'wc_checkout_box' ), 9 );
			add_action( 'woocommerce_checkout_process', array( __CLASS__, 'wc_checkout_validate' ) );
			add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'wc_checkout_save' ), 10, 3 );
			add_action( 'woocommerce_init', array( __CLASS__, 'wc_blocks_register_fields' ) );
			add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'wc_blocks_save' ) );
		}
		if ( $s['consent_wc_register'] ) {
			add_action( 'woocommerce_register_form', array( __CLASS__, 'echo_box_wc_register' ), 20 );
			add_filter( 'woocommerce_registration_errors', array( __CLASS__, 'wc_register_validate' ), 10, 3 );
			add_action( 'woocommerce_created_customer', array( __CLASS__, 'wc_register_save' ) );
		}

		// WordPress core.
		if ( $s['consent_wp_register'] ) {
			add_action( 'register_form', array( __CLASS__, 'echo_box_wp_register' ) );
			add_filter( 'registration_errors', array( __CLASS__, 'wp_register_validate' ), 10, 3 );
			add_action( 'user_register', array( __CLASS__, 'wp_register_save' ) );
		}
		if ( $s['consent_comments'] ) {
			add_filter( 'comment_form_submit_field', array( __CLASS__, 'comment_field' ), 10, 2 );
			add_filter( 'preprocess_comment', array( __CLASS__, 'comment_validate' ) );
			add_action( 'comment_post', array( __CLASS__, 'comment_save' ), 10, 3 );
		}

		// Contact Form 7.
		if ( $s['consent_cf7'] ) {
			add_filter( 'wpcf7_form_elements', array( __CLASS__, 'cf7_inject' ) );
			add_filter( 'wpcf7_validate', array( __CLASS__, 'cf7_validate' ), 20, 2 );
			add_action( 'wpcf7_mail_sent', array( __CLASS__, 'cf7_save' ) );
		}

		// Elementor Pro forms.
		if ( $s['consent_elementor'] ) {
			add_filter( 'elementor/widget/render_content', array( __CLASS__, 'elementor_inject' ), 10, 2 );
			add_action( 'elementor_pro/forms/validation', array( __CLASS__, 'elementor_validate' ), 10, 2 );
			add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'elementor_save' ), 10, 2 );
		}

		// WPForms.
		if ( $s['consent_wpforms'] ) {
			add_action( 'wpforms_display_submit_before', array( __CLASS__, 'wpforms_inject' ) );
			add_action( 'wpforms_process', array( __CLASS__, 'wpforms_validate' ), 10, 3 );
			add_action( 'wpforms_process_complete', array( __CLASS__, 'wpforms_save' ), 10, 4 );
		}

		// Gravity Forms.
		if ( $s['consent_gravity'] ) {
			add_filter( 'gform_submit_button', array( __CLASS__, 'gravity_inject' ), 10, 2 );
			add_filter( 'gform_validation', array( __CLASS__, 'gravity_validate' ) );
			add_filter( 'gform_validation_message', array( __CLASS__, 'gravity_message' ), 10, 2 );
			add_action( 'gform_after_submission', array( __CLASS__, 'gravity_save' ), 10, 2 );
		}

		// Fluent Forms (new + legacy hook names).
		if ( $s['consent_fluent'] ) {
			add_action( 'fluentform/render_item_submit_button', array( __CLASS__, 'fluent_inject' ), 5, 2 );
			add_action( 'fluentform_render_item_submit_button', array( __CLASS__, 'fluent_inject' ), 5, 2 );
			add_filter( 'fluentform/validation_errors', array( __CLASS__, 'fluent_validate' ), 10, 2 );
			add_filter( 'fluentform_validation_errors', array( __CLASS__, 'fluent_validate' ), 10, 2 );
			add_action( 'fluentform/submission_inserted', array( __CLASS__, 'fluent_save' ), 10, 3 );
			add_action( 'fluentform_submission_inserted', array( __CLASS__, 'fluent_save' ), 10, 3 );
		}
	}

	/* ------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Replace placeholders in a consent text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function fill( $text ) {
		$url  = OPG_Plugin::policy_url();
		$link = $url
			? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">מדיניות הפרטיות</a>'
			: 'מדיניות הפרטיות';
		$text = str_replace(
			array( '{privacy_link}', '{business}' ),
			array( $link, esc_html( OPG_Settings::get( 'business_name' ) ) ),
			(string) $text
		);
		return wp_kses( $text, OPG_Settings::allowed_html() );
	}

	/**
	 * Should the marketing box appear?
	 *
	 * @return bool
	 */
	public static function marketing_enabled() {
		return (bool) ( OPG_Settings::get( 'show_marketing_box' ) && OPG_Settings::get( 'direct_marketing' ) );
	}

	/**
	 * The consent block HTML.
	 *
	 * @param string $source  Source key.
	 * @param array  $opts    wrap_class, field_wrap (cf7), required.
	 * @return string
	 */
	public static function box_html( $source, array $opts = array() ) {
		$opts      = wp_parse_args(
			$opts,
			array(
				'wrap_class' => '',
				'cf7'        => false,
				'required'   => true,
				'notice'     => (bool) OPG_Settings::get( 'show_collection_notice' ),
			)
		);
		$uid       = 'opg-' . wp_unique_id();
		$privacy   = self::fill( OPG_Settings::get( 'consent_text_privacy' ) );
		$marketing = self::fill( OPG_Settings::get( 'consent_text_marketing' ) );
		$required  = $opts['required'] ? ' required aria-required="true"' : '';

		$html  = '<div class="opg-consent ' . esc_attr( $opts['wrap_class'] ) . '" data-opg-consent="' . esc_attr( $source ) . '">';
		if ( $opts['notice'] ) {
			$html .= '<p class="opg-notice">' . self::fill( OPG_Settings::get( 'collection_notice' ) ) . '</p>';
		}
		$privacy_input = '<input type="checkbox" id="' . esc_attr( $uid ) . '-p" name="' . self::FIELD_PRIVACY . '" value="1"' . $required . '>';
		if ( $opts['cf7'] ) {
			$privacy_input = '<span class="wpcf7-form-control-wrap" data-name="' . self::FIELD_PRIVACY . '">' . $privacy_input . '</span>';
		}
		$html .= '<label class="opg-check opg-check-privacy" for="' . esc_attr( $uid ) . '-p">' . $privacy_input . ' <span>' . $privacy . ' <abbr class="opg-req" title="שדה חובה">*</abbr></span></label>';
		if ( self::marketing_enabled() ) {
			$html .= '<label class="opg-check opg-check-marketing" for="' . esc_attr( $uid ) . '-m"><input type="checkbox" id="' . esc_attr( $uid ) . '-m" name="' . self::FIELD_MARKETING . '" value="1"> <span>' . $marketing . '</span></label>';
		}
		$html .= '<input type="hidden" name="opg_source" value="' . esc_attr( $source ) . '">';
		$html .= '</div>';
		return $html;
	}

	/**
	 * [opg_consent_box source="newsletter"] for custom forms.
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public static function shortcode_box( $atts ) {
		$atts = shortcode_atts( array( 'source' => 'custom' ), $atts, 'opg_consent_box' );
		return self::box_html( sanitize_key( $atts['source'] ) );
	}

	/**
	 * Front assets.
	 */
	public static function enqueue() {
		wp_enqueue_style( 'opg-front', OPG_URL . 'assets/css/front.css', array(), OPG_VERSION );
		wp_enqueue_script( 'opg-front', OPG_URL . 'assets/js/front.js', array(), OPG_VERSION, true );
		wp_localize_script( 'opg-front', 'OPG', self::js_config() );
	}

	/**
	 * Login/register screen assets.
	 */
	public static function enqueue_login() {
		wp_enqueue_style( 'opg-front', OPG_URL . 'assets/css/front.css', array(), OPG_VERSION );
	}

	/**
	 * Data passed to front.js.
	 *
	 * @return array
	 */
	public static function js_config() {
		$s = OPG_Settings::all();
		return array(
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'opg_consent' ),
			'universal' => (bool) $s['consent_universal'],
			'boxHtml'   => self::box_html( 'universal' ),
			'error'     => $s['consent_error'],
			'banner'    => OPG_Cookie_Banner::config(),
		);
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Is the privacy box checked in this request?
	 *
	 * @param array|null $data Source array (defaults to $_POST).
	 * @return bool
	 */
	public static function posted( $data = null ) {
		$data = null === $data ? $_POST : $data; // phpcs:ignore WordPress.Security.NonceVerification
		return ! empty( $data[ self::FIELD_PRIVACY ] );
	}

	/**
	 * Was our consent box part of the submitted form? (hidden opg_source field)
	 * Server-side enforcement only applies when it was - so a form we could not
	 * inject into (custom theme markup, API calls) is never locked out.
	 *
	 * @param array|null $data Source array.
	 * @return bool
	 */
	public static function box_present( $data = null ) {
		$data = null === $data ? $_POST : $data; // phpcs:ignore WordPress.Security.NonceVerification
		return ! empty( $data['opg_source'] );
	}

	/**
	 * Box present but privacy not checked → reject.
	 *
	 * @param array|null $data Source array.
	 * @return bool
	 */
	public static function missing( $data = null ) {
		return self::box_present( $data ) && ! self::posted( $data );
	}

	/**
	 * Is the marketing box checked in this request?
	 *
	 * @param array|null $data Source array.
	 * @return bool
	 */
	public static function posted_marketing( $data = null ) {
		$data = null === $data ? $_POST : $data; // phpcs:ignore WordPress.Security.NonceVerification
		return ! empty( $data[ self::FIELD_MARKETING ] );
	}

	/**
	 * Find the first email value in a (nested) array.
	 *
	 * @param mixed $data Data.
	 * @return string
	 */
	public static function find_email( $data ) {
		if ( is_string( $data ) ) {
			$data = trim( $data );
			return is_email( $data ) ? $data : '';
		}
		if ( is_array( $data ) ) {
			foreach ( array( 'email', 'your-email', 'user_email', 'billing_email', 'Email', 'e-mail' ) as $key ) {
				if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) && is_email( trim( $data[ $key ] ) ) ) {
					return trim( $data[ $key ] );
				}
			}
			foreach ( $data as $value ) {
				$found = self::find_email( $value );
				if ( $found ) {
					return $found;
				}
			}
		}
		if ( is_object( $data ) ) {
			return self::find_email( get_object_vars( $data ) );
		}
		return '';
	}

	/**
	 * Log a form submission.
	 *
	 * @param string $source Source.
	 * @param string $ref    Form ref.
	 * @param string $email  Email.
	 * @param bool   $marketing Marketing.
	 * @param int    $user_id User.
	 */
	public static function log( $source, $ref, $email, $marketing, $user_id = 0 ) {
		OPG_Consent_Log::record(
			array(
				'source'    => $source,
				'form_ref'  => $ref,
				'email'     => $email,
				'user_id'   => $user_id ? $user_id : get_current_user_id(),
				'privacy'   => 1,
				'marketing' => $marketing ? 1 : 0,
				'text'      => OPG_Settings::get( 'consent_text_privacy' ) . ( $marketing ? ' | ' . OPG_Settings::get( 'consent_text_marketing' ) : '' ),
			)
		);
	}

	/**
	 * Error message.
	 *
	 * @return string
	 */
	public static function error_text() {
		return (string) OPG_Settings::get( 'consent_error' );
	}

	/* ------------------------------------------------------------------
	 * WooCommerce - classic checkout
	 * ------------------------------------------------------------------ */

	public static function wc_checkout_box() {
		echo self::box_html( 'wc_checkout', array( 'wrap_class' => 'form-row validate-required' ) ); // phpcs:ignore
	}

	public static function wc_checkout_validate() {
		if ( self::missing() && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( self::error_text(), 'error' );
		}
	}

	/**
	 * @param int      $order_id Order.
	 * @param array    $posted   Posted.
	 * @param WC_Order $order    Order.
	 */
	public static function wc_checkout_save( $order_id, $posted, $order ) {
		if ( ! self::posted() || ! $order ) {
			return;
		}
		$marketing = self::posted_marketing();
		$order->update_meta_data( '_opg_privacy_consent', current_time( 'mysql', true ) );
		$order->update_meta_data( '_opg_marketing_consent', $marketing ? 'yes' : 'no' );
		$order->save();
		self::log( 'wc_checkout', 'order #' . $order_id, $order->get_billing_email(), $marketing, $order->get_customer_id() );
	}

	/* ------------------------------------------------------------------
	 * WooCommerce - block checkout (additional checkout fields API)
	 * ------------------------------------------------------------------ */

	public static function wc_blocks_register_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		$label   = wp_strip_all_tags( self::fill( OPG_Settings::get( 'consent_text_privacy' ) ) );
		$fields  = array(
			array(
				'id'       => self::BLOCK_PRIVACY,
				'label'    => $label,
				'type'     => 'checkbox',
				'required' => true,
				'error_message' => self::error_text(),
			),
		);
		if ( self::marketing_enabled() ) {
			$fields[] = array(
				'id'    => self::BLOCK_MARKETING,
				'label' => wp_strip_all_tags( self::fill( OPG_Settings::get( 'consent_text_marketing' ) ) ),
				'type'  => 'checkbox',
			);
		}
		// "order" since WooCommerce 8.9; earlier versions call it "additional".
		$location = ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.9', '<' ) ) ? 'additional' : 'order';
		foreach ( $fields as $field ) {
			$field['location'] = $location;
			try {
				woocommerce_register_additional_checkout_field( $field );
			} catch ( \Throwable $e ) {
				// WooCommerce throws on invalid options - never break the checkout because of us.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Ofnoa Privacy Guard: ' . $e->getMessage() ); // phpcs:ignore
				}
			}
		}
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public static function wc_blocks_save( $order ) {
		if ( ! $order || ! is_object( $order ) ) {
			return;
		}
		$privacy = $order->get_meta( '_wc_other/' . self::BLOCK_PRIVACY );
		if ( ! $privacy ) {
			return;
		}
		$marketing = (bool) $order->get_meta( '_wc_other/' . self::BLOCK_MARKETING );
		$order->update_meta_data( '_opg_privacy_consent', current_time( 'mysql', true ) );
		$order->update_meta_data( '_opg_marketing_consent', $marketing ? 'yes' : 'no' );
		$order->save();
		self::log( 'wc_checkout', 'order #' . $order->get_id() . ' (blocks)', $order->get_billing_email(), $marketing, $order->get_customer_id() );
	}

	/* ------------------------------------------------------------------
	 * WooCommerce - My Account registration
	 * ------------------------------------------------------------------ */

	public static function echo_box_wc_register() {
		echo self::box_html( 'wc_register', array( 'wrap_class' => 'woocommerce-form-row form-row' ) ); // phpcs:ignore
	}

	/**
	 * @param WP_Error $errors Errors.
	 * @return WP_Error
	 */
	public static function wc_register_validate( $errors ) {
		// Only when the request came from the My Account form (checkout registration has its own box).
		if ( isset( $_POST['woocommerce-register-nonce'] ) && self::missing() ) { // phpcs:ignore
			$errors->add( 'opg_privacy', self::error_text() );
		}
		return $errors;
	}

	/** @param int $customer_id ID. */
	public static function wc_register_save( $customer_id ) {
		// My Account form only - accounts created at checkout are logged with the order.
		if ( ! isset( $_POST['woocommerce-register-nonce'] ) || ! self::posted() || self::$logged_register ) { // phpcs:ignore
			return;
		}
		self::$logged_register = true;
		$user = get_userdata( $customer_id );
		update_user_meta( $customer_id, '_opg_privacy_consent', current_time( 'mysql', true ) );
		update_user_meta( $customer_id, '_opg_marketing_consent', self::posted_marketing() ? 'yes' : 'no' );
		self::log( 'wc_register', 'user #' . $customer_id, $user ? $user->user_email : '', self::posted_marketing(), $customer_id );
	}

	/* ------------------------------------------------------------------
	 * WordPress registration
	 * ------------------------------------------------------------------ */

	public static function echo_box_wp_register() {
		echo self::box_html( 'wp_register' ); // phpcs:ignore
	}

	/**
	 * @param WP_Error $errors Errors.
	 * @return WP_Error
	 */
	public static function wp_register_validate( $errors ) {
		if ( self::missing() ) {
			$errors->add( 'opg_privacy', '<strong>שגיאה</strong>: ' . esc_html( self::error_text() ) );
		}
		return $errors;
	}

	/** @param int $user_id ID. */
	public static function wp_register_save( $user_id ) {
		// Only front-end registrations that went through our box.
		if ( is_admin() || ! self::posted() || self::$logged_register ) {
			return;
		}
		$source = isset( $_POST['opg_source'] ) ? sanitize_key( wp_unslash( $_POST['opg_source'] ) ) : 'wp_register'; // phpcs:ignore
		if ( 'wc_register' === $source || 'wc_checkout' === $source ) {
			return; // Handled by the WooCommerce hooks.
		}
		self::$logged_register = true;
		$user = get_userdata( $user_id );
		update_user_meta( $user_id, '_opg_privacy_consent', current_time( 'mysql', true ) );
		self::log( 'wp_register', 'user #' . $user_id, $user ? $user->user_email : '', self::posted_marketing(), $user_id );
	}

	/* ------------------------------------------------------------------
	 * Comments
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $submit_field Submit HTML.
	 * @return string
	 */
	public static function comment_field( $submit_field ) {
		if ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
			return $submit_field;
		}
		return self::box_html( 'comment', array( 'wrap_class' => 'comment-form-opg' ) ) . $submit_field;
	}

	/**
	 * @param array $data Comment.
	 * @return array
	 */
	public static function comment_validate( $data ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return $data;
		}
		if ( ! isset( $_POST['comment_post_ID'] ) ) { // phpcs:ignore
			return $data;
		}
		if ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
			return $data;
		}
		if ( isset( $data['comment_type'] ) && in_array( $data['comment_type'], array( 'pingback', 'trackback', 'order_note', 'webhook_delivery' ), true ) ) {
			return $data;
		}
		if ( self::missing() ) {
			wp_die( esc_html( self::error_text() ), esc_html__( 'Comment Submission Failure' ), array( 'back_link' => true, 'response' => 200 ) );
		}
		return $data;
	}

	/**
	 * @param int        $comment_id ID.
	 * @param int|string $approved   Status.
	 * @param array      $data       Data.
	 */
	public static function comment_save( $comment_id, $approved, $data ) {
		if ( ! self::posted() ) {
			return;
		}
		add_comment_meta( $comment_id, '_opg_privacy_consent', current_time( 'mysql', true ), true );
		self::log( 'comment', 'comment #' . $comment_id, isset( $data['comment_author_email'] ) ? $data['comment_author_email'] : '', self::posted_marketing() );
	}

	/* ------------------------------------------------------------------
	 * Contact Form 7
	 * ------------------------------------------------------------------ */

	/**
	 * Insert the box before the submit control.
	 *
	 * @param string $html Form HTML.
	 * @return string
	 */
	public static function cf7_inject( $html ) {
		if ( false !== strpos( $html, 'data-opg-consent' ) || false !== strpos( $html, 'opg-skip' ) ) {
			return $html;
		}
		return self::insert_before_submit( $html, self::box_html( 'cf7', array( 'cf7' => true ) ) );
	}

	/**
	 * @param WPCF7_Validation $result Result.
	 * @return WPCF7_Validation
	 */
	public static function cf7_validate( $result ) {
		if ( self::missing() && class_exists( 'WPCF7_Submission' ) ) {
			$submission = WPCF7_Submission::get_instance();
			$form       = $submission ? $submission->get_contact_form() : null;
			if ( $form && false !== strpos( (string) $form->prop( 'form' ), 'opg-skip' ) ) {
				return $result;
			}
			$result->invalidate(
				array(
					'type' => 'checkbox',
					'name' => self::FIELD_PRIVACY,
				),
				self::error_text()
			);
		}
		return $result;
	}

	/** @param WPCF7_ContactForm $form Form. */
	public static function cf7_save( $form ) {
		if ( ! self::posted() ) {
			return;
		}
		$data = array();
		if ( class_exists( 'WPCF7_Submission' ) ) {
			$submission = WPCF7_Submission::get_instance();
			$data       = $submission ? $submission->get_posted_data() : array();
		}
		self::log( 'cf7', 'CF7: ' . $form->title() . ' (#' . $form->id() . ')', self::find_email( $data ), self::posted_marketing() );
	}

	/* ------------------------------------------------------------------
	 * Elementor Pro
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $content Rendered widget.
	 * @param object $widget  Widget.
	 * @return string
	 */
	public static function elementor_inject( $content, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'form' !== $widget->get_name() ) {
			return $content;
		}
		if ( false !== strpos( $content, 'data-opg-consent' ) || false !== strpos( $content, 'opg-skip' ) ) {
			return $content;
		}
		$box = '<div class="elementor-field-group elementor-column elementor-col-100 opg-elementor">' . self::box_html( 'elementor' ) . '</div>';
		$pos = strpos( $content, 'elementor-field-type-submit' );
		if ( false !== $pos ) {
			$div = strrpos( substr( $content, 0, $pos ), '<div' );
			if ( false !== $div ) {
				return substr( $content, 0, $div ) . $box . substr( $content, $div );
			}
		}
		return self::insert_before_submit( $content, $box );
	}

	/**
	 * @param object $record       Record.
	 * @param object $ajax_handler Handler.
	 */
	public static function elementor_validate( $record, $ajax_handler ) {
		if ( ! self::missing() ) {
			return;
		}
		if ( method_exists( $ajax_handler, 'add_error' ) ) {
			$ajax_handler->add_error( self::FIELD_PRIVACY, self::error_text() );
		}
		if ( method_exists( $ajax_handler, 'add_error_message' ) ) {
			$ajax_handler->add_error_message( self::error_text() );
		}
	}

	/**
	 * @param object $record  Record.
	 * @param object $handler Handler.
	 */
	public static function elementor_save( $record, $handler ) {
		if ( ! self::posted() ) {
			return;
		}
		$email  = '';
		$fields = method_exists( $record, 'get' ) ? (array) $record->get( 'fields' ) : array();
		foreach ( $fields as $field ) {
			if ( isset( $field['type'], $field['value'] ) && 'email' === $field['type'] && is_email( $field['value'] ) ) {
				$email = $field['value'];
				break;
			}
		}
		if ( ! $email ) {
			$email = self::find_email( $fields );
		}
		$name = method_exists( $record, 'get_form_settings' ) ? (string) $record->get_form_settings( 'form_name' ) : '';
		self::log( 'elementor', 'Elementor: ' . $name, $email, self::posted_marketing() );
	}

	/* ------------------------------------------------------------------
	 * WPForms
	 * ------------------------------------------------------------------ */

	/** @param array $form_data Form. */
	public static function wpforms_inject( $form_data ) {
		if ( ! empty( $form_data['settings']['form_class'] ) && false !== strpos( $form_data['settings']['form_class'], 'opg-skip' ) ) {
			return;
		}
		echo self::box_html( 'wpforms', array( 'wrap_class' => 'wpforms-field' ) ); // phpcs:ignore
	}

	/**
	 * @param array $fields    Fields.
	 * @param array $entry     Entry.
	 * @param array $form_data Form.
	 */
	public static function wpforms_validate( $fields, $entry, $form_data ) {
		if ( ! self::missing() || ! function_exists( 'wpforms' ) ) {
			return;
		}
		if ( ! empty( $form_data['settings']['form_class'] ) && false !== strpos( $form_data['settings']['form_class'], 'opg-skip' ) ) {
			return;
		}
		$id = isset( $form_data['id'] ) ? $form_data['id'] : 0;
		wpforms()->process->errors[ $id ]['header'] = self::error_text();
	}

	/**
	 * @param array $fields    Fields.
	 * @param array $entry     Entry.
	 * @param array $form_data Form.
	 * @param int   $entry_id  Entry id.
	 */
	public static function wpforms_save( $fields, $entry, $form_data, $entry_id ) {
		if ( ! self::posted() ) {
			return;
		}
		$email = '';
		foreach ( (array) $fields as $field ) {
			if ( isset( $field['type'], $field['value'] ) && 'email' === $field['type'] && is_email( $field['value'] ) ) {
				$email = $field['value'];
				break;
			}
		}
		$title = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : '';
		self::log( 'wpforms', 'WPForms: ' . $title . ' entry #' . (int) $entry_id, $email ? $email : self::find_email( $fields ), self::posted_marketing() );
	}

	/* ------------------------------------------------------------------
	 * Gravity Forms
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $button Button HTML.
	 * @param array  $form   Form.
	 * @return string
	 */
	public static function gravity_inject( $button, $form ) {
		if ( ! empty( $form['cssClass'] ) && false !== strpos( $form['cssClass'], 'opg-skip' ) ) {
			return $button;
		}
		return self::box_html( 'gravity', array( 'wrap_class' => 'gfield' ) ) . $button;
	}

	/**
	 * @param array $result Validation result.
	 * @return array
	 */
	public static function gravity_validate( $result ) {
		$form = isset( $result['form'] ) ? $result['form'] : array();
		if ( ! empty( $form['cssClass'] ) && false !== strpos( $form['cssClass'], 'opg-skip' ) ) {
			return $result;
		}
		if ( self::missing() ) {
			$result['is_valid'] = false;
		}
		return $result;
	}

	/**
	 * @param string $message Message.
	 * @param array  $form    Form.
	 * @return string
	 */
	public static function gravity_message( $message, $form ) {
		if ( self::missing() ) {
			return '<div class="gform_validation_errors opg-error"><h2 class="gform_submission_error">' . esc_html( self::error_text() ) . '</h2></div>';
		}
		return $message;
	}

	/**
	 * @param array $entry Entry.
	 * @param array $form  Form.
	 */
	public static function gravity_save( $entry, $form ) {
		if ( ! self::posted() ) {
			return;
		}
		$title = isset( $form['title'] ) ? $form['title'] : '';
		self::log( 'gravity', 'Gravity: ' . $title . ' entry #' . ( isset( $entry['id'] ) ? (int) $entry['id'] : 0 ), self::find_email( $entry ), self::posted_marketing() );
	}

	/* ------------------------------------------------------------------
	 * Fluent Forms
	 * ------------------------------------------------------------------ */

	/**
	 * @param array  $item Submit item.
	 * @param object $form Form.
	 */
	public static function fluent_inject( $item, $form ) {
		static $done = array();
		$id = is_object( $form ) && isset( $form->id ) ? (int) $form->id : 0;
		if ( isset( $done[ $id ] ) ) {
			return; // New + legacy hook both fired.
		}
		$done[ $id ] = true;
		echo self::box_html( 'fluent', array( 'wrap_class' => 'ff-el-group' ) ); // phpcs:ignore
	}

	/**
	 * @param array $errors   Errors.
	 * @param array $formData Data.
	 * @return array
	 */
	public static function fluent_validate( $errors, $formData ) { // phpcs:ignore
		if ( self::missing( (array) $formData ) ) {
			$errors[ self::FIELD_PRIVACY ] = array( self::error_text() );
		}
		return $errors;
	}

	/**
	 * @param int    $entry_id Entry.
	 * @param array  $formData Data.
	 * @param object $form     Form.
	 */
	public static function fluent_save( $entry_id, $formData, $form ) { // phpcs:ignore
		static $done = array();
		if ( isset( $done[ $entry_id ] ) || ! self::posted( (array) $formData ) ) {
			return;
		}
		$done[ $entry_id ] = true;
		$title             = is_object( $form ) && isset( $form->title ) ? $form->title : '';
		self::log( 'fluent', 'Fluent: ' . $title . ' entry #' . (int) $entry_id, self::find_email( $formData ), self::posted_marketing( (array) $formData ) );
	}

	/* ------------------------------------------------------------------
	 * Universal JS layer - logging endpoint
	 * ------------------------------------------------------------------ */

	public static function ajax_log() {
		check_ajax_referer( 'opg_consent', 'nonce' );

		// Basic flood protection: 30 log calls per IP per hour.
		$key   = 'opg_rl_' . md5( OPG_Plugin::client_ip( false ) );
		$count = (int) get_transient( $key );
		if ( $count > 30 ) {
			wp_send_json_error( 'rate', 429 );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'form';
		if ( 'cookie' === $type ) {
			$cats = isset( $_POST['categories'] ) ? sanitize_text_field( wp_unslash( $_POST['categories'] ) ) : '';
			OPG_Consent_Log::record(
				array(
					'source'   => 'cookie',
					'form_ref' => 'cookie banner',
					'cookies'  => $cats,
					'privacy'  => 0,
					'text'     => OPG_Settings::get( 'cookie_text' ),
				)
			);
			wp_send_json_success();
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$form  = isset( $_POST['form'] ) ? sanitize_text_field( wp_unslash( $_POST['form'] ) ) : '';
		$page  = isset( $_POST['page'] ) ? esc_url_raw( wp_unslash( $_POST['page'] ) ) : '';
		OPG_Consent_Log::record(
			array(
				'source'    => 'universal',
				'form_ref'  => $form,
				'email'     => $email,
				'privacy'   => 1,
				'marketing' => ! empty( $_POST['marketing'] ) ? 1 : 0,
				'page_url'  => $page,
				'text'      => OPG_Settings::get( 'consent_text_privacy' ),
			)
		);
		wp_send_json_success();
	}

	/* ------------------------------------------------------------------
	 * Utilities
	 * ------------------------------------------------------------------ */

	/**
	 * Insert HTML before the first submit control (or before </form>).
	 *
	 * @param string $html   Form HTML.
	 * @param string $insert Insert.
	 * @return string
	 */
	public static function insert_before_submit( $html, $insert ) {
		if ( preg_match( '/<(input|button)[^>]+type=["\']submit["\'][^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			$pos = $m[0][1];
			// Step back to the opening of a wrapping <p> / <div> if it directly contains the submit.
			$before = substr( $html, 0, $pos );
			$p_pos  = strrpos( $before, '<p' );
			$d_pos  = strrpos( $before, '<div' );
			$wrap   = max( false === $p_pos ? -1 : $p_pos, false === $d_pos ? -1 : $d_pos );
			if ( $wrap >= 0 && false === strpos( substr( $before, $wrap ), '</' ) && ! preg_match( '/<(form|fieldset)\b/i', substr( $before, $wrap + 1 ) ) ) {
				$pos = $wrap;
			}
			return substr( $html, 0, $pos ) . $insert . substr( $html, $pos );
		}
		$end = strripos( $html, '</form>' );
		if ( false !== $end ) {
			return substr( $html, 0, $end ) . $insert . substr( $html, $end );
		}
		return $html . $insert;
	}
}
