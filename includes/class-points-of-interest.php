<?php
/**
 * Points of Interest data class.
 *
 * Internal field objects registry.
 * No public map, no scoring, no geocoding, no automation.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Points of Interest helper class.
 */
class Toptour_Ref_Points_Of_Interest {

	/**
	 * Get full table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_points_of_interest';
	}

	/**
	 * Allowed POI types.
	 *
	 * @return string[]
	 */
	public static function get_allowed_types() {
		return [
			'viewpoint',
			'trail_start',
			'landing_area',
			'takeoff_area',
			'transport_point',
			'restaurant',
			'local_product_place',
			'natural_site',
			'cultural_site',
			'service_point',
			'risk_point',
			'meeting_point',
			'other',
		];
	}

	/**
	 * Allowed POI statuses.
	 *
	 * @return string[]
	 */
	public static function get_allowed_statuses() {
		return [ 'draft', 'watched', 'verifying', 'verified', 'archived' ];
	}

	/**
	 * Get paginated POI list.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function get_points( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'poi_type'       => '',
			'destination_id' => 0,
			'facility_id'    => 0,
			'country'        => '',
			'region'         => '',
			'status'         => '',
			'search'         => '',
			'page'           => 1,
			'per_page'       => 20,
		];
		$args = array_merge( $defaults, $args );

		$where  = [];
		$values = [];

		if ( $args['poi_type'] !== '' && in_array( $args['poi_type'], self::get_allowed_types(), true ) ) {
			$where[]  = 'poi_type = %s';
			$values[] = $args['poi_type'];
		}

		if ( absint( $args['destination_id'] ) > 0 ) {
			$where[]  = 'destination_id = %d';
			$values[] = absint( $args['destination_id'] );
		}

		if ( absint( $args['facility_id'] ) > 0 ) {
			$where[]  = 'facility_id = %d';
			$values[] = absint( $args['facility_id'] );
		}

		if ( $args['country'] !== '' ) {
			$where[]  = 'country = %s';
			$values[] = $args['country'];
		}

		if ( $args['region'] !== '' ) {
			$where[]  = 'region = %s';
			$values[] = $args['region'];
		}

		if ( $args['status'] !== '' && in_array( $args['status'], self::get_allowed_statuses(), true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['search'] !== '' ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR city LIKE %s OR address LIKE %s OR description LIKE %s OR notes LIKE %s)';
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
			'points' => $rows ? $rows : [],
			'total'  => $total,
		];
	}

	/**
	 * Get one POI by ID.
	 *
	 * @param int $id POI ID.
	 * @return object|null
	 */
	public static function get_point( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Create POI.
	 *
	 * @param array $data Validated POI data.
	 * @return int|false
	 */
	public static function create_point( $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'destination_id' => $data['destination_id'],
				'facility_id'    => $data['facility_id'],
				'name'           => $data['name'],
				'slug'           => $data['slug'],
				'poi_type'       => $data['poi_type'],
				'country'        => $data['country'],
				'region'         => $data['region'],
				'city'           => $data['city'],
				'address'        => $data['address'],
				'latitude'       => $data['latitude'] === '' ? null : $data['latitude'],
				'longitude'      => $data['longitude'] === '' ? null : $data['longitude'],
				'description'    => $data['description'],
				'status'         => $data['status'],
				'notes'          => $data['notes'],
				'created_at'     => $now,
				'updated_at'     => $now,
			]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update POI.
	 *
	 * @param int   $id   POI ID.
	 * @param array $data Validated POI data.
	 * @return bool
	 */
	public static function update_point( $id, $data ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'destination_id' => $data['destination_id'],
				'facility_id'    => $data['facility_id'],
				'name'           => $data['name'],
				'slug'           => $data['slug'],
				'poi_type'       => $data['poi_type'],
				'country'        => $data['country'],
				'region'         => $data['region'],
				'city'           => $data['city'],
				'address'        => $data['address'],
				'latitude'       => $data['latitude'] === '' ? null : $data['latitude'],
				'longitude'      => $data['longitude'] === '' ? null : $data['longitude'],
				'description'    => $data['description'],
				'status'         => $data['status'],
				'notes'          => $data['notes'],
				'updated_at'     => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);

		return $result !== false;
	}

	/**
	 * Archive POI (no hard delete).
	 *
	 * @param int $id POI ID.
	 * @return bool
	 */
	public static function archive_point( $id ) {
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
	 * Sanitize POI form input.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_point_data( $input ) {
		$name = sanitize_text_field( $input['name'] ?? '' );
		$slug = sanitize_title( $input['slug'] ?? '' );
		if ( $slug === '' ) {
			$slug = sanitize_title( $name );
		}

		$latitude = sanitize_text_field( $input['latitude'] ?? '' );
		$longitude = sanitize_text_field( $input['longitude'] ?? '' );

		return [
			'destination_id' => absint( $input['destination_id'] ?? 0 ),
			'facility_id'    => absint( $input['facility_id'] ?? 0 ),
			'name'           => $name,
			'slug'           => $slug,
			'poi_type'       => sanitize_text_field( $input['poi_type'] ?? 'other' ),
			'country'        => sanitize_text_field( $input['country'] ?? '' ),
			'region'         => sanitize_text_field( $input['region'] ?? '' ),
			'city'           => sanitize_text_field( $input['city'] ?? '' ),
			'address'        => sanitize_textarea_field( $input['address'] ?? '' ),
			'latitude'       => $latitude,
			'longitude'      => $longitude,
			'description'    => sanitize_textarea_field( $input['description'] ?? '' ),
			'status'         => sanitize_text_field( $input['status'] ?? 'draft' ),
			'notes'          => sanitize_textarea_field( $input['notes'] ?? '' ),
		];
	}

	/**
	 * Validate POI data.
	 *
	 * @param array $data Sanitized data.
	 * @return true|string[]
	 */
	public static function validate_point_data( $data ) {
		$errors = [];

		if ( $data['name'] === '' ) {
			$errors[] = 'name is required';
		}

		if ( $data['slug'] === '' ) {
			$errors[] = 'slug is required';
		}

		if ( ! in_array( $data['poi_type'], self::get_allowed_types(), true ) ) {
			$errors[] = 'invalid poi type';
		}

		if ( ! in_array( $data['status'], self::get_allowed_statuses(), true ) ) {
			$errors[] = 'invalid status';
		}

		if ( ! is_int( $data['destination_id'] ) || $data['destination_id'] < 0 ) {
			$errors[] = 'invalid destination id';
		}

		if ( ! is_int( $data['facility_id'] ) || $data['facility_id'] < 0 ) {
			$errors[] = 'invalid facility id';
		}

		if ( $data['latitude'] !== '' ) {
			if ( ! is_numeric( $data['latitude'] ) ) {
				$errors[] = 'invalid latitude';
			} else {
				$lat = (float) $data['latitude'];
				if ( $lat < -90 || $lat > 90 ) {
					$errors[] = 'latitude out of range';
				} else {
					$data['latitude'] = self::normalize_decimal( $lat );
				}
			}
		}

		if ( $data['longitude'] !== '' ) {
			if ( ! is_numeric( $data['longitude'] ) ) {
				$errors[] = 'invalid longitude';
			} else {
				$lon = (float) $data['longitude'];
				if ( $lon < -180 || $lon > 180 ) {
					$errors[] = 'longitude out of range';
				} else {
					$data['longitude'] = self::normalize_decimal( $lon );
				}
			}
		}

		return $errors ? $errors : true;
	}

	/**
	 * Get active destination options for POI form.
	 *
	 * @return object[]
	 */
	public static function get_destination_options() {
		global $wpdb;
		$table = $wpdb->prefix . 'toptour_ref_destinations';
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, name, country, region FROM $table WHERE status != %s ORDER BY name ASC", 'archived' ) );
	}

	/**
	 * Get active facility options for POI form.
	 *
	 * @return object[]
	 */
	public static function get_facility_options() {
		global $wpdb;
		$table = $wpdb->prefix . 'toptour_ref_facilities';
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, name, country, region, city FROM $table WHERE status != %s ORDER BY name ASC", 'archived' ) );
	}

	/**
	 * Resolve POI label with fallback.
	 *
	 * @param int $poi_id POI ID.
	 * @return string
	 */
	public static function get_point_label( $poi_id ) {
		$poi_id = absint( $poi_id );
		if ( $poi_id <= 0 ) {
			return 'poi#' . $poi_id;
		}

		$point = self::get_point( $poi_id );
		if ( $point && ! empty( $point->name ) ) {
			return $point->name;
		}

		return 'poi#' . $poi_id;
	}

	/**
	 * Normalize decimal value to fit DECIMAL(10,7).
	 *
	 * @param float $value Numeric coordinate.
	 * @return string
	 */
	private static function normalize_decimal( $value ) {
		$normalized = number_format( $value, 7, '.', '' );
		$normalized = rtrim( rtrim( $normalized, '0' ), '.' );
		return $normalized === '-0' ? '0' : $normalized;
	}
}
