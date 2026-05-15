<?php
/**
 * TOPTOUR Reference Finder - Interests View
 *
 * Internal admin screen for interests vocabulary.
 *
 * @package Toptour_Ref
 * @version 0.1.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
}

$base_url = admin_url( 'admin.php?page=toptour-references-interests' );
$action = isset( $_GET['toptour_action'] ) ? sanitize_text_field( wp_unslash( $_GET['toptour_action'] ) ) : '';
$edit_id = isset( $_GET['interest_id'] ) ? absint( $_GET['interest_id'] ) : 0;
$notice = '';
$notice_type = 'success';

if ( 'edit' === $action && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_edit_interest_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
}

if ( 'deactivate' === $action && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_deactivate_interest_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
	$ok = Toptour_Ref_Interests::deactivate_interest( $edit_id );
	$notice = $ok ? __( 'Záujem bol deaktivovaný.', 'toptour-reference-finder' ) : __( 'Záujem sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
	$notice_type = $ok ? 'success' : 'error';
	$action = '';
	$edit_id = 0;
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['toptour_interest_submit'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_save_interest' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
	$post_id = absint( $_POST['interest_id'] ?? 0 );
	$data = Toptour_Ref_Interests::sanitize_interest_data( wp_unslash( $_POST ) );
	$valid = Toptour_Ref_Interests::validate_interest_data( $data, $post_id );

	if ( true === $valid ) {
		$ok = $post_id ? Toptour_Ref_Interests::update_interest( $post_id, $data ) : Toptour_Ref_Interests::create_interest( $data );
		$notice = $ok ? __( 'Záujem bol uložený.', 'toptour-reference-finder' ) : __( 'Záujem sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
		if ( $ok ) {
			$action = '';
			$edit_id = 0;
		} else {
			$action = $post_id ? 'edit' : 'add';
			$edit_id = $post_id;
		}
	} else {
		$notice = __( 'Záujem sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = 'error';
		$action = $post_id ? 'edit' : 'add';
		$edit_id = $post_id;
	}
}

$edit_interest = null;
if ( in_array( $action, [ 'edit', 'add' ], true ) && $edit_id ) {
	$edit_interest = Toptour_Ref_Interests::get_interest( $edit_id );
}

$filter_interest_type = isset( $_GET['filter_interest_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_interest_type'] ) ) : '';
$filter_is_active = isset( $_GET['filter_is_active'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_is_active'] ) ) : '';
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result = Toptour_Ref_Interests::get_interests(
	[
		'interest_type' => $filter_interest_type,
		'is_active'     => $filter_is_active,
		'search'        => $search,
		'page'          => $current_page,
		'per_page'      => 20,
	]
);

$interests = $result['interests'];
$total = $result['total'];
$total_pages = (int) ceil( $total / 20 );
$interest_types = Toptour_Ref_Interests::get_allowed_types();
?>

<div class="wrap toptour-ref-interests">
	<h1><?php esc_html_e( 'Záujmy', 'toptour-reference-finder' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Interný slovník tém, kompetencií a oblastí osožnosti.', 'toptour-reference-finder' ); ?></p>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<?php
		$i = $edit_interest;
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['toptour_interest_submit'] ) ) {
			$i = (object) Toptour_Ref_Interests::sanitize_interest_data( wp_unslash( $_POST ) );
		}
		$form_id = $edit_id;
		?>
		<h2><?php echo $form_id ? esc_html__( 'Upraviť záujem', 'toptour-reference-finder' ) : esc_html__( 'Pridať záujem', 'toptour-reference-finder' ); ?></h2>
		<form method="post" action="<?php echo esc_url( $base_url ); ?>">
			<?php wp_nonce_field( 'toptour_save_interest' ); ?>
			<input type="hidden" name="toptour_interest_submit" value="1">
			<input type="hidden" name="interest_id" value="<?php echo esc_attr( $form_id ); ?>">

			<table class="form-table">
				<tr>
					<th scope="row"><label for="interest_key"><?php esc_html_e( 'Kľúč', 'toptour-reference-finder' ); ?> *</label></th>
					<td><input type="text" id="interest_key" name="interest_key" maxlength="120" class="regular-text" value="<?php echo esc_attr( $i->interest_key ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="interest_name"><?php esc_html_e( 'Názov', 'toptour-reference-finder' ); ?> *</label></th>
					<td><input type="text" id="interest_name" name="name" maxlength="255" required class="regular-text" value="<?php echo esc_attr( $i->name ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="interest_description"><?php esc_html_e( 'Popis', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="interest_description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $i->description ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="interest_type"><?php esc_html_e( 'Typ', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="interest_type" name="interest_type">
							<?php foreach ( $interest_types as $interest_type ) : ?>
								<option value="<?php echo esc_attr( $interest_type ); ?>" <?php selected( $i->interest_type ?? 'other', $interest_type ); ?>><?php echo esc_html( Toptour_Ref_Labels::interest_type_label( $interest_type ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="interest_is_active"><?php esc_html_e( 'Aktívny', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="interest_is_active" name="is_active" value="1" <?php checked( isset( $i->is_active ) ? (int) $i->is_active : 1, 1 ); ?>>
							<?php esc_html_e( 'Aktívny záujem', 'toptour-reference-finder' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button( $form_id ? __( 'Uložiť zmeny', 'toptour-reference-finder' ) : __( 'Pridať záujem', 'toptour-reference-finder' ) ); ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť', 'toptour-reference-finder' ); ?></a>
		</form>
	<?php else : ?>
		<a href="<?php echo esc_url( add_query_arg( 'toptour_action', 'add', $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridať záujem', 'toptour-reference-finder' ); ?></a>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="toptour-references-interests">
			<div style="margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
				<select name="filter_interest_type">
					<option value=""><?php esc_html_e( '- Typ -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $interest_types as $interest_type ) : ?>
						<option value="<?php echo esc_attr( $interest_type ); ?>" <?php selected( $filter_interest_type, $interest_type ); ?>><?php echo esc_html( Toptour_Ref_Labels::interest_type_label( $interest_type ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_is_active">
					<option value=""><?php esc_html_e( '- Aktívny -', 'toptour-reference-finder' ); ?></option>
					<option value="1" <?php selected( $filter_is_active, '1' ); ?>><?php esc_html_e( 'áno', 'toptour-reference-finder' ); ?></option>
					<option value="0" <?php selected( $filter_is_active, '0' ); ?>><?php esc_html_e( 'nie', 'toptour-reference-finder' ); ?></option>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Hľadať...', 'toptour-reference-finder' ); ?>">
				<?php submit_button( __( 'Filtrovať', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
			</div>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Kľúč', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Názov', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Typ', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Aktívny', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Kontakty', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Vytvorené', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $interests ) : ?>
					<?php foreach ( $interests as $interest ) :
						$edit_url = wp_nonce_url(
							add_query_arg( [ 'toptour_action' => 'edit', 'interest_id' => $interest->id ], $base_url ),
							'toptour_edit_interest_' . $interest->id
						);
						$deactivate_url = wp_nonce_url(
							add_query_arg( [ 'toptour_action' => 'deactivate', 'interest_id' => $interest->id ], $base_url ),
							'toptour_deactivate_interest_' . $interest->id
						);
						$count = Toptour_Ref_Contact_Interests::count_contacts_for_interest( $interest->id );
					?>
					<tr>
						<td><?php echo esc_html( $interest->id ); ?></td>
						<td><?php echo esc_html( $interest->interest_key ); ?></td>
						<td><?php echo esc_html( $interest->name ); ?></td>
						<td><?php echo esc_html( Toptour_Ref_Labels::interest_type_label( $interest->interest_type ) ); ?></td>
						<td><?php echo esc_html( (int) $interest->is_active === 1 ? 'áno' : 'nie' ); ?></td>
						<td><?php echo esc_html( $count ); ?></td>
						<td><?php echo esc_html( $interest->created_at ); ?></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a>
							<?php if ( (int) $interest->is_active === 1 ) : ?>
								&nbsp;|&nbsp;
								<a href="<?php echo esc_url( $deactivate_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Deaktivovať tento záujem?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Deaktivovať', 'toptour-reference-finder' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="8"><?php esc_html_e( 'Žiadne záznamy.', 'toptour-reference-finder' ); ?></td>
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
								'page' => 'toptour-references-interests',
								'filter_interest_type' => $filter_interest_type,
								'filter_is_active' => $filter_is_active,
								's' => $search,
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
