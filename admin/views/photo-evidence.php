<?php
/**
 * Admin view: Photo evidence.
 *
 * @package Toptour_Ref
 * @version 0.1.15
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_toptour_references' ) ) {
	wp_die( esc_html__( 'Nemáte oprávnenie na túto stránku.', 'toptour-reference-finder' ) );
}

$message   = '';
$error     = '';
$form_id   = isset( $_GET['evidence_id'] ) ? absint( $_GET['evidence_id'] ) : 0;
$action    = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
$show_form = in_array( $action, [ 'add', 'edit' ], true );

if ( $action === 'edit' && $form_id > 0 ) {
	check_admin_referer( 'toptour_edit_photo_evidence_' . $form_id );
}

if ( $action === 'archive' && $form_id > 0 ) {
	check_admin_referer( 'toptour_archive_photo_evidence_' . $form_id );
	$archived = Toptour_Ref_Photo_Evidence::archive_photo_evidence( $form_id );
	$message  = $archived ? __( 'Fotodôkaz bol archivovaný.', 'toptour-reference-finder' ) : '';
	$error    = ! $archived ? __( 'Archivovanie zlyhalo.', 'toptour-reference-finder' ) : '';
	$show_form = false;
}

if ( isset( $_POST['toptour_photo_evidence_nonce'] ) ) {
	check_admin_referer( 'toptour_save_photo_evidence', 'toptour_photo_evidence_nonce' );

	$data       = Toptour_Ref_Photo_Evidence::sanitize_photo_evidence_data( $_POST );
	$validation = Toptour_Ref_Photo_Evidence::validate_photo_evidence_data( $data );

	if ( $validation !== true ) {
		$error     = implode( ' | ', $validation );
		$show_form = true;
		$form_id   = absint( $_POST['evidence_id'] ?? 0 );
	} else {
		$post_id = absint( $_POST['evidence_id'] ?? 0 );
		if ( $post_id > 0 ) {
			$ok      = Toptour_Ref_Photo_Evidence::update_photo_evidence( $post_id, $data );
			$message = $ok ? __( 'Fotodôkaz bol uložený.', 'toptour-reference-finder' ) : '';
			$error   = ! $ok ? __( 'Ukladanie zlyhalo.', 'toptour-reference-finder' ) : '';
			$form_id = $post_id;
			$show_form = true;
		} else {
			$new_id = Toptour_Ref_Photo_Evidence::create_photo_evidence( $data );
			if ( $new_id ) {
				$edit_url = wp_nonce_url(
					add_query_arg(
						[
							'page'        => 'toptour-references-photo-evidence',
							'action'      => 'edit',
							'evidence_id' => $new_id,
							'saved'       => 1,
						],
						admin_url( 'admin.php' )
					),
					'toptour_edit_photo_evidence_' . $new_id
				);
				wp_safe_redirect( $edit_url );
				exit;
			}
			$error     = __( 'Vytvorenie zlyhalo.', 'toptour-reference-finder' );
			$show_form = true;
		}
	}
}

if ( isset( $_GET['saved'] ) && absint( $_GET['saved'] ) === 1 ) {
	$message = __( 'Fotodôkaz bol vytvorený.', 'toptour-reference-finder' );
}

$record = null;
if ( $show_form && $form_id > 0 ) {
	$record = Toptour_Ref_Photo_Evidence::get_photo_evidence( $form_id );
	if ( ! $record ) {
		$error     = __( 'Fotodôkaz nebol nájdený.', 'toptour-reference-finder' );
		$show_form = false;
	}
}

function toptour_photo_field( $field, $record, $default = '' ) {
	if ( isset( $_POST['toptour_photo_evidence_nonce'] ) ) {
		return sanitize_text_field( (string) ( $_POST[ $field ] ?? $default ) );
	}
	if ( $record && isset( $record->$field ) ) {
		return (string) $record->$field;
	}
	return $default;
}

function toptour_photo_textarea( $field, $record, $default = '' ) {
	if ( isset( $_POST['toptour_photo_evidence_nonce'] ) ) {
		return sanitize_textarea_field( (string) ( $_POST[ $field ] ?? $default ) );
	}
	if ( $record && isset( $record->$field ) ) {
		return (string) $record->$field;
	}
	return $default;
}
?>
<div class="wrap">
	<h1>
		<?php esc_html_e( 'Fotodôkazy', 'toptour-reference-finder' ); ?>
		<?php if ( ! $show_form ) : ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'toptour-references-photo-evidence', 'action' => 'add' ], admin_url( 'admin.php' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridať nové', 'toptour-reference-finder' ); ?></a>
		<?php endif; ?>
	</h1>

	<?php if ( $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<?php if ( $show_form ) : ?>
		<?php
		$evidence_title             = toptour_photo_field( 'evidence_title', $record );
		$source_id                  = toptour_photo_field( 'source_id', $record, '0' );
		$finding_id                 = toptour_photo_field( 'finding_id', $record, '0' );
		$target_type                = toptour_photo_field( 'target_type', $record, 'general' );
		$target_id                  = toptour_photo_field( 'target_id', $record, '0' );
		$photo_type                 = toptour_photo_field( 'photo_type', $record, 'guest_photo' );
		$comparison_category        = toptour_photo_field( 'comparison_category', $record, 'unknown' );
		$visual_area                = toptour_photo_field( 'visual_area', $record, '' );
		$evidence_url               = toptour_photo_field( 'evidence_url', $record );
		$thumbnail_url              = toptour_photo_field( 'thumbnail_url', $record );
		$official_reference_url     = toptour_photo_field( 'official_reference_url', $record );
		$guest_reference_url        = toptour_photo_field( 'guest_reference_url', $record );
		$observation_summary        = toptour_photo_textarea( 'observation_summary', $record );
		$visible_details            = toptour_photo_textarea( 'visible_details', $record );
		$contradiction_note         = toptour_photo_textarea( 'contradiction_note', $record );
		$verification_status        = toptour_photo_field( 'verification_status', $record, 'new' );
		$signal_strength            = toptour_photo_field( 'signal_strength', $record, 'medium' );
		$observed_at                = toptour_photo_field( 'observed_at', $record );
		$language                   = toptour_photo_field( 'language', $record );
		$related_collection_task_id = toptour_photo_field( 'related_collection_task_id', $record, '0' );
		$notes                      = toptour_photo_textarea( 'notes', $record );

		$sources  = Toptour_Ref_Photo_Evidence::get_active_sources_for_select();
		$findings = Toptour_Ref_Photo_Evidence::get_active_findings_for_select();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=toptour-references-photo-evidence' ) ); ?>">
			<?php wp_nonce_field( 'toptour_save_photo_evidence', 'toptour_photo_evidence_nonce' ); ?>
			<input type="hidden" name="evidence_id" value="<?php echo esc_attr( $form_id ); ?>">

			<h2><?php esc_html_e( 'Základ fotodôkazu', 'toptour-reference-finder' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="evidence_title"><?php esc_html_e( 'Názov dôkazu', 'toptour-reference-finder' ); ?> *</label></th>
					<td><input type="text" name="evidence_title" id="evidence_title" class="regular-text" required value="<?php echo esc_attr( $evidence_title ); ?>"></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Zdroj, zistenie a cieľ', 'toptour-reference-finder' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="source_id"><?php esc_html_e( 'Zdroj (source_id)', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select name="source_id" id="source_id">
							<option value="0"><?php esc_html_e( '— bez zdroja —', 'toptour-reference-finder' ); ?></option>
							<?php foreach ( $sources as $src ) : ?>
								<option value="<?php echo esc_attr( $src->id ); ?>" <?php selected( (int) $source_id, (int) $src->id ); ?>><?php echo esc_html( $src->source_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="finding_id"><?php esc_html_e( 'Zistenie (finding_id)', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select name="finding_id" id="finding_id">
							<option value="0"><?php esc_html_e( '— bez zistenia —', 'toptour-reference-finder' ); ?></option>
							<?php foreach ( $findings as $finding ) : ?>
								<option value="<?php echo esc_attr( $finding->id ); ?>" <?php selected( (int) $finding_id, (int) $finding->id ); ?>><?php echo esc_html( $finding->finding_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="target_type"><?php esc_html_e( 'Typ cieľa', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select name="target_type" id="target_type">
							<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_target_types() as $item ) : ?>
								<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $target_type, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::target_type_label( $item ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="target_id"><?php esc_html_e( 'Cieľ ID', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" min="0" class="small-text" name="target_id" id="target_id" value="<?php echo esc_attr( $target_id ); ?>"></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Vizuálna klasifikácia', 'toptour-reference-finder' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="photo_type"><?php esc_html_e( 'Typ fotodôkazu', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select name="photo_type" id="photo_type">
							<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_photo_types() as $item ) : ?>
								<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $photo_type, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::photo_type_label( $item ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="comparison_category"><?php esc_html_e( 'Porovnanie', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select name="comparison_category" id="comparison_category">
							<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_comparison_categories() as $item ) : ?>
								<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $comparison_category, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::comparison_category_label( $item ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="visual_area"><?php esc_html_e( 'Vizuálna oblasť', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select name="visual_area" id="visual_area">
							<option value=""><?php esc_html_e( '— neurčená —', 'toptour-reference-finder' ); ?></option>
							<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_visual_areas() as $item ) : ?>
								<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $visual_area, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::visual_area_label( $item ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="verification_status"><?php esc_html_e( 'Stav overenia', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select name="verification_status" id="verification_status">
							<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_verification_statuses() as $item ) : ?>
								<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $verification_status, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::verification_status_label( $item ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="signal_strength"><?php esc_html_e( 'Sila signálu', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select name="signal_strength" id="signal_strength">
							<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_signal_strengths() as $item ) : ?>
								<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $signal_strength, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::signal_strength_label( $item ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'URL odkazy', 'toptour-reference-finder' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="evidence_url"><?php esc_html_e( 'Evidence URL', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="url" class="regular-text" name="evidence_url" id="evidence_url" value="<?php echo esc_attr( $evidence_url ); ?>"></td>
				</tr>
				<tr>
					<th><label for="thumbnail_url"><?php esc_html_e( 'Thumbnail URL', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="url" class="regular-text" name="thumbnail_url" id="thumbnail_url" value="<?php echo esc_attr( $thumbnail_url ); ?>"></td>
				</tr>
				<tr>
					<th><label for="official_reference_url"><?php esc_html_e( 'Official reference URL', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="url" class="regular-text" name="official_reference_url" id="official_reference_url" value="<?php echo esc_attr( $official_reference_url ); ?>"></td>
				</tr>
				<tr>
					<th><label for="guest_reference_url"><?php esc_html_e( 'Guest reference URL', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="url" class="regular-text" name="guest_reference_url" id="guest_reference_url" value="<?php echo esc_attr( $guest_reference_url ); ?>"></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Pozorovanie a poznámky', 'toptour-reference-finder' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="observation_summary"><?php esc_html_e( 'Zhrnutie pozorovania', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea name="observation_summary" id="observation_summary" rows="3" class="large-text"><?php echo esc_textarea( $observation_summary ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="visible_details"><?php esc_html_e( 'Viditeľné detaily', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea name="visible_details" id="visible_details" rows="3" class="large-text"><?php echo esc_textarea( $visible_details ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="contradiction_note"><?php esc_html_e( 'Poznámka k rozporu', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea name="contradiction_note" id="contradiction_note" rows="3" class="large-text"><?php echo esc_textarea( $contradiction_note ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="observed_at"><?php esc_html_e( 'Dátum pozorovania', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="datetime-local" name="observed_at" id="observed_at" value="<?php echo esc_attr( str_replace( ' ', 'T', $observed_at ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="language"><?php esc_html_e( 'Jazyk', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" class="small-text" name="language" id="language" value="<?php echo esc_attr( $language ); ?>"></td>
				</tr>
				<tr>
					<th><label for="related_collection_task_id"><?php esc_html_e( 'Súvisiaci task ID', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" min="0" class="small-text" name="related_collection_task_id" id="related_collection_task_id" value="<?php echo esc_attr( $related_collection_task_id ); ?>"></td>
				</tr>
				<tr>
					<th><label for="notes"><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea name="notes" id="notes" rows="4" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea></td>
				</tr>
			</table>

			<?php submit_button( $form_id > 0 ? __( 'Uložiť zmeny', 'toptour-reference-finder' ) : __( 'Vytvoriť fotodôkaz', 'toptour-reference-finder' ) ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=toptour-references-photo-evidence' ) ); ?>" class="button"><?php esc_html_e( 'Zrušiť', 'toptour-reference-finder' ); ?></a>
		</form>
	<?php else : ?>
		<?php
		$filter_photo_type          = isset( $_GET['photo_type'] ) ? sanitize_text_field( $_GET['photo_type'] ) : '';
		$filter_comparison_category = isset( $_GET['comparison_category'] ) ? sanitize_text_field( $_GET['comparison_category'] ) : '';
		$filter_visual_area         = isset( $_GET['visual_area'] ) ? sanitize_text_field( $_GET['visual_area'] ) : '';
		$filter_verification_status = isset( $_GET['verification_status'] ) ? sanitize_text_field( $_GET['verification_status'] ) : '';
		$filter_signal_strength     = isset( $_GET['signal_strength'] ) ? sanitize_text_field( $_GET['signal_strength'] ) : '';
		$filter_target_type         = isset( $_GET['target_type'] ) ? sanitize_text_field( $_GET['target_type'] ) : '';
		$filter_source_id           = isset( $_GET['source_id'] ) ? absint( $_GET['source_id'] ) : 0;
		$filter_finding_id          = isset( $_GET['finding_id'] ) ? absint( $_GET['finding_id'] ) : 0;
		$search                     = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$current_page               = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$result = Toptour_Ref_Photo_Evidence::get_photo_evidence_list(
			[
				'photo_type'          => $filter_photo_type,
				'comparison_category' => $filter_comparison_category,
				'visual_area'         => $filter_visual_area,
				'verification_status' => $filter_verification_status,
				'signal_strength'     => $filter_signal_strength,
				'target_type'         => $filter_target_type,
				'source_id'           => $filter_source_id > 0 ? $filter_source_id : '',
				'finding_id'          => $filter_finding_id > 0 ? $filter_finding_id : '',
				'search'              => $search,
				'page'                => $current_page,
				'per_page'            => 20,
			]
		);

		$rows        = $result['rows'];
		$total       = (int) $result['total'];
		$total_pages = (int) ceil( $total / 20 );
		$base_url    = admin_url( 'admin.php?page=toptour-references-photo-evidence' );
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="toptour-references-photo-evidence">
			<div style="display:flex;flex-wrap:wrap;gap:6px;margin:12px 0;">
				<select name="photo_type">
					<option value=""><?php esc_html_e( 'Typ', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_photo_types() as $item ) : ?>
						<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $filter_photo_type, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::photo_type_label( $item ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="comparison_category">
					<option value=""><?php esc_html_e( 'Porovnanie', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_comparison_categories() as $item ) : ?>
						<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $filter_comparison_category, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::comparison_category_label( $item ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="visual_area">
					<option value=""><?php esc_html_e( 'Oblasť', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_visual_areas() as $item ) : ?>
						<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $filter_visual_area, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::visual_area_label( $item ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="verification_status">
					<option value=""><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_verification_statuses() as $item ) : ?>
						<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $filter_verification_status, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::verification_status_label( $item ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="signal_strength">
					<option value=""><?php esc_html_e( 'Sila', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_signal_strengths() as $item ) : ?>
						<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $filter_signal_strength, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::signal_strength_label( $item ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="target_type">
					<option value=""><?php esc_html_e( 'Typ cieľa', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( Toptour_Ref_Photo_Evidence::get_allowed_target_types() as $item ) : ?>
						<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $filter_target_type, $item ); ?>><?php echo esc_html( Toptour_Ref_Labels::target_type_label( $item ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="number" name="source_id" min="0" placeholder="<?php esc_attr_e( 'Source ID', 'toptour-reference-finder' ); ?>" value="<?php echo $filter_source_id > 0 ? esc_attr( $filter_source_id ) : ''; ?>" style="width:90px;">
				<input type="number" name="finding_id" min="0" placeholder="<?php esc_attr_e( 'Finding ID', 'toptour-reference-finder' ); ?>" value="<?php echo $filter_finding_id > 0 ? esc_attr( $filter_finding_id ) : ''; ?>" style="width:90px;">
				<input type="search" name="s" class="regular-text" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Hľadať...', 'toptour-reference-finder' ); ?>">
				<?php submit_button( __( 'Filtrovať', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
				<?php if ( $filter_photo_type || $filter_comparison_category || $filter_visual_area || $filter_verification_status || $filter_signal_strength || $filter_target_type || $filter_source_id > 0 || $filter_finding_id > 0 || $search ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Zrušiť filtre', 'toptour-reference-finder' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<p><?php printf( esc_html__( 'Celkom: %d fotodôkazov', 'toptour-reference-finder' ), $total ); ?></p>

		<?php if ( $rows ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:50px;"><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Názov dôkazu', 'toptour-reference-finder' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Typ', 'toptour-reference-finder' ); ?></th>
						<th style="width:150px;"><?php esc_html_e( 'Porovnanie', 'toptour-reference-finder' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Oblasť', 'toptour-reference-finder' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Zdroj', 'toptour-reference-finder' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Zistenie', 'toptour-reference-finder' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Cieľ', 'toptour-reference-finder' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Sila', 'toptour-reference-finder' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></th>
						<th style="width:70px;"><?php esc_html_e( 'URL', 'toptour-reference-finder' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Vytvorené', 'toptour-reference-finder' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$edit_url = wp_nonce_url(
							add_query_arg(
								[
									'page'        => 'toptour-references-photo-evidence',
									'action'      => 'edit',
									'evidence_id' => $row->id,
								],
								admin_url( 'admin.php' )
							),
							'toptour_edit_photo_evidence_' . $row->id
						);
						$archive_url = wp_nonce_url(
							add_query_arg(
								[
									'page'        => 'toptour-references-photo-evidence',
									'action'      => 'archive',
									'evidence_id' => $row->id,
								],
								admin_url( 'admin.php' )
							),
							'toptour_archive_photo_evidence_' . $row->id
						);
						?>
						<tr>
							<td><?php echo esc_html( $row->id ); ?></td>
							<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $row->evidence_title ); ?></a></td>
							<td><?php echo esc_html( Toptour_Ref_Labels::photo_type_label( $row->photo_type ) ); ?></td>
							<td><?php echo esc_html( Toptour_Ref_Labels::comparison_category_label( $row->comparison_category ) ); ?></td>
							<td><?php echo esc_html( $row->visual_area !== '' ? Toptour_Ref_Labels::visual_area_label( $row->visual_area ) : '—' ); ?></td>
							<td><?php echo esc_html( Toptour_Ref_Photo_Evidence::get_source_label( $row->source_id ) ); ?></td>
							<td><?php echo esc_html( Toptour_Ref_Photo_Evidence::get_finding_label( $row->finding_id ) ); ?></td>
							<td><?php echo esc_html( Toptour_Ref_Photo_Evidence::get_target_label( $row->target_type, $row->target_id ) ); ?></td>
							<td><?php echo esc_html( Toptour_Ref_Labels::signal_strength_label( $row->signal_strength ) ); ?></td>
							<td><?php echo esc_html( Toptour_Ref_Labels::verification_status_label( $row->verification_status ) ); ?></td>
							<td>
								<?php if ( ! empty( $row->evidence_url ) ) : ?>
									<a href="<?php echo esc_url( $row->evidence_url ); ?>" target="_blank" rel="noopener noreferrer">URL</a>
								<?php else : ?>
									<?php echo esc_html( '—' ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td>
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a>
								<?php if ( $row->verification_status !== 'archived' ) : ?>
									| <a href="<?php echo esc_url( $archive_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Naozaj archivovať?', 'toptour-reference-finder' ) ); ?>')"><?php esc_html_e( 'Archivovať', 'toptour-reference-finder' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						$query_args = [
							'page'                => 'toptour-references-photo-evidence',
							'photo_type'          => $filter_photo_type,
							'comparison_category' => $filter_comparison_category,
							'visual_area'         => $filter_visual_area,
							'verification_status' => $filter_verification_status,
							'signal_strength'     => $filter_signal_strength,
							'target_type'         => $filter_target_type,
							'source_id'           => $filter_source_id > 0 ? $filter_source_id : '',
							'finding_id'          => $filter_finding_id > 0 ? $filter_finding_id : '',
							's'                   => $search,
						];
						echo wp_kses_post(
							paginate_links(
								[
									'base'    => add_query_arg( array_merge( $query_args, [ 'paged' => '%#%' ] ), admin_url( 'admin.php' ) ),
									'format'  => '',
									'current' => $current_page,
									'total'   => $total_pages,
								]
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Žiadne fotodôkazy.', 'toptour-reference-finder' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
