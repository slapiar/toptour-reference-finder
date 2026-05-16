<?php
/**
 * TOPTOUR Reference Finder – Facilities View
 *
 * Admin screen for internal facility registry.
 * No scoring, no public output, no scraping, no automation.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Capability check.
if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
}

$base_url    = admin_url( 'admin.php?page=toptour-references-facilities' );
$action      = isset( $_GET['toptour_action'] ) ? sanitize_text_field( wp_unslash( $_GET['toptour_action'] ) ) : '';
$edit_id     = isset( $_GET['facility_id'] ) ? absint( $_GET['facility_id'] ) : 0;
$notice      = '';
$notice_type = 'success';

if ( $action === 'edit' && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_edit_facility_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
}

// ── Handle archive action (GET with nonce) ─────────────────────────────────
if ( $action === 'archive' && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_archive_facility_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
	$archived    = Toptour_Ref_Facilities::archive_facility( $edit_id );
	$notice      = $archived
		? __( 'Zariadenie bolo archivované.', 'toptour-reference-finder' )
		: __( 'Archivácia zlyhala.', 'toptour-reference-finder' );
	$notice_type = $archived ? 'success' : 'error';
	$action      = '';
	$edit_id     = 0;
}

// ── Handle save (POST) ──────────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_fac_submit'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_save_facility' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$post_id = absint( $_POST['facility_id'] ?? 0 );
	$data    = Toptour_Ref_Facilities::sanitize_facility_data( wp_unslash( $_POST ) );
	$valid   = Toptour_Ref_Facilities::validate_facility_data( $data );
	$destination_ids = [];
	if ( isset( $_POST['destination_ids'] ) && is_array( $_POST['destination_ids'] ) ) {
		$destination_ids = array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['destination_ids'] ) ) ) ) );
	}
	$primary_destination_id = absint( $_POST['primary_destination_id'] ?? 0 );
	if ( $primary_destination_id > 0 && ! in_array( $primary_destination_id, $destination_ids, true ) ) {
		$primary_destination_id = 0;
	}

	if ( $valid === true ) {
		$saved_facility_id = 0;
		if ( $post_id ) {
			$ok = Toptour_Ref_Facilities::update_facility( $post_id, $data );
			$saved_facility_id = $post_id;
		} else {
			$created_id = Toptour_Ref_Facilities::create_facility( $data );
			$ok = (bool) $created_id;
			$saved_facility_id = (int) $created_id;
		}

		if ( $ok ) {
			$ok = Toptour_Ref_Facility_Destinations::replace_facility_destinations( $saved_facility_id, $destination_ids, $primary_destination_id );
		}

		$notice      = $ok
			? __( 'Zariadenie bolo uložené.', 'toptour-reference-finder' )
			: __( 'Zariadenie sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
		if ( $ok ) {
			$action  = '';
			$edit_id = 0;
		} else {
			$action = $post_id ? 'edit' : 'add';
		}
	} else {
		$notice      = __( 'Zariadenie sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = 'error';
		$action      = $post_id ? 'edit' : 'add';
		$edit_id     = $post_id;
	}
}

// ── Resolve facility for edit form ──────────────────────────────────────────
$edit_facility = null;
if ( in_array( $action, [ 'edit', 'add' ], true ) && $edit_id ) {
	$edit_facility = Toptour_Ref_Facilities::get_facility( $edit_id );
}

// ── Filter & search args from GET ──────────────────────────────────────────
$filter_type    = isset( $_GET['filter_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_type'] ) ) : '';
$filter_country = isset( $_GET['filter_country'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_country'] ) ) : '';
$filter_region  = isset( $_GET['filter_region'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_region'] ) ) : '';
$filter_status  = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
$search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result      = Toptour_Ref_Facilities::get_facilities( [
	'facility_type' => $filter_type,
	'country'       => $filter_country,
	'region'        => $filter_region,
	'status'        => $filter_status,
	'search'        => $search,
	'page'          => $current_page,
	'per_page'      => 20,
] );
$facilities  = $result['facilities'];
$total       = $result['total'];
$total_pages = (int) ceil( $total / 20 );

$allowed_types    = Toptour_Ref_Facilities::get_allowed_types();
$allowed_statuses = Toptour_Ref_Facilities::get_allowed_statuses();
$available_destinations = Toptour_Ref_Destinations::get_active_destinations_for_assignment();

$assigned_destination_ids = [];
$assigned_primary_destination_id = 0;
if ( $edit_facility && $edit_id > 0 ) {
	$assigned_rows = Toptour_Ref_Facility_Destinations::get_destinations_for_facility( $edit_id );
	foreach ( $assigned_rows as $assigned_row ) {
		$assigned_destination_ids[] = (int) $assigned_row->destination_id;
		if ( (int) $assigned_row->is_primary === 1 ) {
			$assigned_primary_destination_id = (int) $assigned_row->destination_id;
		}
	}
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_fac_submit'] ) ) {
	if ( isset( $_POST['destination_ids'] ) && is_array( $_POST['destination_ids'] ) ) {
		$assigned_destination_ids = array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['destination_ids'] ) ) ) ) );
	} else {
		$assigned_destination_ids = [];
	}
	$assigned_primary_destination_id = absint( $_POST['primary_destination_id'] ?? 0 );
}

$facility_ids = array_map( 'absint', wp_list_pluck( $facilities, 'id' ) );
$destination_labels_by_facility = Toptour_Ref_Facility_Destinations::get_destination_labels_for_facilities( $facility_ids );
?>

<div class="wrap toptour-ref-facilities">
	<h1><?php esc_html_e( 'Zariadenia', 'toptour-reference-finder' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Interná evidencia zariadení, ku ktorým sa budú zbierať referencie, zdroje, zistenia a fotodôkazy.', 'toptour-reference-finder' ); ?></p>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $action === 'add' || $action === 'edit' ) : ?>
		<?php
		$f       = $edit_facility;
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_fac_submit'] ) ) {
			$f = (object) Toptour_Ref_Facilities::sanitize_facility_data( wp_unslash( $_POST ) );
		}
		$form_id = $edit_id;
		?>
		<h2><?php echo $form_id ? esc_html__( 'Upraviť zariadenie', 'toptour-reference-finder' ) : esc_html__( 'Pridať zariadenie', 'toptour-reference-finder' ); ?></h2>
		<form method="post" action="<?php echo esc_url( $base_url ); ?>">
			<?php wp_nonce_field( 'toptour_save_facility' ); ?>
			<input type="hidden" name="toptour_fac_submit" value="1">
			<input type="hidden" name="facility_id" value="<?php echo esc_attr( $form_id ); ?>">

			<table class="form-table">
				<tr>
					<th scope="row"><label for="fac_name"><?php esc_html_e( 'Názov', 'toptour-reference-finder' ); ?> <span class="required">*</span></label></th>
					<td><input type="text" id="fac_name" name="name" class="regular-text" maxlength="255" required value="<?php echo esc_attr( $f->name ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="fac_slug"><?php esc_html_e( 'Slug', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<input type="text" id="fac_slug" name="slug" class="regular-text" maxlength="200" value="<?php echo esc_attr( $f->slug ?? '' ); ?>">
						<p class="description"><?php esc_html_e( 'Ak necháš prázdne, slug sa vygeneruje z názvu.', 'toptour-reference-finder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fac_type"><?php esc_html_e( 'Typ zariadenia', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="fac_type" name="facility_type">
							<?php foreach ( $allowed_types as $t ) : ?>
								<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $f->facility_type ?? '', $t ); ?>><?php echo esc_html( '' === $t ? '— žiadny —' : Toptour_Ref_Labels::facility_type_label( $t ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fac_country"><?php esc_html_e( 'Krajina', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="fac_country" name="country" class="regular-text" maxlength="100" value="<?php echo esc_attr( $f->country ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="fac_region"><?php esc_html_e( 'Región', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="fac_region" name="region" class="regular-text" maxlength="150" value="<?php echo esc_attr( $f->region ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="fac_city"><?php esc_html_e( 'Mesto', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="fac_city" name="city" class="regular-text" maxlength="150" value="<?php echo esc_attr( $f->city ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="fac_address"><?php esc_html_e( 'Adresa', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="fac_address" name="address" rows="2" class="large-text"><?php echo esc_textarea( $f->address ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="fac_website"><?php esc_html_e( 'Web zariadenia', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="url" id="fac_website" name="website_url" class="large-text" value="<?php echo esc_attr( $f->website_url ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="fac_official"><?php esc_html_e( 'Oficiálny zdrojový odkaz', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="url" id="fac_official" name="official_source_url" class="large-text" value="<?php echo esc_attr( $f->official_source_url ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="fac_status"><?php esc_html_e( 'Status', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="fac_status" name="status">
							<?php foreach ( $allowed_statuses as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $f->status ?? 'draft', $s ); ?>><?php echo esc_html( Toptour_Ref_Labels::status_label( $s ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fac_notes"><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="fac_notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $f->notes ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="destination_ids"><?php esc_html_e( 'Priradené destinácie', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="destination_ids" name="destination_ids[]" multiple size="8" style="min-width: 320px;">
							<?php foreach ( $available_destinations as $destination_option ) :
								$destination_label = $destination_option->name;
								if ( $destination_option->country || $destination_option->region ) {
									$destination_label .= ' - ' . trim( $destination_option->country . ' / ' . $destination_option->region, ' /' );
								}
							?>
								<option value="<?php echo esc_attr( $destination_option->id ); ?>" <?php selected( in_array( (int) $destination_option->id, $assigned_destination_ids, true ), true ); ?>>
									<?php echo esc_html( $destination_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Môžeš vybrať jednu alebo viac destinácií.', 'toptour-reference-finder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="primary_destination_id"><?php esc_html_e( 'Primárna destinácia', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="primary_destination_id" name="primary_destination_id">
							<option value="0"><?php esc_html_e( '- žiadna -', 'toptour-reference-finder' ); ?></option>
							<?php foreach ( $available_destinations as $destination_option ) : ?>
								<option value="<?php echo esc_attr( $destination_option->id ); ?>" <?php selected( $assigned_primary_destination_id, (int) $destination_option->id ); ?>>
										<?php echo esc_html( $destination_option->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Primárna destinácia sa uloží iba ak je medzi vybranými destináciami.', 'toptour-reference-finder' ); ?></p>
					</td>
				</tr>
			</table>

			<?php if ( $form_id && $edit_facility ) : ?>
				<p class="description">
					<?php esc_html_e( 'Vytvorené:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_facility->created_at ); ?></strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Aktualizované:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_facility->updated_at ); ?></strong>
				</p>
			<?php endif; ?>

			<?php submit_button( $form_id ? __( 'Uložiť zmeny', 'toptour-reference-finder' ) : __( 'Pridať zariadenie', 'toptour-reference-finder' ) ); ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť', 'toptour-reference-finder' ); ?></a>
		</form>

	<?php else : ?>

		<a href="<?php echo esc_url( add_query_arg( 'toptour_action', 'add', $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridať zariadenie', 'toptour-reference-finder' ); ?></a>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="toptour-references-facilities">
			<div class="toptour-fac-filters" style="margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
				<select name="filter_type">
					<option value=""><?php esc_html_e( '— Typ —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_types as $t ) : if ( $t === '' ) continue; ?>
						<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $filter_type, $t ); ?>><?php echo esc_html( Toptour_Ref_Labels::facility_type_label( $t ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_status">
					<option value=""><?php esc_html_e( '— Status —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_statuses as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $filter_status, $s ); ?>><?php echo esc_html( Toptour_Ref_Labels::status_label( $s ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="filter_country" value="<?php echo esc_attr( $filter_country ); ?>" placeholder="<?php esc_attr_e( 'Krajina', 'toptour-reference-finder' ); ?>" style="width:120px">
				<input type="text" name="filter_region" value="<?php echo esc_attr( $filter_region ); ?>" placeholder="<?php esc_attr_e( 'Región', 'toptour-reference-finder' ); ?>" style="width:120px">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Hľadať...', 'toptour-reference-finder' ); ?>">
				<?php submit_button( __( 'Filtrovať', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
				<?php if ( $filter_type || $filter_status || $filter_country || $filter_region || $search ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť filtre', 'toptour-reference-finder' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<p><?php printf( esc_html__( 'Celkom záznamov: %d', 'toptour-reference-finder' ), $total ); ?></p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:40px"><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Názov', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Destinácie', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Typ', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Krajina', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Región', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Mesto', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Status', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Vytvorené', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( $facilities ) : ?>
				<?php foreach ( $facilities as $fac ) :
					$edit_url    = wp_nonce_url(
						add_query_arg( [ 'toptour_action' => 'edit', 'facility_id' => $fac->id ], $base_url ),
						'toptour_edit_facility_' . $fac->id
					);
					$archive_url = wp_nonce_url(
						add_query_arg( [ 'toptour_action' => 'archive', 'facility_id' => $fac->id ], $base_url ),
						'toptour_archive_facility_' . $fac->id
					);
					$destination_labels = isset( $destination_labels_by_facility[ (int) $fac->id ] ) ? $destination_labels_by_facility[ (int) $fac->id ] : [];
				?>
				<tr>
					<td><?php echo esc_html( $fac->id ); ?></td>
					<td><?php echo esc_html( $fac->name ); ?></td>
					<td><?php echo esc_html( $destination_labels ? implode( ', ', $destination_labels ) : '—' ); ?></td>
					<td><?php echo esc_html( Toptour_Ref_Labels::facility_type_label( $fac->facility_type ) ); ?></td>
					<td><?php echo esc_html( $fac->country ); ?></td>
					<td><?php echo esc_html( $fac->region ); ?></td>
					<td><?php echo esc_html( $fac->city ); ?></td>
					<td><?php echo esc_html( Toptour_Ref_Labels::status_label( $fac->status ) ); ?></td>
					<td><?php echo esc_html( $fac->created_at ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a>
						<?php if ( $fac->status !== 'archived' ) : ?>
							&nbsp;|&nbsp;
							<a href="<?php echo esc_url( $archive_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Archivovať toto zariadenie?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Archivovať', 'toptour-reference-finder' ); ?></a>
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

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					$base_filter = add_query_arg( array_filter( [
						'page'           => 'toptour-references-facilities',
						'filter_type'    => $filter_type,
						'filter_status'  => $filter_status,
						'filter_country' => $filter_country,
						'filter_region'  => $filter_region,
						's'              => $search,
					] ), admin_url( 'admin.php' ) );
					for ( $p = 1; $p <= $total_pages; $p++ ) :
						if ( $p === $current_page ) :
					?>
						<span class="current"><?php echo esc_html( $p ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( 'paged', $p, $base_filter ) ); ?>"><?php echo esc_html( $p ); ?></a>
					<?php
						endif;
					endfor;
					?>
				</div>
			</div>
		<?php endif; ?>

	<?php endif; ?>
</div>
