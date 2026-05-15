<?php
/**
 * Plugin capabilities class.
 *
 * Defines and registers plugin capabilities.
 *
 * @package Toptour_Ref
 * @version 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capabilities class for managing user permissions.
 */
class Toptour_Ref_Capabilities {

	/**
	 * Register plugin capabilities.
	 *
	 * @return void
	 */
	public static function register() {
		self::add_capabilities();
	}

	/**
	 * Add plugin capabilities to administrator role.
	 *
	 * @return void
	 */
	private static function add_capabilities() {
		// Get administrator role.
		$admin_role = get_role( 'administrator' );

		// Bail if role does not exist.
		if ( ! $admin_role ) {
			return;
		}

		// Add main capability for managing reference data.
		if ( ! $admin_role->has_cap( 'manage_toptour_references' ) ) {
			$admin_role->add_cap( 'manage_toptour_references' );
		}

		// Add granular sub-capabilities for future use.
		$capabilities = array(
			'read_toptour_references',
			'edit_toptour_references',
			'delete_toptour_references',
			'manage_toptour_facilities',
			'manage_toptour_destinations',
			'manage_toptour_offers',
			'manage_toptour_sources',
			'manage_toptour_findings',
			'manage_toptour_photo_evidence',
			'manage_toptour_collection_tasks',
		);

		foreach ( $capabilities as $capability ) {
			if ( ! $admin_role->has_cap( $capability ) ) {
				$admin_role->add_cap( $capability );
			}
		}
	}

	/**
	 * Check if current user can manage TOPTOUR references.
	 *
	 * @return bool True if user has capability, false otherwise.
	 */
	public static function user_can_manage_references() {
		return current_user_can( 'manage_toptour_references' );
	}
}
