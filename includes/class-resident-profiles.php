<?php
/**
 * Resident profiles data class.
 *
 * Internal resident profile layer for contacts.
 *
 * @package Toptour_Ref
 * @version 0.1.8
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resident profiles helper class.
 */
class Toptour_Ref_Resident_Profiles {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_resident_profiles';
	}

	/**
	 * Get allowed resident types.
	 *
	 * @return string[]
	 */
	public static function get_allowed_resident_types() {
		return [ 'local_helper', 'local_guide', 'facility_contact', 'destination_observer', 'transport_helper', 'experience_host', 'emergency_helper', 'community_connector', 'other' ];
	}

	/**
	 * Get allowed availability statuses.
	 *
	 * @return string[]
	 */
	public static function get_allowed_availability_statuses() {
		return [ 'unknown', 'available', 'limited', 'unavailable', 'seasonal' ];
	}

	/**
	 * Get allowed verification statuses.
	 *
	 * @return string[]
	 */
	public static function get_allowed_verification_statuses() {
		return [ 'unverified', 'known', 'contacted', 'verified', 'trusted' ];
	}

	/**
	 * Get allowed badge statuses.
	 *
	 * @return string[]
	 */
	public static function get_allowed_badge_statuses() {
		return [ 'none', 'planned', 'issued', 'suspended', 'revoked' ];
	}

	/**
	 * Get profile by contact ID.
	 *
	 * @param int $contact_id Contact ID.
	 * @return object|null
	 */
	public static function get_profile_by_contact_id( $contact_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE contact_id = %d", absint( $contact_id ) ) );
	}

	/**
	 * Check if contact has a resident profile.
	 *
	 * @param int $contact_id Contact ID.
	 * @return bool
	 */
	public static function contact_has_profile( $contact_id ) {
		return null !== self::get_profile_by_contact_id( $contact_id );
	}

	/**
	 * Get has_profile flags for contact IDs.
	 *
	 * @param int[] $contact_ids Contact IDs.
	 * @return array<int,bool>
	 */
	public static function get_profile_flags_for_contacts( $contact_ids ) {
		global $wpdb;
		$contact_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $contact_ids ) ) ) );
		if ( empty( $contact_ids ) ) {
			return [];
		}

		$table = self::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
		$sql = "SELECT contact_id FROM $table WHERE contact_id IN ($placeholders)";
		$rows = $wpdb->get_col( $wpdb->prepare( $sql, $contact_ids ) );

		$flags = [];
		foreach ( $contact_ids as $contact_id ) {
			$flags[ $contact_id ] = false;
		}
		foreach ( $rows as $contact_id ) {
			$flags[ (int) $contact_id ] = true;
		}
		return $flags;
	}

	/**
	 * Create or update profile by contact ID.
	 *
	 * @param int   $contact_id Contact ID.
	 * @param array $data Validated profile data.
	 * @return bool
	 */
	public static function upsert_profile( $contact_id, $data ) {
		global $wpdb;
		$table = self::get_table_name();
		$contact_id = absint( $contact_id );
		$existing = self::get_profile_by_contact_id( $contact_id );

		if ( $existing ) {
			$result = $wpdb->update(
				$table,
				[
					'resident_type'        => $data['resident_type'],
					'availability_status'  => $data['availability_status'],
					'verification_status'  => $data['verification_status'],
					'badge_status'         => $data['badge_status'],
					'qr_code_token'        => $data['qr_code_token'],
					'notes'                => $data['notes'],
					'updated_at'           => current_time( 'mysql' ),
				],
				[ 'contact_id' => $contact_id ]
			);
			return $result !== false;
		}

		$result = $wpdb->insert(
			$table,
			[
				'contact_id'           => $contact_id,
				'resident_type'        => $data['resident_type'],
				'availability_status'  => $data['availability_status'],
				'verification_status'  => $data['verification_status'],
				'badge_status'         => $data['badge_status'],
				'qr_code_token'        => $data['qr_code_token'],
				'notes'                => $data['notes'],
				'created_at'           => current_time( 'mysql' ),
				'updated_at'           => current_time( 'mysql' ),
			]
		);
		return (bool) $result;
	}

	/**
	 * Delete profile by contact ID.
	 *
	 * @param int $contact_id Contact ID.
	 * @return bool
	 */
	public static function delete_profile_by_contact_id( $contact_id ) {
		global $wpdb;
		$table = self::get_table_name();
		$result = $wpdb->delete( $table, [ 'contact_id' => absint( $contact_id ) ] );
		return $result !== false;
	}

	/**
	 * Sanitize profile data.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_profile_data( $input ) {
		return [
			'resident_type'        => sanitize_text_field( $input['resident_type'] ?? 'local_helper' ),
			'availability_status'  => sanitize_text_field( $input['availability_status'] ?? 'unknown' ),
			'verification_status'  => sanitize_text_field( $input['verification_status'] ?? 'unverified' ),
			'badge_status'         => sanitize_text_field( $input['badge_status'] ?? 'none' ),
			'qr_code_token'        => sanitize_text_field( $input['qr_code_token'] ?? '' ),
			'notes'                => sanitize_textarea_field( $input['resident_notes'] ?? '' ),
		];
	}

	/**
	 * Validate profile data.
	 *
	 * @param array $data Sanitized data.
	 * @return true|string[]
	 */
	public static function validate_profile_data( $data ) {
		$errors = [];

		if ( ! in_array( $data['resident_type'], self::get_allowed_resident_types(), true ) ) {
			$errors[] = 'invalid resident_type';
		}

		if ( ! in_array( $data['availability_status'], self::get_allowed_availability_statuses(), true ) ) {
			$errors[] = 'invalid availability_status';
		}

		if ( ! in_array( $data['verification_status'], self::get_allowed_verification_statuses(), true ) ) {
			$errors[] = 'invalid verification_status';
		}

		if ( ! in_array( $data['badge_status'], self::get_allowed_badge_statuses(), true ) ) {
			$errors[] = 'invalid badge_status';
		}

		return $errors ? $errors : true;
	}
}
