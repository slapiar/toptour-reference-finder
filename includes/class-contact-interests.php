<?php
/**
 * Contact interests relation class.
 *
 * Internal relationship metadata between contacts and interests.
 *
 * @package Toptour_Ref
 * @version 0.1.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Contact_Interests {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_contact_interests';
	}

	public static function get_allowed_interest_levels() {
		return [ 'low', 'medium', 'high', 'expert' ];
	}

	public static function get_allowed_relationship_types() {
		return [ 'personal_interest', 'professional_skill', 'business_offer', 'local_knowledge', 'strategic_value', 'potential_partner', 'verified_experience' ];
	}

	public static function get_interests_for_contact( $contact_id ) {
		global $wpdb;
		$table = self::get_table_name();
		$interests_table = Toptour_Ref_Interests::get_table_name();
		$sql = "SELECT ci.*, i.name, i.interest_key
			FROM $table ci
			LEFT JOIN $interests_table i ON i.id = ci.interest_id
			WHERE ci.contact_id = %d
			ORDER BY i.name ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, absint( $contact_id ) ) );
	}

	public static function get_interest_ids_for_contact( $contact_id ) {
		$rows = self::get_interests_for_contact( $contact_id );
		$ids = [];
		foreach ( $rows as $row ) {
			$ids[] = (int) $row->interest_id;
		}
		return $ids;
	}

	public static function replace_contact_interests( $contact_id, $interest_rows ) {
		global $wpdb;
		$table = self::get_table_name();
		$contact_id = absint( $contact_id );
		$deleted = $wpdb->delete( $table, [ 'contact_id' => $contact_id ] );
		if ( false === $deleted ) {
			return false;
		}

		if ( empty( $interest_rows ) || ! is_array( $interest_rows ) ) {
			return true;
		}

		$now = current_time( 'mysql' );
		foreach ( $interest_rows as $row ) {
			$interest_id = absint( $row['interest_id'] ?? 0 );
			if ( $interest_id <= 0 ) {
				continue;
			}

			$interest_level = sanitize_text_field( $row['interest_level'] ?? 'medium' );
			if ( ! in_array( $interest_level, self::get_allowed_interest_levels(), true ) ) {
				$interest_level = 'medium';
			}

			$relationship_type = sanitize_text_field( $row['relationship_type'] ?? 'personal_interest' );
			if ( ! in_array( $relationship_type, self::get_allowed_relationship_types(), true ) ) {
				$relationship_type = 'personal_interest';
			}

			$inserted = $wpdb->insert(
				$table,
				[
					'contact_id'         => $contact_id,
					'interest_id'        => $interest_id,
					'interest_level'     => $interest_level,
					'relationship_type'  => $relationship_type,
					'notes'              => sanitize_textarea_field( $row['notes'] ?? '' ),
					'created_at'         => $now,
					'updated_at'         => $now,
				]
			);

			if ( false === $inserted ) {
				return false;
			}
		}

		return true;
	}

	public static function count_contacts_for_interest( $interest_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT contact_id) FROM $table WHERE interest_id = %d", absint( $interest_id ) ) );
	}

	public static function get_interest_names_for_contact( $contact_id ) {
		$rows = self::get_interests_for_contact( $contact_id );
		$names = [];
		foreach ( $rows as $row ) {
			$names[] = (string) $row->name;
		}
		return $names;
	}

	public static function get_interest_names_for_contacts( $contact_ids ) {
		global $wpdb;
		$contact_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $contact_ids ) ) ) );
		if ( empty( $contact_ids ) ) {
			return [];
		}

		$table = self::get_table_name();
		$interests_table = Toptour_Ref_Interests::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
		$sql = "SELECT ci.contact_id, i.name
			FROM $table ci
			LEFT JOIN $interests_table i ON i.id = ci.interest_id
			WHERE ci.contact_id IN ($placeholders)
			ORDER BY ci.contact_id ASC, i.name ASC";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $contact_ids ) );

		$map = [];
		foreach ( $rows as $row ) {
			$contact_id = (int) $row->contact_id;
			if ( ! isset( $map[ $contact_id ] ) ) {
				$map[ $contact_id ] = [];
			}
			$map[ $contact_id ][] = (string) $row->name;
		}
		return $map;
	}
}
