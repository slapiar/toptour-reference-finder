<?php
/**
 * Offers admin page.
 *
 * @package Toptour_Ref
 * @version 0.2.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Nemáte oprávnenie na zobrazenie tejto stránky.', 'toptour-reference-finder' ) );
}

$notice = '';
$notice_type = 'success';

$action = isset( $_POST['toptour_offer_action'] ) ? sanitize_key( wp_unslash( $_POST['toptour_offer_action'] ) ) : '';
if ( '' !== $action ) {
	check_admin_referer( 'toptour_offer_action' );

	if ( 'save_offer' === $action ) {
		$offer_id = isset( $_POST['offer_id'] ) ? absint( $_POST['offer_id'] ) : 0;
		$data = Toptour_Ref_Offers::sanitize_offer_data( $_POST );
		$validation = Toptour_Ref_Offers::validate_offer_data( $data );

		if ( true !== $validation ) {
			$notice = 'Ponuku sa nepodarilo uložiť: ' . implode( ', ', $validation );
			$notice_type = 'error';
		} else {
			if ( $offer_id > 0 ) {
				$saved = Toptour_Ref_Offers::update_offer( $offer_id, $data );
				$notice = $saved ? 'Ponuka bola aktualizovaná.' : 'Ponuku sa nepodarilo aktualizovať.';
				$notice_type = $saved ? 'success' : 'error';
			} else {
				$saved = Toptour_Ref_Offers::create_offer( $data );
				$notice = $saved ? 'Ponuka bola vytvorená.' : 'Ponuku sa nepodarilo vytvoriť.';
				$notice_type = $saved ? 'success' : 'error';
			}
		}
	}

	if ( 'archive_offer' === $action ) {
		$offer_id = isset( $_POST['offer_id'] ) ? absint( $_POST['offer_id'] ) : 0;
		$saved = $offer_id > 0 ? Toptour_Ref_Offers::archive_offer( $offer_id ) : false;
		$notice = $saved ? 'Ponuka bola archivovaná.' : 'Ponuku sa nepodarilo archivovať.';
		$notice_type = $saved ? 'success' : 'error';
	}
}

$edit_id = isset( $_GET['edit_offer'] ) ? absint( $_GET['edit_offer'] ) : 0;
$editing_offer = $edit_id > 0 ? Toptour_Ref_Offers::get_offer( $edit_id ) : null;

$offers_result = Toptour_Ref_Offers::get_offers(
	[
		'page' => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
		'per_page' => 20,
		'search' => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
	]
);

$offers = $offers_result['rows'];
$offer_statuses = Toptour_Ref_Offers::get_allowed_statuses();
$offer_types = Toptour_Ref_Offers::get_allowed_types();
?>
<div class="wrap toptour-wrap">
	<h1>Ponuky</h1>
	<p>Manuálny register ponúk. Reálny intake URL vie záznamy vytvárať automaticky z detailu Zberovej úlohy.</p>

	<?php if ( '' !== $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $notice_type ? 'error' : 'success' ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<div class="card" style="max-width: 960px; margin-bottom: 20px;">
		<h2><?php echo $editing_offer ? esc_html__( 'Upraviť ponuku', 'toptour-reference-finder' ) : esc_html__( 'Nová ponuka', 'toptour-reference-finder' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'toptour_offer_action' ); ?>
			<input type="hidden" name="toptour_offer_action" value="save_offer" />
			<input type="hidden" name="offer_id" value="<?php echo esc_attr( $editing_offer ? $editing_offer->id : 0 ); ?>" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="offer_name">Názov ponuky</label></th>
					<td><input type="text" class="regular-text" id="offer_name" name="offer_name" required value="<?php echo esc_attr( $editing_offer->offer_name ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="offer_url">URL ponuky</label></th>
					<td><input type="url" class="regular-text" id="offer_url" name="offer_url" value="<?php echo esc_attr( $editing_offer->offer_url ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="offer_type">Typ</label></th>
					<td>
						<select id="offer_type" name="offer_type">
							<?php foreach ( $offer_types as $type ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>" <?php selected( (string) ( $editing_offer->offer_type ?? 'general' ), $type ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $type ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="status">Status</label></th>
					<td>
						<select id="status" name="status">
							<?php foreach ( $offer_statuses as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( (string) ( $editing_offer->status ?? 'needs_review' ), $status ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="description_summary">Zhrnutie</label></th>
					<td><textarea id="description_summary" name="description_summary" rows="4" class="large-text"><?php echo esc_textarea( $editing_offer->description_summary ?? '' ); ?></textarea></td>
				</tr>
			</table>
			<?php submit_button( $editing_offer ? 'Uložiť zmeny' : 'Vytvoriť ponuku' ); ?>
		</form>
	</div>

	<form method="get" style="margin-bottom: 10px;">
		<input type="hidden" name="page" value="toptour-references-offers" />
		<input type="search" name="search" placeholder="Hľadať názov alebo URL" value="<?php echo esc_attr( isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '' ); ?>" />
		<select name="status">
			<option value="">Všetky stavy</option>
			<?php foreach ( $offer_statuses as $status ) : ?>
				<option value="<?php echo esc_attr( $status ); ?>" <?php selected( isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '', $status ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php submit_button( 'Filtrovať', 'secondary', '', false ); ?>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th>ID</th>
				<th>Názov</th>
				<th>URL</th>
				<th>Typ</th>
				<th>Status</th>
				<th>Aktualizované</th>
				<th>Akcie</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $offers ) ) : ?>
				<tr><td colspan="7">Žiadne ponuky.</td></tr>
			<?php else : ?>
				<?php foreach ( $offers as $offer ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $offer->id ); ?></td>
						<td><?php echo esc_html( (string) $offer->offer_name ); ?></td>
						<td>
							<?php if ( ! empty( $offer->offer_url ) ) : ?>
								<a href="<?php echo esc_url( $offer->offer_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $offer->offer_url ); ?></a>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) $offer->offer_type ); ?></td>
						<td><?php echo esc_html( (string) $offer->status ); ?></td>
						<td><?php echo esc_html( (string) $offer->updated_at ); ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=toptour-references-offers&edit_offer=' . absint( $offer->id ) ) ); ?>">Upraviť</a>
							<form method="post" style="display:inline;">
								<?php wp_nonce_field( 'toptour_offer_action' ); ?>
								<input type="hidden" name="toptour_offer_action" value="archive_offer" />
								<input type="hidden" name="offer_id" value="<?php echo esc_attr( (string) $offer->id ); ?>" />
								<button type="submit" class="button button-small" onclick="return confirm('Archivovať ponuku?');">Archivovať</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
