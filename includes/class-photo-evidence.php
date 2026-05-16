<?php
/**
 * Photo evidence data class.
 *
 * Internal visual evidence records with URL references only.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Photo_Evidence {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_photo_evidence';
	}

	public static function get_allowed_target_types() {
		return [ 'general', 'facility', 'destination', 'point_of_interest', 'contact', 'interest', 'offer', 'collection_task' ];
	}

	public static function get_allowed_photo_types() {
		return [ 'official_photo', 'guest_photo', 'guest_video', 'own_photo', 'own_video', 'platform_photo', 'social_media_photo', 'map_photo', 'mixed', 'other' ];
	}

	public static function get_allowed_comparison_categories() {
		return [ 'unknown', 'matches_official', 'slightly_enhanced', 'significant_contradiction', 'risk_detail', 'positive_surprise', 'outdated_official', 'unclear' ];
	}

	public static function get_allowed_visual_areas() {
		return [ 'room', 'bathroom', 'cleanliness', 'food', 'restaurant', 'wellness', 'pool', 'beach', 'slope', 'surroundings', 'entrance', 'parking', 'transport', 'safety', 'accessibility', 'view', 'noise_context', 'family_context', 'senior_context', 'local_experience', 'other' ];
	}

	public static function get_allowed_verification_statuses() {
		return [ 'new', 'checked', 'confirmed', 'disputed', 'rejected', 'archived' ];
	}

	public static function get_allowed_signal_strengths() {
		return [ 'weak', 'medium', 'strong', 'critical' ];
	}

	public static function get_photo_evidence_list( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'photo_type'          => '',
			'comparison_category' => '',
			'visual_area'         => '',
			'verification_status' => '',
			'signal_strength'     => '',
			'target_type'         => '',
			'source_id'           => '',
			'finding_id'          => '',
			'search'              => '',
			'page'                => 1,
			'per_page'            => 20,
		];

		$args   = array_merge( $defaults, $args );
		$where  = [];
		$values = [];

		if ( $args['photo_type'] !== '' && in_array( $args['photo_type'], self::get_allowed_photo_types(), true ) ) {
			$where[]  = 'photo_type = %s';
			$values[] = $args['photo_type'];
		}

		if ( $args['comparison_category'] !== '' && in_array( $args['comparison_category'], self::get_allowed_comparison_categories(), true ) ) {
			$where[]  = 'comparison_category = %s';
			$values[] = $args['comparison_category'];
		}

		if ( $args['visual_area'] !== '' && in_array( $args['visual_area'], self::get_allowed_visual_areas(), true ) ) {
			$where[]  = 'visual_area = %s';
			$values[] = $args['visual_area'];
		}

		if ( $args['verification_status'] !== '' && in_array( $args['verification_status'], self::get_allowed_verification_statuses(), true ) ) {
			$where[]  = 'verification_status = %s';
			$values[] = $args['verification_status'];
		}

		if ( $args['signal_strength'] !== '' && in_array( $args['signal_strength'], self::get_allowed_signal_strengths(), true ) ) {
			$where[]  = 'signal_strength = %s';
			$values[] = $args['signal_strength'];
		}

		if ( $args['target_type'] !== '' && in_array( $args['target_type'], self::get_allowed_target_types(), true ) ) {
			$where[]  = 'target_type = %s';
			$values[] = $args['target_type'];
		}

		if ( $args['source_id'] !== '' && absint( $args['source_id'] ) > 0 ) {
			$where[]  = 'source_id = %d';
			$values[] = absint( $args['source_id'] );
		}

		if ( $args['finding_id'] !== '' && absint( $args['finding_id'] ) > 0 ) {
			$where[]  = 'finding_id = %d';
			$values[] = absint( $args['finding_id'] );
		}

		if ( $args['search'] !== '' ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(evidence_title LIKE %s OR evidence_url LIKE %s OR thumbnail_url LIKE %s OR official_reference_url LIKE %s OR guest_reference_url LIKE %s OR observation_summary LIKE %s OR visible_details LIKE %s OR contradiction_note LIKE %s OR notes LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
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
			'rows'  => $rows ? $rows : [],
			'total' => $total,
		];
	}

	public static function get_photo_evidence( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	public static function create_photo_evidence( $data ) {
		global $wpdb;
		$now    = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'evidence_title'              => $data['evidence_title'],
				'source_id'                   => $data['source_id'],
				'finding_id'                  => $data['finding_id'],
				'target_type'                 => $data['target_type'],
				'target_id'                   => $data['target_id'],
				'photo_type'                  => $data['photo_type'],
				'comparison_category'         => $data['comparison_category'],
				'visual_area'                 => $data['visual_area'],
				'evidence_url'                => $data['evidence_url'],
				'thumbnail_url'               => $data['thumbnail_url'],
				'official_reference_url'      => $data['official_reference_url'],
				'guest_reference_url'         => $data['guest_reference_url'],
				'observation_summary'         => $data['observation_summary'],
				'visible_details'             => $data['visible_details'],
				'contradiction_note'          => $data['contradiction_note'],
				'verification_status'         => $data['verification_status'],
				'signal_strength'             => $data['signal_strength'],
				'observed_at'                 => $data['observed_at'] === '' ? null : $data['observed_at'],
				'language'                    => $data['language'],
				'related_collection_task_id'  => $data['related_collection_task_id'],
				'notes'                       => $data['notes'],
				'created_at'                  => $now,
				'updated_at'                  => $now,
			]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function update_photo_evidence( $id, $data ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'evidence_title'              => $data['evidence_title'],
				'source_id'                   => $data['source_id'],
				'finding_id'                  => $data['finding_id'],
				'target_type'                 => $data['target_type'],
				'target_id'                   => $data['target_id'],
				'photo_type'                  => $data['photo_type'],
				'comparison_category'         => $data['comparison_category'],
				'visual_area'                 => $data['visual_area'],
				'evidence_url'                => $data['evidence_url'],
				'thumbnail_url'               => $data['thumbnail_url'],
				'official_reference_url'      => $data['official_reference_url'],
				'guest_reference_url'         => $data['guest_reference_url'],
				'observation_summary'         => $data['observation_summary'],
				'visible_details'             => $data['visible_details'],
				'contradiction_note'          => $data['contradiction_note'],
				'verification_status'         => $data['verification_status'],
				'signal_strength'             => $data['signal_strength'],
				'observed_at'                 => $data['observed_at'] === '' ? null : $data['observed_at'],
				'language'                    => $data['language'],
				'related_collection_task_id'  => $data['related_collection_task_id'],
				'notes'                       => $data['notes'],
				'updated_at'                  => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);

		return $result !== false;
	}

	public static function archive_photo_evidence( $id ) {
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

	public static function sanitize_photo_evidence_data( $input ) {
		$evidence_url_raw           = trim( (string) ( $input['evidence_url'] ?? '' ) );
		$thumbnail_url_raw          = trim( (string) ( $input['thumbnail_url'] ?? '' ) );
		$official_reference_url_raw = trim( (string) ( $input['official_reference_url'] ?? '' ) );
		$guest_reference_url_raw    = trim( (string) ( $input['guest_reference_url'] ?? '' ) );

		$evidence_url               = $evidence_url_raw === '' ? '' : esc_url_raw( $evidence_url_raw );
		$thumbnail_url              = $thumbnail_url_raw === '' ? '' : esc_url_raw( $thumbnail_url_raw );
		$official_reference_url     = $official_reference_url_raw === '' ? '' : esc_url_raw( $official_reference_url_raw );
		$guest_reference_url        = $guest_reference_url_raw === '' ? '' : esc_url_raw( $guest_reference_url_raw );

		return [
			'evidence_title'               => sanitize_text_field( $input['evidence_title'] ?? '' ),
			'source_id'                    => absint( $input['source_id'] ?? 0 ),
			'finding_id'                   => absint( $input['finding_id'] ?? 0 ),
			'target_type'                  => sanitize_text_field( $input['target_type'] ?? 'general' ),
			'target_id'                    => absint( $input['target_id'] ?? 0 ),
			'photo_type'                   => sanitize_text_field( $input['photo_type'] ?? 'guest_photo' ),
			'comparison_category'          => sanitize_text_field( $input['comparison_category'] ?? 'unknown' ),
			'visual_area'                  => sanitize_text_field( $input['visual_area'] ?? '' ),
			'evidence_url'                 => $evidence_url,
			'thumbnail_url'                => $thumbnail_url,
			'official_reference_url'       => $official_reference_url,
			'guest_reference_url'          => $guest_reference_url,
			'evidence_url_raw'             => $evidence_url_raw,
			'thumbnail_url_raw'            => $thumbnail_url_raw,
			'official_reference_url_raw'   => $official_reference_url_raw,
			'guest_reference_url_raw'      => $guest_reference_url_raw,
			'observation_summary'          => sanitize_textarea_field( $input['observation_summary'] ?? '' ),
			'visible_details'              => sanitize_textarea_field( $input['visible_details'] ?? '' ),
			'contradiction_note'           => sanitize_textarea_field( $input['contradiction_note'] ?? '' ),
			'verification_status'          => sanitize_text_field( $input['verification_status'] ?? 'new' ),
			'signal_strength'              => sanitize_text_field( $input['signal_strength'] ?? 'medium' ),
			'observed_at'                  => sanitize_text_field( str_replace( 'T', ' ', $input['observed_at'] ?? '' ) ),
			'language'                     => sanitize_text_field( $input['language'] ?? '' ),
			'related_collection_task_id'   => absint( $input['related_collection_task_id'] ?? 0 ),
			'notes'                        => sanitize_textarea_field( $input['notes'] ?? '' ),
		];
	}

	public static function validate_photo_evidence_data( $data ) {
		$errors = [];

		if ( $data['evidence_title'] === '' ) {
			$errors[] = 'evidence_title is required';
		}

		if ( ! is_int( $data['source_id'] ) || $data['source_id'] < 0 ) {
			$errors[] = 'invalid source_id';
		}

		if ( ! is_int( $data['finding_id'] ) || $data['finding_id'] < 0 ) {
			$errors[] = 'invalid finding_id';
		}

		if ( ! in_array( $data['target_type'], self::get_allowed_target_types(), true ) ) {
			$errors[] = 'invalid target_type';
		}

		if ( ! is_int( $data['target_id'] ) || $data['target_id'] < 0 ) {
			$errors[] = 'invalid target_id';
		}

		if ( ! in_array( $data['photo_type'], self::get_allowed_photo_types(), true ) ) {
			$errors[] = 'invalid photo_type';
		}

		if ( ! in_array( $data['comparison_category'], self::get_allowed_comparison_categories(), true ) ) {
			$errors[] = 'invalid comparison_category';
		}

		if ( $data['visual_area'] !== '' && ! in_array( $data['visual_area'], self::get_allowed_visual_areas(), true ) ) {
			$errors[] = 'invalid visual_area';
		}

		if ( ! in_array( $data['verification_status'], self::get_allowed_verification_statuses(), true ) ) {
			$errors[] = 'invalid verification_status';
		}

		if ( ! in_array( $data['signal_strength'], self::get_allowed_signal_strengths(), true ) ) {
			$errors[] = 'invalid signal_strength';
		}

		if ( ! is_int( $data['related_collection_task_id'] ) || $data['related_collection_task_id'] < 0 ) {
			$errors[] = 'invalid related_collection_task_id';
		}

		$url_fields = [
			[ 'raw' => 'evidence_url_raw', 'san' => 'evidence_url', 'msg' => 'invalid evidence_url' ],
			[ 'raw' => 'thumbnail_url_raw', 'san' => 'thumbnail_url', 'msg' => 'invalid thumbnail_url' ],
			[ 'raw' => 'official_reference_url_raw', 'san' => 'official_reference_url', 'msg' => 'invalid official_reference_url' ],
			[ 'raw' => 'guest_reference_url_raw', 'san' => 'guest_reference_url', 'msg' => 'invalid guest_reference_url' ],
		];

		foreach ( $url_fields as $field ) {
			if ( ! empty( $data[ $field['raw'] ] ) && $data[ $field['san'] ] === '' ) {
				$errors[] = $field['msg'];
			}
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

	public static function get_finding_label( $finding_id ) {
		$finding_id = absint( $finding_id );
		if ( $finding_id <= 0 ) {
			return '—';
		}
		$finding = Toptour_Ref_Findings::get_finding( $finding_id );
		if ( $finding && ! empty( $finding->finding_title ) ) {
			return $finding->finding_title;
		}
		return 'finding#' . $finding_id;
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

	public static function get_active_findings_for_select() {
		global $wpdb;
		$table = $wpdb->prefix . 'toptour_ref_findings';
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, finding_title FROM $table WHERE verification_status != %s ORDER BY finding_title ASC", 'archived' ) );
	}
}
