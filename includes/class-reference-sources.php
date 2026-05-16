<?php
/**
 * Reference Sources data class.
 *
 * Internal registry for manually captured reference sources.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Reference_Sources {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_sources';
	}

	public static function get_allowed_source_types() {
		return [ 'review', 'guest_photo', 'official_photo', 'video', 'blog', 'forum', 'platform_rating', 'own_client_feedback', 'social_media', 'map_listing', 'article', 'mixed', 'other' ];
	}

	public static function get_allowed_source_origins() {
		return [ 'unknown', 'official_provider', 'verified_platform', 'public_review_platform', 'social_media', 'guest_generated', 'own_client', 'partner', 'local_resident', 'blog_or_article', 'forum', 'map_service', 'manual_discovery', 'other' ];
	}

	public static function get_allowed_target_types() {
		return [ 'general', 'facility', 'destination', 'point_of_interest', 'contact', 'interest', 'offer', 'collection_task' ];
	}

	public static function get_allowed_credibility_levels() {
		return [ 'unknown', 'low', 'medium', 'high', 'verified' ];
	}

	public static function get_allowed_suggested_credibility_levels() {
		return [ '', 'unknown', 'low', 'medium', 'high', 'verified' ];
	}

	public static function get_allowed_verification_methods() {
		return [ 'manual', 'cross_checked', 'client_confirmed', 'resident_confirmed', 'platform_consistency', 'photo_consistency', 'not_verified', 'future_automation' ];
	}

	public static function get_allowed_suggestion_statuses() {
		return [ 'none', 'suggested', 'manager_review', 'accepted', 'rejected', 'applied' ];
	}

	public static function get_allowed_search_priorities() {
		return [ 'low', 'normal', 'high', 'urgent', 'deferred' ];
	}

	public static function get_allowed_next_actions() {
		return [ 'review_source', 'extract_findings', 'compare_photos', 'cross_check', 'ask_resident', 'ask_manager', 'archive', 'ignore' ];
	}

	public static function get_allowed_validation_statuses() {
		return [ 'new', 'checked', 'useful', 'weak', 'duplicate', 'rejected', 'archived' ];
	}

	public static function get_allowed_access_statuses() {
		return [ 'unknown', 'accessible', 'login_required', 'blocked', 'broken', 'paywalled' ];
	}

	public static function get_sources( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'source_type'                  => '',
			'source_origin'                => '',
			'target_type'                  => '',
			'credibility_level'            => '',
			'suggested_credibility_level'  => '',
			'suggestion_status'            => '',
			'search_priority'              => '',
			'next_action'                  => '',
			'validation_status'            => '',
			'access_status'                => '',
			'source_platform'              => '',
			'search'                       => '',
			'page'                         => 1,
			'per_page'                     => 20,
		];

		$args = array_merge( $defaults, $args );
		$where = [];
		$values = [];

		if ( $args['source_type'] !== '' && in_array( $args['source_type'], self::get_allowed_source_types(), true ) ) {
			$where[] = 'source_type = %s';
			$values[] = $args['source_type'];
		}

		if ( $args['source_origin'] !== '' && in_array( $args['source_origin'], self::get_allowed_source_origins(), true ) ) {
			$where[] = 'source_origin = %s';
			$values[] = $args['source_origin'];
		}

		if ( $args['target_type'] !== '' && in_array( $args['target_type'], self::get_allowed_target_types(), true ) ) {
			$where[] = 'target_type = %s';
			$values[] = $args['target_type'];
		}

		if ( $args['credibility_level'] !== '' && in_array( $args['credibility_level'], self::get_allowed_credibility_levels(), true ) ) {
			$where[] = 'credibility_level = %s';
			$values[] = $args['credibility_level'];
		}

		if ( in_array( $args['suggested_credibility_level'], self::get_allowed_suggested_credibility_levels(), true ) && $args['suggested_credibility_level'] !== '' ) {
			$where[] = 'suggested_credibility_level = %s';
			$values[] = $args['suggested_credibility_level'];
		}

		if ( $args['suggestion_status'] !== '' && in_array( $args['suggestion_status'], self::get_allowed_suggestion_statuses(), true ) ) {
			$where[] = 'suggestion_status = %s';
			$values[] = $args['suggestion_status'];
		}

		if ( $args['search_priority'] !== '' && in_array( $args['search_priority'], self::get_allowed_search_priorities(), true ) ) {
			$where[] = 'search_priority = %s';
			$values[] = $args['search_priority'];
		}

		if ( $args['next_action'] !== '' && in_array( $args['next_action'], self::get_allowed_next_actions(), true ) ) {
			$where[] = 'next_action = %s';
			$values[] = $args['next_action'];
		}

		if ( $args['validation_status'] !== '' && in_array( $args['validation_status'], self::get_allowed_validation_statuses(), true ) ) {
			$where[] = 'validation_status = %s';
			$values[] = $args['validation_status'];
		}

		if ( $args['access_status'] !== '' && in_array( $args['access_status'], self::get_allowed_access_statuses(), true ) ) {
			$where[] = 'access_status = %s';
			$values[] = $args['access_status'];
		}

		if ( $args['source_platform'] !== '' ) {
			$where[] = 'source_platform = %s';
			$values[] = $args['source_platform'];
		}

		if ( $args['search'] !== '' ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(source_title LIKE %s OR source_url LIKE %s OR source_platform LIKE %s OR credibility_reason LIKE %s OR verification_notes LIKE %s OR suggestion_reason LIKE %s OR notes LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
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
			'sources' => $rows ? $rows : [],
			'total'   => $total,
		];
	}

	public static function get_source( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	public static function create_source( $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );

		if ( $data['captured_at'] === '' ) {
			$data['captured_at'] = $now;
		}

		if ( $data['credibility_level'] !== 'unknown' && $data['credibility_updated_at'] === '' ) {
			$data['credibility_updated_at'] = $now;
		}

		if ( $data['suggested_credibility_level'] !== '' && in_array( $data['suggestion_status'], [ 'suggested', 'manager_review' ], true ) && $data['suggestion_created_at'] === '' ) {
			$data['suggestion_created_at'] = $now;
		}

		if ( in_array( $data['suggestion_status'], [ 'accepted', 'rejected', 'applied' ], true ) && $data['suggestion_resolved_at'] === '' ) {
			$data['suggestion_resolved_at'] = $now;
		}

		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'source_title'                  => $data['source_title'],
				'source_url'                    => $data['source_url'],
				'source_platform'               => $data['source_platform'],
				'source_type'                   => $data['source_type'],
				'source_origin'                 => $data['source_origin'],
				'target_type'                   => $data['target_type'],
				'target_id'                     => $data['target_id'],
				'collection_task_id'            => $data['collection_task_id'],
				'language'                      => $data['language'],
				'captured_at'                   => $data['captured_at'] === '' ? null : $data['captured_at'],
				'source_date'                   => $data['source_date'] === '' ? null : $data['source_date'],
				'external_rating'               => $data['external_rating'],
				'external_review_count'         => $data['external_review_count'],
				'credibility_level'             => $data['credibility_level'],
				'credibility_reason'            => $data['credibility_reason'],
				'credibility_updated_at'        => $data['credibility_updated_at'] === '' ? null : $data['credibility_updated_at'],
				'verification_method'           => $data['verification_method'],
				'verification_notes'            => $data['verification_notes'],
				'last_verified_at'              => $data['last_verified_at'] === '' ? null : $data['last_verified_at'],
				'suggested_credibility_level'   => $data['suggested_credibility_level'],
				'suggestion_reason'             => $data['suggestion_reason'],
				'suggestion_status'             => $data['suggestion_status'],
				'suggestion_created_at'         => $data['suggestion_created_at'] === '' ? null : $data['suggestion_created_at'],
				'suggestion_resolved_at'        => $data['suggestion_resolved_at'] === '' ? null : $data['suggestion_resolved_at'],
				'suggestion_reviewed_by'        => $data['suggestion_reviewed_by'],
				'search_priority'               => $data['search_priority'],
				'next_action'                   => $data['next_action'],
				'validation_status'             => $data['validation_status'],
				'access_status'                 => $data['access_status'],
				'notes'                         => $data['notes'],
				'created_at'                    => $now,
				'updated_at'                    => $now,
			]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function update_source( $id, $data ) {
		global $wpdb;
		$existing = self::get_source( $id );
		if ( ! $existing ) {
			return false;
		}

		$now = current_time( 'mysql' );

		if ( $data['credibility_level'] !== $existing->credibility_level && $data['credibility_updated_at'] === '' ) {
			$data['credibility_updated_at'] = $now;
		}

		if ( $data['suggested_credibility_level'] !== '' && in_array( $data['suggestion_status'], [ 'suggested', 'manager_review' ], true ) && $data['suggestion_created_at'] === '' ) {
			$data['suggestion_created_at'] = $now;
		}

		if ( in_array( $data['suggestion_status'], [ 'accepted', 'rejected', 'applied' ], true ) && $data['suggestion_resolved_at'] === '' ) {
			$data['suggestion_resolved_at'] = $now;
		}

		$result = $wpdb->update(
			self::get_table_name(),
			[
				'source_title'                  => $data['source_title'],
				'source_url'                    => $data['source_url'],
				'source_platform'               => $data['source_platform'],
				'source_type'                   => $data['source_type'],
				'source_origin'                 => $data['source_origin'],
				'target_type'                   => $data['target_type'],
				'target_id'                     => $data['target_id'],
				'collection_task_id'            => $data['collection_task_id'],
				'language'                      => $data['language'],
				'captured_at'                   => $data['captured_at'] === '' ? null : $data['captured_at'],
				'source_date'                   => $data['source_date'] === '' ? null : $data['source_date'],
				'external_rating'               => $data['external_rating'],
				'external_review_count'         => $data['external_review_count'],
				'credibility_level'             => $data['credibility_level'],
				'credibility_reason'            => $data['credibility_reason'],
				'credibility_updated_at'        => $data['credibility_updated_at'] === '' ? null : $data['credibility_updated_at'],
				'verification_method'           => $data['verification_method'],
				'verification_notes'            => $data['verification_notes'],
				'last_verified_at'              => $data['last_verified_at'] === '' ? null : $data['last_verified_at'],
				'suggested_credibility_level'   => $data['suggested_credibility_level'],
				'suggestion_reason'             => $data['suggestion_reason'],
				'suggestion_status'             => $data['suggestion_status'],
				'suggestion_created_at'         => $data['suggestion_created_at'] === '' ? null : $data['suggestion_created_at'],
				'suggestion_resolved_at'        => $data['suggestion_resolved_at'] === '' ? null : $data['suggestion_resolved_at'],
				'suggestion_reviewed_by'        => $data['suggestion_reviewed_by'],
				'search_priority'               => $data['search_priority'],
				'next_action'                   => $data['next_action'],
				'validation_status'             => $data['validation_status'],
				'access_status'                 => $data['access_status'],
				'notes'                         => $data['notes'],
				'updated_at'                    => $now,
			],
			[ 'id' => absint( $id ) ]
		);

		return $result !== false;
	}

	public static function archive_source( $id ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'validation_status' => 'archived',
				'updated_at'        => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	public static function sanitize_source_data( $input ) {
		$source_url_raw = trim( (string) ( $input['source_url'] ?? '' ) );
		$source_url = $source_url_raw === '' ? '' : esc_url_raw( $source_url_raw );

		return [
			'source_title'                 => sanitize_text_field( $input['source_title'] ?? '' ),
			'source_url'                   => $source_url,
			'source_url_raw'               => $source_url_raw,
			'source_platform'              => sanitize_text_field( $input['source_platform'] ?? '' ),
			'source_type'                  => sanitize_text_field( $input['source_type'] ?? 'review' ),
			'source_origin'                => sanitize_text_field( $input['source_origin'] ?? 'unknown' ),
			'target_type'                  => sanitize_text_field( $input['target_type'] ?? 'general' ),
			'target_id'                    => absint( $input['target_id'] ?? 0 ),
			'collection_task_id'           => absint( $input['collection_task_id'] ?? 0 ),
			'language'                     => sanitize_text_field( $input['language'] ?? '' ),
			'captured_at'                  => sanitize_text_field( $input['captured_at'] ?? '' ),
			'source_date'                  => sanitize_text_field( $input['source_date'] ?? '' ),
			'external_rating'              => sanitize_text_field( $input['external_rating'] ?? '' ),
			'external_review_count'        => absint( $input['external_review_count'] ?? 0 ),
			'credibility_level'            => sanitize_text_field( $input['credibility_level'] ?? 'unknown' ),
			'credibility_reason'           => sanitize_textarea_field( $input['credibility_reason'] ?? '' ),
			'credibility_updated_at'       => sanitize_text_field( $input['credibility_updated_at'] ?? '' ),
			'verification_method'          => sanitize_text_field( $input['verification_method'] ?? 'manual' ),
			'verification_notes'           => sanitize_textarea_field( $input['verification_notes'] ?? '' ),
			'last_verified_at'             => sanitize_text_field( $input['last_verified_at'] ?? '' ),
			'suggested_credibility_level'  => sanitize_text_field( $input['suggested_credibility_level'] ?? '' ),
			'suggestion_reason'            => sanitize_textarea_field( $input['suggestion_reason'] ?? '' ),
			'suggestion_status'            => sanitize_text_field( $input['suggestion_status'] ?? 'none' ),
			'suggestion_created_at'        => sanitize_text_field( $input['suggestion_created_at'] ?? '' ),
			'suggestion_resolved_at'       => sanitize_text_field( $input['suggestion_resolved_at'] ?? '' ),
			'suggestion_reviewed_by'       => absint( $input['suggestion_reviewed_by'] ?? 0 ),
			'search_priority'              => sanitize_text_field( $input['search_priority'] ?? 'normal' ),
			'next_action'                  => sanitize_text_field( $input['next_action'] ?? 'review_source' ),
			'validation_status'            => sanitize_text_field( $input['validation_status'] ?? 'new' ),
			'access_status'                => sanitize_text_field( $input['access_status'] ?? 'unknown' ),
			'notes'                        => sanitize_textarea_field( $input['notes'] ?? '' ),
		];
	}

	public static function validate_source_data( $data ) {
		$errors = [];

		if ( $data['source_title'] === '' ) {
			$errors[] = 'source_title is required';
		}

		if ( ! empty( $data['source_url_raw'] ) && $data['source_url'] === '' ) {
			$errors[] = 'invalid source_url';
		}

		if ( ! in_array( $data['source_type'], self::get_allowed_source_types(), true ) ) {
			$errors[] = 'invalid source_type';
		}

		if ( ! in_array( $data['source_origin'], self::get_allowed_source_origins(), true ) ) {
			$errors[] = 'invalid source_origin';
		}

		if ( ! in_array( $data['target_type'], self::get_allowed_target_types(), true ) ) {
			$errors[] = 'invalid target_type';
		}

		if ( ! in_array( $data['credibility_level'], self::get_allowed_credibility_levels(), true ) ) {
			$errors[] = 'invalid credibility_level';
		}

		if ( ! in_array( $data['suggested_credibility_level'], self::get_allowed_suggested_credibility_levels(), true ) ) {
			$errors[] = 'invalid suggested_credibility_level';
		}

		if ( ! in_array( $data['verification_method'], self::get_allowed_verification_methods(), true ) ) {
			$errors[] = 'invalid verification_method';
		}

		if ( ! in_array( $data['suggestion_status'], self::get_allowed_suggestion_statuses(), true ) ) {
			$errors[] = 'invalid suggestion_status';
		}

		if ( ! in_array( $data['search_priority'], self::get_allowed_search_priorities(), true ) ) {
			$errors[] = 'invalid search_priority';
		}

		if ( ! in_array( $data['next_action'], self::get_allowed_next_actions(), true ) ) {
			$errors[] = 'invalid next_action';
		}

		if ( ! in_array( $data['validation_status'], self::get_allowed_validation_statuses(), true ) ) {
			$errors[] = 'invalid validation_status';
		}

		if ( ! in_array( $data['access_status'], self::get_allowed_access_statuses(), true ) ) {
			$errors[] = 'invalid access_status';
		}

		if ( ! is_int( $data['target_id'] ) || $data['target_id'] < 0 ) {
			$errors[] = 'invalid target_id';
		}

		if ( ! is_int( $data['collection_task_id'] ) || $data['collection_task_id'] < 0 ) {
			$errors[] = 'invalid collection_task_id';
		}

		if ( ! is_int( $data['external_review_count'] ) || $data['external_review_count'] < 0 ) {
			$errors[] = 'invalid external_review_count';
		}

		$date_fields = [ 'captured_at', 'source_date', 'credibility_updated_at', 'last_verified_at', 'suggestion_created_at', 'suggestion_resolved_at' ];
		foreach ( $date_fields as $date_field ) {
			if ( $data[ $date_field ] !== '' && ! self::is_valid_datetime( $data[ $date_field ] ) ) {
				$errors[] = 'invalid ' . $date_field;
			}
		}

		return $errors ? $errors : true;
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

	public static function get_collection_task_label( $collection_task_id ) {
		$collection_task_id = absint( $collection_task_id );
		if ( $collection_task_id <= 0 ) {
			return '—';
		}

		$task = Toptour_Ref_Collection_Tasks::get_task( $collection_task_id );
		if ( $task && ! empty( $task->task_title ) ) {
			return $task->task_title;
		}

		return 'collection_task#' . $collection_task_id;
	}

	private static function is_valid_datetime( $value ) {
		$timestamp = strtotime( $value );
		return $timestamp !== false;
	}
}
