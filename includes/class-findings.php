<?php
/**
 * Findings data class.
 *
 * Internal evidence records extracted manually from sources.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Findings {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_findings';
	}

	public static function get_allowed_finding_types() {
		return [ 'positive', 'risk', 'contradiction', 'repeated_signal', 'neutral', 'uncertainty', 'source_quality' ];
	}

	public static function get_allowed_finding_areas() {
		return [ 'cleanliness', 'food', 'staff', 'location', 'transport', 'noise', 'room', 'bathroom', 'safety', 'beach', 'slope', 'surroundings', 'accessibility', 'photos', 'price_value', 'service_quality', 'wellness', 'family_suitability', 'senior_suitability', 'local_experience', 'other' ];
	}

	public static function get_allowed_signal_strengths() {
		return [ 'weak', 'medium', 'strong', 'critical' ];
	}

	public static function get_allowed_repetition_levels() {
		return [ 'single', 'repeated', 'frequent', 'dominant' ];
	}

	public static function get_allowed_verification_statuses() {
		return [ 'new', 'checked', 'confirmed', 'disputed', 'rejected', 'archived' ];
	}

	public static function get_allowed_statuses() {
		return [ 'new', 'pending_review', 'accepted', 'rejected', 'duplicate', 'needs_verification' ];
	}

	public static function get_allowed_reference_types() {
		return [ 'guest_review', 'supplier_testimonial', 'platform_rating', 'article_mention', 'social_mention', 'other' ];
	}

	public static function get_allowed_analysis_statuses() {
		return [ 'pending', 'analyzed', 'needs_review', 'accepted', 'rejected' ];
	}

	public static function get_allowed_evidence_types() {
		return [ 'text', 'review_excerpt', 'guest_photo', 'official_photo', 'video', 'own_observation', 'resident_feedback', 'client_feedback', 'source_crosscheck', 'mixed', 'other' ];
	}

	public static function get_allowed_target_types() {
		return [ 'general', 'facility', 'destination', 'point_of_interest', 'contact', 'interest', 'offer', 'collection_task' ];
	}

	public static function get_findings( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'finding_type'        => '',
			'finding_area'        => '',
			'signal_strength'     => '',
			'repetition_level'    => '',
			'verification_status' => '',
			'evidence_type'       => '',
			'target_type'         => '',
			'source_id'           => '',
			'signal_pattern_id'   => '',
			'search'              => '',
			'page'                => 1,
			'per_page'            => 20,
		];

		$args   = array_merge( $defaults, $args );
		$where  = [];
		$values = [];

		if ( $args['finding_type'] !== '' && in_array( $args['finding_type'], self::get_allowed_finding_types(), true ) ) {
			$where[]  = 'finding_type = %s';
			$values[] = $args['finding_type'];
		}

		if ( $args['finding_area'] !== '' && in_array( $args['finding_area'], self::get_allowed_finding_areas(), true ) ) {
			$where[]  = 'finding_area = %s';
			$values[] = $args['finding_area'];
		}

		if ( $args['signal_strength'] !== '' && in_array( $args['signal_strength'], self::get_allowed_signal_strengths(), true ) ) {
			$where[]  = 'signal_strength = %s';
			$values[] = $args['signal_strength'];
		}

		if ( $args['repetition_level'] !== '' && in_array( $args['repetition_level'], self::get_allowed_repetition_levels(), true ) ) {
			$where[]  = 'repetition_level = %s';
			$values[] = $args['repetition_level'];
		}

		if ( $args['verification_status'] !== '' && in_array( $args['verification_status'], self::get_allowed_verification_statuses(), true ) ) {
			$where[]  = 'verification_status = %s';
			$values[] = $args['verification_status'];
		}

		if ( $args['evidence_type'] !== '' && in_array( $args['evidence_type'], self::get_allowed_evidence_types(), true ) ) {
			$where[]  = 'evidence_type = %s';
			$values[] = $args['evidence_type'];
		}

		if ( $args['target_type'] !== '' && in_array( $args['target_type'], self::get_allowed_target_types(), true ) ) {
			$where[]  = 'target_type = %s';
			$values[] = $args['target_type'];
		}

		if ( $args['source_id'] !== '' && absint( $args['source_id'] ) > 0 ) {
			$where[]  = 'source_id = %d';
			$values[] = absint( $args['source_id'] );
		}

		if ( $args['signal_pattern_id'] !== '' && absint( $args['signal_pattern_id'] ) > 0 ) {
			$where[]  = 'signal_pattern_id = %d';
			$values[] = absint( $args['signal_pattern_id'] );
		}

		if ( $args['search'] !== '' ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(finding_title LIKE %s OR evidence_excerpt LIKE %s OR evidence_url LIKE %s OR reviewer_name LIKE %s OR reviewer_origin LIKE %s OR notes LIKE %s)';
			$values[] = $like;
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
			'findings' => $rows ? $rows : [],
			'total'    => $total,
		];
	}

	public static function get_finding( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	public static function create_finding( $data ) {
		global $wpdb;
		$now    = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'finding_title'             => $data['finding_title'],
				'task_id'                   => $data['task_id'],
				'run_id'                    => $data['run_id'],
				'source_url'                => $data['source_url'],
				'source_title'              => $data['source_title'],
				'source_type'               => $data['source_type'],
				'excerpt'                   => $data['excerpt'],
				'detected_sentiment'        => $data['detected_sentiment'],
				'review_published_at'       => ( $data['review_published_at'] ?? '' ) === '' ? null : $data['review_published_at'],
				'analysis_performed_at'     => ( $data['analysis_performed_at'] ?? '' ) === '' ? null : $data['analysis_performed_at'],
				'source_detected_at'        => ( $data['source_detected_at'] ?? '' ) === '' ? null : $data['source_detected_at'],
				'source_last_checked_at'    => ( $data['source_last_checked_at'] ?? '' ) === '' ? null : $data['source_last_checked_at'],
				'reference_language'        => $data['reference_language'] ?? '',
				'reference_type'            => $data['reference_type'] ?? 'other',
				'analysis_summary'          => $data['analysis_summary'] ?? '',
				'analysis_status'           => $data['analysis_status'] ?? 'pending',
				'confidence_score'          => $data['confidence_score'] ?? null,
				'destination_mapping_note'  => $data['destination_mapping_note'] ?? '',
				'poi_extraction_note'       => $data['poi_extraction_note'] ?? '',
				'offer_relation_note'       => $data['offer_relation_note'] ?? '',
				'poi_candidate_id'          => $data['poi_candidate_id'],
				'destination_id'            => $data['destination_id'],
				'supplier_id'               => $data['supplier_id'],
				'offer_id'                  => $data['offer_id'],
				'hash'                      => $data['hash'],
				'status'                    => $data['status'],
				'found_at'                  => $data['found_at'] === '' ? null : $data['found_at'],
				'reviewed_by'               => $data['reviewed_by'],
				'reviewed_at'               => $data['reviewed_at'] === '' ? null : $data['reviewed_at'],
				'source_id'                 => $data['source_id'],
				'signal_pattern_id'         => $data['signal_pattern_id'],
				'target_type'               => $data['target_type'],
				'target_id'                 => $data['target_id'],
				'finding_type'              => $data['finding_type'],
				'finding_area'              => $data['finding_area'],
				'signal_strength'           => $data['signal_strength'],
				'repetition_level'          => $data['repetition_level'],
				'verification_status'       => $data['verification_status'],
				'evidence_type'             => $data['evidence_type'],
				'evidence_excerpt'          => $data['evidence_excerpt'],
				'evidence_url'              => $data['evidence_url'],
				'observed_at'               => $data['observed_at'] === '' ? null : $data['observed_at'],
				'reviewer_name'             => $data['reviewer_name'],
				'reviewer_origin'           => $data['reviewer_origin'],
				'language'                  => $data['language'],
				'related_collection_task_id'=> $data['related_collection_task_id'],
				'notes'                     => $data['notes'],
				'created_at'                => $now,
				'updated_at'                => $now,
			]
		);
		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function update_finding( $id, $data ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'finding_title'             => $data['finding_title'],
				'task_id'                   => $data['task_id'],
				'run_id'                    => $data['run_id'],
				'source_url'                => $data['source_url'],
				'source_title'              => $data['source_title'],
				'source_type'               => $data['source_type'],
				'excerpt'                   => $data['excerpt'],
				'detected_sentiment'        => $data['detected_sentiment'],
				'review_published_at'       => ( $data['review_published_at'] ?? '' ) === '' ? null : $data['review_published_at'],
				'analysis_performed_at'     => ( $data['analysis_performed_at'] ?? '' ) === '' ? null : $data['analysis_performed_at'],
				'source_detected_at'        => ( $data['source_detected_at'] ?? '' ) === '' ? null : $data['source_detected_at'],
				'source_last_checked_at'    => ( $data['source_last_checked_at'] ?? '' ) === '' ? null : $data['source_last_checked_at'],
				'reference_language'        => $data['reference_language'] ?? '',
				'reference_type'            => $data['reference_type'] ?? 'other',
				'analysis_summary'          => $data['analysis_summary'] ?? '',
				'analysis_status'           => $data['analysis_status'] ?? 'pending',
				'confidence_score'          => $data['confidence_score'] ?? null,
				'destination_mapping_note'  => $data['destination_mapping_note'] ?? '',
				'poi_extraction_note'       => $data['poi_extraction_note'] ?? '',
				'offer_relation_note'       => $data['offer_relation_note'] ?? '',
				'poi_candidate_id'          => $data['poi_candidate_id'],
				'destination_id'            => $data['destination_id'],
				'supplier_id'               => $data['supplier_id'],
				'offer_id'                  => $data['offer_id'],
				'hash'                      => $data['hash'],
				'status'                    => $data['status'],
				'found_at'                  => $data['found_at'] === '' ? null : $data['found_at'],
				'reviewed_by'               => $data['reviewed_by'],
				'reviewed_at'               => $data['reviewed_at'] === '' ? null : $data['reviewed_at'],
				'source_id'                 => $data['source_id'],
				'signal_pattern_id'         => $data['signal_pattern_id'],
				'target_type'               => $data['target_type'],
				'target_id'                 => $data['target_id'],
				'finding_type'              => $data['finding_type'],
				'finding_area'              => $data['finding_area'],
				'signal_strength'           => $data['signal_strength'],
				'repetition_level'          => $data['repetition_level'],
				'verification_status'       => $data['verification_status'],
				'evidence_type'             => $data['evidence_type'],
				'evidence_excerpt'          => $data['evidence_excerpt'],
				'evidence_url'              => $data['evidence_url'],
				'observed_at'               => $data['observed_at'] === '' ? null : $data['observed_at'],
				'reviewer_name'             => $data['reviewer_name'],
				'reviewer_origin'           => $data['reviewer_origin'],
				'language'                  => $data['language'],
				'related_collection_task_id'=> $data['related_collection_task_id'],
				'notes'                     => $data['notes'],
				'updated_at'                => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	public static function archive_finding( $id ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'verification_status' => 'archived',
				'updated_at'          => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	public static function sanitize_finding_data( $input ) {
		$evidence_url_raw = trim( (string) ( $input['evidence_url'] ?? '' ) );
		$evidence_url     = $evidence_url_raw === '' ? '' : esc_url_raw( $evidence_url_raw );
		$source_url_raw   = trim( (string) ( $input['source_url'] ?? '' ) );
		$source_url       = $source_url_raw === '' ? '' : esc_url_raw( $source_url_raw );

		return [
			'finding_title'              => sanitize_text_field( $input['finding_title'] ?? '' ),
			'task_id'                    => absint( $input['task_id'] ?? 0 ),
			'run_id'                     => absint( $input['run_id'] ?? 0 ),
			'source_url'                 => $source_url,
			'source_url_raw'             => $source_url_raw,
			'source_title'               => sanitize_text_field( $input['source_title'] ?? '' ),
			'source_type'                => sanitize_text_field( $input['source_type'] ?? '' ),
			'excerpt'                    => sanitize_textarea_field( $input['excerpt'] ?? '' ),
			'detected_sentiment'         => sanitize_text_field( $input['detected_sentiment'] ?? '' ),
			'review_published_at'        => sanitize_text_field( str_replace( 'T', ' ', $input['review_published_at'] ?? '' ) ),
			'analysis_performed_at'      => sanitize_text_field( str_replace( 'T', ' ', $input['analysis_performed_at'] ?? '' ) ),
			'source_detected_at'         => sanitize_text_field( str_replace( 'T', ' ', $input['source_detected_at'] ?? '' ) ),
			'source_last_checked_at'     => sanitize_text_field( str_replace( 'T', ' ', $input['source_last_checked_at'] ?? '' ) ),
			'reference_language'         => sanitize_text_field( $input['reference_language'] ?? '' ),
			'reference_type'             => sanitize_text_field( $input['reference_type'] ?? 'other' ),
			'analysis_summary'           => sanitize_textarea_field( $input['analysis_summary'] ?? '' ),
			'analysis_status'            => sanitize_text_field( $input['analysis_status'] ?? 'pending' ),
			'confidence_score'           => ( $input['confidence_score'] ?? '' ) === '' ? null : floatval( $input['confidence_score'] ),
			'destination_mapping_note'   => sanitize_textarea_field( $input['destination_mapping_note'] ?? '' ),
			'poi_extraction_note'        => sanitize_textarea_field( $input['poi_extraction_note'] ?? '' ),
			'offer_relation_note'        => sanitize_textarea_field( $input['offer_relation_note'] ?? '' ),
			'poi_candidate_id'           => absint( $input['poi_candidate_id'] ?? 0 ),
			'destination_id'             => absint( $input['destination_id'] ?? 0 ),
			'supplier_id'                => absint( $input['supplier_id'] ?? 0 ),
			'offer_id'                   => absint( $input['offer_id'] ?? 0 ),
			'hash'                       => sanitize_text_field( $input['hash'] ?? '' ),
			'status'                     => sanitize_text_field( $input['status'] ?? 'new' ),
			'found_at'                   => sanitize_text_field( str_replace( 'T', ' ', $input['found_at'] ?? '' ) ),
			'reviewed_by'                => absint( $input['reviewed_by'] ?? 0 ),
			'reviewed_at'                => sanitize_text_field( str_replace( 'T', ' ', $input['reviewed_at'] ?? '' ) ),
			'source_id'                  => absint( $input['source_id'] ?? 0 ),
			'signal_pattern_id'          => absint( $input['signal_pattern_id'] ?? 0 ),
			'target_type'                => sanitize_text_field( $input['target_type'] ?? 'general' ),
			'target_id'                  => absint( $input['target_id'] ?? 0 ),
			'finding_type'               => sanitize_text_field( $input['finding_type'] ?? 'neutral' ),
			'finding_area'               => sanitize_text_field( $input['finding_area'] ?? '' ),
			'signal_strength'            => sanitize_text_field( $input['signal_strength'] ?? 'medium' ),
			'repetition_level'           => sanitize_text_field( $input['repetition_level'] ?? 'single' ),
			'verification_status'        => sanitize_text_field( $input['verification_status'] ?? 'new' ),
			'evidence_type'              => sanitize_text_field( $input['evidence_type'] ?? 'text' ),
			'evidence_excerpt'           => sanitize_textarea_field( $input['evidence_excerpt'] ?? '' ),
			'evidence_url'               => $evidence_url,
			'evidence_url_raw'           => $evidence_url_raw,
			'observed_at'                => sanitize_text_field( str_replace( 'T', ' ', $input['observed_at'] ?? '' ) ),
			'reviewer_name'              => sanitize_text_field( $input['reviewer_name'] ?? '' ),
			'reviewer_origin'            => sanitize_text_field( $input['reviewer_origin'] ?? '' ),
			'language'                   => sanitize_text_field( $input['language'] ?? '' ),
			'related_collection_task_id' => absint( $input['related_collection_task_id'] ?? 0 ),
			'notes'                      => sanitize_textarea_field( $input['notes'] ?? '' ),
		];
	}

	public static function validate_finding_data( $data ) {
		$errors = [];

		if ( $data['finding_title'] === '' ) {
			$errors[] = 'finding_title is required';
		}

		if ( ! is_int( $data['source_id'] ) || $data['source_id'] < 0 ) {
			$errors[] = 'invalid source_id';
		}

		if ( ! is_int( $data['signal_pattern_id'] ) || $data['signal_pattern_id'] < 0 ) {
			$errors[] = 'invalid signal_pattern_id';
		}

		if ( ! in_array( $data['target_type'], self::get_allowed_target_types(), true ) ) {
			$errors[] = 'invalid target_type';
		}

		if ( ! is_int( $data['target_id'] ) || $data['target_id'] < 0 ) {
			$errors[] = 'invalid target_id';
		}

		if ( ! in_array( $data['finding_type'], self::get_allowed_finding_types(), true ) ) {
			$errors[] = 'invalid finding_type';
		}

		if ( $data['finding_area'] !== '' && ! in_array( $data['finding_area'], self::get_allowed_finding_areas(), true ) ) {
			$errors[] = 'invalid finding_area';
		}

		if ( ! in_array( $data['signal_strength'], self::get_allowed_signal_strengths(), true ) ) {
			$errors[] = 'invalid signal_strength';
		}

		if ( ! in_array( $data['repetition_level'], self::get_allowed_repetition_levels(), true ) ) {
			$errors[] = 'invalid repetition_level';
		}

		if ( ! in_array( $data['verification_status'], self::get_allowed_verification_statuses(), true ) ) {
			$errors[] = 'invalid verification_status';
		}

		if ( ! in_array( $data['evidence_type'], self::get_allowed_evidence_types(), true ) ) {
			$errors[] = 'invalid evidence_type';
		}

		if ( ! in_array( $data['status'], self::get_allowed_statuses(), true ) ) {
			$errors[] = 'invalid status';
		}

		if ( $data['reference_type'] !== '' && ! in_array( $data['reference_type'], self::get_allowed_reference_types(), true ) ) {
			$errors[] = 'invalid reference_type';
		}

		if ( ! in_array( $data['analysis_status'], self::get_allowed_analysis_statuses(), true ) ) {
			$errors[] = 'invalid analysis_status';
		}

		if ( null !== $data['confidence_score'] && ( $data['confidence_score'] < 0 || $data['confidence_score'] > 100 ) ) {
			$errors[] = 'invalid confidence_score';
		}

		if ( ! empty( $data['source_url_raw'] ) && $data['source_url'] === '' ) {
			$errors[] = 'invalid source_url';
		}

		if ( ! empty( $data['evidence_url_raw'] ) && $data['evidence_url'] === '' ) {
			$errors[] = 'invalid evidence_url';
		}

		if ( ! is_int( $data['related_collection_task_id'] ) || $data['related_collection_task_id'] < 0 ) {
			$errors[] = 'invalid related_collection_task_id';
		}

		return $errors ? $errors : true;
	}

	public static function get_source_label( $source_id ) {
		$source_id = absint( $source_id );
		if ( $source_id <= 0 ) {
			return '—';
		}
		$source = Toptour_Ref_Reference_Sources::get_source( $source_id );
		if ( $source && ! empty( $source->source_title ) ) {
			return $source->source_title;
		}
		return 'source#' . $source_id;
	}

	public static function get_signal_pattern_label( $signal_pattern_id ) {
		$signal_pattern_id = absint( $signal_pattern_id );
		if ( $signal_pattern_id <= 0 ) {
			return '—';
		}
		global $wpdb;
		$table = $wpdb->prefix . 'toptour_ref_signal_patterns';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT name, pattern_key FROM $table WHERE id = %d", $signal_pattern_id ) );
		if ( $row ) {
			return ! empty( $row->name ) ? $row->name : $row->pattern_key;
		}
		return 'signal_pattern#' . $signal_pattern_id;
	}

	public static function get_target_label( $target_type, $target_id ) {
		if ( $target_type === 'general' ) {
			return 'General';
		}

		$target_id = absint( $target_id );
		if ( $target_id <= 0 ) {
			return '—';
		}

		switch ( $target_type ) {
			case 'facility':
				$facility = Toptour_Ref_Facilities::get_facility( $target_id );
				return $facility && ! empty( $facility->name ) ? $facility->name : 'facility#' . $target_id;
			case 'destination':
				$destination = Toptour_Ref_Destinations::get_destination( $target_id );
				return $destination && ! empty( $destination->name ) ? $destination->name : 'destination#' . $target_id;
			case 'point_of_interest':
				$point = Toptour_Ref_Points_Of_Interest::get_point( $target_id );
				return $point && ! empty( $point->name ) ? $point->name : 'point_of_interest#' . $target_id;
			case 'contact':
				$contact = Toptour_Ref_Contacts::get_contact( $target_id );
				return $contact && ! empty( $contact->display_name ) ? $contact->display_name : 'contact#' . $target_id;
			case 'interest':
				$interest = Toptour_Ref_Interests::get_interest( $target_id );
				return $interest && ! empty( $interest->name ) ? $interest->name : 'interest#' . $target_id;
			case 'collection_task':
				return self::get_collection_task_label( $target_id );
			case 'offer':
				return 'offer#' . $target_id;
			default:
				return $target_type . '#' . $target_id;
		}
	}

	public static function get_collection_task_label( $task_id ) {
		$task_id = absint( $task_id );
		if ( $task_id <= 0 ) {
			return '—';
		}
		$task = Toptour_Ref_Collection_Tasks::get_task( $task_id );
		if ( $task && ! empty( $task->task_title ) ) {
			return $task->task_title;
		}
		return 'collection_task#' . $task_id;
	}

	public static function get_active_sources_for_select() {
		global $wpdb;
		$table = $wpdb->prefix . 'toptour_ref_sources';
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, source_title FROM $table WHERE validation_status != %s ORDER BY source_title ASC", 'archived' ) );
	}

	public static function get_active_signal_patterns_for_select() {
		global $wpdb;
		$table = $wpdb->prefix . 'toptour_ref_signal_patterns';
		return $wpdb->get_results( "SELECT id, name, pattern_key FROM $table WHERE is_active = 1 ORDER BY name ASC" );
	}
}
