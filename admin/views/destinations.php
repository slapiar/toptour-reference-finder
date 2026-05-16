<?php
/**
 * TOPTOUR Reference Finder - Destinations View
 *
 * Internal admin screen for destination registry.
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

$base_url    = admin_url( 'admin.php?page=toptour-references-destinations' );
$action      = isset( $_GET['toptour_action'] ) ? sanitize_text_field( wp_unslash( $_GET['toptour_action'] ) ) : '';
$edit_id     = isset( $_GET['destination_id'] ) ? absint( $_GET['destination_id'] ) : 0;
$notice      = '';
$notice_type = 'success';

if ( $action === 'edit' && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_edit_destination_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
}

if ( $action === 'archive' && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_archive_destination_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
	$archived    = Toptour_Ref_Destinations::archive_destination( $edit_id );
	$notice      = $archived ? __( 'Destinácia bola archivovaná.', 'toptour-reference-finder' ) : __( 'Archivácia zlyhala.', 'toptour-reference-finder' );
	$notice_type = $archived ? 'success' : 'error';
	$action      = '';
	$edit_id     = 0;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_dest_submit'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_save_destination' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$post_id = absint( $_POST['destination_id'] ?? 0 );
	$data    = Toptour_Ref_Destinations::sanitize_destination_data( wp_unslash( $_POST ) );
	$valid   = Toptour_Ref_Destinations::validate_destination_data( $data );

	if ( $valid === true ) {
		$ok = $post_id ? Toptour_Ref_Destinations::update_destination( $post_id, $data ) : Toptour_Ref_Destinations::create_destination( $data );
		$notice      = $ok ? __( 'Destinácia bola uložená.', 'toptour-reference-finder' ) : __( 'Destináciu sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
		if ( $ok ) {
			$action  = '';
			$edit_id = 0;
		} else {
			$action = $post_id ? 'edit' : 'add';
			$edit_id = $post_id;
		}
	} else {
		$notice      = __( 'Destináciu sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = 'error';
		$action      = $post_id ? 'edit' : 'add';
		$edit_id     = $post_id;
	}
}

$edit_destination = null;
if ( in_array( $action, [ 'edit', 'add' ], true ) && $edit_id ) {
	$edit_destination = Toptour_Ref_Destinations::get_destination( $edit_id );
}

$filter_country = isset( $_GET['filter_country'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_country'] ) ) : '';
$filter_region  = isset( $_GET['filter_region'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_region'] ) ) : '';
$filter_type    = isset( $_GET['filter_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_type'] ) ) : '';
$filter_status  = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
$search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result = Toptour_Ref_Destinations::get_destinations(
	[
		'country'          => $filter_country,
		'region'           => $filter_region,
		'destination_type' => $filter_type,
		'status'           => $filter_status,
		'search'           => $search,
		'page'             => $current_page,
		'per_page'         => 20,
	]
);
$destinations = $result['destinations'];
$total = $result['total'];
$total_pages = (int) ceil( $total / 20 );

$allowed_types = Toptour_Ref_Destinations::get_allowed_types();
$allowed_statuses = Toptour_Ref_Destinations::get_allowed_statuses();
?>

<div class="wrap toptour-ref-destinations">
	<h1><?php esc_html_e( 'Destinácie', 'toptour-reference-finder' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Interná evidencia destinácií, ku ktorým sa budú zbierať referencie a pripájať zariadenia.', 'toptour-reference-finder' ); ?></p>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $action === 'add' || $action === 'edit' ) : ?>
		<?php
		$d = $edit_destination;
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_dest_submit'] ) ) {
			$d = (object) Toptour_Ref_Destinations::sanitize_destination_data( wp_unslash( $_POST ) );
		}
		$form_id = $edit_id;
		?>
		<h2><?php echo $form_id ? esc_html__( 'Upraviť destináciu', 'toptour-reference-finder' ) : esc_html__( 'Pridať destináciu', 'toptour-reference-finder' ); ?></h2>
		<form method="post" action="<?php echo esc_url( $base_url ); ?>">
			<?php wp_nonce_field( 'toptour_save_destination' ); ?>
			<input type="hidden" name="toptour_dest_submit" value="1">
			<input type="hidden" name="destination_id" value="<?php echo esc_attr( $form_id ); ?>">

			<table class="form-table">
				<tr>
					<th scope="row"><label for="dest_name"><?php esc_html_e( 'Názov', 'toptour-reference-finder' ); ?> *</label></th>
					<td><input type="text" id="dest_name" name="name" maxlength="255" required class="regular-text" value="<?php echo esc_attr( $d->name ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="dest_slug"><?php esc_html_e( 'Slug', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<input type="text" id="dest_slug" name="slug" maxlength="200" class="regular-text" value="<?php echo esc_attr( $d->slug ?? '' ); ?>">
						<p class="description"><?php esc_html_e( 'Ak necháš prázdne, slug sa vygeneruje z názvu.', 'toptour-reference-finder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dest_country"><?php esc_html_e( 'Krajina', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="dest_country" name="country" maxlength="100" class="regular-text" value="<?php echo esc_attr( $d->country ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="dest_region"><?php esc_html_e( 'Región', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="dest_region" name="region" maxlength="150" class="regular-text" value="<?php echo esc_attr( $d->region ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="dest_type"><?php esc_html_e( 'Typ destinácie', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="dest_type" name="destination_type">
							<?php foreach ( $allowed_types as $type ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $d->destination_type ?? '', $type ); ?>><?php echo esc_html( '' === $type ? '— žiadny —' : Toptour_Ref_Labels::destination_type_label( $type ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dest_seasonality"><?php esc_html_e( 'Sezónnosť', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="dest_seasonality" name="seasonality" maxlength="150" class="regular-text" value="<?php echo esc_attr( $d->seasonality ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="dest_description"><?php esc_html_e( 'Popis', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="dest_description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $d->description ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="dest_notes"><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="dest_notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $d->notes ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="dest_status"><?php esc_html_e( 'Status', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="dest_status" name="status">
							<?php foreach ( $allowed_statuses as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $d->status ?? 'draft', $status ); ?>><?php echo esc_html( Toptour_Ref_Labels::status_label( $status ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<?php if ( $form_id && $edit_destination ) : ?>
				<p class="description">
					<?php esc_html_e( 'Vytvorené:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_destination->created_at ); ?></strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Aktualizované:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_destination->updated_at ); ?></strong>
				</p>
			<?php endif; ?>

			<?php submit_button( $form_id ? __( 'Uložiť zmeny', 'toptour-reference-finder' ) : __( 'Pridať destináciu', 'toptour-reference-finder' ) ); ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť', 'toptour-reference-finder' ); ?></a>
		</form>
	<?php else : ?>
		<a href="<?php echo esc_url( add_query_arg( 'toptour_action', 'add', $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridať destináciu', 'toptour-reference-finder' ); ?></a>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="toptour-references-destinations">
			<div style="margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
				<select name="filter_type">
					<option value=""><?php esc_html_e( '- Typ destinácie -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_types as $type ) : if ( $type === '' ) { continue; } ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filter_type, $type ); ?>><?php echo esc_html( Toptour_Ref_Labels::destination_type_label( $type ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_status">
					<option value=""><?php esc_html_e( '- Status -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_statuses as $status ) : ?>
						<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filter_status, $status ); ?>><?php echo esc_html( Toptour_Ref_Labels::status_label( $status ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="filter_country" value="<?php echo esc_attr( $filter_country ); ?>" placeholder="<?php esc_attr_e( 'Krajina', 'toptour-reference-finder' ); ?>">
				<input type="text" name="filter_region" value="<?php echo esc_attr( $filter_region ); ?>" placeholder="<?php esc_attr_e( 'Región', 'toptour-reference-finder' ); ?>">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Hľadať...', 'toptour-reference-finder' ); ?>">
				<?php submit_button( __( 'Filtrovať', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
				<?php if ( $filter_country || $filter_region || $filter_type || $filter_status || $search ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť filtre', 'toptour-reference-finder' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<p><?php printf( esc_html__( 'Celkom záznamov: %d', 'toptour-reference-finder' ), $total ); ?></p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Názov', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Krajina', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Región', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Typ destinácie', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Sezónnosť', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Status', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Zariadenia', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Vytvorené', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $destinations ) : ?>
					<?php foreach ( $destinations as $destination ) :
						$edit_url = wp_nonce_url(
							add_query_arg(
								[
									'toptour_action'  => 'edit',
									'destination_id' => $destination->id,
								],
								$base_url
							),
							'toptour_edit_destination_' . $destination->id
						);
						$archive_url = wp_nonce_url(
							add_query_arg(
								[
									'toptour_action'  => 'archive',
									'destination_id' => $destination->id,
								],
								$base_url
							),
							'toptour_archive_destination_' . $destination->id
						);
						$count = Toptour_Ref_Facility_Destinations::count_facilities_for_destination( (int) $destination->id );
					?>
					<tr>
						<td><?php echo esc_html( $destination->id ); ?></td>
						<td><?php echo esc_html( $destination->name ); ?></td>
						<td><?php echo esc_html( $destination->country ); ?></td>
						<td><?php echo esc_html( $destination->region ); ?></td>
						<td><?php echo esc_html( Toptour_Ref_Labels::destination_type_label( $destination->destination_type ) ); ?></td>
						<td><?php echo esc_html( $destination->seasonality ); ?></td>
						<td><?php echo esc_html( Toptour_Ref_Labels::status_label( $destination->status ) ); ?></td>
						<td><?php echo esc_html( $count ); ?></td>
						<td><?php echo esc_html( $destination->created_at ); ?></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a>
							<?php if ( $destination->status !== 'archived' ) : ?>
								&nbsp;|&nbsp;
								<a href="<?php echo esc_url( $archive_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Archivovať túto destináciu?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Archivovať', 'toptour-reference-finder' ); ?></a>
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
					$base_filter = add_query_arg(
						array_filter(
							[
								'page'           => 'toptour-references-destinations',
								'filter_country' => $filter_country,
								'filter_region'  => $filter_region,
								'filter_type'    => $filter_type,
								'filter_status'  => $filter_status,
								's'              => $search,
							]
						),
						admin_url( 'admin.php' )
					);
					for ( $p = 1; $p <= $total_pages; $p++ ) :
						if ( $p === $current_page ) :
							?>
							<span class="current"><?php echo esc_html( $p ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $p, $base_filter ) ); ?>"><?php echo esc_html( $p ); ?></a>
						<?php endif; ?>
					<?php endfor; ?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
