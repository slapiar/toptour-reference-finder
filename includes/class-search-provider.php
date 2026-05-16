<?php
/**
 * Search provider layer for collection task intake.
 *
 * @package Toptour_Ref
 * @version 0.2.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Search_Provider {

	public static function get_settings() {
		$defaults = [
			'search_provider_enabled' => 1,
			'search_provider_type' => 'existing_candidates_only',
			'search_provider_endpoint' => '',
			'search_provider_api_key' => '',
			'max_search_results_per_task' => 15,
		];

		$settings = [
			'search_provider_enabled' => absint( get_option( 'toptour_ref_search_provider_enabled', $defaults['search_provider_enabled'] ) ) ? 1 : 0,
			'search_provider_type' => sanitize_text_field( get_option( 'toptour_ref_search_provider_type', $defaults['search_provider_type'] ) ),
			'search_provider_endpoint' => esc_url_raw( get_option( 'toptour_ref_search_provider_endpoint', $defaults['search_provider_endpoint'] ) ),
			'search_provider_api_key' => sanitize_text_field( get_option( 'toptour_ref_search_provider_api_key', $defaults['search_provider_api_key'] ) ),
			'max_search_results_per_task' => max( 1, min( 100, absint( get_option( 'toptour_ref_max_search_results_per_task', $defaults['max_search_results_per_task'] ) ) ) ),
		];

		if ( ! in_array( $settings['search_provider_type'], [ 'disabled', 'configured_api', 'existing_candidates_only' ], true ) ) {
			$settings['search_provider_type'] = $defaults['search_provider_type'];
		}

		return $settings;
	}

	public static function save_settings( $input ) {
		$enabled = ! empty( $input['search_provider_enabled'] ) ? 1 : 0;
		$type = sanitize_text_field( $input['search_provider_type'] ?? 'existing_candidates_only' );
		if ( ! in_array( $type, [ 'disabled', 'configured_api', 'existing_candidates_only' ], true ) ) {
			$type = 'existing_candidates_only';
		}
		$endpoint = esc_url_raw( $input['search_provider_endpoint'] ?? '' );
		$api_key = sanitize_text_field( $input['search_provider_api_key'] ?? '' );
		$max_results = max( 1, min( 100, absint( $input['max_search_results_per_task'] ?? 15 ) ) );

		update_option( 'toptour_ref_search_provider_enabled', $enabled );
		update_option( 'toptour_ref_search_provider_type', $type );
		update_option( 'toptour_ref_search_provider_endpoint', $endpoint );
		update_option( 'toptour_ref_search_provider_api_key', $api_key );
		update_option( 'toptour_ref_max_search_results_per_task', $max_results );

		return true;
	}

	public static function build_queries_from_task( $task, $limit = 5 ) {
		$limit = max( 1, min( 5, absint( $limit ) ) );
		$title = sanitize_text_field( $task->task_title ?? '' );
		$query_text = sanitize_textarea_field( $task->query_text ?? '' );
		$source_hint = sanitize_textarea_field( $task->source_hint ?? '' );
		$notes = sanitize_textarea_field( $task->notes ?? '' );

		$destination_name = self::resolve_destination_name( absint( $task->destination_id ?? 0 ) );
		$facility_name = self::resolve_facility_name( absint( $task->supplier_id ?? 0 ) );
		$offer_name = self::resolve_offer_name( absint( $task->offer_id ?? 0 ) );

		$haystack = strtolower( implode( ' ', [ $title, $query_text, $source_hint, $notes ] ) );
		$is_negative = (bool) preg_match( '/negativ|zle|zla|staznost|s[ck]laman|risk|problem|complaint|bad\s+review/i', $haystack );
		$is_positive = (bool) preg_match( '/pozitiv|vyborn|odporuc|good\s+review|excellent/i', $haystack );

		$location = trim( $destination_name );
		if ( '' === $location ) {
			$location = self::extract_location_hint( $title . ' ' . $query_text . ' ' . $source_hint );
		}
		$location_slug = '' !== $location ? $location : 'Slovensko';

		$queries = [];
		if ( $is_negative ) {
			$queries[] = 'negatívne recenzie ubytovanie ' . $location_slug;
			$queries[] = 'zlé skúsenosti hotel ' . $location_slug;
			$queries[] = 'sťažnosti hostí pobyt ' . $location_slug;
			$queries[] = 'recenzia hotel ' . $location_slug . ' sklamanie';
			$queries[] = $location_slug . ' hotel bad review';
		} elseif ( $is_positive ) {
			$queries[] = 'pozitívne recenzie ubytovanie ' . $location_slug;
			$queries[] = 'výborné skúsenosti hotel ' . $location_slug;
			$queries[] = 'odporúčané pobyty ' . $location_slug . ' recenzie';
			$queries[] = $location_slug . ' hotel good review';
			$queries[] = 'best accommodation reviews ' . $location_slug;
		} else {
			$queries[] = trim( $title . ' ' . $location_slug . ' recenzie' );
			$queries[] = trim( $query_text . ' ' . $location_slug );
			$queries[] = trim( 'ubytovanie ' . $location_slug . ' hodnotenia hostí' );
			$queries[] = trim( $location_slug . ' hotel reviews' );
			$queries[] = trim( 'guest photo review ' . $location_slug );
		}

		if ( '' !== $destination_name ) {
			$queries[] = trim( 'recenzie ubytovanie ' . $destination_name );
		}
		if ( '' !== $facility_name ) {
			$queries[] = trim( $facility_name . ' recenzia skúsenosť' );
		}
		if ( '' !== $offer_name ) {
			$queries[] = trim( $offer_name . ' ponuka recenzia' );
		}
		if ( '' !== $source_hint ) {
			$queries[] = trim( $source_hint );
		}
		if ( '' !== $query_text ) {
			$queries[] = trim( $query_text );
		}

		$normalized = [];
		foreach ( $queries as $query ) {
			$query = preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $query ) );
			if ( '' !== $query ) {
				$normalized[] = $query;
			}
		}

		return array_slice( array_values( array_unique( $normalized ) ), 0, $limit );
	}

	public static function get_existing_candidate_results( $task_id, $limit = 20 ) {
		global $wpdb;
		$limit = max( 1, absint( $limit ) );
		$table = $wpdb->prefix . 'toptour_ref_discovery_candidates';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT candidate_url, candidate_title, snippet, search_query FROM $table WHERE collection_task_id = %d AND candidate_url <> '' AND candidate_status IN ('new','needs_review','accepted') ORDER BY updated_at DESC, id DESC LIMIT %d",
				absint( $task_id ),
				$limit
			)
		);

		$results = [];
		foreach ( (array) $rows as $row ) {
			$results[] = [
				'result_url' => esc_url_raw( (string) $row->candidate_url ),
				'result_title' => sanitize_text_field( (string) $row->candidate_title ),
				'result_snippet' => sanitize_textarea_field( (string) $row->snippet ),
				'query_used' => sanitize_text_field( (string) $row->search_query ),
				'provider' => 'existing_candidates',
			];
		}

		return $results;
	}

	public static function search_configured_api( $query, $settings, $limit = 10 ) {
		$limit = max( 1, min( 20, absint( $limit ) ) );
		$query = sanitize_text_field( (string) $query );
		$endpoint = esc_url_raw( $settings['search_provider_endpoint'] ?? '' );
		$api_key = sanitize_text_field( $settings['search_provider_api_key'] ?? '' );

		if ( '' === $endpoint || '' === $api_key ) {
			return [
				'success' => false,
				'reason' => 'missing_api_config',
				'results' => [],
			];
		}

		$request_url = add_query_arg(
			[
				'q' => $query,
				'limit' => $limit,
			],
			$endpoint
		);

		$response = wp_remote_get(
			$request_url,
			[
				'timeout' => 12,
				'headers' => [
					'Accept' => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'reason' => 'api_request_failed',
				'message' => sanitize_text_field( $response->get_error_message() ),
				'results' => [],
			];
		}

		$code = absint( wp_remote_retrieve_response_code( $response ) );
		if ( $code < 200 || $code >= 300 ) {
			return [
				'success' => false,
				'reason' => 'api_http_error',
				'message' => 'HTTP ' . $code,
				'results' => [],
			];
		}

		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( (string) $body, true );
		if ( ! is_array( $decoded ) ) {
			return [
				'success' => false,
				'reason' => 'api_invalid_json',
				'results' => [],
			];
		}

		$items = [];
		if ( isset( $decoded['results'] ) && is_array( $decoded['results'] ) ) {
			$items = $decoded['results'];
		} elseif ( isset( $decoded['items'] ) && is_array( $decoded['items'] ) ) {
			$items = $decoded['items'];
		} elseif ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
			$items = $decoded['data'];
		}

		$results = [];
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$url = esc_url_raw( (string) ( $item['url'] ?? $item['link'] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}
			$results[] = [
				'result_url' => $url,
				'result_title' => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) ),
				'result_snippet' => sanitize_textarea_field( (string) ( $item['snippet'] ?? $item['description'] ?? '' ) ),
				'query_used' => $query,
				'provider' => 'configured_api',
			];
			if ( count( $results ) >= $limit ) {
				break;
			}
		}

		return [
			'success' => true,
			'results' => $results,
		];
	}

	private static function resolve_destination_name( $destination_id ) {
		if ( $destination_id <= 0 ) {
			return '';
		}
		$row = Toptour_Ref_Destinations::get_destination( $destination_id );
		return $row ? sanitize_text_field( $row->name ?? '' ) : '';
	}

	private static function resolve_facility_name( $facility_id ) {
		if ( $facility_id <= 0 ) {
			return '';
		}
		$row = Toptour_Ref_Facilities::get_facility( $facility_id );
		return $row ? sanitize_text_field( $row->name ?? '' ) : '';
	}

	private static function resolve_offer_name( $offer_id ) {
		if ( $offer_id <= 0 ) {
			return '';
		}
		$row = Toptour_Ref_Offers::get_offer( $offer_id );
		return $row ? sanitize_text_field( $row->offer_name ?? '' ) : '';
	}

	private static function extract_location_hint( $text ) {
		$text = sanitize_text_field( (string) $text );
		if ( '' === $text ) {
			return '';
		}
		if ( preg_match( '/slovensk[oa]|slovakia/i', $text ) ) {
			return 'Slovensko';
		}
		return '';
	}
}
