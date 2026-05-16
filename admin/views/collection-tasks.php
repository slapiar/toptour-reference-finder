<?php
/**
 * TOPTOUR Reference Finder - Collection Tasks View
 *
 * Admin workflow for controlled discovery planning and review.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
}

if ( ! function_exists( 'toptour_ct_decode_json_array' ) ) {
	function toptour_ct_decode_json_array( $value ) {
		if ( empty( $value ) ) {
			return [];
		}
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}
}

if ( ! function_exists( 'toptour_ct_filter_url' ) ) {
	function toptour_ct_filter_url( $extra = [] ) {
		$params = array_filter(
			[
				'page'               => 'toptour-references-collection',
				'filter_status'      => isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '',
				'filter_priority'    => isset( $_GET['filter_priority'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_priority'] ) ) : '',
				'filter_target_type' => isset( $_GET['filter_target_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_target_type'] ) ) : '',
				's'                  => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			]
		);

		return esc_url( add_query_arg( array_merge( $params, $extra ), admin_url( 'admin.php' ) ) );
	}
}

if ( ! function_exists( 'toptour_ct_format_datetime' ) ) {
	function toptour_ct_format_datetime( $value ) {
		if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
			return '—';
		}
		$timestamp = strtotime( (string) $value );
		if ( ! $timestamp ) {
			return '—';
		}
		return date_i18n( 'd.m.Y H:i', $timestamp );
	}
}

if ( ! function_exists( 'toptour_ct_analysis_status_label' ) ) {
	function toptour_ct_analysis_status_label( $status ) {
		$labels = [
			'pending' => 'Čaká',
			'analyzed' => 'Analyzované',
			'needs_review' => 'Vyžaduje kontrolu',
			'accepted' => 'Prijaté',
			'rejected' => 'Odmietnuté',
		];

		$key = sanitize_text_field( (string) $status );
		return $labels[ $key ] ?? ( '' !== $key ? ucwords( str_replace( '_', ' ', $key ) ) : '—' );
	}
}

if ( ! function_exists( 'toptour_ct_event_note_for_manager' ) ) {
	function toptour_ct_event_note_for_manager( $note ) {
		$raw = sanitize_textarea_field( (string) $note );
		if ( '' === $raw ) {
			return '—';
		}

		$map = [
			'Task run started.' => 'Beh úlohy bol spustený.',
			'Task run finished.' => 'Beh úlohy bol dokončený.',
			'Lifecycle finding created.' => 'Testovací nález bol vytvorený.',
			'Reference analysis metadata created.' => 'Analytické metadáta referencie boli vytvorené.',
			'Offer snapshot created.' => 'Časový záznam ponuky bol vytvorený.',
			'Extrakcia bodov zaujmu je zatial pripravena len ako dalsi krok.' => 'Extrakcia bodov záujmu je zatiaľ pripravená len ako ďalší krok.',
			'Internal lifecycle run completed.' => 'Testovací beh životného cyklu bol dokončený.',
			'Task archived from list action.' => 'Úloha bola archivovaná zo zoznamu.',
			'Task updated from admin form.' => 'Úloha bola upravená z administrácie.',
			'Collection frequency changed.' => 'Frekvencia úlohy bola zmenená.',
			'Task query text changed.' => 'Text zadania úlohy bol zmenený.',
			'Task created from admin form.' => 'Úloha bola vytvorená z administrácie.',
			'Discovery analysis started.' => 'Analýza zadania bola spustená.',
			'Discovery analysis run stored.' => 'Beh analýzy zadania bol uložený.',
			'Discovery run creation failed.' => 'Vytvorenie behu analýzy zlyhalo.',
			'Discovery action failed.' => 'Discovery akcia zlyhala.',
			'Discovery candidate added.' => 'Discovery kandidát bol pridaný.',
			'Candidate accepted as source.' => 'Kandidát bol prijatý ako zdroj.',
			'Candidate rejected.' => 'Kandidát bol odmietnutý.',
			'Candidate marked as duplicate.' => 'Kandidát bol označený ako duplicita.',
		];

		if ( isset( $map[ $raw ] ) ) {
			return $map[ $raw ];
		}

		return $raw;
	}
}

if ( ! function_exists( 'toptour_ct_translate_placeholder_text' ) ) {
	function toptour_ct_translate_placeholder_text( $text ) {
		$clean = sanitize_textarea_field( (string) $text );
		$map = [
			'Internal placeholder analysis only. No external scraping or citation storage.' => 'Testovací analytický záznam. Externý zber ešte nie je zapnutý.',
			'No automatic POI extraction in this phase.' => 'Automatická extrakcia bodov záujmu zatiaľ nie je aktívna.',
			'Internal run placeholder created for lifecycle verification.' => 'Testovací záznam vytvorený na overenie životného cyklu úlohy.',
			'Testovaci analyticky zaznam. Externy zber este nie je zapnuty.' => 'Testovací analytický záznam. Externý zber ešte nie je zapnutý.',
			'Automaticka extrakcia bodov zaujmu zatial nie je aktivna.' => 'Automatická extrakcia bodov záujmu zatiaľ nie je aktívna.',
			'Testovaci zaznam vytvoreny na overenie zivotneho cyklu ulohy.' => 'Testovací záznam vytvorený na overenie životného cyklu úlohy.',
		];

		if ( strpos( $clean, 'Internal analysis placeholder for task #' ) === 0 ) {
			return str_replace( 'Internal analysis placeholder for task #', 'Testovacie analytické zistenie pre úlohu #', $clean );
		}

		if ( strpos( $clean, 'Testovacie analyticke zistenie pre ulohu #' ) === 0 ) {
			return str_replace( 'Testovacie analyticke zistenie pre ulohu #', 'Testovacie analytické zistenie pre úlohu #', $clean );
		}

		if ( isset( $map[ $clean ] ) ) {
			return $map[ $clean ];
		}

		return $clean;
	}
}

if ( ! function_exists( 'toptour_ct_get_discovery_summary_counts' ) ) {
	function toptour_ct_get_discovery_summary_counts( $task_id, $latest_run = null ) {
		global $wpdb;

		$task_id = absint( $task_id );
		$query_seed_count = 0;
		if ( $latest_run && ! empty( $latest_run->search_queries ) ) {
			$decoded_queries = json_decode( (string) $latest_run->search_queries, true );
			$query_seed_count = is_array( $decoded_queries ) ? count( $decoded_queries ) : 0;
		}

		$sources_table = Toptour_Ref_Reference_Sources::get_table_name();
		$facilities_table = Toptour_Ref_Facilities::get_table_name();
		$findings_table = Toptour_Ref_Findings::get_table_name();
		$photo_table = Toptour_Ref_Photo_Evidence::get_table_name();
		$facility_note_like = '%generated_from_task_id:' . $task_id . '%';

		$source_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sources_table WHERE collection_task_id = %d AND source_origin = %s AND suggestion_status = %s", $task_id, 'manual_discovery', 'manager_review' ) );
		$facility_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $facilities_table WHERE notes LIKE %s AND status = %s", $facility_note_like, 'draft' ) );
		$finding_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $findings_table WHERE related_collection_task_id = %d AND status = %s", $task_id, 'pending_review' ) );
		$photo_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $photo_table WHERE related_collection_task_id = %d AND verification_status = %s", $task_id, 'new' ) );

		return [
			'query_seed_count' => $query_seed_count,
			'source_count' => $source_count,
			'facility_count' => $facility_count,
			'finding_count' => $finding_count,
			'photo_count' => $photo_count,
			'pending_count' => $source_count + $finding_count + $photo_count,
		];
	}
}

$base_url    = admin_url( 'admin.php?page=toptour-references-collection' );
$action      = isset( $_GET['toptour_action'] ) ? sanitize_text_field( wp_unslash( $_GET['toptour_action'] ) ) : '';
$edit_id     = isset( $_GET['task_id'] ) ? absint( $_GET['task_id'] ) : 0;
$notice      = '';
$notice_type = 'success';
$intake_result = null;
$search_intake_result = null;
$finder_mode = Toptour_Ref_Task_Processor::get_mode();

if ( $action === 'archive' && $edit_id ) {
	$task_before_archive = Toptour_Ref_Collection_Tasks::get_task( $edit_id );
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_archive_task_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$archived = Toptour_Ref_Collection_Tasks::archive_task( $edit_id );
	if ( $archived && $task_before_archive ) {
		Toptour_Ref_Task_Events::log_event( $edit_id, 'disabled', $task_before_archive->task_status, 'archived', 'Task archived from list action.' );
	}
	$notice = $archived
		? __( 'Úloha bola archivovaná.', 'toptour-reference-finder' )
		: __( 'Archivácia zlyhala.', 'toptour-reference-finder' );
	$notice_type = $archived ? 'success' : 'error';
	$action = '';
	$edit_id = 0;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ct_submit'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_save_collection_task' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$post_id = absint( $_POST['task_id'] ?? 0 );
	$previous_task = $post_id ? Toptour_Ref_Collection_Tasks::get_task( $post_id ) : null;
	$data = Toptour_Ref_Collection_Tasks::sanitize_task_data( wp_unslash( $_POST ) );
	$valid = Toptour_Ref_Collection_Tasks::validate_task_data( $data );

	if ( true === $valid ) {
		$ok = $post_id ? Toptour_Ref_Collection_Tasks::update_task( $post_id, $data ) : Toptour_Ref_Collection_Tasks::create_task( $data );
		$notice = $ok
			? __( 'Úloha bola uložená.', 'toptour-reference-finder' )
			: __( 'Uloženie úlohy zlyhalo.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
		if ( $ok ) {
			$event_task_id = $post_id > 0 ? $post_id : (int) $ok;
			if ( $post_id > 0 ) {
				Toptour_Ref_Task_Events::log_event( $event_task_id, 'updated', $previous_task, $data, 'Task updated from admin form.' );
				if ( $previous_task && (string) $previous_task->frequency !== (string) $data['frequency'] ) {
					Toptour_Ref_Task_Events::log_event( $event_task_id, 'frequency_changed', $previous_task->frequency, $data['frequency'], 'Collection frequency changed.' );
				}
				if ( $previous_task && (string) $previous_task->query_text !== (string) $data['query_text'] ) {
					Toptour_Ref_Task_Events::log_event( $event_task_id, 'query_changed', $previous_task->query_text, $data['query_text'], 'Task query text changed.' );
				}
			} else {
				Toptour_Ref_Task_Events::log_event( $event_task_id, 'created', null, $data, 'Task created from admin form.' );
			}
		}
		if ( $ok ) {
			$action = $post_id ? 'edit' : '';
			$edit_id = $post_id ? $post_id : 0;
		}
	} else {
		$notice = __( 'Úlohu sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = 'error';
		$action = $post_id ? 'edit' : 'add';
		$edit_id = $post_id;
	}
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ct_finder_submit'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_collection_discovery_action' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$finder_action = sanitize_text_field( wp_unslash( $_POST['finder_action'] ?? '' ) );
	$task_id = absint( $_POST['task_id'] ?? 0 );
	$run_id = absint( $_POST['discovery_run_id'] ?? 0 );

	if ( 'intake_source' === $finder_action && $task_id > 0 ) {
		$intake_result = Toptour_Ref_Data_Intake_Router::process_manual_intake(
			$task_id,
			[
				'source_url' => esc_url_raw( wp_unslash( $_POST['intake_source_url'] ?? '' ) ),
				'input_type' => sanitize_text_field( wp_unslash( $_POST['intake_type'] ?? 'auto' ) ),
				'manager_note' => sanitize_textarea_field( wp_unslash( $_POST['intake_manager_note'] ?? '' ) ),
				'destination_id' => absint( $_POST['intake_destination_id'] ?? 0 ),
				'facility_id' => absint( $_POST['intake_facility_id'] ?? 0 ),
				'offer_id' => absint( $_POST['intake_offer_id'] ?? 0 ),
			]
		);

		$notice = sanitize_text_field( $intake_result['message'] ?? '' );
		$notice_type = ! empty( $intake_result['success'] ) ? 'success' : 'error';
	}

	if ( 'analyze_task' === $finder_action && $task_id > 0 ) {
		$task = Toptour_Ref_Collection_Tasks::get_task( $task_id );
		if ( $task ) {
			Toptour_Ref_Task_Events::log_event( $task_id, 'run_started', null, null, 'Discovery analysis started.' );
			$analysis = Toptour_Ref_Collection_Task_Resolver::analyze_task( $task );
			$new_run_id = Toptour_Ref_Collection_Task_Resolver::create_discovery_run( $task_id, $analysis );
			if ( $new_run_id ) {
				Toptour_Ref_Collection_Tasks::touch_task_run( $task_id, 'in_progress' );
				Toptour_Ref_Task_Events::log_event( $task_id, 'run_finished', null, [ 'discovery_run_id' => (int) $new_run_id ], 'Discovery analysis run stored.' );
				$notice = __( 'Analýza zadania bola vytvorená a discovery run uložený.', 'toptour-reference-finder' );
				$notice_type = 'success';
				$run_id = $new_run_id;
			} else {
				Toptour_Ref_Task_Events::log_event( $task_id, 'error', null, null, 'Discovery run creation failed.' );
				$notice = __( 'Discovery run sa nepodarilo vytvoriť.', 'toptour-reference-finder' );
				$notice_type = 'error';
			}
		} else {
			$notice = __( 'Úloha neexistuje. Najprv ju uložte.', 'toptour-reference-finder' );
			$notice_type = 'error';
		}
	}

	if ( 'save_missing_fields' === $finder_action && $run_id > 0 ) {
		$values = isset( $_POST['missing_field_value'] ) && is_array( $_POST['missing_field_value'] ) ? $_POST['missing_field_value'] : [];
		$saved = Toptour_Ref_Discovery_Runs::save_missing_field_values( $run_id, $values );
		$notice = $saved
			? __( 'Chýbajúce údaje boli uložené.', 'toptour-reference-finder' )
			: __( 'Nebolo čo uložiť alebo uloženie zlyhalo.', 'toptour-reference-finder' );
		$notice_type = $saved ? 'success' : 'error';
	}

	if ( 'apply_target' === $finder_action && $task_id > 0 && $run_id > 0 ) {
		$run = Toptour_Ref_Discovery_Runs::get_run( $run_id );
		$analysis = $run ? toptour_ct_decode_json_array( $run->input_summary ) : [];
		$missing_rows = $run ? Toptour_Ref_Discovery_Runs::get_missing_fields( $run_id ) : [];

		$destination_from_missing = '';
		$stay_type_from_missing = '';
		foreach ( $missing_rows as $missing_row ) {
			if ( 'destination_name' === $missing_row->field_key && 'provided' === $missing_row->field_status && '' !== $missing_row->field_value ) {
				$destination_from_missing = sanitize_text_field( $missing_row->field_value );
			}
			if ( 'stay_type' === $missing_row->field_key && 'provided' === $missing_row->field_status && '' !== $missing_row->field_value ) {
				$stay_type_from_missing = sanitize_text_field( $missing_row->field_value );
			}
		}

		$resolved_target_type = $run ? sanitize_text_field( $run->resolved_target_type ) : 'general';
		$resolved_target_id = $run ? (int) $run->resolved_target_id : 0;
		$resolved_target_label = $run ? sanitize_text_field( $run->resolved_target_label ) : '';

		if ( 'destination' === $resolved_target_type && 0 === $resolved_target_id ) {
			$candidate_destination = $destination_from_missing !== '' ? $destination_from_missing : sanitize_text_field( $run->detected_destination );
			if ( '' !== $candidate_destination ) {
				$resolved_target_id = (int) Toptour_Ref_Collection_Task_Resolver::create_destination_from_candidate( $candidate_destination );
				$resolved_target_label = $candidate_destination;
				if ( $resolved_target_id > 0 ) {
					global $wpdb;
					$wpdb->update(
						Toptour_Ref_Discovery_Runs::get_table_name(),
						[
							'resolved_target_id' => $resolved_target_id,
							'resolved_target_label' => $resolved_target_label,
							'updated_at' => current_time( 'mysql' ),
						],
						[ 'id' => $run_id ]
					);
				}
			}
		}

		$resolution = [
			'target_type' => $resolved_target_type,
			'target_id' => $resolved_target_id,
			'expected_source_type' => $stay_type_from_missing !== '' ? 'mixed' : sanitize_text_field( $analysis['expected_source_type'] ?? 'mixed' ),
		];

		$ok = Toptour_Ref_Collection_Task_Resolver::apply_resolution_to_task( $task_id, $resolution );
		$notice = $ok
			? __( 'Cieľ bol vytvorený alebo priradený k úlohe.', 'toptour-reference-finder' )
			: __( 'Cieľ sa nepodarilo priradiť.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
	}

	if ( 'prepare_queries' === $finder_action && $run_id > 0 ) {
		$run = Toptour_Ref_Discovery_Runs::get_run( $run_id );
		$analysis = $run ? toptour_ct_decode_json_array( $run->input_summary ) : [];
		$missing_rows = $run ? Toptour_Ref_Discovery_Runs::get_missing_fields( $run_id ) : [];

		foreach ( $missing_rows as $missing_row ) {
			if ( 'provided' !== $missing_row->field_status || '' === $missing_row->field_value ) {
				continue;
			}
			if ( 'destination_name' === $missing_row->field_key ) {
				$analysis['destination_candidate'] = sanitize_text_field( $missing_row->field_value );
			}
			if ( 'stay_type' === $missing_row->field_key ) {
				$analysis['stay_type'] = sanitize_text_field( $missing_row->field_value );
			}
			if ( 'source_languages' === $missing_row->field_key ) {
				$analysis['source_languages'] = sanitize_text_field( $missing_row->field_value );
			}
		}

		$queries = Toptour_Ref_Discovery_Provider::build_search_queries( $analysis );
		$ok = Toptour_Ref_Discovery_Runs::update_run_search_queries( $run_id, $queries );
		if ( $ok ) {
			Toptour_Ref_Discovery_Runs::update_run_status( $run_id, 'ready' );
		}

		$notice = $ok
			? __( 'Discovery queries boli pripravené a uložené.', 'toptour-reference-finder' )
			: __( 'Discovery queries sa nepodarilo pripraviť.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
	}

	if ( 'run_discovery' === $finder_action && $run_id > 0 ) {
		$run = Toptour_Ref_Discovery_Runs::get_run( $run_id );
		$task_run_id = false;
		$provider = sanitize_text_field( wp_unslash( $_POST['discovery_provider'] ?? ( $run ? $run->discovery_provider : 'manual' ) ) );
		if ( ! in_array( $provider, Toptour_Ref_Discovery_Runs::get_allowed_providers(), true ) ) {
			$provider = 'manual';
		}

		global $wpdb;
		$wpdb->update(
			Toptour_Ref_Discovery_Runs::get_table_name(),
			[
				'discovery_provider' => $provider,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $run_id ]
		);

		if ( 'search_api' === $provider ) {
			$task_run_id = Toptour_Ref_Task_Runs::create_run(
				$task_id,
				[
					'status' => 'running',
					'started_at' => current_time( 'mysql' ),
					'summary' => 'Search API discovery run started.',
				]
			);
			$result = Toptour_Ref_Discovery_Provider::run_search_api_discovery( $run_id );
		} elseif ( 'manual' === $provider ) {
			$task_run_id = Toptour_Ref_Task_Runs::create_run(
				$task_id,
				[
					'status' => 'running',
					'started_at' => current_time( 'mysql' ),
					'summary' => 'Manual discovery run started.',
				]
			);
			$result = Toptour_Ref_Discovery_Provider::run_manual_discovery( $run_id );
		} else {
			Toptour_Ref_Discovery_Runs::update_run_status( $run_id, 'needs_input' );
			$result = [
				'success' => false,
				'message' => 'Future provider je pripraveny na neskorsiu integraciu a v MVP sa nespusta.',
			];
		}

		$notice = sanitize_text_field( $result['message'] ?? __( 'Discovery akcia ukoncena.', 'toptour-reference-finder' ) );
		$notice_type = ! empty( $result['success'] ) ? 'success' : 'error';
		if ( $task_run_id ) {
			$status = ! empty( $result['success'] ) ? 'finished' : 'failed';
			$run_candidates = Toptour_Ref_Discovery_Candidates::get_candidates_for_run( $run_id );
			$found_count = is_array( $run_candidates ) ? count( $run_candidates ) : 0;
			$new_count = 0;
			$duplicate_count = 0;
			if ( is_array( $run_candidates ) ) {
				foreach ( $run_candidates as $run_candidate ) {
					if ( 'duplicate' === $run_candidate->candidate_status ) {
						$duplicate_count++;
					}
					if ( in_array( $run_candidate->candidate_status, [ 'new', 'needs_review' ], true ) ) {
						$new_count++;
					}
				}
			}
			Toptour_Ref_Task_Runs::update_run(
				$task_run_id,
				[
					'status' => $status,
					'finished_at' => current_time( 'mysql' ),
					'found_count' => $found_count,
					'new_count' => $new_count,
					'duplicate_count' => $duplicate_count,
					'error_count' => ! empty( $result['success'] ) ? 0 : 1,
					'summary' => sanitize_text_field( $result['message'] ?? '' ),
				]
			);
			Toptour_Ref_Task_Events::log_event( $task_id, 'run_finished', null, [ 'task_run_id' => (int) $task_run_id, 'status' => $status ], sanitize_text_field( $result['message'] ?? '' ) );
		}
		if ( empty( $result['success'] ) ) {
			Toptour_Ref_Task_Events::log_event( $task_id, 'error', null, null, sanitize_text_field( $result['message'] ?? 'Discovery action failed.' ) );
		}
		if ( $task_id > 0 ) {
			Toptour_Ref_Collection_Tasks::touch_task_run( $task_id, 'in_progress' );
		}
	}

	if ( ( 'search_intake' === $finder_action || 'run_test' === $finder_action ) && $task_id > 0 ) {
		$search_intake_result = Toptour_Ref_Task_Processor::process_task( $task_id, 'manual' );
		$notice = sanitize_text_field( $search_intake_result['message'] ?? __( 'Search intake bol vykonaný.', 'toptour-reference-finder' ) );
		$notice_type = ! empty( $search_intake_result['success'] ) ? 'success' : 'error';
	}

	if ( 'create_candidate' === $finder_action && $run_id > 0 && $task_id > 0 ) {
		$candidate_data = [
			'discovery_run_id'            => $run_id,
			'collection_task_id'          => $task_id,
			'candidate_title'             => sanitize_text_field( wp_unslash( $_POST['candidate_title'] ?? '' ) ),
			'candidate_url'               => esc_url_raw( wp_unslash( $_POST['candidate_url'] ?? '' ) ),
			'candidate_platform'          => sanitize_text_field( wp_unslash( $_POST['candidate_platform'] ?? '' ) ),
			'candidate_source_type'       => sanitize_text_field( wp_unslash( $_POST['candidate_source_type'] ?? 'other' ) ),
			'candidate_origin'            => 'manual_discovery',
			'snippet'                     => sanitize_textarea_field( wp_unslash( $_POST['candidate_snippet'] ?? '' ) ),
			'detected_language'           => sanitize_text_field( wp_unslash( $_POST['candidate_language'] ?? '' ) ),
			'suggested_target_type'       => sanitize_text_field( wp_unslash( $_POST['suggested_target_type'] ?? 'general' ) ),
			'suggested_target_id'         => absint( $_POST['suggested_target_id'] ?? 0 ),
			'suggested_credibility_level' => sanitize_text_field( wp_unslash( $_POST['suggested_credibility_level'] ?? 'unknown' ) ),
			'suggestion_reason'           => sanitize_textarea_field( wp_unslash( $_POST['suggestion_reason'] ?? '' ) ),
			'search_query'                => sanitize_text_field( wp_unslash( $_POST['search_query'] ?? '' ) ),
			'candidate_status'            => 'new',
			'notes'                       => sanitize_textarea_field( wp_unslash( $_POST['candidate_notes'] ?? '' ) ),
		];

		$created = Toptour_Ref_Discovery_Candidates::create_candidate( $candidate_data );
		if ( $created ) {
			Toptour_Ref_Task_Events::log_event( $task_id, 'manual_note_added', null, [ 'candidate_id' => (int) $created ], 'Discovery candidate added.' );
		}
		$notice = $created
			? __( 'Discovery kandidat bol pridany.', 'toptour-reference-finder' )
			: __( 'Kandidata sa nepodarilo ulozit.', 'toptour-reference-finder' );
		$notice_type = $created ? 'success' : 'error';
	}

	if ( 'candidate_decision' === $finder_action && $run_id > 0 ) {
		$candidate_id = absint( $_POST['candidate_id'] ?? 0 );
		$decision = sanitize_text_field( wp_unslash( $_POST['candidate_decision'] ?? '' ) );

		if ( 'accept' === $decision ) {
			$source_id = Toptour_Ref_Discovery_Candidates::accept_candidate_as_source( $candidate_id );
			if ( $source_id ) {
				Toptour_Ref_Task_Events::log_event( $task_id, 'finding_accepted', null, [ 'candidate_id' => $candidate_id, 'source_id' => (int) $source_id ], 'Candidate accepted as source.' );
				$notice = sprintf( __( 'Kandidat prijaty ako Reference Source #%d.', 'toptour-reference-finder' ), (int) $source_id );
				$notice_type = 'success';
			} else {
				$notice = __( 'Kandidata sa nepodarilo prijat ako zdroj.', 'toptour-reference-finder' );
				$notice_type = 'error';
			}
		}

		if ( 'reject' === $decision ) {
			$ok = Toptour_Ref_Discovery_Candidates::reject_candidate( $candidate_id );
			if ( $ok ) {
				Toptour_Ref_Task_Events::log_event( $task_id, 'finding_rejected', null, [ 'candidate_id' => $candidate_id ], 'Candidate rejected.' );
			}
			$notice = $ok ? __( 'Kandidat bol odmietnuty.', 'toptour-reference-finder' ) : __( 'Akcia odmietnutia zlyhala.', 'toptour-reference-finder' );
			$notice_type = $ok ? 'success' : 'error';
		}

		if ( 'duplicate' === $decision ) {
			$ok = Toptour_Ref_Discovery_Candidates::mark_duplicate( $candidate_id );
			if ( $ok ) {
				Toptour_Ref_Task_Events::log_event( $task_id, 'updated', null, [ 'candidate_id' => $candidate_id, 'status' => 'duplicate' ], 'Candidate marked as duplicate.' );
			}
			$notice = $ok ? __( 'Kandidat bol oznaceny ako duplicita.', 'toptour-reference-finder' ) : __( 'Akcia duplicity zlyhala.', 'toptour-reference-finder' );
			$notice_type = $ok ? 'success' : 'error';
		}
	}

	if ( $task_id > 0 ) {
		$action = 'edit';
		$edit_id = $task_id;
	}
}

$edit_task = null;
if ( in_array( $action, [ 'edit', 'add' ], true ) && $edit_id ) {
	$edit_task = Toptour_Ref_Collection_Tasks::get_task( $edit_id );
}

$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
$filter_priority = isset( $_GET['filter_priority'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_priority'] ) ) : '';
$filter_target_type = isset( $_GET['filter_target_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_target_type'] ) ) : '';
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result = Toptour_Ref_Collection_Tasks::get_tasks(
	[
		'status'      => $filter_status,
		'priority'    => $filter_priority,
		'target_type' => $filter_target_type,
		'search'      => $search,
		'page'        => $current_page,
		'per_page'    => 20,
	]
);
$tasks = $result['tasks'];
$total = $result['total'];
$total_pages = (int) ceil( $total / 20 );

$allowed_statuses = Toptour_Ref_Collection_Tasks::get_allowed_statuses();
$allowed_frequencies = Toptour_Ref_Collection_Tasks::get_allowed_frequencies();
$allowed_priorities = Toptour_Ref_Collection_Tasks::get_allowed_priorities();
$allowed_target_types = Toptour_Ref_Collection_Tasks::get_allowed_target_types();
$allowed_source_types = Toptour_Ref_Collection_Tasks::get_allowed_source_types();

$latest_run = null;
$missing_rows = [];
$run_analysis = [];
$run_queries = [];
$run_interest_candidates = [];
$run_finding_areas = [];
$candidates = [];
$task_stats = [
	'total_found' => 0,
	'new_found' => 0,
	'pending_review' => 0,
	'poi_suggestions' => 0,
	'error_count' => 0,
];
$task_recent_findings = [];
$task_recent_snapshots = [];
$task_events = [];
$task_runs = [];

if ( $edit_task ) {
	$task_stats = Toptour_Ref_Collection_Tasks::get_task_stats( (int) $edit_task->id );
	$task_recent_findings = Toptour_Ref_Collection_Tasks::get_recent_findings( (int) $edit_task->id, 10 );
	$task_events = Toptour_Ref_Task_Events::get_events_for_task( (int) $edit_task->id, 50 );
	$task_runs = Toptour_Ref_Task_Runs::get_runs_for_task( (int) $edit_task->id, 10 );
	$task_recent_snapshots = Toptour_Ref_Offer_Snapshots::get_recent_for_task( (int) $edit_task->id, 10 );
	$latest_run = Toptour_Ref_Discovery_Runs::get_latest_run_for_task( (int) $edit_task->id );
	if ( $latest_run ) {
		$missing_rows = Toptour_Ref_Discovery_Runs::get_missing_fields( (int) $latest_run->id );
		$run_analysis = toptour_ct_decode_json_array( $latest_run->input_summary );
		$run_queries = toptour_ct_decode_json_array( $latest_run->search_queries );
		$run_interest_candidates = toptour_ct_decode_json_array( $latest_run->detected_interests );
		$run_finding_areas = toptour_ct_decode_json_array( $latest_run->detected_finding_areas );
		$candidates = Toptour_Ref_Discovery_Candidates::get_candidates_for_run( (int) $latest_run->id );
	}
}

$collection_admin_url = admin_url( 'admin.php' );
$collection_page_url = add_query_arg(
	[
		'page' => 'toptour-references-collection',
	],
	$collection_admin_url
);
$findings_page_url = add_query_arg(
	[
		'page' => 'toptour-references-findings',
	],
	$collection_admin_url
);
$offers_page_url = add_query_arg(
	[
		'page' => 'toptour-references-offers',
	],
	$collection_admin_url
);
$facilities_page_url = add_query_arg(
	[
		'page' => 'toptour-references-facilities',
	],
	$collection_admin_url
);
$destinations_page_url = add_query_arg(
	[
		'page' => 'toptour-references-destinations',
	],
	$collection_admin_url
);
$photo_evidence_page_url = add_query_arg(
	[
		'page' => 'toptour-references-photo-evidence',
	],
	$collection_admin_url
);
$settings_page_url = add_query_arg(
	[
		'page' => 'toptour-references-settings',
	],
	$collection_admin_url
);

$is_task_detail = ( $edit_task && $edit_id > 0 );
$has_context = false;
$has_findings = false;
$needs_review = false;
$has_errors = false;
$has_latest_run = ( $latest_run && ! empty( $latest_run->id ) );

if ( $is_task_detail ) {
	$is_discovery_task = Toptour_Ref_Collection_Task_Resolver::is_discovery_task( $edit_task );
	$has_context =
		( '' !== trim( (string) ( $edit_task->task_title ?? '' ) ) ) ||
		( '' !== trim( (string) ( $edit_task->query_text ?? '' ) ) ) ||
		( '' !== trim( (string) ( $edit_task->notes ?? '' ) ) ) ||
		( (int) ( $edit_task->target_id ?? 0 ) > 0 ) ||
		( (int) ( $edit_task->destination_id ?? 0 ) > 0 ) ||
		( (int) ( $edit_task->supplier_id ?? 0 ) > 0 ) ||
		( (int) ( $edit_task->offer_id ?? 0 ) > 0 ) ||
		$is_discovery_task;

	$has_findings = ( (int) ( $task_stats['total_found'] ?? 0 ) > 0 );
	$needs_review = ( (int) ( $task_stats['pending_review'] ?? 0 ) > 0 );
	$has_errors = ( (int) ( $task_stats['error_count'] ?? 0 ) > 0 );
}

$step_states = [
	1 => 'available',
	2 => 'locked',
	3 => 'locked',
	4 => 'locked',
	5 => 'locked',
	6 => 'available',
	7 => 'available',
	8 => 'available',
	9 => 'available',
];

$current_step = 1;

if ( $is_task_detail ) {
	$step_states[1] = 'done';
	$step_states[2] = $has_context ? 'done' : 'available';
	$step_states[3] = 'available';
	$step_states[4] = $has_errors && ! $has_findings ? 'error' : 'available';
	$step_states[5] = 'available';
	if ( $has_findings ) {
		$step_states[6] = $needs_review ? 'review' : 'done';
		$step_states[7] = 'available';
		$step_states[8] = $needs_review ? 'review' : 'available';
	} else {
		$step_states[6] = 'locked';
		$step_states[7] = 'locked';
		$step_states[8] = 'locked';
	}
	$step_states[9] = 'available';

	if ( ! $has_context ) {
		$current_step = 2;
	} elseif ( ! $has_latest_run ) {
		$current_step = 3;
	} elseif ( ! $has_findings ) {
		$current_step = 4;
	} elseif ( $needs_review ) {
		$current_step = 8;
	} else {
		$current_step = 9;
	}
}

if ( isset( $step_states[ $current_step ] ) && in_array( $step_states[ $current_step ], [ 'available', 'review', 'error' ], true ) ) {
	$step_states[ $current_step ] = 'current';
}

$state_labels = [
	'done' => 'Hotové',
	'current' => 'Aktuálny krok',
	'available' => 'Dostupné',
	'locked' => 'Zamknuté',
	'review' => 'Vyžaduje kontrolu',
	'error' => 'Chyba',
];

$current_task_edit_url = '';
$current_task_anchor_url = '';
if ( $is_task_detail ) {
	$current_task_edit_url = add_query_arg(
		[
			'page' => 'toptour-references-collection',
			'toptour_action' => 'edit',
			'task_id' => (int) $edit_task->id,
		],
		$collection_admin_url
	);
	$current_task_anchor_url = $current_task_edit_url . '#tt-ref-real-intake';
}
?>

<div class="wrap toptour-ref-collection-tasks">
	<h1><?php esc_html_e( 'Zber referencií', 'toptour-reference-finder' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Kontrolovaný admin workflow pre discovery zdrojov a reálny intake verejných URL vstupov.', 'toptour-reference-finder' ); ?></p>
	<div class="notice notice-info inline">
		<p>
			<?php if ( 'manual' === $finder_mode ) : ?>
				<?php esc_html_e( 'Automatické spúšťanie je vypnuté. Úlohy sa spúšťajú iba ručne.', 'toptour-reference-finder' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Automatické spúšťanie je zapnuté. Aktívne úlohy sa spracujú podľa frequency a next_run_at.', 'toptour-reference-finder' ); ?>
			<?php endif; ?>
		</p>
	</div>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<section class="tt-ref-process-map" aria-label="Proces zberu referencií">
		<div class="tt-ref-process-map__header">
			<h2><?php esc_html_e( 'Proces zberu referencií', 'toptour-reference-finder' ); ?></h2>
			<p><?php esc_html_e( 'Postupujte po krokoch. Zamknuté kroky sa sprístupnia až po splnení predchádzajúcich podmienok.', 'toptour-reference-finder' ); ?></p>
		</div>

		<div class="tt-ref-process-grid">
			<?php $step_state = $step_states[1]; ?>
			<article class="tt-ref-step tt-ref-step--<?php echo esc_attr( $step_state ); ?>">
				<div class="tt-ref-step__head">
					<span class="tt-ref-step__number" aria-hidden="true">1</span>
					<div class="tt-ref-step__title-wrap">
						<h3><?php esc_html_e( 'Návrh úlohy', 'toptour-reference-finder' ); ?></h3>
						<span class="tt-ref-step__badge"><?php echo esc_html( $state_labels[ $step_state ] ); ?></span>
					</div>
				</div>
				<p><?php esc_html_e( 'Vytvorí sa pracovný spis zberu referencií.', 'toptour-reference-finder' ); ?></p>
				<p class="tt-ref-step__note"><em><?php esc_html_e( 'Tu sa pomenúva, čo má systém sledovať a prečo.', 'toptour-reference-finder' ); ?></em></p>
				<div class="tt-ref-step__action">
					<?php if ( $is_task_detail ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $current_task_edit_url ); ?>"><?php esc_html_e( 'Upraviť úlohu', 'toptour-reference-finder' ); ?></a>
					<?php else : ?>
						<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( [ 'page' => 'toptour-references-collection', 'toptour_action' => 'add' ], $collection_admin_url ) ); ?>"><?php esc_html_e( 'Pridať úlohu', 'toptour-reference-finder' ); ?></a>
					<?php endif; ?>
				</div>
			</article>

			<?php $step_state = $step_states[2]; ?>
			<article class="tt-ref-step tt-ref-step--<?php echo esc_attr( $step_state ); ?>">
				<div class="tt-ref-step__head">
					<span class="tt-ref-step__number" aria-hidden="true">2</span>
					<div class="tt-ref-step__title-wrap">
						<h3><?php esc_html_e( 'Cieľ a kontext', 'toptour-reference-finder' ); ?></h3>
						<span class="tt-ref-step__badge"><?php echo esc_html( $state_labels[ $step_state ] ); ?></span>
					</div>
				</div>
				<p><?php esc_html_e( 'Doplní sa cieľ, destinácia, zariadenie, ponuka, typ signálu a poznámky.', 'toptour-reference-finder' ); ?></p>
				<p class="tt-ref-step__note"><em><?php esc_html_e( 'Čím presnejší kontext, tým lepšie sa budú triediť nálezy.', 'toptour-reference-finder' ); ?></em></p>
				<div class="tt-ref-step__action">
					<?php if ( 'locked' === $step_state ) : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv dokončite predchádzajúci krok.', 'toptour-reference-finder' ); ?></span>
					<?php else : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $current_task_edit_url ); ?>"><?php esc_html_e( 'Upraviť úlohu', 'toptour-reference-finder' ); ?></a>
					<?php endif; ?>
				</div>
			</article>

			<?php $step_state = $step_states[3]; ?>
			<article class="tt-ref-step tt-ref-step--<?php echo esc_attr( $step_state ); ?>">
				<div class="tt-ref-step__head">
					<span class="tt-ref-step__number" aria-hidden="true">3</span>
					<div class="tt-ref-step__title-wrap">
						<h3><?php esc_html_e( 'Vyhľadávacie dotazy', 'toptour-reference-finder' ); ?></h3>
						<span class="tt-ref-step__badge"><?php echo esc_html( $state_labels[ $step_state ] ); ?></span>
					</div>
				</div>
				<p><?php esc_html_e( 'Zo zadania úlohy sa pripravia dotazy pre reálny zber.', 'toptour-reference-finder' ); ?></p>
				<p class="tt-ref-step__note"><em><?php esc_html_e( 'Systém z názvu, cieľa a poznámok pripraví otázky pre vyhľadanie zdrojov.', 'toptour-reference-finder' ); ?></em></p>
				<div class="tt-ref-step__action">
					<?php if ( 'locked' === $step_state ) : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv dokončite predchádzajúci krok.', 'toptour-reference-finder' ); ?></span>
					<?php elseif ( $is_task_detail && $has_latest_run ) : ?>
						<form method="post" action="<?php echo esc_url( $base_url ); ?>">
							<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
							<input type="hidden" name="toptour_ct_finder_submit" value="1">
							<input type="hidden" name="finder_action" value="prepare_queries">
							<input type="hidden" name="task_id" value="<?php echo esc_attr( (int) $edit_task->id ); ?>">
							<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( (int) $latest_run->id ); ?>">
							<button type="submit" class="button button-secondary"><?php esc_html_e( 'Pripraviť dotazy', 'toptour-reference-finder' ); ?></button>
						</form>
					<?php else : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv analyzujte zadanie úlohy.', 'toptour-reference-finder' ); ?></span>
					<?php endif; ?>
				</div>
			</article>

			<?php $step_state = $step_states[4]; ?>
			<article class="tt-ref-step tt-ref-step--<?php echo esc_attr( $step_state ); ?>">
				<div class="tt-ref-step__head">
					<span class="tt-ref-step__number" aria-hidden="true">4</span>
					<div class="tt-ref-step__title-wrap">
						<h3><?php esc_html_e( 'Vyhľadať reálne URL', 'toptour-reference-finder' ); ?></h3>
						<span class="tt-ref-step__badge"><?php echo esc_html( $state_labels[ $step_state ] ); ?></span>
					</div>
				</div>
				<p><?php esc_html_e( 'Úloha vyhľadá alebo použije existujúce kandidátske URL.', 'toptour-reference-finder' ); ?></p>
				<p class="tt-ref-step__note"><em><?php esc_html_e( 'Zdrojom môže byť ponuka, recenzia, článok, stránka zariadenia alebo verejný fotodôkaz.', 'toptour-reference-finder' ); ?></em></p>
				<div class="tt-ref-step__action">
					<?php if ( 'locked' === $step_state ) : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv dokončite predchádzajúci krok.', 'toptour-reference-finder' ); ?></span>
					<?php elseif ( $is_task_detail ) : ?>
						<form method="post" action="<?php echo esc_url( $base_url ); ?>">
							<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
							<input type="hidden" name="toptour_ct_finder_submit" value="1">
							<input type="hidden" name="finder_action" value="search_intake">
							<input type="hidden" name="task_id" value="<?php echo esc_attr( (int) $edit_task->id ); ?>">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Vyhľadať a zapísať reálne dáta', 'toptour-reference-finder' ); ?></button>
						</form>
					<?php else : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv vyberte konkrétnu úlohu.', 'toptour-reference-finder' ); ?></span>
					<?php endif; ?>
				</div>
			</article>

			<?php $step_state = $step_states[5]; ?>
			<article class="tt-ref-step tt-ref-step--<?php echo esc_attr( $step_state ); ?>">
				<div class="tt-ref-step__head">
					<span class="tt-ref-step__number" aria-hidden="true">5</span>
					<div class="tt-ref-step__title-wrap">
						<h3><?php esc_html_e( 'Reálny vstup dát', 'toptour-reference-finder' ); ?></h3>
						<span class="tt-ref-step__badge"><?php echo esc_html( $state_labels[ $step_state ] ); ?></span>
					</div>
				</div>
				<p><?php esc_html_e( 'Manažér môže vložiť konkrétnu URL a zaradiť ju cez Data Intake Router.', 'toptour-reference-finder' ); ?></p>
				<p class="tt-ref-step__note"><em><?php esc_html_e( 'Toto je priama cesta, keď už poznáme konkrétny zdroj.', 'toptour-reference-finder' ); ?></em></p>
				<div class="tt-ref-step__action">
					<?php if ( 'locked' === $step_state ) : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv dokončite predchádzajúci krok.', 'toptour-reference-finder' ); ?></span>
					<?php elseif ( $is_task_detail ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $current_task_anchor_url ); ?>"><?php esc_html_e( 'Otvoriť sekciu Reálny vstup dát', 'toptour-reference-finder' ); ?></a>
					<?php else : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv vyberte konkrétnu úlohu.', 'toptour-reference-finder' ); ?></span>
					<?php endif; ?>
				</div>
			</article>

			<?php $step_state = $step_states[6]; ?>
			<article class="tt-ref-step tt-ref-step--<?php echo esc_attr( $step_state ); ?>">
				<div class="tt-ref-step__head">
					<span class="tt-ref-step__number" aria-hidden="true">6</span>
					<div class="tt-ref-step__title-wrap">
						<h3><?php esc_html_e( 'Zistenia', 'toptour-reference-finder' ); ?></h3>
						<span class="tt-ref-step__badge"><?php echo esc_html( $state_labels[ $step_state ] ); ?></span>
					</div>
				</div>
				<p><?php esc_html_e( 'Z reálnych zdrojov vzniknú extrahované poznatky.', 'toptour-reference-finder' ); ?></p>
				<p class="tt-ref-step__note"><em><?php esc_html_e( 'Manažér kontroluje pozitíva, riziká, rozpory a opakujúce sa signály.', 'toptour-reference-finder' ); ?></em></p>
				<div class="tt-ref-step__action">
					<?php if ( 'locked' === $step_state ) : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv dokončite predchádzajúci krok.', 'toptour-reference-finder' ); ?></span>
					<?php else : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $findings_page_url ); ?>"><?php esc_html_e( 'Otvoriť Zistenia', 'toptour-reference-finder' ); ?></a>
					<?php endif; ?>
				</div>
			</article>

			<?php $step_state = $step_states[7]; ?>
			<article class="tt-ref-step tt-ref-step--<?php echo esc_attr( $step_state ); ?>">
				<div class="tt-ref-step__head">
					<span class="tt-ref-step__number" aria-hidden="true">7</span>
					<div class="tt-ref-step__title-wrap">
						<h3><?php esc_html_e( 'Rozdelenie do modulov', 'toptour-reference-finder' ); ?></h3>
						<span class="tt-ref-step__badge"><?php echo esc_html( $state_labels[ $step_state ] ); ?></span>
					</div>
				</div>
				<p><?php esc_html_e( 'Dáta sa prepoja so zariadeniami, destináciami, ponukami, fotodôkazmi a referenčnými zdrojmi.', 'toptour-reference-finder' ); ?></p>
				<p class="tt-ref-step__note"><em><?php esc_html_e( 'Cieľom nie je len nález, ale rozšírenie znalostnej mapy TOPTOUR.', 'toptour-reference-finder' ); ?></em></p>
				<div class="tt-ref-step__action tt-ref-step__action-links">
					<?php if ( 'locked' === $step_state ) : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv dokončite predchádzajúci krok.', 'toptour-reference-finder' ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( $offers_page_url ); ?>"><?php esc_html_e( 'Ponuky', 'toptour-reference-finder' ); ?></a>
						<a href="<?php echo esc_url( $facilities_page_url ); ?>"><?php esc_html_e( 'Zariadenia', 'toptour-reference-finder' ); ?></a>
						<a href="<?php echo esc_url( $destinations_page_url ); ?>"><?php esc_html_e( 'Destinácie', 'toptour-reference-finder' ); ?></a>
						<a href="<?php echo esc_url( $photo_evidence_page_url ); ?>"><?php esc_html_e( 'Fotodôkazy', 'toptour-reference-finder' ); ?></a>
					<?php endif; ?>
				</div>
			</article>

			<?php $step_state = $step_states[8]; ?>
			<article class="tt-ref-step tt-ref-step--<?php echo esc_attr( $step_state ); ?>">
				<div class="tt-ref-step__head">
					<span class="tt-ref-step__number" aria-hidden="true">8</span>
					<div class="tt-ref-step__title-wrap">
						<h3><?php esc_html_e( 'Manažérske rozhodnutie', 'toptour-reference-finder' ); ?></h3>
						<span class="tt-ref-step__badge"><?php echo esc_html( $state_labels[ $step_state ] ); ?></span>
					</div>
				</div>
				<p><?php esc_html_e( 'Manažér potvrdí, upraví, odmietne alebo doplní výsledky.', 'toptour-reference-finder' ); ?></p>
				<p class="tt-ref-step__note"><em><?php esc_html_e( 'Systém navrhuje, človek rozhoduje.', 'toptour-reference-finder' ); ?></em></p>
				<div class="tt-ref-step__action">
					<?php if ( 'locked' === $step_state ) : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv dokončite predchádzajúci krok.', 'toptour-reference-finder' ); ?></span>
					<?php else : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $findings_page_url ); ?>"><?php esc_html_e( 'Otvoriť Zistenia', 'toptour-reference-finder' ); ?></a>
					<?php endif; ?>
				</div>
			</article>

			<?php $step_state = $step_states[9]; ?>
			<article class="tt-ref-step tt-ref-step--<?php echo esc_attr( $step_state ); ?>">
				<div class="tt-ref-step__head">
					<span class="tt-ref-step__number" aria-hidden="true">9</span>
					<div class="tt-ref-step__title-wrap">
						<h3><?php esc_html_e( 'Plán ďalšieho behu', 'toptour-reference-finder' ); ?></h3>
						<span class="tt-ref-step__badge"><?php echo esc_html( $state_labels[ $step_state ] ); ?></span>
					</div>
				</div>
				<p><?php esc_html_e( 'Úloha sa nastaví na manuálny alebo automatický režim podľa potreby.', 'toptour-reference-finder' ); ?></p>
				<p class="tt-ref-step__note"><em><?php esc_html_e( 'V sezóne možno zvýšiť frekvenciu, pri reputačnom riziku sledovať častejšie.', 'toptour-reference-finder' ); ?></em></p>
				<div class="tt-ref-step__action">
					<?php if ( 'locked' === $step_state ) : ?>
						<span class="tt-ref-step__hint" aria-disabled="true"><?php esc_html_e( 'Najprv dokončite predchádzajúci krok.', 'toptour-reference-finder' ); ?></span>
					<?php else : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $settings_page_url ); ?>"><?php esc_html_e( 'Otvoriť Nastavenia', 'toptour-reference-finder' ); ?></a>
					<?php endif; ?>
				</div>
			</article>
		</div>
	</section>

	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<?php
		$f = $edit_task;
		if ( $action === 'send_to_ai' && $edit_id ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_send_to_ai_' . $edit_id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
			}

			$ai_result = Toptour_Ref_AI_Bridge::generate_inbox_batch( $edit_id );
			if ( $ai_result['ok'] ) {
				$notice      = sprintf(
					/* translators: %s: filename */
					__( 'Batch odoslaný do AI inbox: %s', 'toptour-reference-finder' ),
					esc_html( $ai_result['filename'] ?? '' )
				);
				$notice_type = 'success';
			} else {
				$notice      = sprintf(
					/* translators: %s: error message */
					__( 'Odoslanie do AI zlyhalo: %s', 'toptour-reference-finder' ),
					esc_html( $ai_result['message'] ?? '' )
				);
				$notice_type = 'error';
			}
			$action  = '';
			$edit_id = 0;
		}

		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ct_submit'] ) ) {
			$f = (object) Toptour_Ref_Collection_Tasks::sanitize_task_data( wp_unslash( $_POST ) );
		}
		$form_id = $edit_id;
		?>
		<h2><?php echo $form_id ? esc_html__( 'Upraviť úlohu', 'toptour-reference-finder' ) : esc_html__( 'Pridať úlohu', 'toptour-reference-finder' ); ?></h2>
		<form method="post" action="<?php echo esc_url( $base_url ); ?>">
			<?php wp_nonce_field( 'toptour_save_collection_task' ); ?>
			<input type="hidden" name="toptour_ct_submit" value="1">
			<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">

			<table class="form-table">
				<tr>
					<th scope="row"><label for="task_title"><?php esc_html_e( 'Názov úlohy', 'toptour-reference-finder' ); ?> <span class="required">*</span></label></th>
					<td><input type="text" id="task_title" name="task_title" class="regular-text" maxlength="255" required value="<?php echo esc_attr( $f->task_title ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="frequency"><?php esc_html_e( 'Frekvencia', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="frequency" name="frequency">
							<?php foreach ( $allowed_frequencies as $frequency_item ) : ?>
								<option value="<?php echo esc_attr( $frequency_item ); ?>" <?php selected( $f->frequency ?? 'manual', $frequency_item ); ?>><?php echo esc_html( Toptour_Ref_Labels::collection_frequency_label( $frequency_item ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="next_run_at"><?php esc_html_e( 'Ďalší beh', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="datetime-local" id="next_run_at" name="next_run_at" value="<?php echo ! empty( $f->next_run_at ) ? esc_attr( gmdate( 'Y-m-d\TH:i', strtotime( $f->next_run_at ) ) ) : ''; ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="destination_id"><?php esc_html_e( 'Destinácia ID', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" id="destination_id" name="destination_id" min="0" value="<?php echo esc_attr( $f->destination_id ?? 0 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="supplier_id"><?php esc_html_e( 'Supplier ID', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" id="supplier_id" name="supplier_id" min="0" value="<?php echo esc_attr( $f->supplier_id ?? 0 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="offer_id"><?php esc_html_e( 'Offer ID', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" id="offer_id" name="offer_id" min="0" value="<?php echo esc_attr( $f->offer_id ?? 0 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="target_type"><?php esc_html_e( 'Typ cieľa', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="target_type" name="target_type">
							<?php foreach ( $allowed_target_types as $tt ) : ?>
								<option value="<?php echo esc_attr( $tt ); ?>" <?php selected( $f->target_type ?? 'general', $tt ); ?>><?php echo esc_html( Toptour_Ref_Labels::target_type_label( $tt ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="target_id"><?php esc_html_e( 'ID cieľa', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" id="target_id" name="target_id" min="0" value="<?php echo esc_attr( $f->target_id ?? 0 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="query_text"><?php esc_html_e( 'Text zadania', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="query_text" name="query_text" rows="4" class="large-text"><?php echo esc_textarea( $f->query_text ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="source_hint"><?php esc_html_e( 'Nápoveda k zdrojom', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="source_hint" name="source_hint" rows="2" class="large-text"><?php echo esc_textarea( $f->source_hint ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="expected_source_type"><?php esc_html_e( 'Očakávaný typ zdroja', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="expected_source_type" name="expected_source_type">
							<?php foreach ( $allowed_source_types as $st ) : ?>
								<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $f->expected_source_type ?? '', $st ); ?>><?php echo esc_html( Toptour_Ref_Labels::expected_source_type_label( $st ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="task_status"><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="task_status" name="task_status">
							<?php foreach ( $allowed_statuses as $status_item ) : ?>
								<option value="<?php echo esc_attr( $status_item ); ?>" <?php selected( $f->task_status ?? 'pending', $status_item ); ?>><?php echo esc_html( Toptour_Ref_Labels::task_status_label( $status_item ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="priority"><?php esc_html_e( 'Priorita', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="priority" name="priority">
							<?php foreach ( $allowed_priorities as $priority_item ) : ?>
								<option value="<?php echo esc_attr( $priority_item ); ?>" <?php selected( $f->priority ?? 'normal', $priority_item ); ?>><?php echo esc_html( Toptour_Ref_Labels::priority_label( $priority_item ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="assigned_to"><?php esc_html_e( 'Priradené (User ID)', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" id="assigned_to" name="assigned_to" min="0" value="<?php echo esc_attr( $f->assigned_to ?? 0 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="notes"><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $f->notes ?? '' ); ?></textarea></td>
				</tr>
			</table>

			<?php if ( $form_id && $edit_task ) : ?>
				<p class="description">
					<strong><?php esc_html_e( 'ID úlohy:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( '#' . (int) $edit_task->id ); ?>
					&nbsp;|&nbsp;
					<strong><?php esc_html_e( 'Discovery režim:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( Toptour_Ref_Collection_Task_Resolver::is_discovery_task( $edit_task ) ? __( 'áno', 'toptour-reference-finder' ) : __( 'nie', 'toptour-reference-finder' ) ); ?>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Pokusy:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_task->attempts ); ?></strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Vytvorené:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_task->created_at ); ?></strong>
				</p>
			<?php endif; ?>

			<?php submit_button( $form_id ? __( 'Uložiť zmeny', 'toptour-reference-finder' ) : __( 'Pridať úlohu', 'toptour-reference-finder' ) ); ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť', 'toptour-reference-finder' ); ?></a>
		</form>

		<h2><?php esc_html_e( 'Finder / Rozpoznanie zadania', 'toptour-reference-finder' ); ?></h2>
		<?php if ( ! $form_id ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Pre analýzu najprv uložte úlohu.', 'toptour-reference-finder' ); ?></p></div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $base_url ); ?>" style="margin-bottom: 12px;">
				<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
				<input type="hidden" name="toptour_ct_finder_submit" value="1">
				<input type="hidden" name="finder_action" value="analyze_task">
				<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
				<?php submit_button( __( 'Analyzovať zadanie', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( $base_url ); ?>" style="margin-bottom: 12px;">
				<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
				<input type="hidden" name="toptour_ct_finder_submit" value="1">
				<input type="hidden" name="finder_action" value="search_intake">
				<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
				<?php submit_button( __( 'Vyhľadať a zapísať reálne dáta', 'toptour-reference-finder' ), 'primary', '', false ); ?>
			</form>

			<div id="tt-ref-real-intake" style="background: #fff; border: 1px solid #dcdcde; padding: 12px; margin-bottom: 16px;">
				<h3><?php esc_html_e( 'Reálny vstup dát', 'toptour-reference-finder' ); ?></h3>
				<form method="post" action="<?php echo esc_url( $base_url ); ?>">
					<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
					<input type="hidden" name="toptour_ct_finder_submit" value="1">
					<input type="hidden" name="finder_action" value="intake_source">
					<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
					<table class="form-table">
						<tr>
							<th><label for="intake_source_url"><?php esc_html_e( 'URL zdroja', 'toptour-reference-finder' ); ?> *</label></th>
							<td><input type="url" id="intake_source_url" name="intake_source_url" class="regular-text" required placeholder="https://..." /></td>
						</tr>
						<tr>
							<th><label for="intake_type"><?php esc_html_e( 'Typ vstupu', 'toptour-reference-finder' ); ?></label></th>
							<td>
								<select id="intake_type" name="intake_type">
									<option value="auto"><?php esc_html_e( 'Auto', 'toptour-reference-finder' ); ?></option>
									<option value="ponuka"><?php esc_html_e( 'Ponuka', 'toptour-reference-finder' ); ?></option>
									<option value="zariadenie"><?php esc_html_e( 'Zariadenie', 'toptour-reference-finder' ); ?></option>
									<option value="destinacia"><?php esc_html_e( 'Destinácia', 'toptour-reference-finder' ); ?></option>
									<option value="referencia"><?php esc_html_e( 'Referencia', 'toptour-reference-finder' ); ?></option>
									<option value="fotodokaz"><?php esc_html_e( 'Foto dôkaz', 'toptour-reference-finder' ); ?></option>
									<option value="clanok"><?php esc_html_e( 'Článok', 'toptour-reference-finder' ); ?></option>
									<option value="ine"><?php esc_html_e( 'Iné', 'toptour-reference-finder' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="intake_destination_id"><?php esc_html_e( 'Link destinácia ID', 'toptour-reference-finder' ); ?></label></th>
							<td><input type="number" min="0" id="intake_destination_id" name="intake_destination_id" value="<?php echo esc_attr( (int) ( $edit_task->destination_id ?? 0 ) ); ?>" /></td>
						</tr>
						<tr>
							<th><label for="intake_facility_id"><?php esc_html_e( 'Link zariadenie ID', 'toptour-reference-finder' ); ?></label></th>
							<td><input type="number" min="0" id="intake_facility_id" name="intake_facility_id" value="<?php echo esc_attr( (int) ( $edit_task->supplier_id ?? 0 ) ); ?>" /></td>
						</tr>
						<tr>
							<th><label for="intake_offer_id"><?php esc_html_e( 'Link ponuka ID', 'toptour-reference-finder' ); ?></label></th>
							<td><input type="number" min="0" id="intake_offer_id" name="intake_offer_id" value="<?php echo esc_attr( (int) ( $edit_task->offer_id ?? 0 ) ); ?>" /></td>
						</tr>
						<tr>
							<th><label for="intake_manager_note"><?php esc_html_e( 'Poznámka manažéra', 'toptour-reference-finder' ); ?></label></th>
							<td><textarea id="intake_manager_note" name="intake_manager_note" rows="3" class="large-text"></textarea></td>
						</tr>
					</table>
					<?php submit_button( __( 'Načítať a zaradiť dáta', 'toptour-reference-finder' ), 'primary', '', false ); ?>
				</form>

				<?php if ( is_array( $intake_result ) && isset( $intake_result['details'] ) ) : ?>
					<?php $d = $intake_result['details']; ?>
					<table class="widefat striped" style="margin-top: 10px;">
						<tbody>
							<tr><th><?php esc_html_e( 'Detekovaný typ', 'toptour-reference-finder' ); ?></th><td><?php echo esc_html( (string) ( $d['detected_type'] ?? '—' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'HTTP status', 'toptour-reference-finder' ); ?></th><td><?php echo esc_html( (string) ( $d['http_status'] ?? '0' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Source vytvorený', 'toptour-reference-finder' ); ?></th><td><?php echo ! empty( $d['source_created'] ) ? 'YES' : 'NO'; ?></td></tr>
							<tr><th><?php esc_html_e( 'Source aktualizovaný', 'toptour-reference-finder' ); ?></th><td><?php echo ! empty( $d['source_updated'] ) ? 'YES' : 'NO'; ?></td></tr>
							<tr><th><?php esc_html_e( 'Finding vytvorený', 'toptour-reference-finder' ); ?></th><td><?php echo ! empty( $d['finding_created'] ) ? 'YES' : 'NO'; ?></td></tr>
							<tr><th><?php esc_html_e( 'Offer vytvorený', 'toptour-reference-finder' ); ?></th><td><?php echo ! empty( $d['offer_created'] ) ? 'YES' : 'NO'; ?></td></tr>
							<tr><th><?php esc_html_e( 'Offer aktualizovaný', 'toptour-reference-finder' ); ?></th><td><?php echo ! empty( $d['offer_updated'] ) ? 'YES' : 'NO'; ?></td></tr>
							<tr><th><?php esc_html_e( 'Facility kandidát vytvorený', 'toptour-reference-finder' ); ?></th><td><?php echo ! empty( $d['facility_candidate_created'] ) ? 'YES' : 'NO'; ?></td></tr>
							<tr><th><?php esc_html_e( 'Destination kandidát vytvorený', 'toptour-reference-finder' ); ?></th><td><?php echo ! empty( $d['destination_candidate_created'] ) ? 'YES' : 'NO'; ?></td></tr>
							<tr><th><?php esc_html_e( 'Photo evidence vytvorený', 'toptour-reference-finder' ); ?></th><td><?php echo ! empty( $d['photo_evidence_created'] ) ? 'YES' : 'NO'; ?></td></tr>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( is_array( $search_intake_result ) ) : ?>
					<table class="widefat striped" style="margin-top: 10px;">
						<tbody>
							<tr><th><?php esc_html_e( 'Search intake run ID', 'toptour-reference-finder' ); ?></th><td><?php echo esc_html( (string) ( $search_intake_result['run_id'] ?? '0' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Nájdené URL', 'toptour-reference-finder' ); ?></th><td><?php echo esc_html( (string) ( $search_intake_result['found_count'] ?? '0' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Spracované URL', 'toptour-reference-finder' ); ?></th><td><?php echo esc_html( (string) ( $search_intake_result['processed_count'] ?? '0' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Duplikáty', 'toptour-reference-finder' ); ?></th><td><?php echo esc_html( (string) ( $search_intake_result['duplicate_count'] ?? '0' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Chyby', 'toptour-reference-finder' ); ?></th><td><?php echo esc_html( (string) ( $search_intake_result['error_count'] ?? '0' ) ); ?></td></tr>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<?php if ( $latest_run ) : ?>
				<div style="background: #fff; border: 1px solid #dcdcde; padding: 12px; margin-bottom: 16px;">
					<h3><?php esc_html_e( 'Výsledok analýzy', 'toptour-reference-finder' ); ?></h3>
					<p><strong><?php esc_html_e( 'Run ID:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( $latest_run->id ); ?> | <strong><?php esc_html_e( 'Stav:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( Toptour_Ref_Labels::discovery_run_status_label( $latest_run->run_status ) ); ?> | <strong><?php esc_html_e( 'Provider:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( Toptour_Ref_Labels::discovery_provider_label( $latest_run->discovery_provider ) ); ?></p>
					<ul style="list-style: disc; padding-left: 20px;">
						<li><strong><?php esc_html_e( 'Navrhnutý target_type:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( Toptour_Ref_Labels::target_type_label( $latest_run->resolved_target_type ) ); ?></li>
						<li><strong><?php esc_html_e( 'Navrhnutý cieľ:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( $latest_run->resolved_target_label ? $latest_run->resolved_target_label : '—' ); ?></li>
						<li><strong><?php esc_html_e( 'Existuje v DB:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( (int) $latest_run->resolved_target_id > 0 ? 'ano' : 'nie' ); ?></li>
						<li><strong><?php esc_html_e( 'Platformové hinty:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( ! empty( $run_analysis['platform_hints'] ) && is_array( $run_analysis['platform_hints'] ) ? implode( ', ', $run_analysis['platform_hints'] ) : '—' ); ?></li>
						<li><strong><?php esc_html_e( 'Navrhnuté záujmy:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( ! empty( $run_interest_candidates ) ? implode( ', ', $run_interest_candidates ) : '—' ); ?></li>
						<li><strong><?php esc_html_e( 'Navrhnuté oblasti sledovania:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( ! empty( $run_finding_areas ) ? implode( ', ', $run_finding_areas ) : '—' ); ?></li>
					</ul>
				</div>

				<?php if ( ! empty( $missing_rows ) ) : ?>
					<div style="background: #fffbe6; border: 1px solid #dba617; padding: 12px; margin-bottom: 16px;">
						<h3><?php esc_html_e( 'Na pokračovanie doplňte údaje', 'toptour-reference-finder' ); ?></h3>
						<form method="post" action="<?php echo esc_url( $base_url ); ?>">
							<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
							<input type="hidden" name="toptour_ct_finder_submit" value="1">
							<input type="hidden" name="finder_action" value="save_missing_fields">
							<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
							<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
							<table class="form-table">
								<?php foreach ( $missing_rows as $missing_row ) : ?>
									<tr>
										<th scope="row">
											<label for="missing_field_<?php echo esc_attr( $missing_row->id ); ?>">
												<?php echo esc_html( $missing_row->field_label ); ?>
												<?php if ( (int) $missing_row->is_required === 1 ) : ?>
													<span class="required">*</span>
												<?php endif; ?>
											</label>
										</th>
										<td>
											<input
												type="text"
												id="missing_field_<?php echo esc_attr( $missing_row->id ); ?>"
												name="missing_field_value[<?php echo esc_attr( $missing_row->field_key ); ?>]"
												value="<?php echo esc_attr( $missing_row->field_value ); ?>"
												class="regular-text"
											>
											<?php if ( ! empty( $missing_row->help_text ) ) : ?>
												<p class="description"><?php echo esc_html( $missing_row->help_text ); ?></p>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
							<?php submit_button( __( 'Uložiť doplnené údaje', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
						</form>
					</div>
				<?php endif; ?>

				<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
					<form method="post" action="<?php echo esc_url( $base_url ); ?>">
						<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
						<input type="hidden" name="toptour_ct_finder_submit" value="1">
						<input type="hidden" name="finder_action" value="apply_target">
						<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
						<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
						<?php submit_button( __( 'Vytvoriť/priradiť cieľ', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( $base_url ); ?>">
						<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
						<input type="hidden" name="toptour_ct_finder_submit" value="1">
						<input type="hidden" name="finder_action" value="prepare_queries">
						<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
						<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
						<?php submit_button( __( 'Pripraviť discovery query', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( $base_url ); ?>">
						<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
						<input type="hidden" name="toptour_ct_finder_submit" value="1">
						<input type="hidden" name="finder_action" value="run_discovery">
						<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
						<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
						<select name="discovery_provider">
							<?php foreach ( Toptour_Ref_Discovery_Runs::get_allowed_providers() as $provider_item ) : ?>
								<option value="<?php echo esc_attr( $provider_item ); ?>" <?php selected( $latest_run->discovery_provider, $provider_item ); ?>><?php echo esc_html( Toptour_Ref_Labels::discovery_provider_label( $provider_item ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Spustiť discovery', 'toptour-reference-finder' ), 'primary', '', false ); ?>
					</form>
				</div>

				<?php if ( ! empty( $run_queries ) ) : ?>
					<div style="background: #fff; border: 1px solid #dcdcde; padding: 12px; margin-bottom: 16px;">
						<h3><?php esc_html_e( 'Pripravené vyhľadávacie query', 'toptour-reference-finder' ); ?></h3>
						<ul style="list-style: disc; padding-left: 20px;">
							<?php foreach ( $run_queries as $run_query ) : ?>
								<li><?php echo esc_html( $run_query ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<div style="background: #fff; border: 1px solid #dcdcde; padding: 12px; margin-bottom: 16px;">
					<h3><?php esc_html_e( 'Manuálny discovery kandidát', 'toptour-reference-finder' ); ?></h3>
					<form method="post" action="<?php echo esc_url( $base_url ); ?>">
						<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
						<input type="hidden" name="toptour_ct_finder_submit" value="1">
						<input type="hidden" name="finder_action" value="create_candidate">
						<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
						<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
						<table class="form-table">
							<tr>
								<th><label for="candidate_title"><?php esc_html_e( 'Názov kandidáta', 'toptour-reference-finder' ); ?></label></th>
								<td><input type="text" id="candidate_title" name="candidate_title" class="regular-text" required></td>
							</tr>
							<tr>
								<th><label for="candidate_url"><?php esc_html_e( 'URL', 'toptour-reference-finder' ); ?></label></th>
								<td><input type="url" id="candidate_url" name="candidate_url" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="candidate_platform"><?php esc_html_e( 'Platforma', 'toptour-reference-finder' ); ?></label></th>
								<td><input type="text" id="candidate_platform" name="candidate_platform" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="candidate_source_type"><?php esc_html_e( 'Typ zdroja', 'toptour-reference-finder' ); ?></label></th>
								<td><input type="text" id="candidate_source_type" name="candidate_source_type" value="other" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="candidate_snippet"><?php esc_html_e( 'Snippet', 'toptour-reference-finder' ); ?></label></th>
								<td><textarea id="candidate_snippet" name="candidate_snippet" rows="3" class="large-text"></textarea></td>
							</tr>
							<tr>
								<th><label for="suggested_target_type"><?php esc_html_e( 'Navrhnuty target_type', 'toptour-reference-finder' ); ?></label></th>
								<td><input type="text" id="suggested_target_type" name="suggested_target_type" value="<?php echo esc_attr( $latest_run->resolved_target_type ); ?>" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="suggested_target_id"><?php esc_html_e( 'Navrhnuty target_id', 'toptour-reference-finder' ); ?></label></th>
								<td><input type="number" id="suggested_target_id" name="suggested_target_id" min="0" value="<?php echo esc_attr( $latest_run->resolved_target_id ); ?>"></td>
							</tr>
							<tr>
								<th><label for="suggested_credibility_level"><?php esc_html_e( 'Navrhnutá dôveryhodnosť', 'toptour-reference-finder' ); ?></label></th>
								<td><input type="text" id="suggested_credibility_level" name="suggested_credibility_level" value="unknown" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="suggestion_reason"><?php esc_html_e( 'Dôvod návrhu', 'toptour-reference-finder' ); ?></label></th>
								<td><textarea id="suggestion_reason" name="suggestion_reason" rows="2" class="large-text"></textarea></td>
							</tr>
							<tr>
								<th><label for="search_query"><?php esc_html_e( 'Search query', 'toptour-reference-finder' ); ?></label></th>
								<td><input type="text" id="search_query" name="search_query" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="candidate_notes"><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></label></th>
								<td><textarea id="candidate_notes" name="candidate_notes" rows="2" class="large-text"></textarea></td>
							</tr>
						</table>
						<?php submit_button( __( 'Pridať kandidáta', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
					</form>
				</div>

				<div style="background: #fff; border: 1px solid #dcdcde; padding: 12px;">
					<h3><?php esc_html_e( 'Discovery kandidati', 'toptour-reference-finder' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Názov', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Platforma', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Typ', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $candidates ) ) : ?>
								<?php foreach ( $candidates as $candidate ) : ?>
									<tr>
										<td><?php echo esc_html( $candidate->id ); ?></td>
										<td>
											<?php echo esc_html( $candidate->candidate_title ); ?>
											<?php if ( ! empty( $candidate->candidate_url ) ) : ?>
												<br><a href="<?php echo esc_url( $candidate->candidate_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvoriť URL', 'toptour-reference-finder' ); ?></a>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $candidate->candidate_platform ); ?></td>
										<td><?php echo esc_html( Toptour_Ref_Labels::source_type_label( $candidate->candidate_source_type ) ); ?></td>
										<td><?php echo esc_html( Toptour_Ref_Labels::discovery_candidate_status_label( $candidate->candidate_status ) ); ?></td>
										<td>
											<?php if ( in_array( $candidate->candidate_status, [ 'new', 'needs_review' ], true ) ) : ?>
												<form method="post" action="<?php echo esc_url( $base_url ); ?>" style="display:inline-block; margin-right: 6px;">
													<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
													<input type="hidden" name="toptour_ct_finder_submit" value="1">
													<input type="hidden" name="finder_action" value="candidate_decision">
													<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
													<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
													<input type="hidden" name="candidate_id" value="<?php echo esc_attr( $candidate->id ); ?>">
													<input type="hidden" name="candidate_decision" value="accept">
													<button type="submit" class="button button-small"><?php esc_html_e( 'Prijatý ako zdroj', 'toptour-reference-finder' ); ?></button>
												</form>
												<form method="post" action="<?php echo esc_url( $base_url ); ?>" style="display:inline-block; margin-right: 6px;">
													<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
													<input type="hidden" name="toptour_ct_finder_submit" value="1">
													<input type="hidden" name="finder_action" value="candidate_decision">
													<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
													<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
													<input type="hidden" name="candidate_id" value="<?php echo esc_attr( $candidate->id ); ?>">
													<input type="hidden" name="candidate_decision" value="reject">
													<button type="submit" class="button button-small"><?php esc_html_e( 'Odmietnuť', 'toptour-reference-finder' ); ?></button>
												</form>
												<form method="post" action="<?php echo esc_url( $base_url ); ?>" style="display:inline-block;">
													<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
													<input type="hidden" name="toptour_ct_finder_submit" value="1">
													<input type="hidden" name="finder_action" value="candidate_decision">
													<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
													<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
													<input type="hidden" name="candidate_id" value="<?php echo esc_attr( $candidate->id ); ?>">
													<input type="hidden" name="candidate_decision" value="duplicate">
													<button type="submit" class="button button-small"><?php esc_html_e( 'Duplicita', 'toptour-reference-finder' ); ?></button>
												</form>
											<?php else : ?>
												<?php echo esc_html__( 'Bez akcií', 'toptour-reference-finder' ); ?>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="6"><?php esc_html_e( 'Zatiaľ nie sú pridaní kandidáti.', 'toptour-reference-finder' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( $edit_task ) : ?>
				<?php
				$detail_task_status = (string) ( $edit_task->task_status ?? '' );
				if ( 'in_progress' === $detail_task_status ) {
					$detail_task_status = 'active';
				}
				$latest_task_run = ! empty( $task_runs ) ? $task_runs[0] : null;
				$latest_task_run_status = $latest_task_run ? Toptour_Ref_Labels::task_run_status_label( $latest_task_run->status ) : '—';
				?>
				<div style="margin-top: 24px; background: #fff; border: 1px solid #dcdcde; padding: 16px;">
					<h2><?php esc_html_e( 'Detail úlohy (MVP)', 'toptour-reference-finder' ); ?></h2>
					<p>
						<strong><?php esc_html_e( 'ID úlohy:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( '#' . (int) $edit_task->id ); ?>
						| <strong><?php esc_html_e( 'Discovery režim:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( Toptour_Ref_Collection_Task_Resolver::is_discovery_task( $edit_task ) ? __( 'áno', 'toptour-reference-finder' ) : __( 'nie', 'toptour-reference-finder' ) ); ?>
						| <strong><?php esc_html_e( 'Typ cieľa:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( Toptour_Ref_Labels::target_type_label( $edit_task->target_type ?? 'general' ) ); ?>
						| <strong><?php esc_html_e( 'ID cieľa:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( (int) ( $edit_task->target_id ?? 0 ) ); ?>
					</p>
					<?php $discovery_summary = toptour_ct_get_discovery_summary_counts( (int) $edit_task->id, $latest_run ); ?>
					<p>
						<strong><?php esc_html_e( 'Query seeds:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( (int) $discovery_summary['query_seed_count'] ); ?>
						| <strong><?php esc_html_e( 'Zdrojové kandidáty:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( (int) $discovery_summary['source_count'] ); ?>
						| <strong><?php esc_html_e( 'Kandidátske zariadenia:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( (int) $discovery_summary['facility_count'] ); ?>
						| <strong><?php esc_html_e( 'Zistenia:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( (int) $discovery_summary['finding_count'] ); ?>
						| <strong><?php esc_html_e( 'Fotodôkazy:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( (int) $discovery_summary['photo_count'] ); ?>
						| <strong><?php esc_html_e( 'Čaká na review:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( (int) $discovery_summary['pending_count'] ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Úloha:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( $edit_task->task_title ); ?>
						| <strong><?php esc_html_e( 'Stav úlohy:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( Toptour_Ref_Labels::task_status_label( $detail_task_status ) ); ?>
						| <strong><?php esc_html_e( 'Stav posledného behu:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( $latest_task_run_status ); ?>
						| <strong><?php esc_html_e( 'Frekvencia:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( Toptour_Ref_Labels::collection_frequency_label( $edit_task->frequency ?? 'manual' ) ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Posledný beh:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( toptour_ct_format_datetime( $edit_task->last_run_at ) ); ?>
						| <strong><?php esc_html_e( 'Ďalší beh:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( toptour_ct_format_datetime( $edit_task->next_run_at ) ); ?>
					</p>

					<h3><?php esc_html_e( 'Prehľad', 'toptour-reference-finder' ); ?></h3>
					<ul style="list-style: disc; padding-left: 20px;">
						<li><?php printf( esc_html__( 'Celkom nájdených výsledkov: %d', 'toptour-reference-finder' ), (int) $task_stats['total_found'] ); ?></li>
						<li><?php printf( esc_html__( 'Nových výsledkov: %d', 'toptour-reference-finder' ), (int) $task_stats['new_found'] ); ?></li>
						<li><?php printf( esc_html__( 'Čaká na kontrolu: %d', 'toptour-reference-finder' ), (int) $task_stats['pending_review'] ); ?></li>
						<li><?php printf( esc_html__( 'Navrhov POI: %d', 'toptour-reference-finder' ), (int) $task_stats['poi_suggestions'] ); ?></li>
						<li><?php printf( esc_html__( 'Počet chýb: %d', 'toptour-reference-finder' ), (int) $task_stats['error_count'] ); ?></li>
					</ul>

					<h3><?php esc_html_e( 'Nálezy', 'toptour-reference-finder' ); ?></h3>
					<table class="wp-list-table widefat fixed striped" style="margin-bottom: 16px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Čas nálezu / analýzy', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Názov', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Typ zdroja', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Sentiment', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Stav analýzy', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Zhrnutie', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $task_recent_findings ) ) : ?>
								<?php foreach ( $task_recent_findings as $task_finding ) : ?>
									<?php $finding_edit_url = add_query_arg( [ 'page' => 'toptour-references-findings', 'action' => 'edit', 'finding_id' => (int) $task_finding->id ], admin_url( 'admin.php' ) ); ?>
									<?php $finding_title_display = toptour_ct_translate_placeholder_text( $task_finding->finding_title ?? '' ); ?>
									<tr>
										<td><?php echo esc_html( toptour_ct_format_datetime( ! empty( $task_finding->analysis_performed_at ) ? $task_finding->analysis_performed_at : ( ! empty( $task_finding->found_at ) ? $task_finding->found_at : $task_finding->created_at ) ) ); ?></td>
										<td><?php echo esc_html( $finding_title_display !== '' ? $finding_title_display : '—' ); ?></td>
										<td><?php echo esc_html( Toptour_Ref_Labels::source_type_label( $task_finding->source_type ) ); ?></td>
										<td><?php echo esc_html( ! empty( $task_finding->detected_sentiment ) ? $task_finding->detected_sentiment : '—' ); ?></td>
										<td><?php echo esc_html( toptour_ct_analysis_status_label( $task_finding->analysis_status ?? '' ) ); ?></td>
										<td><?php echo esc_html( ! empty( $task_finding->analysis_summary ) ? toptour_ct_translate_placeholder_text( $task_finding->analysis_summary ) : '—' ); ?></td>
										<td><a href="<?php echo esc_url( $finding_edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a></td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="7"><?php esc_html_e( 'Zatiaľ bez nálezov pre túto úlohu.', 'toptour-reference-finder' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Offer snapshots', 'toptour-reference-finder' ); ?></h3>
					<table class="wp-list-table widefat fixed striped" style="margin-bottom: 16px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Názov ponuky', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'URL', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Supplier', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Destinacia', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Cena', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Mena', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Poznámka k cene', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Dĺžka pobytu', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Osoby', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Sezóna/platnosť', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Strava', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Doprava', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Ubytovanie', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Kategória zariadenia', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Zahrnuté služby', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Nezahrnuté služby', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Podmienky', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Dostupnosť', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Analýza', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $task_recent_snapshots ) ) : ?>
								<?php foreach ( $task_recent_snapshots as $snapshot ) : ?>
									<tr>
										<td><?php echo esc_html( $snapshot->offer_name ?: '—' ); ?></td>
										<td><?php if ( ! empty( $snapshot->source_url ) ) : ?><a href="<?php echo esc_url( $snapshot->source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvoriť', 'toptour-reference-finder' ); ?></a><?php else : ?>—<?php endif; ?></td>
										<td><?php echo esc_html( (int) $snapshot->supplier_id > 0 ? (int) $snapshot->supplier_id : '—' ); ?></td>
										<td><?php echo esc_html( (int) $snapshot->destination_id > 0 ? (int) $snapshot->destination_id : '—' ); ?></td>
										<td><?php echo null !== $snapshot->price_value ? esc_html( $snapshot->price_value ) : '—'; ?></td>
										<td><?php echo esc_html( $snapshot->price_currency ?: '—' ); ?></td>
										<td><?php echo esc_html( $snapshot->price_note ?: '—' ); ?></td>
										<td><?php echo esc_html( $snapshot->stay_duration ?: '—' ); ?></td>
										<td><?php echo esc_html( (int) $snapshot->persons_min > 0 || (int) $snapshot->persons_max > 0 ? ((int) $snapshot->persons_min) . '-' . ((int) $snapshot->persons_max) : '—' ); ?></td>
										<td><?php echo esc_html( $snapshot->season ?: '—' ); ?><?php echo esc_html( ( ! empty( $snapshot->valid_from ) || ! empty( $snapshot->valid_to ) ) ? ' (' . ( $snapshot->valid_from ?: '?' ) . ' - ' . ( $snapshot->valid_to ?: '?' ) . ')' : '' ); ?></td>
										<td><?php echo esc_html( $snapshot->meal_plan ?: '—' ); ?></td>
										<td><?php echo esc_html( $snapshot->transport_type ?: '—' ); ?></td>
										<td><?php echo esc_html( $snapshot->accommodation_type ?: '—' ); ?></td>
										<td><?php echo esc_html( $snapshot->facility_category ?: '—' ); ?></td>
										<td><?php echo esc_html( $snapshot->included_services_summary ?: '—' ); ?></td>
										<td><?php echo esc_html( $snapshot->excluded_services_summary ?: '—' ); ?></td>
										<td><?php echo esc_html( $snapshot->booking_conditions_summary ?: '—' ); ?></td>
										<td><?php echo esc_html( $snapshot->availability_note ?: '—' ); ?></td>
										<td><?php echo esc_html( toptour_ct_format_datetime( $snapshot->analysis_performed_at ) ); ?></td>
										<td><?php echo esc_html( $snapshot->status ?: '—' ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="20"><?php esc_html_e( 'Zatiaľ nie sú uložené žiadne časové záznamy verejných parametrov ponuky pre túto úlohu.', 'toptour-reference-finder' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Behy úlohy', 'toptour-reference-finder' ); ?></h3>
					<table class="wp-list-table widefat fixed striped" style="margin-bottom: 16px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Start', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Koniec', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Nálezy', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Nové', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Duplicity', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Chyby', 'toptour-reference-finder' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $task_runs ) ) : ?>
								<?php foreach ( $task_runs as $task_run ) : ?>
									<tr>
										<td><?php echo esc_html( toptour_ct_format_datetime( $task_run->started_at ) ); ?></td>
										<td><?php echo esc_html( toptour_ct_format_datetime( $task_run->finished_at ) ); ?></td>
										<td><?php echo esc_html( Toptour_Ref_Labels::task_run_status_label( $task_run->status ) ); ?></td>
										<td><?php echo esc_html( (int) $task_run->found_count ); ?></td>
										<td><?php echo esc_html( (int) $task_run->new_count ); ?></td>
										<td><?php echo esc_html( (int) $task_run->duplicate_count ); ?></td>
										<td><?php echo esc_html( (int) $task_run->error_count ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="7"><?php esc_html_e( 'Zatiaľ bez zaznamenaných behov.', 'toptour-reference-finder' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'História', 'toptour-reference-finder' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Čas', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Udalosť', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Poznámka', 'toptour-reference-finder' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $task_events ) ) : ?>
								<?php foreach ( $task_events as $task_event ) : ?>
									<tr>
										<td><?php echo esc_html( toptour_ct_format_datetime( $task_event->created_at ) ); ?></td>
										<td><?php echo esc_html( Toptour_Ref_Labels::task_event_type_label( $task_event->event_type ) ); ?></td>
										<td><?php echo esc_html( toptour_ct_event_note_for_manager( $task_event->note ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="3"><?php esc_html_e( 'Zatiaľ bez udalostí.', 'toptour-reference-finder' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		<?php endif; ?>

	<?php else : ?>
		<a href="<?php echo esc_url( add_query_arg( [ 'toptour_action' => 'add' ], $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridať úlohu', 'toptour-reference-finder' ); ?></a>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="toptour-references-collection">
			<div style="margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
				<select name="filter_status">
					<option value=""><?php esc_html_e( '- Stav -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_statuses as $status_item ) : ?>
						<option value="<?php echo esc_attr( $status_item ); ?>" <?php selected( $filter_status, $status_item ); ?>><?php echo esc_html( Toptour_Ref_Labels::task_status_label( $status_item ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_priority">
					<option value=""><?php esc_html_e( '- Priorita -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_priorities as $priority_item ) : ?>
						<option value="<?php echo esc_attr( $priority_item ); ?>" <?php selected( $filter_priority, $priority_item ); ?>><?php echo esc_html( Toptour_Ref_Labels::priority_label( $priority_item ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_target_type">
					<option value=""><?php esc_html_e( '- Typ cieľa -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_target_types as $target_type_item ) : ?>
						<option value="<?php echo esc_attr( $target_type_item ); ?>" <?php selected( $filter_target_type, $target_type_item ); ?>><?php echo esc_html( Toptour_Ref_Labels::target_type_label( $target_type_item ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Hľadať...', 'toptour-reference-finder' ); ?>">
				<?php submit_button( __( 'Filtrovať', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
				<?php if ( $filter_status || $filter_priority || $filter_target_type || $search ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť filtre', 'toptour-reference-finder' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<p><?php printf( esc_html__( 'Celkom záznamov: %d', 'toptour-reference-finder' ), $total ); ?></p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Názov úlohy', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Destinácia', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Frekvencia', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Posledné spustenie', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Ďalšie spustenie', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Nové nálezy', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Čaká na kontrolu', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Chyby', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'AI', 'toptour-reference-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( $tasks ) : ?>
				<?php foreach ( $tasks as $task ) : ?>
					<?php
					$row_stats = Toptour_Ref_Collection_Tasks::get_task_stats( (int) $task->id );
					$last_task_run = Toptour_Ref_Task_Runs::get_latest_run_for_task( (int) $task->id );
					$edit_url = add_query_arg( [ 'toptour_action' => 'edit', 'task_id' => $task->id ], $base_url );
					$archive_url = wp_nonce_url( add_query_arg( [ 'toptour_action' => 'archive', 'task_id' => $task->id ], $base_url ), 'toptour_archive_task_' . $task->id );
										$send_ai_url = wp_nonce_url( add_query_arg( [ 'toptour_action' => 'send_to_ai', 'task_id' => $task->id ], $base_url ), 'toptour_send_to_ai_' . $task->id );
					?>
					<tr>
						<td><?php echo esc_html( (int) $task->id ); ?></td>
						<td><?php echo esc_html( $task->task_title ); ?></td>
						<td><?php echo esc_html( Toptour_Ref_Collection_Tasks::get_destination_label( $task ) ); ?></td>
						<td><?php echo esc_html( Toptour_Ref_Labels::task_status_label( $task->task_status ) ); ?></td>
						<td><?php echo esc_html( Toptour_Ref_Labels::collection_frequency_label( $task->frequency ?? 'manual' ) ); ?></td>
						<td><?php echo esc_html( ! empty( $task->last_run_at ) ? toptour_ct_format_datetime( $task->last_run_at ) : ( $last_task_run && ! empty( $last_task_run->started_at ) ? toptour_ct_format_datetime( $last_task_run->started_at ) : '—' ) ); ?></td>
						<td><?php echo esc_html( toptour_ct_format_datetime( $task->next_run_at ) ); ?></td>
						<td><?php echo esc_html( $row_stats['new_found'] ); ?></td>
						<td><?php echo esc_html( $row_stats['pending_review'] ); ?></td>
						<td><?php echo esc_html( $row_stats['error_count'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a>
							<?php if ( 'archived' !== $task->task_status ) : ?>
								&nbsp;|&nbsp;
								<a href="<?php echo esc_url( $archive_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Archivovať túto úlohu?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Archivovať', 'toptour-reference-finder' ); ?></a>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( 'archived' !== $task->task_status ) : ?>
								<a href="<?php echo esc_url( $send_ai_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Odoslať úlohu do AI inbox?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Odoslať do AI', 'toptour-reference-finder' ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="12"><?php esc_html_e( 'Žiadne záznamy.', 'toptour-reference-finder' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
						<?php if ( $p === $current_page ) : ?>
							<span class="current"><?php echo esc_html( $p ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $p, toptour_ct_filter_url() ) ); ?>"><?php echo esc_html( $p ); ?></a>
						<?php endif; ?>
					<?php endfor; ?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
