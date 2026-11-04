<?php
/**
 * Uninstall.
 *
 * The consent log and the rights-request log are legal evidence, so the tables are kept
 * unless the site owner explicitly defines OPG_DELETE_DATA as true in wp-config.php.
 *
 * @package Ofnoa_Privacy_Guard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'opg_weekly_scan' );
wp_clear_scheduled_hook( 'opg_daily_cleanup' );
delete_site_transient( 'opg_update_info' );

if ( defined( 'OPG_DELETE_DATA' ) && OPG_DELETE_DATA ) {
	global $wpdb;
	foreach ( array( 'opg_consent_log', 'opg_requests', 'opg_audit_log' ) as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore
	}
	foreach ( array( 'opg_settings', 'opg_last_scan', 'opg_docs', 'opg_db_version', 'opg_activated_at' ) as $option ) {
		delete_option( $option );
	}
}
