<?php
/**
 * TOPTOUR Reference Finder - Points of Interest View
 *
 * Internal admin screen for field points of interest.
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

$base_url    = admin_url( 'admin.php?page=toptour-references-points-of-interest' );
$action      = isset( $_GET['toptour_action'] ) ? sanitize_text_field( wp_unslash( $_GET['toptour_action'] ) ) : '';
$edit_id     = isset( $_GET['poi_id'] ) ? absint( $_GET['poi_id'] ) : 0;
$notice      = '';
$notice_type = 'success';

if ( $action === 'edit' && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_edit_poi_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
}

if ( $action === 'archive' && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_archive_poi_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
	$archived    = Toptour_Ref_Points_Of_Interest::archive_point( $edit_id );
	$notice      = $archived ? __( 'Bod záujmu bol archivovaný.', 'toptour-reference-finder' ) : __( 'Archivácia zlyhala.', 'toptour-reference-finder' );
	$notice_type = $archived ? 'success' : 'error';
	$action      = '';
	$edit_id     = 0;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_poi_submit'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_save_poi' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$post_id = absint( $_POST['poi_id'] ?? 0 );
	$data    = Toptour_Ref_Points_Of_Interest::sanitize_point_data( wp_unslash( $_POST ) );
	$valid   = Toptour_Ref_Points_Of_Interest::validate_point_data( $data );

	if ( $valid === true ) {
		if ( $post_id ) {
			$ok = Toptour_Ref_Points_Of_Interest::update_point( $post_id, $data );
		} else {
			$created_id = Toptour_Ref_Points_Of_Interest::create_point( $data );
			$ok = (bool) $created_id;
		}

		$notice      = $ok ? __( 'Bod záujmu bol uložený.', 'toptour-reference-finder' ) : __( 'Bod záujmu sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
		if ( $ok ) {
			$action  = '';
			$edit_id = 0;
		} else {
			$action = $post_id ? 'edit' : 'add';
			$edit_id = $post_id;
		}
	} else {
		$notice      = __( 'Bod záujmu sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = 'error';
		$action      = $post_id ? 'edit' : 'add';
		$edit_id     = $post_id;
	}
}

$edit_point = null;
if ( in_array( $action, [ 'edit', 'add' ], true ) && $edit_id ) {
	$edit_point = Toptour_Ref_Points_Of_Interest::get_point( $edit_id );
}

$filter_type       = isset( $_GET['filter_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_type'] ) ) : '';
$filter_destination = isset( $_GET['filter_destination_id'] ) ? absint( $_GET['filter_destination_id'] ) : 0;
$filter_facility   = isset( $_GET['filter_facility_id'] ) ? absint( $_GET['filter_facility_id'] ) : 0;
$filter_country    = isset( $_GET['filter_country'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_country'] ) ) : '';
$filter_region     = isset( $_GET['filter_region'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_region'] ) ) : '';
$filter_status     = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
$search            = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result = Toptour_Ref_Points_Of_Interest::get_points(
	[
		'poi_type'       => $filter_type,
		'destination_id' => $filter_destination,
		'facility_id'    => $filter_facility,
		'country'        => $filter_country,
		'region'         => $filter_region,
		'status'         => $filter_status,
		'search'         => $search,
		'page'           => $current_page,
		'per_page'       => 20,
	]
);

$points      = $result['points'];
$total       = $result['total'];
$total_pages = (int) ceil( $total / 20 );

$allowed_types    = Toptour_Ref_Points_Of_Interest::get_allowed_types();
$allowed_statuses = Toptour_Ref_Points_Of_Interest::get_allowed_statuses();
$destination_options = Toptour_Ref_Points_Of_Interest::get_destination_options();
$facility_options    = Toptour_Ref_Points_Of_Interest::get_facility_options();

$destination_map = [];
foreach ( $destination_options as $destination_option ) {
	$destination_map[ (int) $destination_option->id ] = $destination_option;
}

$facility_map = [];
foreach ( $facility_options as $facility_option ) {
	$facility_map[ (int) $facility_option->id ] = $facility_option;
}
?>

<div class="wrap toptour-ref-poi">
	<h1><?php esc_html_e( 'Body záujmu', 'toptour-reference-finder' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Interná evidencia konkrétnych miest v teréne pre budúci zber referencií, zistení a fotodôkazov.', 'toptour-reference-finder' ); ?></p>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $action === 'add' || $action === 'edit' ) : ?>
		<?php
		$p = $edit_point;
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_poi_submit'] ) ) {
			$p = (object) Toptour_Ref_Points_Of_Interest::sanitize_point_data( wp_unslash( $_POST ) );
		}
		$form_id = $edit_id;
		$selected_destination_id = absint( $p->destination_id ?? 0 );
		$selected_facility_id = absint( $p->facility_id ?? 0 );
		?>
		<h2><?php echo $form_id ? esc_html__( 'Upraviť bod záujmu', 'toptour-reference-finder' ) : esc_html__( 'Pridať bod záujmu', 'toptour-reference-finder' ); ?></h2>
		<form method="post" action="<?php echo esc_url( $base_url ); ?>">
			<?php wp_nonce_field( 'toptour_save_poi' ); ?>
			<input type="hidden" name="toptour_poi_submit" value="1">
			<input type="hidden" name="poi_id" value="<?php echo esc_attr( $form_id ); ?>">

			<table class="form-table">
				<tr>
					<th scope="row"><label for="poi_name"><?php esc_html_e( 'Názov', 'toptour-reference-finder' ); ?> <span class="required">*</span></label></th>
					<td><input type="text" id="poi_name" name="name" class="regular-text" maxlength="255" required value="<?php echo esc_attr( $p->name ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_slug"><?php esc_html_e( 'Slug', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<input type="text" id="poi_slug" name="slug" class="regular-text" maxlength="200" value="<?php echo esc_attr( $p->slug ?? '' ); ?>">
						<p class="description"><?php esc_html_e( 'Ak necháš prázdne, slug sa vygeneruje z názvu.', 'toptour-reference-finder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_type"><?php esc_html_e( 'Typ bodu', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="poi_type" name="poi_type">
							<?php foreach ( $allowed_types as $poi_type ) : ?>
								<option value="<?php echo esc_attr( $poi_type ); ?>" <?php selected( $p->poi_type ?? 'other', $poi_type ); ?>><?php echo esc_html( Toptour_Ref_Labels::poi_type_label( $poi_type ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_destination"><?php esc_html_e( 'Destinácia', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="poi_destination" name="destination_id">
							<option value="0"><?php esc_html_e( '—', 'toptour-reference-finder' ); ?></option>
							<?php foreach ( $destination_options as $destination_option ) :
								$destination_label = $destination_option->name;
								if ( $destination_option->country || $destination_option->region ) {
									$destination_label .= ' - ' . trim( $destination_option->country . ' / ' . $destination_option->region, ' /' );
								}
							?>
								<option value="<?php echo esc_attr( $destination_option->id ); ?>" <?php selected( $selected_destination_id, (int) $destination_option->id ); ?>><?php echo esc_html( $destination_label ); ?></option>
							<?php endforeach; ?>
							<?php if ( $selected_destination_id > 0 && ! isset( $destination_map[ $selected_destination_id ] ) ) : ?>
								<option value="<?php echo esc_attr( $selected_destination_id ); ?>" selected><?php echo esc_html( 'destination#' . $selected_destination_id ); ?></option>
							<?php endif; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_facility"><?php esc_html_e( 'Zariadenie', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="poi_facility" name="facility_id">
							<option value="0"><?php esc_html_e( '—', 'toptour-reference-finder' ); ?></option>
							<?php foreach ( $facility_options as $facility_option ) :
								$facility_label = $facility_option->name;
								if ( $facility_option->city || $facility_option->country ) {
									$facility_label .= ' - ' . trim( $facility_option->city . ' / ' . $facility_option->country, ' /' );
								}
							?>
								<option value="<?php echo esc_attr( $facility_option->id ); ?>" <?php selected( $selected_facility_id, (int) $facility_option->id ); ?>><?php echo esc_html( $facility_label ); ?></option>
							<?php endforeach; ?>
							<?php if ( $selected_facility_id > 0 && ! isset( $facility_map[ $selected_facility_id ] ) ) : ?>
								<option value="<?php echo esc_attr( $selected_facility_id ); ?>" selected><?php echo esc_html( 'facility#' . $selected_facility_id ); ?></option>
							<?php endif; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_country"><?php esc_html_e( 'Krajina', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="poi_country" name="country" class="regular-text" maxlength="100" value="<?php echo esc_attr( $p->country ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_region"><?php esc_html_e( 'Región', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="poi_region" name="region" class="regular-text" maxlength="150" value="<?php echo esc_attr( $p->region ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_city"><?php esc_html_e( 'Mesto', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="poi_city" name="city" class="regular-text" maxlength="150" value="<?php echo esc_attr( $p->city ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_address"><?php esc_html_e( 'Adresa', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="poi_address" name="address" rows="2" class="large-text"><?php echo esc_textarea( $p->address ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_latitude"><?php esc_html_e( 'Latitude', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="poi_latitude" name="latitude" class="regular-text" value="<?php echo esc_attr( $p->latitude ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_longitude"><?php esc_html_e( 'Longitude', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="poi_longitude" name="longitude" class="regular-text" value="<?php echo esc_attr( $p->longitude ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_description"><?php esc_html_e( 'Popis', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="poi_description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $p->description ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_status"><?php esc_html_e( 'Status', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="poi_status" name="status">
							<?php foreach ( $allowed_statuses as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $p->status ?? 'draft', $status ); ?>><?php echo esc_html( Toptour_Ref_Labels::status_label( $status ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="poi_notes"><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="poi_notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $p->notes ?? '' ); ?></textarea></td>
				</tr>
			</table>

			<?php if ( $form_id && $edit_point ) : ?>
				<p class="description">
					<?php esc_html_e( 'Vytvorené:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_point->created_at ); ?></strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Aktualizované:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_point->updated_at ); ?></strong>
				</p>
			<?php endif; ?>

			<?php submit_button( $form_id ? __( 'Uložiť zmeny', 'toptour-reference-finder' ) : __( 'Pridať bod záujmu', 'toptour-reference-finder' ) ); ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť', 'toptour-reference-finder' ); ?></a>
		</form>
	<?php else : ?>
		<a href="<?php echo esc_url( add_query_arg( 'toptour_action', 'add', $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridať bod záujmu', 'toptour-reference-finder' ); ?></a>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="toptour-references-points-of-interest">
			<div style="margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
				<select name="filter_type">
					<option value=""><?php esc_html_e( '— Typ —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_types as $poi_type ) : ?>
						<option value="<?php echo esc_attr( $poi_type ); ?>" <?php selected( $filter_type, $poi_type ); ?>><?php echo esc_html( Toptour_Ref_Labels::poi_type_label( $poi_type ) ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_destination_id">
					<option value="0"><?php esc_html_e( '— Destinácia —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $destination_options as $destination_option ) : ?>
						<option value="<?php echo esc_attr( $destination_option->id ); ?>" <?php selected( $filter_destination, (int) $destination_option->id ); ?>><?php echo esc_html( $destination_option->name ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_facility_id">
					<option value="0"><?php esc_html_e( '— Zariadenie —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $facility_options as $facility_option ) : ?>
						<option value="<?php echo esc_attr( $facility_option->id ); ?>" <?php selected( $filter_facility, (int) $facility_option->id ); ?>><?php echo esc_html( $facility_option->name ); ?></option>
					<?php endforeach; ?>
				</select>

				<input type="text" name="filter_country" value="<?php echo esc_attr( $filter_country ); ?>" placeholder="<?php esc_attr_e( 'Krajina', 'toptour-reference-finder' ); ?>" style="width:120px">
				<input type="text" name="filter_region" value="<?php echo esc_attr( $filter_region ); ?>" placeholder="<?php esc_attr_e( 'Región', 'toptour-reference-finder' ); ?>" style="width:120px">

				<select name="filter_status">
					<option value=""><?php esc_html_e( '— Status —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_statuses as $status ) : ?>
						<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filter_status, $status ); ?>><?php echo esc_html( Toptour_Ref_Labels::status_label( $status ) ); ?></option>
					<?php endforeach; ?>
				</select>

				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Hľadať...', 'toptour-reference-finder' ); ?>">
				<?php submit_button( __( 'Filtrovať', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
				<?php if ( $filter_type || $filter_destination || $filter_facility || $filter_country || $filter_region || $filter_status || $search ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť filtre', 'toptour-reference-finder' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<p><?php printf( esc_html__( 'Celkom záznamov: %d', 'toptour-reference-finder' ), $total ); ?></p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 50px;"><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Názov', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Typ', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Destinácia', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Zariadenie', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Lokalita', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'GPS', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Status', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Vytvorené', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $points ) : ?>
					<?php foreach ( $points as $point ) :
						$edit_url = wp_nonce_url(
							add_query_arg(
								[
									'toptour_action' => 'edit',
									'poi_id'        => $point->id,
								],
								$base_url
							),
							'toptour_edit_poi_' . $point->id
						);
						$archive_url = wp_nonce_url(
							add_query_arg(
								[
									'toptour_action' => 'archive',
									'poi_id'        => $point->id,
								],
								$base_url
							),
							'toptour_archive_poi_' . $point->id
						);

						$destination_id = absint( $point->destination_id );
						if ( $destination_id > 0 ) {
							$destination_label = isset( $destination_map[ $destination_id ] ) ? $destination_map[ $destination_id ]->name : 'destination#' . $destination_id;
						} else {
							$destination_label = '—';
						}

						$facility_id = absint( $point->facility_id );
						if ( $facility_id > 0 ) {
							$facility_label = isset( $facility_map[ $facility_id ] ) ? $facility_map[ $facility_id ]->name : 'facility#' . $facility_id;
						} else {
							$facility_label = '—';
						}

						$location_parts = [];
						if ( ! empty( $point->country ) ) {
							$location_parts[] = $point->country;
						}
						if ( ! empty( $point->region ) ) {
							$location_parts[] = $point->region;
						}
						if ( ! empty( $point->city ) ) {
							$location_parts[] = $point->city;
						}
						$location_label = $location_parts ? implode( ' / ', $location_parts ) : '—';

						$has_lat = $point->latitude !== null && $point->latitude !== '';
						$has_lon = $point->longitude !== null && $point->longitude !== '';
						$gps_label = ( $has_lat && $has_lon ) ? $point->latitude . ', ' . $point->longitude : '—';
					?>
						<tr>
							<td><?php echo esc_html( $point->id ); ?></td>
							<td><?php echo esc_html( $point->name ); ?></td>
							<td><?php echo esc_html( Toptour_Ref_Labels::poi_type_label( $point->poi_type ) ); ?></td>
							<td><?php echo esc_html( $destination_label ); ?></td>
							<td><?php echo esc_html( $facility_label ); ?></td>
							<td><?php echo esc_html( $location_label ); ?></td>
							<td><?php echo esc_html( $gps_label ); ?></td>
							<td><?php echo esc_html( Toptour_Ref_Labels::status_label( $point->status ) ); ?></td>
							<td><?php echo esc_html( $point->created_at ); ?></td>
							<td>
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a>
								<?php if ( $point->status !== 'archived' ) : ?>
									&nbsp;|&nbsp;
									<a href="<?php echo esc_url( $archive_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Archivovať tento bod záujmu?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Archivovať', 'toptour-reference-finder' ); ?></a>
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
								'page'                  => 'toptour-references-points-of-interest',
								'filter_type'           => $filter_type,
								'filter_destination_id' => $filter_destination,
								'filter_facility_id'    => $filter_facility,
								'filter_country'        => $filter_country,
								'filter_region'         => $filter_region,
								'filter_status'         => $filter_status,
								's'                     => $search,
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
