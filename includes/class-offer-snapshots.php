<?php
/**
 * Offer snapshots data class.
 *
 * Stores internal time-based snapshots of publicly presented offer metadata.
 *
 * @package Toptour_Ref
 * @version 0.2.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Offer_Snapshots {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_offer_snapshots';
	}

	public static function get_allowed_statuses() {
		return [ 'new', 'active', 'outdated', 'superseded', 'needs_review', 'rejected' ];
	}

	public static function create_snapshot( $data ) {
		global $wpdb;

		$status = sanitize_text_field( $data['status'] ?? 'new' );
		if ( ! in_array( $status, self::get_allowed_statuses(), true ) ) {
			$status = 'new';
		}

		$now = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'finding_id'                 => absint( $data['finding_id'] ?? 0 ),
				'task_id'                    => absint( $data['task_id'] ?? 0 ),
				'run_id'                     => absint( $data['run_id'] ?? 0 ),
				'offer_id'                   => absint( $data['offer_id'] ?? 0 ),
				'supplier_id'                => absint( $data['supplier_id'] ?? 0 ),
				'destination_id'             => absint( $data['destination_id'] ?? 0 ),
				'source_url'                 => esc_url_raw( $data['source_url'] ?? '' ),
				'source_title'               => sanitize_text_field( $data['source_title'] ?? '' ),
				'offer_name'                 => sanitize_text_field( $data['offer_name'] ?? '' ),
				'offer_description_summary'  => sanitize_textarea_field( $data['offer_description_summary'] ?? '' ),
				'price_value'                => self::normalize_price( $data['price_value'] ?? null ),
				'price_currency'             => sanitize_text_field( $data['price_currency'] ?? '' ),
				'price_note'                 => sanitize_text_field( $data['price_note'] ?? '' ),
				'stay_duration'              => sanitize_text_field( $data['stay_duration'] ?? '' ),
				'persons_min'                => absint( $data['persons_min'] ?? 0 ),
				'persons_max'                => absint( $data['persons_max'] ?? 0 ),
				'valid_from'                 => self::normalize_datetime_or_null( $data['valid_from'] ?? '' ),
				'valid_to'                   => self::normalize_datetime_or_null( $data['valid_to'] ?? '' ),
				'season'                     => sanitize_text_field( $data['season'] ?? '' ),
				'meal_plan'                  => sanitize_text_field( $data['meal_plan'] ?? '' ),
				'transport_type'             => sanitize_text_field( $data['transport_type'] ?? '' ),
				'accommodation_type'         => sanitize_text_field( $data['accommodation_type'] ?? '' ),
				'facility_category'          => sanitize_text_field( $data['facility_category'] ?? '' ),
				'included_services_summary'  => sanitize_textarea_field( $data['included_services_summary'] ?? '' ),
				'excluded_services_summary'  => sanitize_textarea_field( $data['excluded_services_summary'] ?? '' ),
				'availability_note'          => sanitize_text_field( $data['availability_note'] ?? '' ),
				'booking_conditions_summary' => sanitize_textarea_field( $data['booking_conditions_summary'] ?? '' ),
				'public_offer_published_at'  => self::normalize_datetime_or_null( $data['public_offer_published_at'] ?? '' ),
				'source_detected_at'         => self::normalize_datetime_or_null( $data['source_detected_at'] ?? '' ),
				'source_last_checked_at'     => self::normalize_datetime_or_null( $data['source_last_checked_at'] ?? '' ),
				'analysis_performed_at'      => self::normalize_datetime_or_null( $data['analysis_performed_at'] ?? $now ),
				'snapshot_hash'              => sanitize_text_field( $data['snapshot_hash'] ?? '' ),
				'status'                     => $status,
				'created_by'                 => absint( $data['created_by'] ?? get_current_user_id() ),
				'created_at'                 => $now,
				'updated_at'                 => $now,
			]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function mark_previous_snapshots_superseded( $task_id, $offer_id, $current_snapshot_id ) {
		global $wpdb;

		$task_id = absint( $task_id );
		$offer_id = absint( $offer_id );
		$current_snapshot_id = absint( $current_snapshot_id );
		if ( $task_id <= 0 || $current_snapshot_id <= 0 ) {
			return false;
		}

		$where_sql = $offer_id > 0 ? 'task_id = %d AND offer_id = %d AND id != %d' : 'task_id = %d AND id != %d';
		$params = $offer_id > 0 ? [ $task_id, $offer_id, $current_snapshot_id ] : [ $task_id, $current_snapshot_id ];

		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . self::get_table_name() . " WHERE $where_sql AND status IN ('new','active')", $params ) );
		if ( empty( $ids ) ) {
			return true;
		}

		$id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql = "UPDATE " . self::get_table_name() . " SET status = %s, updated_at = %s WHERE id IN ($id_placeholders)";
		$values = array_merge( [ 'superseded', current_time( 'mysql' ) ], array_map( 'absint', $ids ) );

		return $wpdb->query( $wpdb->prepare( $sql, $values ) ) !== false;
	}

	public static function get_recent_for_task( $task_id, $limit = 10 ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE task_id = %d ORDER BY COALESCE(analysis_performed_at, created_at) DESC, id DESC LIMIT %d",
				absint( $task_id ),
				max( 1, absint( $limit ) )
			)
		);
	}

	private static function normalize_datetime_or_null( $value ) {
		$value = sanitize_text_field( str_replace( 'T', ' ', (string) $value ) );
		return '' === $value ? null : $value;
	}

	private static function normalize_price( $value ) {
		if ( $value === null || $value === '' ) {
			return null;
		}
		$price = floatval( $value );
		return $price > 0 ? $price : null;
	}
}
