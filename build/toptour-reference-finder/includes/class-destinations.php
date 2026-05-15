<?php
/**
 * Destinations data class.
 *
 * Internal destination registry for reference collection targets.
 * No scoring, no public output, no scraping.
 *
 * @package Toptour_Ref
 * @version 0.1.5
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Destinations helper class.
 */
class Toptour_Ref_Destinations {

	/**
	 * Get full table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_destinations';
	}

	/**
	 * Get allowed destination types.
	 *
	 * @return string[]
	 */
	public static function get_allowed_types() {
		return [ '', 'city', 'mountains', 'sea', 'spa', 'countryside', 'adventure', 'family', 'cultural', 'nature', 'other' ];
	}

	/**
	 * Get allowed statuses.
	 *
	 * @return string[]
	 */
	public static function get_allowed_statuses() {
		return [ 'draft', 'watched', 'verifying', 'verified', 'archived' ];
	}

	/**
	 * Get paginated destinations list.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_destinations( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'country'          => '',
			'region'           => '',
			'destination_type' => '',
			'status'           => '',
			'search'           => '',
			'page'             => 1,
			'per_page'         => 20,
		];
		$args = array_merge( $defaults, $args );

		$where  = [];
		$values = [];

		if ( $args['country'] !== '' ) {
			$where[]  = 'country = %s';
			$values[] = $args['country'];
		}

		if ( $args['region'] !== '' ) {
			$where[]  = 'region = %s';
			$values[] = $args['region'];
		}

		if ( $args['destination_type'] !== '' && in_array( $args['destination_type'], self::get_allowed_types(), true ) ) {
			$where[]  = 'destination_type = %s';
			$values[] = $args['destination_type'];
		}

		if ( $args['status'] !== '' && in_array( $args['status'], self::get_allowed_statuses(), true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['search'] !== '' ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR country LIKE %s OR region LIKE %s OR description LIKE %s OR notes LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$page      = max( 1, absint( $args['page'] ) );
		$per_page  = max( 1, absint( $args['per_page'] ) );
		$offset    = ( $page - 1 ) * $per_page;

		if ( $values ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where_sql", $values ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $values, [ $per_page, $offset ] ) ) );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		}

		return [
			'destinations' => $rows ? $rows : [],
			'total'        => $total,
		];
	}

	/**
	 * Get one destination by ID.
	 *
	 * @param int $id Destination ID.
	 * @return object|null
	 */
	public static function get_destination( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Get destinations for assignment dropdown.
	 *
	 * @return object[]
	 */
	public static function get_active_destinations_for_assignment() {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, name, country, region FROM $table WHERE status != %s ORDER BY name ASC", 'archived' ) );
	}

	/**
	 * Create destination.
	 *
	 * @param array $data Validated destination data.
	 * @return int|false
	 */
	public static function create_destination( $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'name'             => $data['name'],
				'slug'             => $data['slug'],
				'country'          => $data['country'],
				'region'           => $data['region'],
				'destination_type' => $data['destination_type'],
				'seasonality'      => $data['seasonality'],
				'description'      => $data['description'],
				'notes'            => $data['notes'],
				'status'           => $data['status'],
				'created_at'       => $now,
				'updated_at'       => $now,
			]
		);
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update destination.
	 *
	 * @param int   $id Destination ID.
	 * @param array $data Validated destination data.
	 * @return bool
	 */
	public static function update_destination( $id, $data ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'name'             => $data['name'],
				'slug'             => $data['slug'],
				'country'          => $data['country'],
				'region'           => $data['region'],
				'destination_type' => $data['destination_type'],
				'seasonality'      => $data['seasonality'],
				'description'      => $data['description'],
				'notes'            => $data['notes'],
				'status'           => $data['status'],
				'updated_at'       => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	/**
	 * Archive destination.
	 *
	 * @param int $id Destination ID.
	 * @return bool
	 */
	public static function archive_destination( $id ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'status'     => 'archived',
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	/**
	 * Sanitize destination form input.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_destination_data( $input ) {
		$name = sanitize_text_field( $input['name'] ?? '' );
		$slug = sanitize_title( $input['slug'] ?? '' );
		if ( $slug === '' ) {
			$slug = sanitize_title( $name );
		}

		return [
			'name'             => $name,
			'slug'             => $slug,
			'country'          => sanitize_text_field( $input['country'] ?? '' ),
			'region'           => sanitize_text_field( $input['region'] ?? '' ),
			'destination_type' => sanitize_text_field( $input['destination_type'] ?? '' ),
			'seasonality'      => sanitize_text_field( $input['seasonality'] ?? '' ),
			'description'      => sanitize_textarea_field( $input['description'] ?? '' ),
			'notes'            => sanitize_textarea_field( $input['notes'] ?? '' ),
			'status'           => sanitize_text_field( $input['status'] ?? 'draft' ),
		];
	}

	/**
	 * Validate destination data.
	 *
	 * @param array $data Sanitized data.
	 * @return true|string[]
	 */
	public static function validate_destination_data( $data ) {
		$errors = [];

		if ( $data['name'] === '' ) {
			$errors[] = 'name is required';
		}

		if ( $data['slug'] === '' ) {
			$errors[] = 'slug is required';
		}

		if ( $data['destination_type'] !== '' && ! in_array( $data['destination_type'], self::get_allowed_types(), true ) ) {
			$errors[] = 'invalid destination type';
		}

		if ( ! in_array( $data['status'], self::get_allowed_statuses(), true ) ) {
			$errors[] = 'invalid status';
		}

		return $errors ? $errors : true;
	}
}
