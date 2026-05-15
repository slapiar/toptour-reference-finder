<?php
/**
 * TOPTOUR Reference Finder - Collection Tasks View
 *
 * Admin workflow for controlled discovery planning and review.
 *
 * @package Toptour_Ref
 * @version 0.2.0
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

$base_url    = admin_url( 'admin.php?page=toptour-references-collection' );
$action      = isset( $_GET['toptour_action'] ) ? sanitize_text_field( wp_unslash( $_GET['toptour_action'] ) ) : '';
$edit_id     = isset( $_GET['task_id'] ) ? absint( $_GET['task_id'] ) : 0;
$notice      = '';
$notice_type = 'success';

if ( $action === 'archive' && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_archive_task_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$archived = Toptour_Ref_Collection_Tasks::archive_task( $edit_id );
	$notice = $archived
		? __( 'Uloha bola archivovana.', 'toptour-reference-finder' )
		: __( 'Archivacia zlyhala.', 'toptour-reference-finder' );
	$notice_type = $archived ? 'success' : 'error';
	$action = '';
	$edit_id = 0;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ct_submit'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_save_collection_task' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$post_id = absint( $_POST['task_id'] ?? 0 );
	$data = Toptour_Ref_Collection_Tasks::sanitize_task_data( wp_unslash( $_POST ) );
	$valid = Toptour_Ref_Collection_Tasks::validate_task_data( $data );

	if ( true === $valid ) {
		$ok = $post_id ? Toptour_Ref_Collection_Tasks::update_task( $post_id, $data ) : Toptour_Ref_Collection_Tasks::create_task( $data );
		$notice = $ok
			? __( 'Uloha bola ulozena.', 'toptour-reference-finder' )
			: __( 'Ulozenie ulohy zlyhalo.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
		if ( $ok ) {
			$action = $post_id ? 'edit' : '';
			$edit_id = $post_id ? $post_id : 0;
		}
	} else {
		$notice = __( 'Ulohu sa nepodarilo ulozit. Skontrolujte povinne polia.', 'toptour-reference-finder' );
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

	if ( 'analyze_task' === $finder_action && $task_id > 0 ) {
		$task = Toptour_Ref_Collection_Tasks::get_task( $task_id );
		if ( $task ) {
			$analysis = Toptour_Ref_Collection_Task_Resolver::analyze_task( $task );
			$new_run_id = Toptour_Ref_Collection_Task_Resolver::create_discovery_run( $task_id, $analysis );
			if ( $new_run_id ) {
				Toptour_Ref_Collection_Tasks::touch_task_run( $task_id, 'in_progress' );
				$notice = __( 'Analyza zadania bola vytvorena a discovery run ulozeny.', 'toptour-reference-finder' );
				$notice_type = 'success';
				$run_id = $new_run_id;
			} else {
				$notice = __( 'Discovery run sa nepodarilo vytvorit.', 'toptour-reference-finder' );
				$notice_type = 'error';
			}
		} else {
			$notice = __( 'Uloha neexistuje. Najprv ju ulozte.', 'toptour-reference-finder' );
			$notice_type = 'error';
		}
	}

	if ( 'save_missing_fields' === $finder_action && $run_id > 0 ) {
		$values = isset( $_POST['missing_field_value'] ) && is_array( $_POST['missing_field_value'] ) ? $_POST['missing_field_value'] : [];
		$saved = Toptour_Ref_Discovery_Runs::save_missing_field_values( $run_id, $values );
		$notice = $saved
			? __( 'Chybajuce udaje boli ulozene.', 'toptour-reference-finder' )
			: __( 'Nebolo co ulozit alebo ulozenie zlyhalo.', 'toptour-reference-finder' );
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
			? __( 'Ciel bol vytvoreny alebo priradeny k ulohe.', 'toptour-reference-finder' )
			: __( 'Ciel sa nepodarilo priradit.', 'toptour-reference-finder' );
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
			? __( 'Discovery queries boli pripravene a ulozene.', 'toptour-reference-finder' )
			: __( 'Discovery queries sa nepodarilo pripravit.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
	}

	if ( 'run_discovery' === $finder_action && $run_id > 0 ) {
		$run = Toptour_Ref_Discovery_Runs::get_run( $run_id );
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
			$result = Toptour_Ref_Discovery_Provider::run_search_api_discovery( $run_id );
		} elseif ( 'manual' === $provider ) {
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
		if ( $task_id > 0 ) {
			Toptour_Ref_Collection_Tasks::touch_task_run( $task_id, 'in_progress' );
		}
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
				$notice = sprintf( __( 'Kandidat prijaty ako Reference Source #%d.', 'toptour-reference-finder' ), (int) $source_id );
				$notice_type = 'success';
			} else {
				$notice = __( 'Kandidata sa nepodarilo prijat ako zdroj.', 'toptour-reference-finder' );
				$notice_type = 'error';
			}
		}

		if ( 'reject' === $decision ) {
			$ok = Toptour_Ref_Discovery_Candidates::reject_candidate( $candidate_id );
			$notice = $ok ? __( 'Kandidat bol odmietnuty.', 'toptour-reference-finder' ) : __( 'Akcia odmietnutia zlyhala.', 'toptour-reference-finder' );
			$notice_type = $ok ? 'success' : 'error';
		}

		if ( 'duplicate' === $decision ) {
			$ok = Toptour_Ref_Discovery_Candidates::mark_duplicate( $candidate_id );
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

if ( $edit_task ) {
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
?>

<div class="wrap toptour-ref-collection-tasks">
	<h1><?php esc_html_e( 'Zber referencii', 'toptour-reference-finder' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Kontrolovany admin workflow pre discovery zdrojov. Bez automatickeho scrapingu, bez AI a bez verejneho vystupu.', 'toptour-reference-finder' ); ?></p>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<?php
		$f = $edit_task;
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ct_submit'] ) ) {
			$f = (object) Toptour_Ref_Collection_Tasks::sanitize_task_data( wp_unslash( $_POST ) );
		}
		$form_id = $edit_id;
		?>
		<h2><?php echo $form_id ? esc_html__( 'Upravit ulohu', 'toptour-reference-finder' ) : esc_html__( 'Pridat ulohu', 'toptour-reference-finder' ); ?></h2>
		<form method="post" action="<?php echo esc_url( $base_url ); ?>">
			<?php wp_nonce_field( 'toptour_save_collection_task' ); ?>
			<input type="hidden" name="toptour_ct_submit" value="1">
			<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">

			<table class="form-table">
				<tr>
					<th scope="row"><label for="task_title"><?php esc_html_e( 'Nazov ulohy', 'toptour-reference-finder' ); ?> <span class="required">*</span></label></th>
					<td><input type="text" id="task_title" name="task_title" class="regular-text" maxlength="255" required value="<?php echo esc_attr( $f->task_title ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="target_type"><?php esc_html_e( 'Typ ciela', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="target_type" name="target_type">
							<?php foreach ( $allowed_target_types as $tt ) : ?>
								<option value="<?php echo esc_attr( $tt ); ?>" <?php selected( $f->target_type ?? 'general', $tt ); ?>><?php echo esc_html( $tt ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="target_id"><?php esc_html_e( 'ID ciela', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" id="target_id" name="target_id" min="0" value="<?php echo esc_attr( $f->target_id ?? 0 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="query_text"><?php esc_html_e( 'Text zadania', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="query_text" name="query_text" rows="4" class="large-text"><?php echo esc_textarea( $f->query_text ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="source_hint"><?php esc_html_e( 'Napoveda k zdrojom', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="source_hint" name="source_hint" rows="2" class="large-text"><?php echo esc_textarea( $f->source_hint ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="expected_source_type"><?php esc_html_e( 'Ocakavany typ zdroja', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="expected_source_type" name="expected_source_type">
							<?php foreach ( $allowed_source_types as $st ) : ?>
								<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $f->expected_source_type ?? '', $st ); ?>><?php echo esc_html( '' === $st ? '— ziadny —' : $st ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="task_status"><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="task_status" name="task_status">
							<?php foreach ( $allowed_statuses as $status_item ) : ?>
								<option value="<?php echo esc_attr( $status_item ); ?>" <?php selected( $f->task_status ?? 'pending', $status_item ); ?>><?php echo esc_html( $status_item ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="priority"><?php esc_html_e( 'Priorita', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="priority" name="priority">
							<?php foreach ( $allowed_priorities as $priority_item ) : ?>
								<option value="<?php echo esc_attr( $priority_item ); ?>" <?php selected( $f->priority ?? 'normal', $priority_item ); ?>><?php echo esc_html( $priority_item ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="assigned_to"><?php esc_html_e( 'Priradene (User ID)', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" id="assigned_to" name="assigned_to" min="0" value="<?php echo esc_attr( $f->assigned_to ?? 0 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="notes"><?php esc_html_e( 'Poznamky', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $f->notes ?? '' ); ?></textarea></td>
				</tr>
			</table>

			<?php if ( $form_id && $edit_task ) : ?>
				<p class="description">
					<?php esc_html_e( 'Pokusy:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_task->attempts ); ?></strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Vytvorene:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_task->created_at ); ?></strong>
				</p>
			<?php endif; ?>

			<?php submit_button( $form_id ? __( 'Ulozit zmeny', 'toptour-reference-finder' ) : __( 'Pridat ulohu', 'toptour-reference-finder' ) ); ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrusit', 'toptour-reference-finder' ); ?></a>
		</form>

		<h2><?php esc_html_e( 'Finder / Rozpoznanie zadania', 'toptour-reference-finder' ); ?></h2>
		<?php if ( ! $form_id ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Pre analyzu najprv ulozte ulohu.', 'toptour-reference-finder' ); ?></p></div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $base_url ); ?>" style="margin-bottom: 12px;">
				<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
				<input type="hidden" name="toptour_ct_finder_submit" value="1">
				<input type="hidden" name="finder_action" value="analyze_task">
				<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
				<?php submit_button( __( 'Analyzovat zadanie', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( $latest_run ) : ?>
				<div style="background: #fff; border: 1px solid #dcdcde; padding: 12px; margin-bottom: 16px;">
					<h3><?php esc_html_e( 'Vysledok analyzy', 'toptour-reference-finder' ); ?></h3>
					<p><strong><?php esc_html_e( 'Run ID:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( $latest_run->id ); ?> | <strong><?php esc_html_e( 'Stav:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( $latest_run->run_status ); ?> | <strong><?php esc_html_e( 'Provider:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( $latest_run->discovery_provider ); ?></p>
					<ul style="list-style: disc; padding-left: 20px;">
						<li><strong><?php esc_html_e( 'Navrhnuty target_type:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( $latest_run->resolved_target_type ); ?></li>
						<li><strong><?php esc_html_e( 'Navrhnuty ciel:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( $latest_run->resolved_target_label ? $latest_run->resolved_target_label : '—' ); ?></li>
						<li><strong><?php esc_html_e( 'Existuje v DB:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( (int) $latest_run->resolved_target_id > 0 ? 'ano' : 'nie' ); ?></li>
						<li><strong><?php esc_html_e( 'Platformove hinty:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( ! empty( $run_analysis['platform_hints'] ) && is_array( $run_analysis['platform_hints'] ) ? implode( ', ', $run_analysis['platform_hints'] ) : '—' ); ?></li>
						<li><strong><?php esc_html_e( 'Navrhnute zaujmy:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( ! empty( $run_interest_candidates ) ? implode( ', ', $run_interest_candidates ) : '—' ); ?></li>
						<li><strong><?php esc_html_e( 'Navrhnute oblasti sledovania:', 'toptour-reference-finder' ); ?></strong> <?php echo esc_html( ! empty( $run_finding_areas ) ? implode( ', ', $run_finding_areas ) : '—' ); ?></li>
					</ul>
				</div>

				<?php if ( ! empty( $missing_rows ) ) : ?>
					<div style="background: #fffbe6; border: 1px solid #dba617; padding: 12px; margin-bottom: 16px;">
						<h3><?php esc_html_e( 'Na pokracovanie doplnte udaje', 'toptour-reference-finder' ); ?></h3>
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
							<?php submit_button( __( 'Ulozit doplnene udaje', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
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
						<?php submit_button( __( 'Vytvorit/priradit ciel', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( $base_url ); ?>">
						<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
						<input type="hidden" name="toptour_ct_finder_submit" value="1">
						<input type="hidden" name="finder_action" value="prepare_queries">
						<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
						<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
						<?php submit_button( __( 'Pripravit discovery query', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( $base_url ); ?>">
						<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
						<input type="hidden" name="toptour_ct_finder_submit" value="1">
						<input type="hidden" name="finder_action" value="run_discovery">
						<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
						<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
						<select name="discovery_provider">
							<?php foreach ( Toptour_Ref_Discovery_Runs::get_allowed_providers() as $provider_item ) : ?>
								<option value="<?php echo esc_attr( $provider_item ); ?>" <?php selected( $latest_run->discovery_provider, $provider_item ); ?>><?php echo esc_html( $provider_item ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Spustit discovery', 'toptour-reference-finder' ), 'primary', '', false ); ?>
					</form>
				</div>

				<?php if ( ! empty( $run_queries ) ) : ?>
					<div style="background: #fff; border: 1px solid #dcdcde; padding: 12px; margin-bottom: 16px;">
						<h3><?php esc_html_e( 'Pripravene vyhladavacie query', 'toptour-reference-finder' ); ?></h3>
						<ul style="list-style: disc; padding-left: 20px;">
							<?php foreach ( $run_queries as $run_query ) : ?>
								<li><?php echo esc_html( $run_query ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<div style="background: #fff; border: 1px solid #dcdcde; padding: 12px; margin-bottom: 16px;">
					<h3><?php esc_html_e( 'Manualny discovery kandidat', 'toptour-reference-finder' ); ?></h3>
					<form method="post" action="<?php echo esc_url( $base_url ); ?>">
						<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
						<input type="hidden" name="toptour_ct_finder_submit" value="1">
						<input type="hidden" name="finder_action" value="create_candidate">
						<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
						<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
						<table class="form-table">
							<tr>
								<th><label for="candidate_title"><?php esc_html_e( 'Nazov kandidata', 'toptour-reference-finder' ); ?></label></th>
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
								<th><label for="suggested_credibility_level"><?php esc_html_e( 'Navrhnuta doveryhodnost', 'toptour-reference-finder' ); ?></label></th>
								<td><input type="text" id="suggested_credibility_level" name="suggested_credibility_level" value="unknown" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="suggestion_reason"><?php esc_html_e( 'Dovod navrhu', 'toptour-reference-finder' ); ?></label></th>
								<td><textarea id="suggestion_reason" name="suggestion_reason" rows="2" class="large-text"></textarea></td>
							</tr>
							<tr>
								<th><label for="search_query"><?php esc_html_e( 'Search query', 'toptour-reference-finder' ); ?></label></th>
								<td><input type="text" id="search_query" name="search_query" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="candidate_notes"><?php esc_html_e( 'Poznamky', 'toptour-reference-finder' ); ?></label></th>
								<td><textarea id="candidate_notes" name="candidate_notes" rows="2" class="large-text"></textarea></td>
							</tr>
						</table>
						<?php submit_button( __( 'Pridat kandidata', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
					</form>
				</div>

				<div style="background: #fff; border: 1px solid #dcdcde; padding: 12px;">
					<h3><?php esc_html_e( 'Discovery kandidati', 'toptour-reference-finder' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
								<th><?php esc_html_e( 'Nazov', 'toptour-reference-finder' ); ?></th>
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
												<br><a href="<?php echo esc_url( $candidate->candidate_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvorit URL', 'toptour-reference-finder' ); ?></a>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $candidate->candidate_platform ); ?></td>
										<td><?php echo esc_html( $candidate->candidate_source_type ); ?></td>
										<td><?php echo esc_html( $candidate->candidate_status ); ?></td>
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
													<button type="submit" class="button button-small"><?php esc_html_e( 'Prijat ako zdroj', 'toptour-reference-finder' ); ?></button>
												</form>
												<form method="post" action="<?php echo esc_url( $base_url ); ?>" style="display:inline-block; margin-right: 6px;">
													<?php wp_nonce_field( 'toptour_collection_discovery_action' ); ?>
													<input type="hidden" name="toptour_ct_finder_submit" value="1">
													<input type="hidden" name="finder_action" value="candidate_decision">
													<input type="hidden" name="task_id" value="<?php echo esc_attr( $form_id ); ?>">
													<input type="hidden" name="discovery_run_id" value="<?php echo esc_attr( $latest_run->id ); ?>">
													<input type="hidden" name="candidate_id" value="<?php echo esc_attr( $candidate->id ); ?>">
													<input type="hidden" name="candidate_decision" value="reject">
													<button type="submit" class="button button-small"><?php esc_html_e( 'Odmietnut', 'toptour-reference-finder' ); ?></button>
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
												<?php echo esc_html__( 'Bez akcii', 'toptour-reference-finder' ); ?>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="6"><?php esc_html_e( 'Zatial nie su pridani kandidati.', 'toptour-reference-finder' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		<?php endif; ?>

	<?php else : ?>
		<a href="<?php echo esc_url( add_query_arg( [ 'toptour_action' => 'add' ], $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridat ulohu', 'toptour-reference-finder' ); ?></a>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="toptour-references-collection">
			<div style="margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
				<select name="filter_status">
					<option value=""><?php esc_html_e( '- Stav -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_statuses as $status_item ) : ?>
						<option value="<?php echo esc_attr( $status_item ); ?>" <?php selected( $filter_status, $status_item ); ?>><?php echo esc_html( $status_item ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_priority">
					<option value=""><?php esc_html_e( '- Priorita -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_priorities as $priority_item ) : ?>
						<option value="<?php echo esc_attr( $priority_item ); ?>" <?php selected( $filter_priority, $priority_item ); ?>><?php echo esc_html( $priority_item ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_target_type">
					<option value=""><?php esc_html_e( '- Typ ciela -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_target_types as $target_type_item ) : ?>
						<option value="<?php echo esc_attr( $target_type_item ); ?>" <?php selected( $filter_target_type, $target_type_item ); ?>><?php echo esc_html( $target_type_item ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Hladat...', 'toptour-reference-finder' ); ?>">
				<?php submit_button( __( 'Filtrovat', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
				<?php if ( $filter_status || $filter_priority || $filter_target_type || $search ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrusit filtre', 'toptour-reference-finder' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<p><?php printf( esc_html__( 'Celkom zaznamov: %d', 'toptour-reference-finder' ), $total ); ?></p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:40px"><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Nazov ulohy', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Ciel', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Ocakavany zdroj', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Priorita', 'toptour-reference-finder' ); ?></th>
					<th style="width:55px"><?php esc_html_e( 'Pokusy', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Posledne spustenie', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Vytvorene', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( $tasks ) : ?>
				<?php foreach ( $tasks as $task ) : ?>
					<?php
					$target_col = $task->target_type;
					if ( ! empty( $task->target_id ) && (int) $task->target_id > 0 ) {
						$target_col .= ' #' . $task->target_id;
					}
					$edit_url = add_query_arg( [ 'toptour_action' => 'edit', 'task_id' => $task->id ], $base_url );
					$archive_url = wp_nonce_url( add_query_arg( [ 'toptour_action' => 'archive', 'task_id' => $task->id ], $base_url ), 'toptour_archive_task_' . $task->id );
					?>
					<tr>
						<td><?php echo esc_html( $task->id ); ?></td>
						<td><?php echo esc_html( $task->task_title ); ?></td>
						<td><?php echo esc_html( $target_col ); ?></td>
						<td><?php echo esc_html( $task->expected_source_type ); ?></td>
						<td><?php echo esc_html( $task->task_status ); ?></td>
						<td><?php echo esc_html( $task->priority ); ?></td>
						<td><?php echo esc_html( $task->attempts ); ?></td>
						<td><?php echo $task->last_run_at ? esc_html( $task->last_run_at ) : '—'; ?></td>
						<td><?php echo esc_html( $task->created_at ); ?></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upravit', 'toptour-reference-finder' ); ?></a>
							<?php if ( 'archived' !== $task->task_status ) : ?>
								&nbsp;|&nbsp;
								<a href="<?php echo esc_url( $archive_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Archivovat tuto ulohu?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Archivovat', 'toptour-reference-finder' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="10"><?php esc_html_e( 'Ziadne zaznamy.', 'toptour-reference-finder' ); ?></td></tr>
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
