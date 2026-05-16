<?php
/**
 * Offers data class.
 *
 * Internal registry of public offers/deals used by reference workflows.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Offers {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_offers';
	}

	public static function get_allowed_statuses() {
		return [ 'active', 'draft', 'needs_review', 'archived' ];
	}

	public static function get_allowed_types() {
		return [ 'general', 'deal', 'package', 'accommodation', 'tour', 'other' ];
	}

	public static function get_offers( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'status' => '',
			'destination_id' => 0,
			'facility_id' => 0,
			'search' => '',
			'page' => 1,
			'per_page' => 20,
		];
		$args = array_merge( $defaults, $args );

		$where = [];
		$values = [];

		if ( $args['status'] !== '' && in_array( $args['status'], self::get_allowed_statuses(), true ) ) {
			$where[] = 'status = %s';
			$values[] = $args['status'];
		}

		if ( absint( $args['destination_id'] ) > 0 ) {
			$where[] = 'destination_id = %d';
			$values[] = absint( $args['destination_id'] );
		}

		if ( absint( $args['facility_id'] ) > 0 ) {
			$where[] = 'facility_id = %d';
			$values[] = absint( $args['facility_id'] );
		}

		if ( $args['search'] !== '' ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(offer_name LIKE %s OR offer_url LIKE %s OR description_summary LIKE %s OR price_note LIKE %s)';
			$values[] = $like;
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
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $values, [ $per_page, $offset ] ) ) );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		}

		return [
			'rows' => $rows ? $rows : [],
			'total' => $total,
		];
	}

	public static function get_offer( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	public static function find_offer_by_url( $url ) {
		global $wpdb;
		$table = self::get_table_name();
		$raw = trim( (string) $url );
		if ( '' === $raw ) {
			return null;
		}
		$normalized = self::normalize_url( $raw );
		$host = wp_parse_url( $normalized, PHP_URL_HOST );
		if ( ! $host ) {
			return null;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE offer_url LIKE %s ORDER BY updated_at DESC LIMIT 200",
				'%' . $wpdb->esc_like( $host ) . '%'
			)
		);

		foreach ( (array) $rows as $row ) {
			if ( self::normalize_url( (string) $row->offer_url ) === $normalized ) {
				return $row;
			}
		}

		return null;
	}

	public static function create_offer( $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'facility_id' => absint( $data['facility_id'] ?? 0 ),
				'destination_id' => absint( $data['destination_id'] ?? 0 ),
				'reference_source_id' => absint( $data['reference_source_id'] ?? 0 ),
				'offer_name' => sanitize_text_field( $data['offer_name'] ?? '' ),
				'offer_url' => esc_url_raw( $data['offer_url'] ?? '' ),
				'offer_type' => sanitize_text_field( $data['offer_type'] ?? 'general' ),
				'description_summary' => sanitize_textarea_field( $data['description_summary'] ?? '' ),
				'price_value' => self::normalize_price( $data['price_value'] ?? null ),
				'price_currency' => sanitize_text_field( $data['price_currency'] ?? '' ),
				'price_note' => sanitize_text_field( $data['price_note'] ?? '' ),
				'stay_duration' => sanitize_text_field( $data['stay_duration'] ?? '' ),
				'persons_min' => absint( $data['persons_min'] ?? 0 ),
				'persons_max' => absint( $data['persons_max'] ?? 0 ),
				'meal_plan' => sanitize_text_field( $data['meal_plan'] ?? '' ),
				'transport_type' => sanitize_text_field( $data['transport_type'] ?? '' ),
				'accommodation_type' => sanitize_text_field( $data['accommodation_type'] ?? '' ),
				'season' => sanitize_text_field( $data['season'] ?? '' ),
				'valid_from' => self::normalize_datetime_or_null( $data['valid_from'] ?? '' ),
				'valid_to' => self::normalize_datetime_or_null( $data['valid_to'] ?? '' ),
				'status' => sanitize_text_field( $data['status'] ?? 'needs_review' ),
				'created_by' => absint( $data['created_by'] ?? get_current_user_id() ),
				'created_at' => $now,
				'updated_at' => $now,
			]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function update_offer( $id, $data ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'facility_id' => absint( $data['facility_id'] ?? 0 ),
				'destination_id' => absint( $data['destination_id'] ?? 0 ),
				'reference_source_id' => absint( $data['reference_source_id'] ?? 0 ),
				'offer_name' => sanitize_text_field( $data['offer_name'] ?? '' ),
				'offer_url' => esc_url_raw( $data['offer_url'] ?? '' ),
				'offer_type' => sanitize_text_field( $data['offer_type'] ?? 'general' ),
				'description_summary' => sanitize_textarea_field( $data['description_summary'] ?? '' ),
				'price_value' => self::normalize_price( $data['price_value'] ?? null ),
				'price_currency' => sanitize_text_field( $data['price_currency'] ?? '' ),
				'price_note' => sanitize_text_field( $data['price_note'] ?? '' ),
				'stay_duration' => sanitize_text_field( $data['stay_duration'] ?? '' ),
				'persons_min' => absint( $data['persons_min'] ?? 0 ),
				'persons_max' => absint( $data['persons_max'] ?? 0 ),
				'meal_plan' => sanitize_text_field( $data['meal_plan'] ?? '' ),
				'transport_type' => sanitize_text_field( $data['transport_type'] ?? '' ),
				'accommodation_type' => sanitize_text_field( $data['accommodation_type'] ?? '' ),
				'season' => sanitize_text_field( $data['season'] ?? '' ),
				'valid_from' => self::normalize_datetime_or_null( $data['valid_from'] ?? '' ),
				'valid_to' => self::normalize_datetime_or_null( $data['valid_to'] ?? '' ),
				'status' => sanitize_text_field( $data['status'] ?? 'needs_review' ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);

		return $result !== false;
	}

	public static function archive_offer( $id ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'status' => 'archived',
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);

		return $result !== false;
	}

	public static function sanitize_offer_data( $input ) {
		return [
			'facility_id' => absint( $input['facility_id'] ?? 0 ),
			'destination_id' => absint( $input['destination_id'] ?? 0 ),
			'reference_source_id' => absint( $input['reference_source_id'] ?? 0 ),
			'offer_name' => sanitize_text_field( $input['offer_name'] ?? '' ),
			'offer_url' => esc_url_raw( $input['offer_url'] ?? '' ),
			'offer_type' => sanitize_text_field( $input['offer_type'] ?? 'general' ),
			'description_summary' => sanitize_textarea_field( $input['description_summary'] ?? '' ),
			'price_value' => ( $input['price_value'] ?? '' ) === '' ? null : floatval( $input['price_value'] ),
			'price_currency' => sanitize_text_field( $input['price_currency'] ?? '' ),
			'price_note' => sanitize_text_field( $input['price_note'] ?? '' ),
			'stay_duration' => sanitize_text_field( $input['stay_duration'] ?? '' ),
			'persons_min' => absint( $input['persons_min'] ?? 0 ),
			'persons_max' => absint( $input['persons_max'] ?? 0 ),
			'meal_plan' => sanitize_text_field( $input['meal_plan'] ?? '' ),
			'transport_type' => sanitize_text_field( $input['transport_type'] ?? '' ),
			'accommodation_type' => sanitize_text_field( $input['accommodation_type'] ?? '' ),
			'season' => sanitize_text_field( $input['season'] ?? '' ),
			'valid_from' => sanitize_text_field( str_replace( 'T', ' ', $input['valid_from'] ?? '' ) ),
			'valid_to' => sanitize_text_field( str_replace( 'T', ' ', $input['valid_to'] ?? '' ) ),
			'status' => sanitize_text_field( $input['status'] ?? 'needs_review' ),
		];
	}

	public static function validate_offer_data( $data ) {
		$errors = [];
		if ( sanitize_text_field( (string) $data['offer_name'] ) === '' ) {
			$errors[] = 'offer_name is required';
		}
		if ( ! in_array( (string) $data['status'], self::get_allowed_statuses(), true ) ) {
			$errors[] = 'invalid status';
		}
		if ( ! in_array( (string) $data['offer_type'], self::get_allowed_types(), true ) ) {
			$errors[] = 'invalid offer_type';
		}
		if ( ! empty( $data['offer_url'] ) && ! wp_http_validate_url( (string) $data['offer_url'] ) ) {
			$errors[] = 'invalid offer_url';
		}
		return $errors ? $errors : true;
	}

	private static function normalize_price( $value ) {
		if ( $value === null || $value === '' ) {
			return null;
		}
		$price = floatval( $value );
		return $price > 0 ? $price : null;
	}

	private static function normalize_datetime_or_null( $value ) {
		$value = sanitize_text_field( str_replace( 'T', ' ', (string) $value ) );
		return '' === $value ? null : $value;
	}

	private static function normalize_url( $url ) {
		$url = trim( (string) $url );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $url;
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$host = strtolower( $parts['host'] );
		$path = isset( $parts['path'] ) ? rtrim( $parts['path'], '/' ) : '';
		$query = '';
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_args );
			if ( is_array( $query_args ) ) {
				foreach ( array_keys( $query_args ) as $key ) {
					if ( strpos( (string) $key, 'utm_' ) === 0 ) {
						unset( $query_args[ $key ] );
					}
				}
				ksort( $query_args );
				$query = http_build_query( $query_args );
			}
		}
		return $scheme . '://' . $host . $path . ( $query ? '?' . $query : '' );
	}
}
