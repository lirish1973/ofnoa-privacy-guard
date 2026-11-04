<?php
/**
 * Generates public pages (drafts) and internal documents (.doc / print view).
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Generator {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 3 );
	}

	/**
	 * Build the context used by every template.
	 *
	 * @return array
	 */
	public static function context() {
		$s     = OPG_Settings::all();
		$scan  = OPG_Scanner::last();
		$home  = OPG_Detector::fetch( home_url( '/' ) );
		$third = OPG_Detector::third_parties( $home['body'] );

		$dt_labels = OPG_Settings::data_type_labels();
		$pu_labels = OPG_Settings::purpose_labels();
		$dt_en     = array(
			'contact'   => 'Contact details (name, phone, email)',
			'account'   => 'Account details (username, hashed password)',
			'order'     => 'Order and shipping details',
			'payment'   => 'Payment details (processed by the payment provider)',
			'usage'     => 'Usage and browsing data',
			'technical' => 'Technical data (IP address, browser, device, logs)',
			'cookies'   => 'Cookie and advertising identifiers',
			'location'  => 'Approximate location',
			'content'   => 'Content you submit (comments, files, chat messages)',
			'id'        => 'ID / company number (for invoices)',
			'sensitive' => 'Special-category data',
		);
		$pu_en     = array(
			'service'   => 'Providing the service, orders and delivery',
			'support'   => 'Responding to inquiries and customer support',
			'account'   => 'Managing user accounts',
			'billing'   => 'Invoicing and legal obligations',
			'improve'   => 'Analytics and improving the site',
			'marketing' => 'Direct marketing (with consent)',
			'personal'  => 'Personalized content and offers',
			'ads'       => 'Targeted advertising and campaign measurement',
			'security'  => 'Security and fraud prevention',
		);

		$types    = array_values( array_intersect_key( $dt_labels, array_flip( (array) $s['data_types'] ) ) );
		$types_en = array_values( array_intersect_key( $dt_en, array_flip( (array) $s['data_types'] ) ) );
		$purposes = array_values( array_intersect_key( $pu_labels, array_flip( (array) $s['purposes'] ) ) );
		$purp_en  = array_values( array_intersect_key( $pu_en, array_flip( (array) $s['purposes'] ) ) );

		$req_page = (int) $s['requests_page_id'];
		$req_url  = $req_page ? get_permalink( $req_page ) : home_url( '/privacy-requests/' );
		$cookie   = (int) $s['cookie_page_id'];
		$admins   = get_users(
			array(
				'role'   => 'administrator',
				'fields' => array( 'user_login' ),
			)
		);
		$subjects = array(
			'lt10k'    => 'פחות מ-10,000',
			'10k-100k' => '10,000 - 100,000',
			'gt100k'   => 'יותר מ-100,000',
		);
		$level    = 'בסיסית';
		if ( $s['sensitive_data'] || in_array( $s['q_subjects'], array( '10k-100k', 'gt100k' ), true ) ) {
			$level = 'בינונית (מידע רגיש או היקף גדול)';
		}
		if ( $s['sensitive_data'] && 'gt100k' === $s['q_subjects'] ) {
			$level = 'גבוהה';
		}
		$counts = count_users();
		global $wpdb;

		return array(
			'name'               => $s['business_name'],
			'id'                 => $s['business_id'],
			'address'            => $s['business_address'],
			'email'              => $s['contact_email'],
			'phone'              => $s['contact_phone'],
			'officer_name'       => $s['officer_name'],
			'officer_email'      => $s['officer_email'],
			'officer_is_dpo'     => (bool) $s['officer_is_dpo'],
			'domain'             => $s['site_domain'] ? $s['site_domain'] : wp_parse_url( home_url(), PHP_URL_HOST ),
			'site_url'           => home_url( '/' ),
			'date'               => wp_date( 'd/m/Y' ),
			'next_review'        => wp_date( 'd/m/Y', strtotime( '+12 months' ) ),
			'data_types'         => $types ? $types : array( $dt_labels['contact'] ),
			'data_types_en'      => $types_en ? $types_en : array( $dt_en['contact'] ),
			'purposes'           => $purposes ? $purposes : array( $pu_labels['support'] ),
			'purposes_en'        => $purp_en ? $purp_en : array( $pu_en['support'] ),
			'retention'          => $s['retention_text'],
			'abroad'             => (bool) $s['transfer_abroad'],
			'abroad_text'        => $s['abroad_text'],
			'marketing'          => (bool) $s['direct_marketing'],
			'sensitive'          => (bool) $s['sensitive_data'],
			'third'              => $third,
			'forms'              => OPG_Detector::forms(),
			'requests_url'       => $req_url,
			'policy_url'         => OPG_Plugin::policy_url() ? OPG_Plugin::policy_url() : home_url( '/privacy-policy/' ),
			'cookie_url'         => $cookie && 'publish' === get_post_status( $cookie ) ? get_permalink( $cookie ) : '',
			'has_woo'            => class_exists( 'WooCommerce' ),
			'users_can_register' => (bool) get_option( 'users_can_register' ),
			'analytics'          => (bool) array_filter(
				$third,
				function ( $t ) {
					return in_array( $t['category'], array( 'analytics', 'marketing' ), true );
				}
			),
			'log_months'         => (int) $s['log_retention_months'],
			'subjects_label'     => isset( $subjects[ $s['q_subjects'] ] ) ? $subjects[ $s['q_subjects'] ] : '',
			'user_count'         => isset( $counts['total_users'] ) ? (int) $counts['total_users'] : 0,
			'admin_count'        => count( $admins ),
			'admins'             => wp_list_pluck( $admins, 'user_login' ),
			'obligations'        => OPG_Scanner::obligations( $s ),
			'wp_version'         => get_bloginfo( 'version' ),
			'db_prefix'          => $wpdb->prefix,
			'backup'             => OPG_Detector::backup_plugin(),
			'security'           => OPG_Detector::security_plugin(),
			'https'              => 0 === strpos( home_url(), 'https://' ),
			'security_level'     => $level,
			'scan_score'         => $scan ? $scan['score'] : null,
		);
	}

	/**
	 * Render a document body.
	 *
	 * @param string $key Doc key.
	 * @return string
	 */
	public static function render( $key ) {
		$c   = self::context();
		$map = array(
			'privacy_he'       => 'privacy_policy_he',
			'privacy_en'       => 'privacy_policy_en',
			'cookie_policy'    => 'cookie_policy',
			'rights_page'      => 'rights_page',
			'data_map'         => 'data_map',
			'db_definitions'   => 'db_definitions',
			'security_policy'  => 'security_policy',
			'incident_plan'    => 'incident_plan',
			'vendor_dpa'       => 'vendor_dpa',
			'staff_nda'        => 'staff_nda',
			'response_letters' => 'response_letters',
		);
		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}
		return call_user_func( array( 'OPG_Templates', $map[ $key ] ), $c );
	}

	/**
	 * Remember when a doc was generated.
	 *
	 * @param string $key Key.
	 */
	public static function mark( $key ) {
		$docs         = (array) get_option( 'opg_docs', array() );
		$docs[ $key ] = time();
		update_option( 'opg_docs', $docs, false );
		OPG_Audit_Log::log( 'document_generated', $key );
	}

	/**
	 * Create a draft page for a public document.
	 *
	 * @param string $key Key.
	 * @return int|WP_Error Post id.
	 */
	public static function create_page( $key, $status = 'draft' ) {
		$cat = OPG_Templates::catalogue();
		if ( ! isset( $cat[ $key ] ) || 'public' !== $cat[ $key ][1] ) {
			return new WP_Error( 'opg_bad_doc', 'Unknown document' );
		}
		$titles = array(
			'privacy_he'    => 'מדיניות פרטיות',
			'privacy_en'    => 'Privacy Policy',
			'cookie_policy' => 'מדיניות Cookie',
			'rights_page'   => 'בקשות בנושא פרטיות',
		);
		$slugs  = array(
			'privacy_he'    => 'privacy-policy',
			'privacy_en'    => 'privacy-policy-en',
			'cookie_policy' => 'cookie-policy',
			'rights_page'   => 'privacy-requests',
		);
		$body   = self::render( $key );
		if ( 'rights_page' !== $key ) {
			$body = OPG_Templates::review_note() . "\n" . $body;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => in_array( $status, array( 'draft', 'publish' ), true ) ? $status : 'draft',
				'post_title'   => $titles[ $key ],
				'post_name'    => $slugs[ $key ],
				'post_content' => $body,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		update_post_meta( $post_id, '_opg_doc', $key );
		self::mark( $key );

		$s = array();
		if ( 'rights_page' === $key ) {
			$s['requests_page_id'] = $post_id;
		} elseif ( 'cookie_policy' === $key ) {
			$s['cookie_page_id'] = $post_id;
		} elseif ( 'privacy_en' === $key ) {
			$s['policy_page_en_id'] = $post_id;
		}
		if ( $s ) {
			OPG_Settings::update( $s );
		}
		return $post_id;
	}

	/**
	 * One click: create (or reuse) the rights-request page, publish it, and link it
	 * from the privacy policy. The footer bar links it automatically.
	 *
	 * @return array{page_id:int,policy_linked:bool,note:string}|WP_Error
	 */
	public static function setup_rights_page() {
		$id = (int) OPG_Settings::get( 'requests_page_id' );
		if ( $id && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
			if ( 'publish' !== get_post_status( $id ) ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'publish',
					)
				);
			}
			if ( ! has_shortcode( (string) get_post_field( 'post_content', $id ), 'opg_rights_request' ) ) {
				wp_update_post(
					array(
						'ID'           => $id,
						'post_content' => get_post_field( 'post_content', $id ) . "\n[opg_rights_request]",
					)
				);
			}
		} else {
			$id = self::create_page( 'rights_page', 'publish' );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
		}
		$linked = self::link_from_policy( (int) $id );
		return array(
			'page_id'       => (int) $id,
			'policy_linked' => $linked,
		);
	}

	/**
	 * Add a link to the rights page at the end of the privacy policy (classic / block content).
	 * Pages built with Elementor are left alone - the footer bar covers them.
	 *
	 * @param int $page_id Rights page.
	 * @return bool Linked (now or already).
	 */
	public static function link_from_policy( $page_id ) {
		$policy_id = (int) get_option( 'wp_page_for_privacy_policy' );
		$policy    = $policy_id ? get_post( $policy_id ) : null;
		if ( ! $policy || ! $page_id ) {
			return false;
		}
		$url = get_permalink( $page_id );
		if ( false !== strpos( $policy->post_content, $url ) || false !== strpos( $policy->post_content, 'page_id=' . $page_id ) || false !== strpos( $policy->post_content, 'opg-rights-link' ) ) {
			return true;
		}
		if ( 'builder' === get_post_meta( $policy_id, '_elementor_edit_mode', true ) ) {
			return false;
		}
		$para = '<p class="opg-rights-link">לבקשות עיון במידע, תיקון, מחיקה או הסרה מדיוור - מלאו את <a href="' . esc_url( $url ) . '">טופס בקשות הפרטיות</a>.</p>';
		if ( function_exists( 'has_blocks' ) && has_blocks( $policy->post_content ) ) {
			$para = "<!-- wp:paragraph {\"className\":\"opg-rights-link\"} -->\n" . $para . "\n<!-- /wp:paragraph -->";
		}
		$res = wp_update_post(
			array(
				'ID'           => $policy_id,
				'post_content' => rtrim( $policy->post_content ) . "\n\n" . $para,
			),
			true
		);
		return ! is_wp_error( $res );
	}

	/**
	 * When a generated privacy policy is published - make it the WP privacy page.
	 *
	 * @param string  $new  New status.
	 * @param string  $old  Old status.
	 * @param WP_Post $post Post.
	 */
	public static function on_publish( $new, $old, $post ) {
		if ( 'publish' !== $new || 'publish' === $old || 'page' !== $post->post_type ) {
			return;
		}
		if ( 'privacy_he' === get_post_meta( $post->ID, '_opg_doc', true ) ) {
			update_option( 'wp_page_for_privacy_policy', $post->ID );
		}
	}

	/**
	 * Wrap an internal document in a standalone RTL HTML document.
	 *
	 * @param string $key   Key.
	 * @param bool   $print Add print button.
	 * @return string
	 */
	public static function standalone( $key, $print = false ) {
		$cat   = OPG_Templates::catalogue();
		$title = isset( $cat[ $key ] ) ? $cat[ $key ][0] : $key;
		$dir   = 'privacy_en' === $key ? 'ltr' : 'rtl';
		$css   = 'body{font-family:Arial,"Segoe UI",sans-serif;font-size:11pt;line-height:1.6;color:#222;max-width:900px;margin:24px auto;padding:0 20px}'
			. 'h1{font-size:18pt;border-bottom:2px solid #1d4ed8;padding-bottom:6px}h2{font-size:13pt;color:#1d4ed8;margin-top:22px}'
			. 'table{border-collapse:collapse;width:100%;margin:10px 0}td,th{border:1px solid #999;padding:6px;vertical-align:top;text-align:' . ( 'rtl' === $dir ? 'right' : 'left' ) . '}th{background:#eef2ff}'
			. '.opg-meta td:first-child{width:30%;background:#f6f6f6;font-weight:bold}.opg-warn{background:#fff8e1;border:1px solid #f0c36d;padding:8px}'
			. '.opg-print{position:fixed;top:12px;left:12px;padding:8px 14px;background:#1d4ed8;color:#fff;border:0;border-radius:6px;cursor:pointer}@media print{.opg-print{display:none}}';
		$html  = '<!DOCTYPE html><html dir="' . $dir . '" lang="' . ( 'rtl' === $dir ? 'he' : 'en' ) . '" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title><style>' . $css . '</style></head><body>';
		if ( $print ) {
			$html .= '<button class="opg-print" onclick="window.print()">הדפסה / שמירה כ-PDF</button>';
		}
		$html .= self::render( $key ) . '</body></html>';
		return $html;
	}

	/**
	 * Stream an internal document as .doc.
	 *
	 * @param string $key Key.
	 */
	public static function download( $key ) {
		$html = self::standalone( $key );
		self::mark( $key );
		nocache_headers();
		header( 'Content-Type: application/msword; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $key . '-' . gmdate( 'Y-m-d' ) . '.doc' ) );
		echo "\xEF\xBB\xBF" . $html; // phpcs:ignore
		exit;
	}

	/**
	 * Print view.
	 *
	 * @param string $key Key.
	 */
	public static function view( $key ) {
		self::mark( $key );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo self::standalone( $key, true ); // phpcs:ignore
		exit;
	}
}
