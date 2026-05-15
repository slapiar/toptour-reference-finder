<?php
/**
 * TOPTOUR Reference Finder – Collection Tasks View
 *
 * Admin screen for the internal reference collection work queue.
 * Read/write to {prefix}toptour_ref_collection_tasks only.
 * No scraping, no automation, no external requests.
 *
 * @package Toptour_Ref
 * @version 0.1.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Capability check.
if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
}

$base_url   = admin_url( 'admin.php?page=toptour-references-collection' );
$action     = isset( $_GET['toptour_action'] ) ? sanitize_text_field( wp_unslash( $_GET['toptour_action'] ) ) : '';
$edit_id    = isset( $_GET['task_id'] ) ? absint( $_GET['task_id'] ) : 0;
$notice     = '';
$notice_type = 'success';

// ── Handle archive action (GET with nonce) ─────────────────────────────────
if ( $action === 'archive' && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_archive_task_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
	$archived = Toptour_Ref_Collection_Tasks::archive_task( $edit_id );
	$notice   = $archived
		? __( 'Úloha bola archivovaná.', 'toptour-reference-finder' )
		: __( 'Archivácia zlyhala.', 'toptour-reference-finder' );
	$notice_type = $archived ? 'success' : 'error';
	$action = '';
	$edit_id = 0;
}

// ── Handle save (POST) ──────────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ct_submit'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_save_collection_task' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$post_id = absint( $_POST['task_id'] ?? 0 );
	$data    = Toptour_Ref_Collection_Tasks::sanitize_task_data( wp_unslash( $_POST ) );
	$valid   = Toptour_Ref_Collection_Tasks::validate_task_data( $data );

	if ( $valid === true ) {
		if ( $post_id ) {
			$ok = Toptour_Ref_Collection_Tasks::update_task( $post_id, $data );
		} else {
			$ok = Toptour_Ref_Collection_Tasks::create_task( $data );
		}
		$notice      = $ok
			? __( 'Úloha bola uložená.', 'toptour-reference-finder' )
			: __( 'Úlohu sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
		if ( $ok ) {
			$action  = '';
			$edit_id = 0;
		} else {
			$action = $post_id ? 'edit' : 'add';
		}
	} else {
		$notice      = __( 'Úlohu sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = 'error';
		$action      = $post_id ? 'edit' : 'add';
		$edit_id     = $post_id;
	}
}

// ── Resolve task for edit form ──────────────────────────────────────────────
$edit_task = null;
if ( in_array( $action, [ 'edit', 'add' ], true ) && $edit_id ) {
	$edit_task = Toptour_Ref_Collection_Tasks::get_task( $edit_id );
}

// ── Filter & search args from GET ──────────────────────────────────────────
$filter_status      = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
$filter_priority    = isset( $_GET['filter_priority'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_priority'] ) ) : '';
$filter_target_type = isset( $_GET['filter_target_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_target_type'] ) ) : '';
$search             = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result   = Toptour_Ref_Collection_Tasks::get_tasks( [
	'status'      => $filter_status,
	'priority'    => $filter_priority,
	'target_type' => $filter_target_type,
	'search'      => $search,
	'page'        => $current_page,
	'per_page'    => 20,
] );
$tasks      = $result['tasks'];
$total      = $result['total'];
$total_pages = (int) ceil( $total / 20 );

// ── Allowed value sets ──────────────────────────────────────────────────────
$allowed_statuses      = Toptour_Ref_Collection_Tasks::get_allowed_statuses();
$allowed_priorities    = Toptour_Ref_Collection_Tasks::get_allowed_priorities();
$allowed_target_types  = Toptour_Ref_Collection_Tasks::get_allowed_target_types();
$allowed_source_types  = Toptour_Ref_Collection_Tasks::get_allowed_source_types();

// ── Helper: build filter URL ────────────────────────────────────────────────
function toptour_ct_filter_url( $extra = [] ) {
	global $base_url, $filter_status, $filter_priority, $filter_target_type, $search;
	$params = array_filter( [
		'page'              => 'toptour-references-collection',
		'filter_status'     => $filter_status,
		'filter_priority'   => $filter_priority,
		'filter_target_type'=> $filter_target_type,
		's'                 => $search,
	] );
	return esc_url( add_query_arg( array_merge( $params, $extra ), admin_url( 'admin.php' ) ) );
}
?>

<div class="wrap toptour-ref-collection-tasks">
	<h1><?php esc_html_e( 'Zber referencií', 'toptour-reference-finder' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Interný pracovný zásobník dopytov pre ručný zber referencií. Táto obrazovka nevykonáva scraping ani automatické spracovanie.', 'toptour-reference-finder' ); ?></p>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $action === 'add' || $action === 'edit' ) : ?>
		<?php
		// Use prefilled data from a failed save attempt if present, else load from DB or defaults.
		$f = $edit_task;
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ct_submit'] ) ) {
			// Repopulate from POST on validation failure.
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
					<th scope="row"><label for="target_type"><?php esc_html_e( 'Typ cieľa', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="target_type" name="target_type">
							<?php foreach ( $allowed_target_types as $tt ) : ?>
								<option value="<?php echo esc_attr( $tt ); ?>" <?php selected( $f->target_type ?? 'general', $tt ); ?>><?php echo esc_html( $tt ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="target_id"><?php esc_html_e( 'ID cieľa', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" id="target_id" name="target_id" min="0" value="<?php echo esc_attr( $f->target_id ?? 0 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="query_text"><?php esc_html_e( 'Text dopytu', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="query_text" name="query_text" rows="4" class="large-text"><?php echo esc_textarea( $f->query_text ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="source_hint"><?php esc_html_e( 'Nápoveda k zdroju', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="source_hint" name="source_hint" rows="2" class="large-text"><?php echo esc_textarea( $f->source_hint ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="expected_source_type"><?php esc_html_e( 'Očakávaný typ zdroja', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="expected_source_type" name="expected_source_type">
							<?php foreach ( $allowed_source_types as $st ) : ?>
								<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $f->expected_source_type ?? '', $st ); ?>><?php echo esc_html( $st === '' ? '— žiadny —' : $st ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="task_status"><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="task_status" name="task_status">
							<?php foreach ( $allowed_statuses as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $f->task_status ?? 'pending', $s ); ?>><?php echo esc_html( $s ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="priority"><?php esc_html_e( 'Priorita', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="priority" name="priority">
							<?php foreach ( $allowed_priorities as $p ) : ?>
								<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $f->priority ?? 'normal', $p ); ?>><?php echo esc_html( $p ); ?></option>
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
					<?php esc_html_e( 'Pokusy:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_task->attempts ); ?></strong> &nbsp;|&nbsp;
					<?php esc_html_e( 'Vytvorené:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_task->created_at ); ?></strong>
				</p>
			<?php endif; ?>

			<?php submit_button( $form_id ? __( 'Uložiť zmeny', 'toptour-reference-finder' ) : __( 'Pridať úlohu', 'toptour-reference-finder' ) ); ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť', 'toptour-reference-finder' ); ?></a>
		</form>

	<?php else : ?>
		<?php // ── LIST VIEW ─────────────────────────────────────────────────────── ?>
		<a href="<?php echo esc_url( add_query_arg( [ 'toptour_action' => 'add' ], $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridať úlohu', 'toptour-reference-finder' ); ?></a>

		<?php // ── Filters & Search ──────────────────────────────────────────────── ?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="toptour-references-collection">
			<div class="toptour-ct-filters" style="margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
				<select name="filter_status">
					<option value=""><?php esc_html_e( '— Stav —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_statuses as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $filter_status, $s ); ?>><?php echo esc_html( $s ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_priority">
					<option value=""><?php esc_html_e( '— Priorita —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_priorities as $p ) : ?>
						<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $filter_priority, $p ); ?>><?php echo esc_html( $p ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_target_type">
					<option value=""><?php esc_html_e( '— Typ cieľa —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_target_types as $tt ) : ?>
						<option value="<?php echo esc_attr( $tt ); ?>" <?php selected( $filter_target_type, $tt ); ?>><?php echo esc_html( $tt ); ?></option>
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
					<th style="width:40px"><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Názov úlohy', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Cieľ', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Očakávaný zdroj', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Priorita', 'toptour-reference-finder' ); ?></th>
					<th style="width:55px"><?php esc_html_e( 'Pokusy', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Posledné spustenie', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Vytvorené', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( $tasks ) : ?>
				<?php foreach ( $tasks as $task ) :
					$target_col = $task->target_type;
					if ( ! empty( $task->target_id ) && (int) $task->target_id > 0 ) {
						$target_col .= ' #' . $task->target_id;
					}
					$edit_url    = add_query_arg( [ 'toptour_action' => 'edit', 'task_id' => $task->id ], $base_url );
					$archive_url = wp_nonce_url(
						add_query_arg( [ 'toptour_action' => 'archive', 'task_id' => $task->id ], $base_url ),
						'toptour_archive_task_' . $task->id
					);
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
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a>
						<?php if ( $task->task_status !== 'archived' ) : ?>
							&nbsp;|&nbsp;
							<a href="<?php echo esc_url( $archive_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Archivovať túto úlohu?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Archivovať', 'toptour-reference-finder' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="10"><?php esc_html_e( 'Žiadne záznamy.', 'toptour-reference-finder' ); ?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>

		<?php // ── Pagination ────────────────────────────────────────────────────── ?>
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
