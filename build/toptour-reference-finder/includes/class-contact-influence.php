<?php
/**
 * Contact influence data class.
 *
 * Internal registry of influence/usefulness/mutuality records
 * for contacts against multiple targets.
 *
 * @package Toptour_Ref
 * @version 0.1.10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Contact_Influence {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_contact_influence';
	}

	public static function get_allowed_target_types() {
		return [ 'general', 'destination', 'facility', 'interest', 'contact', 'point_of_interest' ];
	}

	public static function get_allowed_levels() {
		return [ 'unknown', 'low', 'medium', 'high', 'critical' ];
	}

	public static function get_allowed_influence_types() {
		return [
			'local_authority',
			'social_network',
			'business_access',
			'operational_help',
			'knowledge_source',
			'trust_bridge',
			'marketing_reach',
			'safety_support',
			'logistics_support',
			'community_connector',
		];
	}

	public static function get_allowed_usefulness_levels() {
		return [ 'unknown', 'low', 'medium', 'high', 'exceptional' ];
	}

	public static function get_allowed_mutuality_levels() {
		return [ 'unknown', 'one_way', 'balanced', 'strong_mutual', 'strategic' ];
	}

	public static function get_record( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	public static function get_records_for_contact( $contact_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE contact_id = %d ORDER BY created_at ASC, id ASC", absint( $contact_id ) ) );
	}

	public static function create_record( $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'contact_id'       => $data['contact_id'],
				'target_type'      => $data['target_type'],
				'target_id'        => $data['target_id'],
				'point_label'      => $data['point_label'],
				'influence_type'   => $data['influence_type'],
				'influence_level'  => $data['influence_level'],
				'usefulness_level' => $data['usefulness_level'],
				'mutuality_level'  => $data['mutuality_level'],
				'evidence_note'    => $data['evidence_note'],
				'notes'            => $data['notes'],
				'created_at'       => $now,
				'updated_at'       => $now,
			]
		);
		return $result ? $wpdb->insert_id : false;
	}

	public static function update_record( $id, $data ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'contact_id'       => $data['contact_id'],
				'target_type'      => $data['target_type'],
				'target_id'        => $data['target_id'],
				'point_label'      => $data['point_label'],
				'influence_type'   => $data['influence_type'],
				'influence_level'  => $data['influence_level'],
				'usefulness_level' => $data['usefulness_level'],
				'mutuality_level'  => $data['mutuality_level'],
				'evidence_note'    => $data['evidence_note'],
				'notes'            => $data['notes'],
				'updated_at'       => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	public static function delete_record( $id ) {
		global $wpdb;
		$result = $wpdb->delete( self::get_table_name(), [ 'id' => absint( $id ) ] );
		return false !== $result;
	}

	public static function sanitize_record_data( $input ) {
		return [
			'contact_id'       => absint( $input['contact_id'] ?? 0 ),
			'target_type'      => sanitize_text_field( $input['target_type'] ?? 'general' ),
			'target_id'        => absint( $input['target_id'] ?? 0 ),
			'point_label'      => sanitize_text_field( $input['point_label'] ?? '' ),
			'influence_type'   => sanitize_text_field( $input['influence_type'] ?? '' ),
			'influence_level'  => sanitize_text_field( $input['influence_level'] ?? 'unknown' ),
			'usefulness_level' => sanitize_text_field( $input['usefulness_level'] ?? 'unknown' ),
			'mutuality_level'  => sanitize_text_field( $input['mutuality_level'] ?? 'unknown' ),
			'evidence_note'    => sanitize_textarea_field( $input['evidence_note'] ?? '' ),
			'notes'            => sanitize_textarea_field( $input['notes'] ?? '' ),
		];
	}

	public static function get_default_record_data() {
		return [
			'contact_id'       => 0,
			'target_type'      => 'general',
			'target_id'        => 0,
			'point_label'      => '',
			'influence_type'   => '',
			'influence_level'  => 'unknown',
			'usefulness_level' => 'unknown',
			'mutuality_level'  => 'unknown',
			'evidence_note'    => '',
			'notes'            => '',
			'remove'           => 0,
		];
	}

	public static function sanitize_records_data( $rows ) {
		$rows = is_array( $rows ) ? $rows : [];
		$sanitized = [];

		foreach ( $rows as $row ) {
			$record = self::sanitize_record_data( is_array( $row ) ? $row : [] );
			$record['remove'] = ( is_array( $row ) && isset( $row['remove'] ) ) ? 1 : 0;
			$sanitized[] = $record;
		}

		return $sanitized;
	}

	public static function is_empty_record_data( $data ) {
		if ( absint( $data['target_id'] ) > 0 ) {
			return false;
		}

		if ( '' !== trim( (string) $data['point_label'] ) ) {
			return false;
		}

		if ( '' !== trim( (string) $data['influence_type'] ) ) {
			return false;
		}

		if ( '' !== trim( (string) $data['evidence_note'] ) ) {
			return false;
		}

		if ( '' !== trim( (string) $data['notes'] ) ) {
			return false;
		}

		if ( 'general' !== $data['target_type'] ) {
			return false;
		}

		if ( 'unknown' !== $data['influence_level'] || 'unknown' !== $data['usefulness_level'] || 'unknown' !== $data['mutuality_level'] ) {
			return false;
		}

		return true;
	}

	public static function replace_records_for_contact( $contact_id, $rows ) {
		global $wpdb;
		$table = self::get_table_name();
		$contact_id = absint( $contact_id );

		$deleted = $wpdb->delete( $table, [ 'contact_id' => $contact_id ] );
		if ( false === $deleted ) {
			return false;
		}

		$records = self::sanitize_records_data( $rows );
		if ( empty( $records ) ) {
			return true;
		}

		$now = current_time( 'mysql' );
		foreach ( $records as $record ) {
			if ( ! empty( $record['remove'] ) ) {
				continue;
			}

			if ( self::is_empty_record_data( $record ) ) {
				continue;
			}

			$record['contact_id'] = $contact_id;
			$valid = self::validate_record_data( $record );
			if ( true !== $valid ) {
				return false;
			}

			$inserted = $wpdb->insert(
				$table,
				[
					'contact_id'       => $record['contact_id'],
					'target_type'      => $record['target_type'],
					'target_id'        => $record['target_id'],
					'point_label'      => $record['point_label'],
					'influence_type'   => $record['influence_type'],
					'influence_level'  => $record['influence_level'],
					'usefulness_level' => $record['usefulness_level'],
					'mutuality_level'  => $record['mutuality_level'],
					'evidence_note'    => $record['evidence_note'],
					'notes'            => $record['notes'],
					'created_at'       => $now,
					'updated_at'       => $now,
				]
			);

			if ( false === $inserted ) {
				return false;
			}
		}

		return true;
	}

	public static function get_influence_counts_for_contacts( $contact_ids ) {
		global $wpdb;
		$contact_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $contact_ids ) ) ) );
		if ( empty( $contact_ids ) ) {
			return [];
		}

		$table = self::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
		$sql = "SELECT contact_id, COUNT(*) AS records_count FROM $table WHERE contact_id IN ($placeholders) GROUP BY contact_id";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $contact_ids ) );

		$map = [];
		foreach ( $rows as $row ) {
			$map[ (int) $row->contact_id ] = (int) $row->records_count;
		}

		return $map;
	}

	public static function get_target_label( $record ) {
		$point_label = '';
		$target_type = 'general';
		$target_id = 0;

		if ( is_array( $record ) ) {
			$point_label = (string) ( $record['point_label'] ?? '' );
			$target_type = (string) ( $record['target_type'] ?? 'general' );
			$target_id = absint( $record['target_id'] ?? 0 );
		} elseif ( is_object( $record ) ) {
			$point_label = (string) ( $record->point_label ?? '' );
			$target_type = (string) ( $record->target_type ?? 'general' );
			$target_id = absint( $record->target_id ?? 0 );
		}

		$point_label = trim( $point_label );
		if ( '' !== $point_label ) {
			return $point_label;
		}

		if ( 'general' === $target_type ) {
			return 'General';
		}

		$resolved = self::resolve_target_entity_label( $target_type, $target_id );
		if ( '' !== $resolved ) {
			return $resolved;
		}

		return $target_type . '#' . $target_id;
	}

	public static function get_influence_summary_label( $count ) {
		$count = absint( $count );
		if ( $count <= 0 ) {
			return '—';
		}

		if ( 1 === $count ) {
			return '1 záznam';
		}

		if ( $count >= 2 && $count <= 4 ) {
			return $count . ' záznamy';
		}

		return $count . ' záznamov';
	}

	public static function resolve_target_entity_label( $target_type, $target_id ) {
		global $wpdb;
		$target_id = absint( $target_id );
		if ( $target_id <= 0 ) {
			return '';
		}

		switch ( $target_type ) {
			case 'destination':
				$table = $wpdb->prefix . 'toptour_ref_destinations';
				return (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $table WHERE id = %d", $target_id ) );
			case 'facility':
				$table = $wpdb->prefix . 'toptour_ref_facilities';
				return (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $table WHERE id = %d", $target_id ) );
			case 'interest':
				$table = $wpdb->prefix . 'toptour_ref_interests';
				return (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $table WHERE id = %d", $target_id ) );
			case 'contact':
				$table = $wpdb->prefix . 'toptour_ref_contacts';
				return (string) $wpdb->get_var( $wpdb->prepare( "SELECT display_name FROM $table WHERE id = %d", $target_id ) );
			case 'point_of_interest':
				$table = $wpdb->prefix . 'toptour_ref_points_of_interest';
				return (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $table WHERE id = %d", $target_id ) );
			default:
				return '';
		}
	}

	public static function validate_record_data( $data ) {
		$errors = [];

		if ( absint( $data['contact_id'] ) <= 0 ) {
			$errors[] = 'contact_id is required';
		} else {
			$contact = Toptour_Ref_Contacts::get_contact( $data['contact_id'] );
			if ( ! $contact ) {
				$errors[] = 'contact not found';
			}
		}

		if ( ! in_array( $data['target_type'], self::get_allowed_target_types(), true ) ) {
			$errors[] = 'invalid target_type';
		}

		if ( ! in_array( $data['influence_level'], self::get_allowed_levels(), true ) ) {
			$errors[] = 'invalid influence_level';
		}

		if ( '' !== $data['influence_type'] && ! in_array( $data['influence_type'], self::get_allowed_influence_types(), true ) ) {
			$errors[] = 'invalid influence_type';
		}

		if ( ! in_array( $data['usefulness_level'], self::get_allowed_usefulness_levels(), true ) ) {
			$errors[] = 'invalid usefulness_level';
		}

		if ( ! in_array( $data['mutuality_level'], self::get_allowed_mutuality_levels(), true ) ) {
			$errors[] = 'invalid mutuality_level';
		}

		if ( in_array( $data['target_type'], [ 'destination', 'facility', 'interest', 'contact', 'point_of_interest' ], true ) && absint( $data['target_id'] ) <= 0 ) {
			$errors[] = 'target_id is required for this target_type';
		}

		return $errors ? $errors : true;
	}
}
