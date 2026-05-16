<?php
/**
 * Facilities data class.
 *
 * Internal evidence of facilities as reference collection targets.
 * No scoring, no public output, no scraping.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Facilities helper class.
 *
 * Handles read and write operations for the internal facilities registry.
 */
class Toptour_Ref_Facilities {

	/**
	 * Get the full table name with WP prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_facilities';
	}

	/**
	 * Allowed values for facility_type.
	 *
	 * @return string[]
	 */
	public static function get_allowed_types() {
		return [ '', 'hotel', 'pension', 'apartment', 'resort', 'campsite', 'chalet', 'wellness', 'restaurant', 'service', 'other' ];
	}

	/**
	 * Allowed values for status.
	 *
	 * @return string[]
	 */
	public static function get_allowed_statuses() {
		return [ 'draft', 'watched', 'verifying', 'verified', 'archived' ];
	}

	/**
	 * Get paginated list of facilities with optional filters and search.
	 *
	 * @param array $args {
	 *     @type string $facility_type Filter by facility_type.
	 *     @type string $country       Filter by country.
	 *     @type string $region        Filter by region.
	 *     @type string $status        Filter by status.
	 *     @type string $search        Search across name, city, address, website_url, notes.
	 *     @type int    $page          Current page (1-based).
	 *     @type int    $per_page      Records per page. Default 20.
	 * }
	 * @return array { facilities: array, total: int }
	 */
	public static function get_facilities( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'facility_type' => '',
			'country'       => '',
			'region'        => '',
			'status'        => '',
			'search'        => '',
			'page'          => 1,
			'per_page'      => 20,
		];
		$args = array_merge( $defaults, $args );

		$where  = [];
		$values = [];

		if ( $args['facility_type'] !== '' && in_array( $args['facility_type'], self::get_allowed_types(), true ) ) {
			$where[]  = 'facility_type = %s';
			$values[] = $args['facility_type'];
		}

		if ( $args['status'] !== '' && in_array( $args['status'], self::get_allowed_statuses(), true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['country'] !== '' ) {
			$where[]  = 'country = %s';
			$values[] = $args['country'];
		}

		if ( $args['region'] !== '' ) {
			$where[]  = 'region = %s';
			$values[] = $args['region'];
		}

		if ( $args['search'] !== '' ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR city LIKE %s OR address LIKE %s OR website_url LIKE %s OR notes LIKE %s)';
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
			$total      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where_sql", $values ) );
			$facilities = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $values, [ $per_page, $offset ] ) ) );
		} else {
			$total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
			$facilities = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		}

		return [
			'facilities' => $facilities ? $facilities : [],
			'total'      => $total,
		];
	}

	/**
	 * Get a single facility by ID.
	 *
	 * @param int $id Facility ID.
	 * @return object|null
	 */
	public static function get_facility( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::get_table_name() . " WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Create a new facility.
	 *
	 * @param array $data Sanitized and validated facility data.
	 * @return int|false Inserted ID or false on failure.
	 */
	public static function create_facility( $data ) {
		global $wpdb;
		$now    = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'name'                => $data['name'],
				'slug'                => $data['slug'],
				'facility_type'       => $data['facility_type'],
				'country'             => $data['country'],
				'region'              => $data['region'],
				'city'                => $data['city'],
				'address'             => $data['address'],
				'website_url'         => $data['website_url'],
				'official_source_url' => $data['official_source_url'],
				'status'              => $data['status'],
				'notes'               => $data['notes'],
				'created_at'          => $now,
				'updated_at'          => $now,
			]
		);
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing facility.
	 *
	 * @param int   $id   Facility ID.
	 * @param array $data Sanitized and validated facility data.
	 * @return bool
	 */
	public static function update_facility( $id, $data ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'name'                => $data['name'],
				'slug'                => $data['slug'],
				'facility_type'       => $data['facility_type'],
				'country'             => $data['country'],
				'region'              => $data['region'],
				'city'                => $data['city'],
				'address'             => $data['address'],
				'website_url'         => $data['website_url'],
				'official_source_url' => $data['official_source_url'],
				'status'              => $data['status'],
				'notes'               => $data['notes'],
				'updated_at'          => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	/**
	 * Archive a facility (sets status to archived, does not delete).
	 *
	 * @param int $id Facility ID.
	 * @return bool
	 */
	public static function archive_facility( $id ) {
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
	 * Sanitize raw POST input for a facility.
	 *
	 * @param array $input Raw POST data.
	 * @return array
	 */
	public static function sanitize_facility_data( $input ) {
		$name = sanitize_text_field( $input['name'] ?? '' );
		$slug = sanitize_title( $input['slug'] ?? '' );
		if ( $slug === '' ) {
			$slug = sanitize_title( $name );
		}
		return [
			'name'                => $name,
			'slug'                => $slug,
			'facility_type'       => sanitize_text_field( $input['facility_type'] ?? '' ),
			'country'             => sanitize_text_field( $input['country'] ?? '' ),
			'region'              => sanitize_text_field( $input['region'] ?? '' ),
			'city'                => sanitize_text_field( $input['city'] ?? '' ),
			'address'             => sanitize_textarea_field( $input['address'] ?? '' ),
			'website_url'         => esc_url_raw( $input['website_url'] ?? '' ),
			'official_source_url' => esc_url_raw( $input['official_source_url'] ?? '' ),
			'status'              => sanitize_text_field( $input['status'] ?? 'draft' ),
			'notes'               => sanitize_textarea_field( $input['notes'] ?? '' ),
		];
	}

	/**
	 * Validate sanitized facility data.
	 *
	 * @param array $data Sanitized data.
	 * @return true|string[] True on success, array of error messages on failure.
	 */
	public static function validate_facility_data( $data ) {
		$errors = [];

		if ( $data['name'] === '' ) {
			$errors[] = 'name is required.';
		}

		if ( $data['slug'] === '' ) {
			$errors[] = 'slug could not be generated from name.';
		}

		if ( ! in_array( $data['status'], self::get_allowed_statuses(), true ) ) {
			$errors[] = 'Invalid status.';
		}

		if ( $data['facility_type'] !== '' && ! in_array( $data['facility_type'], self::get_allowed_types(), true ) ) {
			$errors[] = 'Invalid facility_type.';
		}

		return $errors ? $errors : true;
	}
}
