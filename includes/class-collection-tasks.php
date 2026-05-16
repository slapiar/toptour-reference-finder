<?php
/**
 * Collection Tasks data class.
 *
 * Internal work queue for reference collection planning.
 * No scraping, no automation, no external requests.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collection Tasks helper class.
 *
 * Handles read and write operations for the internal collection task queue.
 * Does not perform any automated execution, scraping or external requests.
 */
class Toptour_Ref_Collection_Tasks {

	/**
	 * Get the full table name with WP prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_collection_tasks';
	}

	/**
	 * Allowed values for target_type.
	 *
	 * @return string[]
	 */
	public static function get_allowed_target_types() {
		return [ 'general', 'facility', 'destination', 'point_of_interest', 'contact', 'interest', 'offer', 'source', 'collection_task' ];
	}

	/**
	 * Allowed values for task_status.
	 *
	 * @return string[]
	 */
	public static function get_allowed_statuses() {
		return [ 'draft', 'active', 'paused', 'archived', 'pending', 'in_progress', 'done', 'failed', 'needs_review' ];
	}

	/**
	 * Allowed values for frequency.
	 *
	 * @return string[]
	 */
	public static function get_allowed_frequencies() {
		return [ 'manual', 'daily', 'twice_daily', 'three_times_daily', 'six_daily', 'custom' ];
	}

	/**
	 * Allowed values for priority.
	 *
	 * @return string[]
	 */
	public static function get_allowed_priorities() {
		return [ 'low', 'normal', 'high', 'urgent' ];
	}

	/**
	 * Allowed values for expected_source_type.
	 *
	 * @return string[]
	 */
	public static function get_allowed_source_types() {
		return [ '', 'review', 'guest_photo', 'official_photo', 'video', 'blog', 'forum', 'platform_rating', 'mixed', 'other' ];
	}

	/**
	 * Update task last run timestamp and optional status.
	 *
	 * @param int         $task_id      Task ID.
	 * @param string|null $task_status  Optional task status.
	 * @return bool
	 */
	public static function touch_task_run( $task_id, $task_status = null ) {
		global $wpdb;

		$data = [
			'last_run_at' => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		];

		if ( null !== $task_status && in_array( $task_status, self::get_allowed_statuses(), true ) ) {
			$data['task_status'] = $task_status;
		}

		$result = $wpdb->update(
			self::get_table_name(),
			$data,
			[ 'id' => absint( $task_id ) ]
		);

		return $result !== false;
	}

	/**
	 * Get paginated list of tasks with optional filters and search.
	 *
	 * @param array $args {
	 *     Optional query args.
	 *     @type string $status      Filter by task_status.
	 *     @type string $priority    Filter by priority.
	 *     @type string $target_type Filter by target_type.
	 *     @type string $search      Search across task_title, query_text, source_hint, notes.
	 *     @type int    $page        Current page (1-based).
	 *     @type int    $per_page    Records per page. Default 20.
	 * }
	 * @return array { tasks: array, total: int }
	 */
	public static function get_tasks( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'status'      => '',
			'priority'    => '',
			'target_type' => '',
			'search'      => '',
			'page'        => 1,
			'per_page'    => 20,
		];
		$args = array_merge( $defaults, $args );

		$where   = [];
		$values  = [];

		if ( $args['status'] !== '' && in_array( $args['status'], self::get_allowed_statuses(), true ) ) {
			$where[]  = 'task_status = %s';
			$values[] = $args['status'];
		}

		if ( $args['priority'] !== '' && in_array( $args['priority'], self::get_allowed_priorities(), true ) ) {
			$where[]  = 'priority = %s';
			$values[] = $args['priority'];
		}

		if ( $args['target_type'] !== '' && in_array( $args['target_type'], self::get_allowed_target_types(), true ) ) {
			$where[]  = 'target_type = %s';
			$values[] = $args['target_type'];
		}

		if ( $args['search'] !== '' ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(task_title LIKE %s OR query_text LIKE %s OR source_hint LIKE %s OR notes LIKE %s)';
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
			$total_sql = "SELECT COUNT(*) FROM $table $where_sql";
			$total     = (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $values ) );

			$tasks_sql = "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
			$tasks     = $wpdb->get_results( $wpdb->prepare( $tasks_sql, array_merge( $values, [ $per_page, $offset ] ) ) );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
			$tasks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		}

		return [
			'tasks' => $tasks ? $tasks : [],
			'total' => $total,
		];
	}

	/**
	 * Get a single task by ID.
	 *
	 * @param int $id Task ID.
	 * @return object|null
	 */
	public static function get_task( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Create a new task.
	 *
	 * @param array $data Sanitized and validated task data.
	 * @return int|false Inserted ID or false on failure.
	 */
	public static function create_task( $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$created_by = ! empty( $data['created_by'] ) ? absint( $data['created_by'] ) : (int) get_current_user_id();
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'task_title'           => $data['task_title'],
				'destination_id'       => $data['destination_id'],
				'supplier_id'          => $data['supplier_id'],
				'offer_id'             => $data['offer_id'],
				'frequency'            => $data['frequency'],
				'next_run_at'          => $data['next_run_at'] === '' ? null : $data['next_run_at'],
				'created_by'           => $created_by,
				'target_type'          => $data['target_type'],
				'target_id'            => $data['target_id'],
				'query_text'           => $data['query_text'],
				'source_hint'          => $data['source_hint'],
				'expected_source_type' => $data['expected_source_type'],
				'task_status'          => $data['task_status'],
				'priority'             => $data['priority'],
				'assigned_to'          => $data['assigned_to'],
				'attempts'             => 0,
				'notes'                => $data['notes'],
				'created_at'           => $now,
				'updated_at'           => $now,
			]
		);
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing task.
	 *
	 * @param int   $id   Task ID.
	 * @param array $data Sanitized and validated task data.
	 * @return bool
	 */
	public static function update_task( $id, $data ) {
		global $wpdb;
		$existing_task = self::get_task( $id );
		$attempts = absint( $existing_task->attempts ?? 0 );
		if ( $existing_task && 'failed' === (string) ( $existing_task->task_status ?? '' ) && 'failed' !== (string) ( $data['task_status'] ?? '' ) ) {
			$attempts = 0;
		}
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'task_title'           => $data['task_title'],
				'destination_id'       => $data['destination_id'],
				'supplier_id'          => $data['supplier_id'],
				'offer_id'             => $data['offer_id'],
				'frequency'            => $data['frequency'],
				'next_run_at'          => $data['next_run_at'] === '' ? null : $data['next_run_at'],
				'target_type'          => $data['target_type'],
				'target_id'            => $data['target_id'],
				'query_text'           => $data['query_text'],
				'source_hint'          => $data['source_hint'],
				'expected_source_type' => $data['expected_source_type'],
				'task_status'          => $data['task_status'],
				'priority'             => $data['priority'],
				'assigned_to'          => $data['assigned_to'],
				'notes'                => $data['notes'],
				'attempts'             => $attempts,
				'updated_at'           => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	/**
	 * Archive a task (sets task_status to archived, does not delete).
	 *
	 * @param int $id Task ID.
	 * @return bool
	 */
	public static function archive_task( $id ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'task_status' => 'archived',
				'updated_at'  => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	/**
	 * Sanitize raw POST input for a task.
	 *
	 * @param array $input Raw POST data.
	 * @return array
	 */
	public static function sanitize_task_data( $input ) {
		$next_run_at = sanitize_text_field( str_replace( 'T', ' ', $input['next_run_at'] ?? '' ) );

		return [
			'task_title'           => sanitize_text_field( $input['task_title'] ?? '' ),
			'destination_id'       => absint( $input['destination_id'] ?? 0 ),
			'supplier_id'          => absint( $input['supplier_id'] ?? 0 ),
			'offer_id'             => absint( $input['offer_id'] ?? 0 ),
			'frequency'            => sanitize_text_field( $input['frequency'] ?? 'manual' ),
			'next_run_at'          => $next_run_at,
			'created_by'           => absint( $input['created_by'] ?? 0 ),
			'target_type'          => sanitize_text_field( $input['target_type'] ?? 'general' ),
			'target_id'            => absint( $input['target_id'] ?? 0 ),
			'query_text'           => sanitize_textarea_field( $input['query_text'] ?? '' ),
			'source_hint'          => sanitize_textarea_field( $input['source_hint'] ?? '' ),
			'expected_source_type' => sanitize_text_field( $input['expected_source_type'] ?? '' ),
			'task_status'          => sanitize_text_field( $input['task_status'] ?? 'pending' ),
			'priority'             => sanitize_text_field( $input['priority'] ?? 'normal' ),
			'assigned_to'          => absint( $input['assigned_to'] ?? 0 ),
			'notes'                => sanitize_textarea_field( $input['notes'] ?? '' ),
		];
	}

	/**
	 * Validate sanitized task data.
	 *
	 * @param array $data Sanitized data.
	 * @return true|string[] True on success, array of error messages on failure.
	 */
	public static function validate_task_data( $data ) {
		$errors = [];

		if ( $data['task_title'] === '' ) {
			$errors[] = 'task_title is required.';
		}

		if ( ! in_array( $data['target_type'], self::get_allowed_target_types(), true ) ) {
			$errors[] = 'Invalid target_type.';
		}

		if ( ! in_array( $data['expected_source_type'], self::get_allowed_source_types(), true ) ) {
			$errors[] = 'Invalid expected_source_type.';
		}

		if ( ! in_array( $data['task_status'], self::get_allowed_statuses(), true ) ) {
			$errors[] = 'Invalid task_status.';
		}

		if ( ! in_array( $data['frequency'], self::get_allowed_frequencies(), true ) ) {
			$errors[] = 'Invalid frequency.';
		}

		if ( ! in_array( $data['priority'], self::get_allowed_priorities(), true ) ) {
			$errors[] = 'Invalid priority.';
		}

		return $errors ? $errors : true;
	}

	/**
	 * Build summary stats for a task detail.
	 *
	 * @param int $task_id Task ID.
	 * @return array
	 */
	public static function get_task_stats( $task_id ) {
		global $wpdb;

		$task_id = absint( $task_id );
		$findings_table = Toptour_Ref_Findings::get_table_name();
		$runs_table = Toptour_Ref_Task_Runs::get_table_name();

		$total_found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $findings_table WHERE task_id = %d OR related_collection_task_id = %d", $task_id, $task_id ) );
		$new_found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $findings_table WHERE (task_id = %d OR related_collection_task_id = %d) AND (status = %s OR verification_status = %s)", $task_id, $task_id, 'new', 'new' ) );
		$pending_review = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $findings_table WHERE (task_id = %d OR related_collection_task_id = %d) AND (status = %s OR verification_status = %s)", $task_id, $task_id, 'pending_review', 'checked' ) );
		$poi_suggestions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $findings_table WHERE (task_id = %d OR related_collection_task_id = %d) AND poi_candidate_id > 0", $task_id, $task_id ) );
		$error_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(error_count),0) FROM $runs_table WHERE task_id = %d", $task_id ) );

		return [
			'total_found' => $total_found,
			'new_found' => $new_found,
			'pending_review' => $pending_review,
			'poi_suggestions' => $poi_suggestions,
			'error_count' => $error_count,
		];
	}

	/**
	 * Get recent findings for task detail preview.
	 *
	 * @param int $task_id Task ID.
	 * @param int $limit Result limit.
	 * @return array
	 */
	public static function get_recent_findings( $task_id, $limit = 10 ) {
		global $wpdb;
		$table = Toptour_Ref_Findings::get_table_name();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE task_id = %d OR related_collection_task_id = %d ORDER BY COALESCE(found_at, created_at) DESC LIMIT %d",
				absint( $task_id ),
				absint( $task_id ),
				max( 1, absint( $limit ) )
			)
		);
	}

	/**
	 * Resolve destination label for task.
	 *
	 * @param object $task Task row.
	 * @return string
	 */
	public static function get_destination_label( $task ) {
		if ( ! $task || empty( $task->destination_id ) ) {
			return '—';
		}

		$destination = Toptour_Ref_Destinations::get_destination( absint( $task->destination_id ) );
		if ( $destination && ! empty( $destination->name ) ) {
			return $destination->name;
		}

		return 'destination#' . absint( $task->destination_id );
	}
}
