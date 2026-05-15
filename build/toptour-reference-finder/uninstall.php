<?php
/**
 * Plugin uninstall file.
 *
 * Executed when the plugin is uninstalled via WordPress plugin management.
 *
 * @package Toptour_Ref
 * @version 0.1.0
 */

// Exit if uninstall.php is not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin options on uninstall.
 *
 * NOTE: Database tables are NOT dropped on uninstall to prevent accidental data loss.
 * Administrators can manually delete tables if needed via phpMyAdmin or WP CLI.
 */
function toptour_ref_uninstall() {
	// Get WordPress database interface.
	global $wpdb;

	// Delete plugin options.
	delete_option( 'toptour_ref_version' );
	delete_option( 'toptour_ref_db_version' );
	delete_option( 'toptour_ref_activated' );

	// Log uninstall for debugging.
	error_log( 'TOPTOUR Reference Finder plugin uninstalled at ' . current_time( 'mysql' ) );
}

// Run uninstall cleanup.
toptour_ref_uninstall();
