<?php
/**
 * Facility-Destination relation class.
 *
 * Manages many-to-many relations between facilities and destinations.
 *
 * @package Toptour_Ref
 * @version 0.1.5
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Facility-Destination relation helper.
 */
class Toptour_Ref_Facility_Destinations {

	/**
	 * Get full relation table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_facility_destination';
	}

	/**
	 * Allowed relation types.
	 *
	 * @return string[]
	 */
	public static function get_allowed_relation_types() {
		return [ 'primary_area', 'wider_region', 'nearby', 'service_area', 'transit_area', 'other' ];
	}

	/**
	 * Get destination rows assigned to facility.
	 *
	 * @param int $facility_id Facility ID.
	 * @return object[]
	 */
	public static function get_destinations_for_facility( $facility_id ) {
		global $wpdb;
		$rel_table  = self::get_table_name();
		$dest_table = Toptour_Ref_Destinations::get_table_name();
		$sql = "SELECT r.destination_id, r.relation_type, r.is_primary, d.name, d.country, d.region
			FROM $rel_table r
			LEFT JOIN $dest_table d ON d.id = r.destination_id
			WHERE r.facility_id = %d
			ORDER BY r.is_primary DESC, d.name ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, absint( $facility_id ) ) );
	}

	/**
	 * Get destination IDs assigned to facility.
	 *
	 * @param int $facility_id Facility ID.
	 * @return int[]
	 */
	public static function get_destination_ids_for_facility( $facility_id ) {
		$rows = self::get_destinations_for_facility( $facility_id );
		$ids = [];
		foreach ( $rows as $row ) {
			$ids[] = (int) $row->destination_id;
		}
		return $ids;
	}

	/**
	 * Get primary destination ID for a facility.
	 *
	 * @param int $facility_id Facility ID.
	 * @return int
	 */
	public static function get_primary_destination_id_for_facility( $facility_id ) {
		$rows = self::get_destinations_for_facility( $facility_id );
		foreach ( $rows as $row ) {
			if ( (int) $row->is_primary === 1 ) {
				return (int) $row->destination_id;
			}
		}
		return 0;
	}

	/**
	 * Get facilities assigned to destination.
	 *
	 * @param int $destination_id Destination ID.
	 * @return object[]
	 */
	public static function get_facilities_for_destination( $destination_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE destination_id = %d", absint( $destination_id ) ) );
	}

	/**
	 * Replace destination assignments for one facility.
	 *
	 * @param int   $facility_id Facility ID.
	 * @param int[] $destination_ids Destination IDs.
	 * @param int   $primary_destination_id Primary destination ID.
	 * @return bool
	 */
	public static function replace_facility_destinations( $facility_id, $destination_ids, $primary_destination_id = 0 ) {
		global $wpdb;
		$table = self::get_table_name();
		$facility_id = absint( $facility_id );
		$primary_destination_id = absint( $primary_destination_id );
		$destination_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $destination_ids ) ) ) );

		$wpdb->delete( $table, [ 'facility_id' => $facility_id ] );

		if ( empty( $destination_ids ) ) {
			return true;
		}

		$now = current_time( 'mysql' );
		foreach ( $destination_ids as $destination_id ) {
			$wpdb->insert(
				$table,
				[
					'facility_id'    => $facility_id,
					'destination_id' => $destination_id,
					'relation_type'  => 'primary_area',
					'is_primary'     => ( $primary_destination_id > 0 && $primary_destination_id === $destination_id ) ? 1 : 0,
					'notes'          => '',
					'created_at'     => $now,
					'updated_at'     => $now,
				]
			);
		}

		return true;
	}

	/**
	 * Count facilities linked to a destination.
	 *
	 * @param int $destination_id Destination ID.
	 * @return int
	 */
	public static function count_facilities_for_destination( $destination_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE destination_id = %d", absint( $destination_id ) ) );
	}

	/**
	 * Get destination names for a single facility.
	 *
	 * @param int $facility_id Facility ID.
	 * @return string[]
	 */
	public static function get_destination_names_for_facility( $facility_id ) {
		$rows = self::get_destinations_for_facility( $facility_id );
		$names = [];
		foreach ( $rows as $row ) {
			$label = (string) $row->name;
			if ( (int) $row->is_primary === 1 ) {
				$label .= ' (primárna)';
			}
			$names[] = $label;
		}
		return $names;
	}

	/**
	 * Get destination labels grouped by facility.
	 *
	 * @param int[] $facility_ids Facility IDs.
	 * @return array<int, string[]>
	 */
	public static function get_destination_labels_for_facilities( $facility_ids ) {
		global $wpdb;
		$facility_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $facility_ids ) ) ) );
		if ( empty( $facility_ids ) ) {
			return [];
		}

		$rel_table  = self::get_table_name();
		$dest_table = Toptour_Ref_Destinations::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $facility_ids ), '%d' ) );
		$sql = "SELECT r.facility_id, r.is_primary, d.name
			FROM $rel_table r
			LEFT JOIN $dest_table d ON d.id = r.destination_id
			WHERE r.facility_id IN ($placeholders)
			ORDER BY r.facility_id ASC, r.is_primary DESC, d.name ASC";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $facility_ids ) );
		$map = [];
		foreach ( $rows as $row ) {
			if ( ! isset( $map[ (int) $row->facility_id ] ) ) {
				$map[ (int) $row->facility_id ] = [];
			}
			$label = (string) $row->name;
			if ( (int) $row->is_primary === 1 ) {
				$label .= ' (primárna)';
			}
			$map[ (int) $row->facility_id ][] = $label;
		}
		return $map;
	}

	/**
	 * Get counts of facilities for destination IDs.
	 *
	 * @param int[] $destination_ids Destination IDs.
	 * @return array<int,int>
	 */
	public static function get_facility_counts_for_destinations( $destination_ids ) {
		global $wpdb;
		$destination_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $destination_ids ) ) ) );
		if ( empty( $destination_ids ) ) {
			return [];
		}

		$table = self::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $destination_ids ), '%d' ) );
		$sql = "SELECT destination_id, COUNT(*) AS cnt FROM $table WHERE destination_id IN ($placeholders) GROUP BY destination_id";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $destination_ids ) );

		$counts = [];
		foreach ( $rows as $row ) {
			$counts[ (int) $row->destination_id ] = (int) $row->cnt;
		}
		return $counts;
	}
}
