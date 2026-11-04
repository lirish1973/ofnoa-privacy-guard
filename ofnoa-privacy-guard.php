<?php
/**
 * Plugin Name:       Ofnoa Privacy Guard
 * Plugin URI:        https://github.com/lirish1973/ofnoa-privacy-guard
 * Description:       סורק ציות לחוק הגנת הפרטיות (תיקון 13): בודק מדיניות פרטיות, טפסים, צדדים שלישיים ואבטחה; מוסיף צ'קבוקסים של הסכמה לכל הטפסים, שומר יומן הסכמות כהוכחה, מנהל בקשות עיון/תיקון/מחיקה ומייצר את המסמכים הנדרשים.
 * Version:           1.0.6
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Ofnoacomps
 * Author URI:        https://ofnoacomps.co.il
 * License:           GPL-2.0-or-later
 * Text Domain:       ofnoa-privacy-guard
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'OPG_VERSION', '1.0.6' );
define( 'OPG_FILE', __FILE__ );
define( 'OPG_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPG_URL', plugin_dir_url( __FILE__ ) );
define( 'OPG_BASENAME', plugin_basename( __FILE__ ) );
define( 'OPG_DB_VERSION', '1' );
define( 'OPG_GITHUB_REPO', 'lirish1973/ofnoa-privacy-guard' );

$opg_files = array(
	'class-opg-settings.php',
	'class-opg-plugin.php',
	'class-opg-audit-log.php',
	'class-opg-consent-log.php',
	'class-opg-consent.php',
	'class-opg-requests.php',
	'class-opg-cookie-banner.php',
	'class-opg-detector.php',
	'class-opg-scanner.php',
	'class-opg-templates.php',
	'class-opg-generator.php',
	'class-opg-core-privacy.php',
	'class-opg-admin.php',
	'class-opg-updater.php',
);
foreach ( $opg_files as $opg_file ) {
	require_once OPG_DIR . 'includes/' . $opg_file;
}
unset( $opg_files, $opg_file );

register_activation_hook( __FILE__, array( 'OPG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OPG_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'OPG_Plugin', 'init' ) );
