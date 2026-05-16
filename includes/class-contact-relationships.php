<?php
/**
 * Contact relationships data class.
 *
 * Internal directional relationship records for contacts.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Contact_Relationships {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_contact_relationships';
	}

	public static function get_allowed_relationship_types() {
		return [ 'knows', 'family', 'friend', 'business_partner', 'supplier', 'client', 'local_partner', 'community_member', 'recommended_by', 'conflict', 'unknown' ];
	}

	public static function get_allowed_relationship_strengths() {
		return [ 'weak', 'medium', 'strong', 'critical' ];
	}

	public static function get_allowed_mutuality_levels() {
		return [ 'unknown', 'one_way', 'balanced', 'strong_mutual', 'strategic' ];
	}

	public static function get_default_relationship_row() {
		return [
			'contact_id'            => 0,
			'related_contact_id'    => 0,
			'relationship_type'     => 'knows',
			'relationship_strength' => 'medium',
			'mutuality_level'       => 'unknown',
			'trust_note'            => '',
			'notes'                 => '',
			'remove'                => 0,
		];
	}

	public static function sanitize_relationship_row( $row ) {
		$row = is_array( $row ) ? $row : [];
		return [
			'contact_id'            => absint( $row['contact_id'] ?? 0 ),
			'related_contact_id'    => absint( $row['related_contact_id'] ?? 0 ),
			'relationship_type'     => sanitize_text_field( $row['relationship_type'] ?? 'knows' ),
			'relationship_strength' => sanitize_text_field( $row['relationship_strength'] ?? 'medium' ),
			'mutuality_level'       => sanitize_text_field( $row['mutuality_level'] ?? 'unknown' ),
			'trust_note'            => sanitize_textarea_field( $row['trust_note'] ?? '' ),
			'notes'                 => sanitize_textarea_field( $row['notes'] ?? '' ),
			'remove'                => isset( $row['remove'] ) ? 1 : 0,
		];
	}

	public static function sanitize_relationship_rows( $rows ) {
		$rows = is_array( $rows ) ? $rows : [];
		$sanitized = [];
		foreach ( $rows as $row ) {
			$sanitized[] = self::sanitize_relationship_row( $row );
		}
		return $sanitized;
	}

	public static function validate_relationship_row( $row ) {
		$errors = [];
		$row = self::sanitize_relationship_row( $row );

		if ( absint( $row['contact_id'] ) <= 0 ) {
			$errors[] = 'contact_id is required';
		}

		if ( absint( $row['related_contact_id'] ) <= 0 ) {
			$errors[] = 'related_contact_id is required';
		}

		if ( absint( $row['related_contact_id'] ) === absint( $row['contact_id'] ) ) {
			$errors[] = 'related_contact_id cannot equal contact_id';
		}

		if ( ! in_array( $row['relationship_type'], self::get_allowed_relationship_types(), true ) ) {
			$errors[] = 'invalid relationship_type';
		}

		if ( ! in_array( $row['relationship_strength'], self::get_allowed_relationship_strengths(), true ) ) {
			$errors[] = 'invalid relationship_strength';
		}

		if ( ! in_array( $row['mutuality_level'], self::get_allowed_mutuality_levels(), true ) ) {
			$errors[] = 'invalid mutuality_level';
		}

		if ( absint( $row['related_contact_id'] ) > 0 ) {
			$related = Toptour_Ref_Contacts::get_contact( absint( $row['related_contact_id'] ) );
			if ( ! $related ) {
				$errors[] = 'related contact not found';
			}
		}

		return $errors ? $errors : true;
	}

	public static function is_empty_relationship_row( $row ) {
		$row = self::sanitize_relationship_row( $row );
		if ( absint( $row['related_contact_id'] ) > 0 ) {
			return false;
		}

		if ( '' !== trim( (string) $row['trust_note'] ) ) {
			return false;
		}

		if ( '' !== trim( (string) $row['notes'] ) ) {
			return false;
		}

		if ( 'knows' !== $row['relationship_type'] ) {
			return false;
		}

		if ( 'medium' !== $row['relationship_strength'] ) {
			return false;
		}

		if ( 'unknown' !== $row['mutuality_level'] ) {
			return false;
		}

		return true;
	}

	public static function get_relationships_for_contact( $contact_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE contact_id = %d ORDER BY created_at ASC, id ASC", absint( $contact_id ) ) );
	}

	public static function replace_contact_relationships( $contact_id, $rows ) {
		global $wpdb;
		$table = self::get_table_name();
		$contact_id = absint( $contact_id );

		$deleted = $wpdb->delete( $table, [ 'contact_id' => $contact_id ] );
		if ( false === $deleted ) {
			return false;
		}

		$rows = self::sanitize_relationship_rows( $rows );
		if ( empty( $rows ) ) {
			return true;
		}

		$seen = [];
		$now = current_time( 'mysql' );
		foreach ( $rows as $row ) {
			if ( ! empty( $row['remove'] ) ) {
				continue;
			}

			if ( self::is_empty_relationship_row( $row ) ) {
				continue;
			}

			$row['contact_id'] = $contact_id;
			if ( absint( $row['related_contact_id'] ) <= 0 ) {
				continue;
			}

			if ( absint( $row['related_contact_id'] ) === $contact_id ) {
				continue;
			}

			$valid = self::validate_relationship_row( $row );
			if ( true !== $valid ) {
				continue;
			}

			$dedupe_key = $row['contact_id'] . '|' . absint( $row['related_contact_id'] ) . '|' . $row['relationship_type'];
			if ( isset( $seen[ $dedupe_key ] ) ) {
				continue;
			}
			$seen[ $dedupe_key ] = true;

			$inserted = $wpdb->insert(
				$table,
				[
					'contact_id'            => $row['contact_id'],
					'related_contact_id'    => absint( $row['related_contact_id'] ),
					'relationship_type'     => $row['relationship_type'],
					'relationship_strength' => $row['relationship_strength'],
					'mutuality_level'       => $row['mutuality_level'],
					'trust_note'            => $row['trust_note'],
					'notes'                 => $row['notes'],
					'created_at'            => $now,
					'updated_at'            => $now,
				]
			);

			if ( false === $inserted ) {
				return false;
			}
		}

		return true;
	}

	public static function count_relationships_for_contact( $contact_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE contact_id = %d", absint( $contact_id ) ) );
	}

	public static function get_relationship_counts_for_contacts( $contact_ids ) {
		global $wpdb;
		$contact_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $contact_ids ) ) ) );
		if ( empty( $contact_ids ) ) {
			return [];
		}

		$table = self::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
		$sql = "SELECT contact_id, COUNT(*) AS rel_count FROM $table WHERE contact_id IN ($placeholders) GROUP BY contact_id";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $contact_ids ) );

		$map = [];
		foreach ( $rows as $row ) {
			$map[ (int) $row->contact_id ] = (int) $row->rel_count;
		}
		return $map;
	}

	public static function get_relationship_summary_label( $contact_id ) {
		$count = self::count_relationships_for_contact( $contact_id );
		return self::get_relationship_summary_label_from_count( $count );
	}

	public static function get_relationship_summary_label_from_count( $count ) {
		$count = absint( $count );
		if ( $count <= 0 ) {
			return '—';
		}
		if ( 1 === $count ) {
			return '1 vzťah';
		}
		if ( $count >= 2 && $count <= 4 ) {
			return $count . ' vzťahy';
		}
		return $count . ' vzťahov';
	}

	public static function get_contact_label( $contact_id ) {
		$contact_id = absint( $contact_id );
		if ( $contact_id <= 0 ) {
			return 'contact#0';
		}

		$contact = Toptour_Ref_Contacts::get_contact( $contact_id );
		if ( $contact && isset( $contact->display_name ) && '' !== trim( (string) $contact->display_name ) ) {
			return (string) $contact->display_name;
		}

		return 'contact#' . $contact_id;
	}
}
