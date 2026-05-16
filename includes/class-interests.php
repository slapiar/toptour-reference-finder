<?php
/**
 * Interests data class.
 *
 * Shared vocabulary of topics, competencies and strategic value areas.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Interests {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_interests';
	}

	public static function get_allowed_types() {
		return [ 'tourism', 'hospitality', 'transport', 'nature', 'culture', 'sport', 'wellness', 'food', 'local_product', 'safety', 'marketing', 'community', 'investment', 'technology', 'other' ];
	}

	public static function get_interests( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'interest_type' => '',
			'is_active'     => '',
			'search'        => '',
			'page'          => 1,
			'per_page'      => 20,
		];
		$args = array_merge( $defaults, $args );

		$where = [];
		$values = [];

		if ( '' !== $args['interest_type'] && in_array( $args['interest_type'], self::get_allowed_types(), true ) ) {
			$where[] = 'interest_type = %s';
			$values[] = $args['interest_type'];
		}

		if ( '' !== $args['is_active'] ) {
			$active = absint( $args['is_active'] ) > 0 ? 1 : 0;
			$where[] = 'is_active = %d';
			$values[] = $active;
		}

		if ( '' !== $args['search'] ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(interest_key LIKE %s OR name LIKE %s OR description LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$page = max( 1, absint( $args['page'] ) );
		$per_page = max( 1, absint( $args['per_page'] ) );
		$offset = ( $page - 1 ) * $per_page;

		if ( $values ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where_sql", $values ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where_sql ORDER BY name ASC LIMIT %d OFFSET %d", array_merge( $values, [ $per_page, $offset ] ) ) );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY name ASC LIMIT %d OFFSET %d", $per_page, $offset ) );
		}

		return [
			'interests' => $rows ? $rows : [],
			'total'     => $total,
		];
	}

	public static function get_active_interests() {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_results( "SELECT id, name FROM $table WHERE is_active = 1 ORDER BY name ASC" );
	}

	public static function get_interest( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	public static function get_interest_by_key( $interest_key ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE interest_key = %s", $interest_key ) );
	}

	public static function create_interest( $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'interest_key'  => $data['interest_key'],
				'name'          => $data['name'],
				'description'   => $data['description'],
				'interest_type' => $data['interest_type'],
				'is_active'     => $data['is_active'],
				'created_at'    => $now,
				'updated_at'    => $now,
			]
		);
		return $result ? $wpdb->insert_id : false;
	}

	public static function update_interest( $id, $data ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'interest_key'  => $data['interest_key'],
				'name'          => $data['name'],
				'description'   => $data['description'],
				'interest_type' => $data['interest_type'],
				'is_active'     => $data['is_active'],
				'updated_at'    => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	public static function deactivate_interest( $id ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'is_active'  => 0,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	public static function sanitize_interest_data( $input ) {
		$name = sanitize_text_field( $input['name'] ?? '' );
		$interest_key = sanitize_title( $input['interest_key'] ?? '' );
		if ( '' === $interest_key ) {
			$interest_key = sanitize_title( $name );
		}

		return [
			'interest_key'  => $interest_key,
			'name'          => $name,
			'description'   => sanitize_textarea_field( $input['description'] ?? '' ),
			'interest_type' => sanitize_text_field( $input['interest_type'] ?? 'other' ),
			'is_active'     => isset( $input['is_active'] ) ? ( absint( $input['is_active'] ) > 0 ? 1 : 0 ) : 0,
		];
	}

	public static function validate_interest_data( $data, $interest_id = 0 ) {
		$errors = [];
		if ( '' === $data['name'] ) {
			$errors[] = 'name is required';
		}

		if ( '' === $data['interest_key'] ) {
			$errors[] = 'interest_key is required';
		}

		if ( ! in_array( $data['interest_type'], self::get_allowed_types(), true ) ) {
			$errors[] = 'invalid interest_type';
		}

		if ( ! in_array( (int) $data['is_active'], [ 0, 1 ], true ) ) {
			$errors[] = 'invalid is_active';
		}

		if ( '' !== $data['interest_key'] ) {
			$existing = self::get_interest_by_key( $data['interest_key'] );
			if ( $existing && (int) $existing->id !== absint( $interest_id ) ) {
				$errors[] = 'interest_key must be unique';
			}
		}

		return $errors ? $errors : true;
	}

	public static function count_contacts_for_interest( $interest_id ) {
		return Toptour_Ref_Contact_Interests::count_contacts_for_interest( $interest_id );
	}
}
