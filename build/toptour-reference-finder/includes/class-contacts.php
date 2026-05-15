<?php
/**
 * Contacts data class.
 *
 * Internal contacts registry for reference collection workflows.
 *
 * @package Toptour_Ref
 * @version 0.1.8
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contacts helper class.
 */
class Toptour_Ref_Contacts {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_contacts';
	}

	/**
	 * Get allowed contact types.
	 *
	 * @return string[]
	 */
	public static function get_allowed_contact_types() {
		return [ 'person', 'organization', 'group' ];
	}

	/**
	 * Get allowed statuses.
	 *
	 * @return string[]
	 */
	public static function get_allowed_statuses() {
		return [ 'draft', 'active', 'watched', 'inactive', 'archived' ];
	}

	/**
	 * Get allowed trust levels.
	 *
	 * @return string[]
	 */
	public static function get_allowed_trust_levels() {
		return [ 'unknown', 'low', 'medium', 'high', 'verified' ];
	}

	/**
	 * Get contacts list.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function get_contacts( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'contact_type' => '',
			'status'       => '',
			'trust_level'  => '',
			'country'      => '',
			'region'       => '',
			'search'       => '',
			'page'         => 1,
			'per_page'     => 20,
		];
		$args = array_merge( $defaults, $args );

		$where = [];
		$values = [];

		if ( $args['contact_type'] !== '' && in_array( $args['contact_type'], self::get_allowed_contact_types(), true ) ) {
			$where[] = 'contact_type = %s';
			$values[] = $args['contact_type'];
		}

		if ( $args['status'] !== '' && in_array( $args['status'], self::get_allowed_statuses(), true ) ) {
			$where[] = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['trust_level'] !== '' && in_array( $args['trust_level'], self::get_allowed_trust_levels(), true ) ) {
			$where[] = 'trust_level = %s';
			$values[] = $args['trust_level'];
		}

		if ( $args['country'] !== '' ) {
			$where[] = 'country = %s';
			$values[] = $args['country'];
		}

		if ( $args['region'] !== '' ) {
			$where[] = 'region = %s';
			$values[] = $args['region'];
		}

		if ( $args['search'] !== '' ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(display_name LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR organization_name LIKE %s OR email LIKE %s OR phone LIKE %s OR city LIKE %s OR notes LIKE %s)';
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
			'contacts' => $rows ? $rows : [],
			'total'    => $total,
		];
	}

	/**
	 * Get one contact by ID.
	 *
	 * @param int $id Contact ID.
	 * @return object|null
	 */
	public static function get_contact( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Get contacts for admin selection fields.
	 *
	 * @param bool $include_archived Whether archived contacts should be included.
	 * @return object[]
	 */
	public static function get_contacts_for_selection( $include_archived = false ) {
		global $wpdb;
		$table = self::get_table_name();

		if ( $include_archived ) {
			return $wpdb->get_results( "SELECT id, display_name, status FROM $table ORDER BY display_name ASC" );
		}

		return $wpdb->get_results( $wpdb->prepare( "SELECT id, display_name, status FROM $table WHERE status != %s ORDER BY display_name ASC", 'archived' ) );
	}

	/**
	 * Create contact.
	 *
	 * @param array $data Validated contact data.
	 * @return int|false
	 */
	public static function create_contact( $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'contact_type'        => $data['contact_type'],
				'display_name'        => $data['display_name'],
				'first_name'          => $data['first_name'],
				'last_name'           => $data['last_name'],
				'organization_name'   => $data['organization_name'],
				'email'               => $data['email'],
				'phone'               => $data['phone'],
				'website_url'         => $data['website_url'],
				'country'             => $data['country'],
				'region'              => $data['region'],
				'city'                => $data['city'],
				'address'             => $data['address'],
				'preferred_language'  => $data['preferred_language'],
				'status'              => $data['status'],
				'trust_level'         => $data['trust_level'],
				'notes'               => $data['notes'],
				'created_at'          => $now,
				'updated_at'          => $now,
			]
		);
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update contact.
	 *
	 * @param int   $id Contact ID.
	 * @param array $data Validated contact data.
	 * @return bool
	 */
	public static function update_contact( $id, $data ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'contact_type'        => $data['contact_type'],
				'display_name'        => $data['display_name'],
				'first_name'          => $data['first_name'],
				'last_name'           => $data['last_name'],
				'organization_name'   => $data['organization_name'],
				'email'               => $data['email'],
				'phone'               => $data['phone'],
				'website_url'         => $data['website_url'],
				'country'             => $data['country'],
				'region'              => $data['region'],
				'city'                => $data['city'],
				'address'             => $data['address'],
				'preferred_language'  => $data['preferred_language'],
				'status'              => $data['status'],
				'trust_level'         => $data['trust_level'],
				'notes'               => $data['notes'],
				'updated_at'          => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	/**
	 * Archive contact.
	 *
	 * @param int $id Contact ID.
	 * @return bool
	 */
	public static function archive_contact( $id ) {
		global $wpdb;
		$result = $wpdb->update(
			self::get_table_name(),
			[
				'status'     => 'archived',
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ]
		);
		return $result !== false;
	}

	/**
	 * Sanitize contact form data.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_contact_data( $input ) {
		return [
			'contact_type'       => sanitize_text_field( $input['contact_type'] ?? 'person' ),
			'display_name'       => sanitize_text_field( $input['display_name'] ?? '' ),
			'first_name'         => sanitize_text_field( $input['first_name'] ?? '' ),
			'last_name'          => sanitize_text_field( $input['last_name'] ?? '' ),
			'organization_name'  => sanitize_text_field( $input['organization_name'] ?? '' ),
			'email'              => sanitize_email( $input['email'] ?? '' ),
			'phone'              => sanitize_text_field( $input['phone'] ?? '' ),
			'website_url'        => esc_url_raw( $input['website_url'] ?? '' ),
			'country'            => sanitize_text_field( $input['country'] ?? '' ),
			'region'             => sanitize_text_field( $input['region'] ?? '' ),
			'city'               => sanitize_text_field( $input['city'] ?? '' ),
			'address'            => sanitize_textarea_field( $input['address'] ?? '' ),
			'preferred_language' => sanitize_text_field( $input['preferred_language'] ?? '' ),
			'status'             => sanitize_text_field( $input['status'] ?? 'draft' ),
			'trust_level'        => sanitize_text_field( $input['trust_level'] ?? 'unknown' ),
			'notes'              => sanitize_textarea_field( $input['notes'] ?? '' ),
		];
	}

	/**
	 * Validate contact data.
	 *
	 * @param array $data Sanitized data.
	 * @param array $raw_input Raw user input.
	 * @return true|string[]
	 */
	public static function validate_contact_data( $data, $raw_input = [] ) {
		$errors = [];

		if ( '' === $data['display_name'] ) {
			$errors[] = 'display_name is required';
		}

		if ( ! in_array( $data['contact_type'], self::get_allowed_contact_types(), true ) ) {
			$errors[] = 'invalid contact_type';
		}

		if ( ! in_array( $data['status'], self::get_allowed_statuses(), true ) ) {
			$errors[] = 'invalid status';
		}

		if ( ! in_array( $data['trust_level'], self::get_allowed_trust_levels(), true ) ) {
			$errors[] = 'invalid trust_level';
		}

		$raw_email = isset( $raw_input['email'] ) ? trim( (string) $raw_input['email'] ) : '';
		if ( '' !== $raw_email && '' === $data['email'] ) {
			$errors[] = 'invalid email';
		}

		return $errors ? $errors : true;
	}
}
