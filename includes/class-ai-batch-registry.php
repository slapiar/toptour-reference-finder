<?php
/**
 * AI Batch Registry class.
 *
 * Idempotency registry for AI batch imports.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_AI_Batch_Registry {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_ai_batch_registry';
	}

	public static function claim_batch( $batch_id, $task_id, $outbox_file, $claimant, $stale_minutes = 30 ) {
		global $wpdb;
		$table = self::get_table_name();

		$batch_id = sanitize_text_field( (string) $batch_id );
		$task_id = absint( $task_id );
		$outbox_file = sanitize_file_name( (string) $outbox_file );
		$claimant = sanitize_text_field( (string) $claimant );
		$stale_minutes = max( 5, absint( $stale_minutes ) );
		$now = current_time( 'mysql' );
		$stale_cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $stale_minutes * MINUTE_IN_SECONDS ) );

		if ( '' === $batch_id ) {
			return [
				'ok' => false,
				'state' => 'invalid_batch_id',
			];
		}

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE batch_id = %s", $batch_id ) );
		if ( ! $existing ) {
			$inserted = $wpdb->insert(
				$table,
				[
					'batch_id' => $batch_id,
					'task_id' => $task_id,
					'outbox_file' => $outbox_file,
					'status' => 'pending',
					'claimed_by' => $claimant,
					'claimed_at' => $now,
					'processed_at' => null,
					'last_error' => '',
					'created_at' => $now,
					'updated_at' => $now,
				]
			);

			if ( $inserted ) {
				return [
					'ok' => true,
					'state' => 'claimed',
				];
			}

			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE batch_id = %s", $batch_id ) );
			if ( ! $existing ) {
				return [
					'ok' => false,
					'state' => 'claim_failed',
				];
			}
		}

		$status = sanitize_key( (string) ( $existing->status ?? '' ) );
		if ( 'processed' === $status ) {
			return [
				'ok' => false,
				'state' => 'already_processed',
			];
		}

		$claimed_at = sanitize_text_field( (string) ( $existing->claimed_at ?? '' ) );
		$is_stale = '' === $claimed_at || $claimed_at < $stale_cutoff;
		if ( 'pending' === $status && ! $is_stale && sanitize_text_field( (string) ( $existing->claimed_by ?? '' ) ) !== $claimant ) {
			return [
				'ok' => false,
				'state' => 'already_claimed',
			];
		}

		$updated = $wpdb->update(
			$table,
			[
				'task_id' => $task_id,
				'outbox_file' => $outbox_file,
				'status' => 'pending',
				'claimed_by' => $claimant,
				'claimed_at' => $now,
				'processed_at' => null,
				'last_error' => '',
				'updated_at' => $now,
			],
			[ 'batch_id' => $batch_id ]
		);

		return [
			'ok' => false !== $updated,
			'state' => false !== $updated ? 'claimed' : 'claim_failed',
		];
	}

	public static function mark_processed( $batch_id ) {
		global $wpdb;
		$table = self::get_table_name();
		$batch_id = sanitize_text_field( (string) $batch_id );
		if ( '' === $batch_id ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			[
				'status' => 'processed',
				'processed_at' => current_time( 'mysql' ),
				'last_error' => '',
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'batch_id' => $batch_id ]
		);

		return false !== $result;
	}

	public static function mark_failed( $batch_id, $message ) {
		global $wpdb;
		$table = self::get_table_name();
		$batch_id = sanitize_text_field( (string) $batch_id );
		if ( '' === $batch_id ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			[
				'status' => 'failed',
				'last_error' => sanitize_text_field( (string) $message ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'batch_id' => $batch_id ]
		);

		return false !== $result;
	}
}
