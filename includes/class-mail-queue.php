<?php
/**
 * Mail queue data class.
 *
 * Internal draft/test-send queue for manually triggered notifications.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Mail_Queue {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_mail_queue';
	}

	public static function get_allowed_statuses() {
		return [ 'draft', 'ready', 'sent', 'failed', 'cancelled' ];
	}

	public static function create_mail( $data ) {
		global $wpdb;
		$mail_status = sanitize_text_field( $data['mail_status'] ?? 'draft' );
		if ( ! in_array( $mail_status, self::get_allowed_statuses(), true ) ) {
			$mail_status = 'draft';
		}

		$now = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'template_key'     => sanitize_text_field( $data['template_key'] ?? '' ),
				'related_type'     => sanitize_text_field( $data['related_type'] ?? '' ),
				'related_id'       => absint( $data['related_id'] ?? 0 ),
				'recipient_email'  => sanitize_email( $data['recipient_email'] ?? '' ),
				'recipient_user_id'=> absint( $data['recipient_user_id'] ?? 0 ),
				'subject'          => sanitize_text_field( $data['subject'] ?? '' ),
				'body'             => sanitize_textarea_field( $data['body'] ?? '' ),
				'mail_status'      => $mail_status,
				'send_attempts'    => absint( $data['send_attempts'] ?? 0 ),
				'last_error'       => sanitize_textarea_field( $data['last_error'] ?? '' ),
				'scheduled_at'     => ! empty( $data['scheduled_at'] ) ? sanitize_text_field( $data['scheduled_at'] ) : null,
				'sent_at'          => ! empty( $data['sent_at'] ) ? sanitize_text_field( $data['sent_at'] ) : null,
				'created_at'       => $now,
				'updated_at'       => $now,
			]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function get_mail( $mail_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $mail_id ) ) );
	}

	public static function get_last_mail_for_related( $related_type, $related_id, $template_key ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE related_type = %s AND related_id = %d AND template_key = %s ORDER BY created_at DESC, id DESC LIMIT 1",
				sanitize_text_field( $related_type ),
				absint( $related_id ),
				sanitize_text_field( $template_key )
			)
		);
	}

	public static function update_mail( $mail_id, $data ) {
		global $wpdb;
		$update_data = [
			'updated_at' => current_time( 'mysql' ),
		];

		if ( isset( $data['recipient_email'] ) ) {
			$update_data['recipient_email'] = sanitize_email( $data['recipient_email'] );
		}

		if ( isset( $data['subject'] ) ) {
			$update_data['subject'] = sanitize_text_field( $data['subject'] );
		}

		if ( isset( $data['body'] ) ) {
			$update_data['body'] = sanitize_textarea_field( $data['body'] );
		}

		if ( isset( $data['mail_status'] ) && in_array( $data['mail_status'], self::get_allowed_statuses(), true ) ) {
			$update_data['mail_status'] = $data['mail_status'];
		}

		$result = $wpdb->update( self::get_table_name(), $update_data, [ 'id' => absint( $mail_id ) ] );
		return $result !== false;
	}

	public static function mark_sent( $mail_id ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'mail_status' => 'sent',
				'sent_at'     => current_time( 'mysql' ),
				'last_error'  => '',
				'updated_at'  => current_time( 'mysql' ),
			],
			[ 'id' => absint( $mail_id ) ]
		);
		return $result !== false;
	}

	public static function mark_failed( $mail_id, $error ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'mail_status' => 'failed',
				'last_error'  => sanitize_textarea_field( (string) $error ),
				'updated_at'  => current_time( 'mysql' ),
			],
			[ 'id' => absint( $mail_id ) ]
		);
		return $result !== false;
	}

	public static function send_test_mail( $mail_id ) {
		global $wpdb;
		$mail = self::get_mail( $mail_id );
		if ( ! $mail ) {
			return [ 'success' => false, 'error' => 'Mail not found.' ];
		}

		$new_attempts = absint( $mail->send_attempts ) + 1;
		$wpdb->update(
			self::get_table_name(),
			[
				'send_attempts' => $new_attempts,
				'updated_at'    => current_time( 'mysql' ),
			],
			[ 'id' => absint( $mail_id ) ]
		);

		$recipient_email = sanitize_email( $mail->recipient_email );
		if ( ! is_email( $recipient_email ) ) {
			self::mark_failed( $mail_id, 'Invalid recipient_email.' );
			return [ 'success' => false, 'error' => 'Invalid recipient_email.' ];
		}

		$sent = wp_mail( $recipient_email, $mail->subject, $mail->body );
		if ( $sent ) {
			self::mark_sent( $mail_id );
			return [ 'success' => true, 'error' => '' ];
		}

		self::mark_failed( $mail_id, 'wp_mail returned false.' );
		return [ 'success' => false, 'error' => 'wp_mail returned false.' ];
	}
}
