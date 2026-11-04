<?php
/**
 * Auto-updates from GitHub Releases (no API token, no API rate limit).
 *
 * Reads the latest tag from the 302 redirect of github.com/<repo>/releases/latest and
 * downloads releases/download/vX.Y.Z/ofnoa-privacy-guard.zip.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'ABSPATH' ) || exit;

class OPG_Updater {

	const SLUG      = 'ofnoa-privacy-guard';
	const TRANSIENT = 'opg_update_info';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ) );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'plugin_action_links_' . OPG_BASENAME, array( __CLASS__, 'action_links' ) );
		add_action( 'admin_init', array( __CLASS__, 'manual_check' ) );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
	}

	/**
	 * Latest release {version, package, url}.
	 *
	 * @param bool $force Skip cache.
	 * @return array|null
	 */
	public static function latest( $force = false ) {
		$cached = get_site_transient( self::TRANSIENT );
		if ( ! $force && is_array( $cached ) ) {
			return $cached['version'] ? $cached : null;
		}
		$repo = OPG_GITHUB_REPO;
		$res  = wp_remote_head(
			"https://github.com/{$repo}/releases/latest",
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'user-agent'  => 'OfnoaPrivacyGuard/' . OPG_VERSION,
			)
		);
		$version = '';
		if ( ! is_wp_error( $res ) ) {
			$loc = wp_remote_retrieve_header( $res, 'location' );
			if ( is_array( $loc ) ) {
				$loc = end( $loc );
			}
			if ( $loc && preg_match( '#/releases/tag/v?([0-9][0-9A-Za-z.\-]*)#', (string) $loc, $m ) ) {
				$version = $m[1];
			}
		}
		$data = array(
			'version' => $version,
			'package' => $version ? "https://github.com/{$repo}/releases/download/v{$version}/" . self::SLUG . '.zip' : '',
			'url'     => "https://github.com/{$repo}",
			'checked' => time(),
		);
		set_site_transient( self::TRANSIENT, $data, $version ? HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
		return $version ? $data : null;
	}

	/**
	 * Inject into the update transient.
	 *
	 * @param object $transient Transient.
	 * @return object
	 */
	public static function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$latest = self::latest();
		$item   = (object) array(
			'id'          => OPG_GITHUB_REPO,
			'slug'        => self::SLUG,
			'plugin'      => OPG_BASENAME,
			'new_version' => $latest ? $latest['version'] : OPG_VERSION,
			'url'         => 'https://github.com/' . OPG_GITHUB_REPO,
			'package'     => $latest ? $latest['package'] : '',
			'tested'      => get_bloginfo( 'version' ),
			'requires_php'=> '7.4',
			'icons'       => array(),
		);
		if ( $latest && version_compare( $latest['version'], OPG_VERSION, '>' ) ) {
			if ( ! isset( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ OPG_BASENAME ] = $item;
			if ( isset( $transient->no_update[ OPG_BASENAME ] ) ) {
				unset( $transient->no_update[ OPG_BASENAME ] );
			}
		} else {
			if ( ! isset( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$item->new_version                    = OPG_VERSION;
			$transient->no_update[ OPG_BASENAME ] = $item;
		}
		return $transient;
	}

	/**
	 * "View details" popup.
	 *
	 * @param mixed  $result Result.
	 * @param string $action Action.
	 * @param object $args   Args.
	 * @return mixed
	 */
	public static function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$latest = self::latest();
		return (object) array(
			'name'          => 'Ofnoa Privacy Guard',
			'slug'          => self::SLUG,
			'version'       => $latest ? $latest['version'] : OPG_VERSION,
			'author'        => '<a href="https://ofnoacomps.co.il">Ofnoacomps</a>',
			'homepage'      => 'https://github.com/' . OPG_GITHUB_REPO,
			'download_link' => $latest ? $latest['package'] : '',
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'sections'      => array(
				'description' => 'סורק ציות לחוק הגנת הפרטיות (תיקון 13), הסכמות בטפסים, יומן הסכמות, בקשות זכויות ומחולל מסמכים.',
				'changelog'   => '<p>ראו <a href="https://github.com/' . OPG_GITHUB_REPO . '/releases" target="_blank">GitHub Releases</a>.</p>',
			),
		);
	}

	/**
	 * Plugin row links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$check = wp_nonce_url( admin_url( 'plugins.php?opg_check_update=1' ), 'opg_check_update' );
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=opg-scan' ) ) . '">דוח פרטיות</a>' );
		$links[] = '<a href="' . esc_url( $check ) . '">בדיקת עדכונים</a>';
		return $links;
	}

	/**
	 * Handle "check for updates".
	 */
	public static function manual_check() {
		if ( empty( $_GET['opg_check_update'] ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		check_admin_referer( 'opg_check_update' );
		delete_site_transient( self::TRANSIENT );
		self::latest( true );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'plugins.php?plugin_status=all' ) );
		exit;
	}

	/**
	 * Make sure the extracted folder is named ofnoa-privacy-guard.
	 *
	 * @param string      $source        Source.
	 * @param string      $remote_source Remote.
	 * @param WP_Upgrader $upgrader      Upgrader.
	 * @param array       $extra         Extra.
	 * @return string|WP_Error
	 */
	public static function fix_folder( $source, $remote_source, $upgrader, $extra = array() ) {
		if ( empty( $extra['plugin'] ) || OPG_BASENAME !== $extra['plugin'] ) {
			return $source;
		}
		global $wp_filesystem;
		$target = trailingslashit( $remote_source ) . self::SLUG . '/';
		if ( trailingslashit( $source ) === $target || ! $wp_filesystem ) {
			return $source;
		}
		if ( $wp_filesystem->move( $source, $target, true ) ) {
			return $target;
		}
		return $source;
	}
}
