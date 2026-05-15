<?php
/**
 * Discovery Candidates helper class.
 *
 * Stores candidate sources before manual acceptance.
 *
 * @package Toptour_Ref
 * @version 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Discovery_Candidates {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_discovery_candidates';
	}

	public static function get_allowed_statuses() {
		return [ 'new', 'accepted', 'rejected', 'duplicate', 'needs_review', 'archived' ];
	}

	public static function get_candidates_for_run( $run_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE discovery_run_id = %d ORDER BY created_at DESC", absint( $run_id ) ) );
	}

	public static function get_candidate( $candidate_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $candidate_id ) ) );
	}

	public static function create_candidate( $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );

		$candidate_status = sanitize_text_field( $data['candidate_status'] ?? 'new' );
		if ( ! in_array( $candidate_status, self::get_allowed_statuses(), true ) ) {
			$candidate_status = 'new';
		}

		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'discovery_run_id'              => absint( $data['discovery_run_id'] ?? 0 ),
				'collection_task_id'            => absint( $data['collection_task_id'] ?? 0 ),
				'candidate_title'               => sanitize_text_field( $data['candidate_title'] ?? '' ),
				'candidate_url'                 => esc_url_raw( $data['candidate_url'] ?? '' ),
				'candidate_platform'            => sanitize_text_field( $data['candidate_platform'] ?? '' ),
				'candidate_source_type'         => sanitize_text_field( $data['candidate_source_type'] ?? 'other' ),
				'candidate_origin'              => sanitize_text_field( $data['candidate_origin'] ?? 'manual_discovery' ),
				'snippet'                       => sanitize_textarea_field( $data['snippet'] ?? '' ),
				'detected_language'             => sanitize_text_field( $data['detected_language'] ?? '' ),
				'suggested_target_type'         => sanitize_text_field( $data['suggested_target_type'] ?? 'general' ),
				'suggested_target_id'           => absint( $data['suggested_target_id'] ?? 0 ),
				'suggested_credibility_level'   => sanitize_text_field( $data['suggested_credibility_level'] ?? 'unknown' ),
				'suggestion_reason'             => sanitize_textarea_field( $data['suggestion_reason'] ?? '' ),
				'search_query'                  => sanitize_text_field( $data['search_query'] ?? '' ),
				'candidate_status'              => $candidate_status,
				'accepted_source_id'            => absint( $data['accepted_source_id'] ?? 0 ),
				'notes'                         => sanitize_textarea_field( $data['notes'] ?? '' ),
				'created_at'                    => $now,
				'updated_at'                    => $now,
			]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function accept_candidate_as_source( $candidate_id ) {
		global $wpdb;

		$candidate = self::get_candidate( $candidate_id );
		if ( ! $candidate || 'accepted' === $candidate->candidate_status ) {
			return false;
		}

		$source_type = in_array( $candidate->candidate_source_type, Toptour_Ref_Reference_Sources::get_allowed_source_types(), true )
			? $candidate->candidate_source_type
			: 'other';

		$target_type = in_array( $candidate->suggested_target_type, Toptour_Ref_Reference_Sources::get_allowed_target_types(), true )
			? $candidate->suggested_target_type
			: 'general';

		$credibility = in_array( $candidate->suggested_credibility_level, Toptour_Ref_Reference_Sources::get_allowed_credibility_levels(), true )
			? $candidate->suggested_credibility_level
			: 'unknown';

		$source_data = [
			'source_title'                 => $candidate->candidate_title,
			'source_url'                   => $candidate->candidate_url,
			'source_url_raw'               => $candidate->candidate_url,
			'source_platform'              => $candidate->candidate_platform,
			'source_type'                  => $source_type,
			'source_origin'                => $candidate->candidate_origin ?: 'manual_discovery',
			'target_type'                  => $target_type,
			'target_id'                    => (int) $candidate->suggested_target_id,
			'collection_task_id'           => (int) $candidate->collection_task_id,
			'language'                     => $candidate->detected_language,
			'captured_at'                  => '',
			'source_date'                  => '',
			'external_rating'              => '',
			'external_review_count'        => 0,
			'credibility_level'            => $credibility,
			'credibility_reason'           => $candidate->suggestion_reason,
			'credibility_updated_at'       => '',
			'verification_method'          => 'manual',
			'verification_notes'           => '',
			'last_verified_at'             => '',
			'suggested_credibility_level'  => '',
			'suggestion_reason'            => '',
			'suggestion_status'            => 'none',
			'suggestion_created_at'        => '',
			'suggestion_resolved_at'       => '',
			'suggestion_reviewed_by'       => 0,
			'search_priority'              => 'normal',
			'next_action'                  => 'review_source',
			'validation_status'            => 'new',
			'access_status'                => 'unknown',
			'notes'                        => $candidate->notes,
		];

		$validation = Toptour_Ref_Reference_Sources::validate_source_data( $source_data );
		if ( true !== $validation ) {
			return false;
		}

		$source_id = Toptour_Ref_Reference_Sources::create_source( $source_data );
		if ( ! $source_id ) {
			return false;
		}

		$result = $wpdb->update(
			self::get_table_name(),
			[
				'candidate_status'   => 'accepted',
				'accepted_source_id' => (int) $source_id,
				'updated_at'         => current_time( 'mysql' ),
			],
			[ 'id' => absint( $candidate_id ) ]
		);

		return $result !== false ? (int) $source_id : false;
	}

	public static function reject_candidate( $candidate_id ) {
		return self::set_candidate_status( $candidate_id, 'rejected' );
	}

	public static function mark_duplicate( $candidate_id ) {
		return self::set_candidate_status( $candidate_id, 'duplicate' );
	}

	private static function set_candidate_status( $candidate_id, $status ) {
		global $wpdb;

		if ( ! in_array( $status, self::get_allowed_statuses(), true ) ) {
			return false;
		}

		$result = $wpdb->update(
			self::get_table_name(),
			[
				'candidate_status' => $status,
				'updated_at'       => current_time( 'mysql' ),
			],
			[ 'id' => absint( $candidate_id ) ]
		);

		return $result !== false;
	}
}
