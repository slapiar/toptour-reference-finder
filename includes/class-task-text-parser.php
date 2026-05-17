<?php
/**
 * Task Text Parser for bulk text imports.
 *
 * Parses human-readable task text format and converts to Collection Task data.
 *
 * Format:
 * TASK TITLE
 * [title text]
 * 
 * QUERY TEXT
 * [query text, can be multi-line]
 * 
 * SOURCE HINT
 * [source hint, can be multi-line]
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Task_Text_Parser {

	const SECTION_MARKERS = [
		'TASK TITLE',
		'QUERY TEXT',
		'SOURCE HINT',
	];

	/**
	 * Parse task text and extract structured data.
	 *
	 * @param string $text Raw text input.
	 * @return array { ok: bool, message: string, data?: array, errors?: array }
	 */
	public static function parse( $text ) {
		$text = (string) $text;
		if ( '' === trim( $text ) ) {
			return [
				'ok' => false,
				'message' => 'Vstupný text je prázdny.',
				'errors' => [],
			];
		}

		$sections = self::extract_sections( $text );
		if ( empty( $sections ) ) {
			return [
				'ok' => false,
				'message' => 'Text neobsahuje požadované sekcie (TASK TITLE, QUERY TEXT, SOURCE HINT).',
				'errors' => [ 'missing_sections' ],
			];
		}

		$data = [
			'task_title'  => sanitize_text_field( trim( $sections['TASK TITLE'] ?? '' ) ),
			'query_text'  => sanitize_textarea_field( trim( $sections['QUERY TEXT'] ?? '' ) ),
			'source_hint' => sanitize_textarea_field( trim( $sections['SOURCE HINT'] ?? '' ) ),
			'target_type' => 'general',
			'task_status' => 'draft',
			'priority'    => 'normal',
			'frequency'   => 'manual',
		];

		$validation = Toptour_Ref_Collection_Tasks::validate_task_data( $data );
		if ( ! empty( $validation['errors'] ) ) {
			return [
				'ok' => false,
				'message' => 'Validácia dát zlyhala: ' . implode( ', ', $validation['errors'] ),
				'errors' => $validation['errors'],
			];
		}

		return [
			'ok' => true,
			'message' => 'Text bol úspešne naparsovaný.',
			'data' => $data,
		];
	}

	/**
	 * Extract sections from text.
	 *
	 * @param string $text Raw text input.
	 * @return array Sections indexed by marker, or empty if incomplete.
	 */
	private static function extract_sections( $text ) {
		$text = (string) $text;
		$sections = [];

		// Normalize line endings.
		$text = str_replace( "\r\n", "\n", $text );

		foreach ( self::SECTION_MARKERS as $marker ) {
			$pattern = '/^' . preg_quote( $marker, '/' ) . '\s*\n(.*?)(?=\n(?:' . implode( '|', array_map( 'preg_quote', self::SECTION_MARKERS, array_fill( 0, count( self::SECTION_MARKERS ), '/' ) ) ) . ')\s*\n|$)/s';
			if ( preg_match( $pattern, $text, $matches ) ) {
				$sections[ $marker ] = trim( $matches[1] );
			}
		}

		// Return only if all sections present.
		return count( $sections ) === count( self::SECTION_MARKERS ) ? $sections : [];
	}

	/**
	 * Import parsed data and create Collection Task.
	 *
	 * @param array $data Parsed task data (output from self::parse()).
	 * @return array { ok: bool, message: string, task_id?: int }
	 */
	public static function import_task( $data ) {
		if ( ! is_array( $data ) || empty( $data['task_title'] ) ) {
			return [
				'ok' => false,
				'message' => 'Nevalidný formát dát.',
			];
		}

		$task_id = Toptour_Ref_Collection_Tasks::create_task( $data );
		if ( ! $task_id ) {
			return [
				'ok' => false,
				'message' => 'Úloha sa nepodarila vytvoriť (chyba DB).',
			];
		}

		return [
			'ok' => true,
			'message' => sprintf(
				/* translators: 1: task title, 2: task ID */
				__( 'Úloha "%1$s" (ID: %2$d) bola úspešne vytvorená.', 'toptour-reference-finder' ),
				sanitize_text_field( $data['task_title'] ),
				$task_id
			),
			'task_id' => $task_id,
		];
	}

	/**
	 * Parse and import in one go.
	 *
	 * @param string $text Raw text input.
	 * @return array { ok: bool, message: string, task_id?: int, debug?: string }
	 */
	public static function parse_and_import( $text ) {
		$parse_result = self::parse( $text );
		if ( ! $parse_result['ok'] ) {
			return $parse_result;
		}

		return self::import_task( $parse_result['data'] );
	}
}
