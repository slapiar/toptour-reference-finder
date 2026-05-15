<?php
/**
 * REST API class for TOPTOUR Reference Finder.
 *
 * Handles registration of REST API routes for PWA integration.
 * Current implementation is skeleton-only. All endpoints are reserved for future development.
 *
 * @package Toptour_Ref
 * @version 0.1.0
 *
 * REST Namespace: toptour-ref/v1
 *
 * All future endpoints will require proper capability checks and nonce validation.
 * No sensitive data will be exposed without authentication.
 * Service Worker caching is explicitly NOT used in this project.
 * PWA integration will use standard REST API calls with explicit error handling.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API class.
 */
class Toptour_Ref_REST_API {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'toptour-ref/v1';

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		// Current implementation: skeleton only, no routes registered.
		// All endpoints are documented below for future implementation.
		// Each endpoint will require proper authentication and capability checks.
		// No sensitive data is exposed in this version.
	}

	/**
	 * Check if current user can access reference data via REST API.
	 *
	 * @return bool
	 */
	public static function user_can_access_api() {
		return Toptour_Ref_Capabilities::user_can_manage_references();
	}

	/**
	 * Prepare response with error handling.
	 *
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status code.
	 *
	 * @return WP_REST_Response REST response object.
	 */
	public static function prepare_response( $data, $status = 200 ) {
		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Prepare error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status HTTP status code.
	 *
	 * @return WP_REST_Response Error response object.
	 */
	public static function prepare_error_response( $message, $status = 400 ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
				'error'   => true,
			),
			$status
		);
	}
}

/**
 * PLANNED REST API ENDPOINTS
 * ========================
 *
 * Namespace: toptour-ref/v1
 *
 * 1. GET /wp-json/toptour-ref/v1/status
 *    Purpose: Health check for PWA connectivity
 *    Response: { "ok": true, "plugin": "toptour-reference-finder", "version": "0.1.0" }
 *    Auth: Optional (public endpoint)
 *    TODO: Implement in MVP+1
 *
 * 2. GET /wp-json/toptour-ref/v1/facilities
 *    Purpose: List of monitored facilities
 *    Params: search, destination_id, type, status, page, per_page
 *    Auth: Required (manage_toptour_references)
 *    TODO: Implement in MVP+1
 *
 * 3. GET /wp-json/toptour-ref/v1/facilities/{id}
 *    Purpose: Facility detail including sources, findings, photo evidence
 *    Auth: Required (manage_toptour_references)
 *    TODO: Implement in MVP+1
 *
 * 4. GET /wp-json/toptour-ref/v1/destinations
 *    Purpose: List of destinations
 *    Params: search, country, region, type, status, page, per_page
 *    Auth: Required (manage_toptour_references)
 *    TODO: Implement in MVP+1
 *
 * 5. GET /wp-json/toptour-ref/v1/destinations/{id}
 *    Purpose: Destination detail with summary of facilities, offers, sources
 *    Auth: Required (manage_toptour_references)
 *    TODO: Implement in MVP+1
 *
 * 6. GET /wp-json/toptour-ref/v1/offers
 *    Purpose: List of offers being monitored for evidence
 *    Params: search, facility_id, destination_id, source, verification_status, page, per_page
 *    Auth: Required (manage_toptour_references)
 *    TODO: Implement in MVP+1
 *
 * 7. GET /wp-json/toptour-ref/v1/offers/{id}
 *    Purpose: Offer detail with supporting and contradicting sources
 *    Auth: Required (manage_toptour_references)
 *    TODO: Implement in MVP+1
 *
 * 8. GET /wp-json/toptour-ref/v1/sources
 *    Purpose: List of reference sources
 *    Params: facility_id, destination_id, offer_id, platform, source_type, credibility, page, per_page
 *    Auth: Required (manage_toptour_references)
 *    TODO: Implement in MVP+1
 *
 * 9. GET /wp-json/toptour-ref/v1/findings
 *    Purpose: List of extracted findings from reviews, photos, references
 *    Params: facility_id, destination_id, offer_id, source_id, finding_type, area, signal_strength, status, page, per_page
 *    Auth: Required (manage_toptour_references)
 *    TODO: Implement in MVP+1
 *
 * 10. GET /wp-json/toptour-ref/v1/findings/{id}
 *     Purpose: Finding detail with source and evidence
 *     Auth: Required (manage_toptour_references)
 *     TODO: Implement in MVP+1
 *
 * 11. GET /wp-json/toptour-ref/v1/photo-evidence
 *     Purpose: List of photo evidence and visual observations
 *     Params: facility_id, destination_id, offer_id, photo_type, comparison_category, status, page, per_page
 *     Auth: Required (manage_toptour_references)
 *     TODO: Implement in MVP+1
 *
 * 12. GET /wp-json/toptour-ref/v1/collection-tasks
 *     Purpose: List of reference collection work tasks for internal PWA dashboard
 *     Params: target_type, target_id, status, assigned_to, page, per_page
 *     Auth: Required (manage_toptour_references)
 *     TODO: Implement in MVP+1
 *
 * 13. POST /wp-json/toptour-ref/v1/collection-tasks
 *     Purpose: Create new collection task from PWA
 *     Auth: Required (manage_toptour_references) + nonce validation
 *     TODO: Implement in MVP+1
 *
 * 14. POST /wp-json/toptour-ref/v1/findings
 *     Purpose: Manual finding entry from PWA research
 *     Auth: Required (manage_toptour_references) + nonce validation
 *     TODO: Implement in MVP+1
 *
 * 15. POST /wp-json/toptour-ref/v1/photo-evidence
 *     Purpose: Add photo evidence or visual observation from PWA
 *     Auth: Required (manage_toptour_references) + nonce validation
 *     TODO: Implement in MVP+1
 *
 *
 * PWA INTEGRATION FALLBACK PRINCIPLES
 * ===================================
 *
 * All PWA implementations must handle these gracefully:
 *
 * 1. API Health Fallback
 *    - If /status endpoint does not respond: show "API unavailable" state
 *    - Do not retry endlessly
 *    - Do not crash the entire application
 *
 * 2. Empty State Fallback
 *    - If endpoint returns empty list: show friendly "no data yet" message
 *    - Example: "Zatiaľ nie sú evidované žiadne zistenia."
 *
 * 3. Permission Fallback
 *    - If REST API returns 401 or 403: show "Nemáte oprávnenie" message
 *    - Do not retry in a loop
 *    - Prompt user to log in or contact admin if needed
 *
 * 4. Network Fallback
 *    - If network is unreachable: show "Spojenie nie je dostupné" message
 *    - Do not attempt infinite polling
 *    - Do not use aggressive retry loops
 *
 * 5. Invalid Response Fallback
 *    - If API returns invalid JSON or missing fields: show safe error
 *    - Do not crash the screen
 *    - Log error for debugging
 *
 * 6. Pagination Fallback
 *    - If endpoint lacks pagination or returns invalid page: show first safe dataset
 *    - Do not require infinite scroll
 *    - Show reasonable default limits
 *
 * 7. Feature Unavailable Fallback
 *    - If endpoint not yet implemented: show "Táto časť ešte nie je dostupná."
 *    - Not "fatal error"
 *    - Plan for graceful feature rollout
 *
 *
 * SERVICE WORKER EXCLUSION POLICY
 * ==============================
 *
 * This project EXPLICITLY DOES NOT USE service workers for:
 * - Caching
 * - Background synchronization
 * - Push notifications
 * - Offline support
 * - Silent updates
 * - Security layers
 *
 * Reasons:
 * - Previous service worker implementations caused cached stale data issues
 * - Service workers can display outdated information without clear indication
 * - Debugging becomes harder
 * - Can create false sense of availability or security
 * - Internal work system requires predictable, online-first behavior
 *
 * PWA Integration Strategy:
 * - Standard REST API calls only
 * - Explicit fetch from frontend application
 * - Clear error handling states
 * - No hidden cache layers
 * - No offline security illusions
 */
