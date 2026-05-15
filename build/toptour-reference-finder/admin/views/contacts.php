<?php
/**
 * TOPTOUR Reference Finder - Contacts View
 *
 * Internal contacts and resident profile admin screen.
 *
 * @package Toptour_Ref
 * @version 0.1.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
}

$base_url    = admin_url( 'admin.php?page=toptour-references-contacts' );
$action      = isset( $_GET['toptour_action'] ) ? sanitize_text_field( wp_unslash( $_GET['toptour_action'] ) ) : '';
$edit_id     = isset( $_GET['contact_id'] ) ? absint( $_GET['contact_id'] ) : 0;
$notice      = '';
$notice_type = 'success';

if ( 'edit' === $action && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_edit_contact_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
}

if ( 'archive' === $action && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_archive_contact_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$archived = Toptour_Ref_Contacts::archive_contact( $edit_id );
	$notice = $archived ? __( 'Kontakt bol archivovaný.', 'toptour-reference-finder' ) : __( 'Kontakt sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
	$notice_type = $archived ? 'success' : 'error';
	$action = '';
	$edit_id = 0;
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['toptour_contact_submit'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_save_contact' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$post_id = absint( $_POST['contact_id'] ?? 0 );
	$raw_post = wp_unslash( $_POST );
	$contact_data = Toptour_Ref_Contacts::sanitize_contact_data( $raw_post );
	$contact_valid = Toptour_Ref_Contacts::validate_contact_data( $contact_data, $raw_post );

	$has_resident_profile = isset( $_POST['has_resident_profile'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['has_resident_profile'] ) );
	$raw_interest_ids = isset( $_POST['interest_ids'] ) ? (array) wp_unslash( $_POST['interest_ids'] ) : [];
	$interest_ids = array_values( array_unique( array_filter( array_map( 'absint', $raw_interest_ids ) ) ) );
	$interest_rows = [];
	$raw_influence_rows = isset( $raw_post['influence_rows'] ) ? (array) $raw_post['influence_rows'] : [];
	$influence_rows = Toptour_Ref_Contact_Influence::sanitize_records_data( $raw_influence_rows );
	$raw_relationship_rows = isset( $raw_post['relationship_rows'] ) ? (array) $raw_post['relationship_rows'] : [];
	$relationship_rows = Toptour_Ref_Contact_Relationships::sanitize_relationship_rows( $raw_relationship_rows );
	foreach ( $interest_ids as $interest_id ) {
		$interest_rows[] = [
			'interest_id' => $interest_id,
			'interest_level' => 'medium',
			'relationship_type' => 'personal_interest',
			'notes' => '',
		];
	}
	$profile_data = Toptour_Ref_Resident_Profiles::sanitize_profile_data( $raw_post );
	$profile_valid = true;
	if ( $has_resident_profile ) {
		$profile_valid = Toptour_Ref_Resident_Profiles::validate_profile_data( $profile_data );
	}

	if ( true === $contact_valid && true === $profile_valid ) {
		$relationship_validation_failed = false;
		$relationship_save_failed = false;
		$relationship_rows_for_save = [];

		if ( $post_id ) {
			$ok = Toptour_Ref_Contacts::update_contact( $post_id, $contact_data );
			$saved_contact_id = $post_id;
		} else {
			$created_id = Toptour_Ref_Contacts::create_contact( $contact_data );
			$ok = (bool) $created_id;
			$saved_contact_id = (int) $created_id;
		}

		if ( $ok ) {
			if ( $has_resident_profile ) {
				$ok = Toptour_Ref_Resident_Profiles::upsert_profile( $saved_contact_id, $profile_data );
			} else {
				$ok = Toptour_Ref_Resident_Profiles::delete_profile_by_contact_id( $saved_contact_id );
			}
		}

		if ( $ok ) {
			$ok = Toptour_Ref_Contact_Interests::replace_contact_interests( $saved_contact_id, $interest_rows );
		}

		if ( $ok ) {
			$ok = Toptour_Ref_Contact_Influence::replace_records_for_contact( $saved_contact_id, $influence_rows );
		}

		if ( $ok ) {
			$relationship_seen = [];
			foreach ( $relationship_rows as $relationship_row ) {
				if ( ! empty( $relationship_row['remove'] ) || Toptour_Ref_Contact_Relationships::is_empty_relationship_row( $relationship_row ) ) {
					continue;
				}

				$relationship_row['contact_id'] = $saved_contact_id;

				if ( absint( $relationship_row['related_contact_id'] ) <= 0 || absint( $relationship_row['related_contact_id'] ) === absint( $saved_contact_id ) ) {
					$relationship_validation_failed = true;
					continue;
				}

				$relationship_valid = Toptour_Ref_Contact_Relationships::validate_relationship_row( $relationship_row );
				if ( true !== $relationship_valid ) {
					$relationship_validation_failed = true;
					continue;
				}

				$relationship_key = absint( $relationship_row['related_contact_id'] ) . '|' . $relationship_row['relationship_type'];
				if ( isset( $relationship_seen[ $relationship_key ] ) ) {
					$relationship_validation_failed = true;
					continue;
				}

				$relationship_seen[ $relationship_key ] = true;
				$relationship_rows_for_save[] = $relationship_row;
			}

			$relationships_ok = Toptour_Ref_Contact_Relationships::replace_contact_relationships( $saved_contact_id, $relationship_rows_for_save );
			if ( ! $relationships_ok ) {
				$relationship_save_failed = true;
			}
		}

		if ( $ok && $relationship_save_failed ) {
			$notice = __( 'Kontakt bol uložený, ale vzťahy kontaktu sa nepodarilo uložiť.', 'toptour-reference-finder' );
			$notice_type = 'error';
		} elseif ( $ok && $relationship_validation_failed ) {
			$notice = __( 'Niektoré vzťahy kontaktu nebolo možné uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
			$notice_type = 'error';
		} else {
			$notice = $ok ? __( 'Kontakt bol uložený.', 'toptour-reference-finder' ) : __( 'Kontakt sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
			$notice_type = $ok ? 'success' : 'error';
		}

		if ( $ok ) {
			$action = '';
			$edit_id = 0;
		} else {
			$action = $post_id ? 'edit' : 'add';
			$edit_id = $post_id;
		}
	} else {
		$notice = __( 'Kontakt sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = 'error';
		$action = $post_id ? 'edit' : 'add';
		$edit_id = $post_id;
	}
}

$edit_contact = null;
$resident_profile = null;
if ( in_array( $action, [ 'edit', 'add' ], true ) && $edit_id ) {
	$edit_contact = Toptour_Ref_Contacts::get_contact( $edit_id );
	$resident_profile = Toptour_Ref_Resident_Profiles::get_profile_by_contact_id( $edit_id );
}

$filter_contact_type = isset( $_GET['filter_contact_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_contact_type'] ) ) : '';
$filter_status       = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
$filter_trust_level  = isset( $_GET['filter_trust_level'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_trust_level'] ) ) : '';
$filter_country      = isset( $_GET['filter_country'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_country'] ) ) : '';
$filter_region       = isset( $_GET['filter_region'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_region'] ) ) : '';
$search              = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page        = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result = Toptour_Ref_Contacts::get_contacts(
	[
		'contact_type' => $filter_contact_type,
		'status'       => $filter_status,
		'trust_level'  => $filter_trust_level,
		'country'      => $filter_country,
		'region'       => $filter_region,
		'search'       => $search,
		'page'         => $current_page,
		'per_page'     => 20,
	]
);

$contacts = $result['contacts'];
$total = $result['total'];
$total_pages = (int) ceil( $total / 20 );

$contact_types = Toptour_Ref_Contacts::get_allowed_contact_types();
$statuses = Toptour_Ref_Contacts::get_allowed_statuses();
$trust_levels = Toptour_Ref_Contacts::get_allowed_trust_levels();

$resident_types = Toptour_Ref_Resident_Profiles::get_allowed_resident_types();
$availability_statuses = Toptour_Ref_Resident_Profiles::get_allowed_availability_statuses();
$verification_statuses = Toptour_Ref_Resident_Profiles::get_allowed_verification_statuses();
$badge_statuses = Toptour_Ref_Resident_Profiles::get_allowed_badge_statuses();
$available_interests = Toptour_Ref_Interests::get_active_interests();
$selected_interest_ids = $edit_id ? Toptour_Ref_Contact_Interests::get_interest_ids_for_contact( $edit_id ) : [];
$influence_target_types = Toptour_Ref_Contact_Influence::get_allowed_target_types();
$influence_levels = Toptour_Ref_Contact_Influence::get_allowed_levels();
$influence_types = Toptour_Ref_Contact_Influence::get_allowed_influence_types();
$usefulness_levels = Toptour_Ref_Contact_Influence::get_allowed_usefulness_levels();
$mutuality_levels = Toptour_Ref_Contact_Influence::get_allowed_mutuality_levels();
$relationship_types = Toptour_Ref_Contact_Relationships::get_allowed_relationship_types();
$relationship_strengths = Toptour_Ref_Contact_Relationships::get_allowed_relationship_strengths();
$relationship_mutuality_levels = Toptour_Ref_Contact_Relationships::get_allowed_mutuality_levels();

$influence_form_rows = [];
if ( $edit_id ) {
	$stored_influence_rows = Toptour_Ref_Contact_Influence::get_records_for_contact( $edit_id );
	foreach ( $stored_influence_rows as $stored_influence_row ) {
		$row = (array) $stored_influence_row;
		$row['remove'] = 0;
		$influence_form_rows[] = $row;
	}
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['toptour_contact_submit'] ) ) {
	$influence_form_rows = Toptour_Ref_Contact_Influence::sanitize_records_data( (array) wp_unslash( $_POST['influence_rows'] ?? [] ) );
}

while ( count( $influence_form_rows ) < 3 ) {
	$influence_form_rows[] = Toptour_Ref_Contact_Influence::get_default_record_data();
}

$relationship_form_rows = [];
if ( $edit_id ) {
	$stored_relationship_rows = Toptour_Ref_Contact_Relationships::get_relationships_for_contact( $edit_id );
	foreach ( $stored_relationship_rows as $stored_relationship_row ) {
		$row = (array) $stored_relationship_row;
		$row['remove'] = 0;
		$relationship_form_rows[] = $row;
	}
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['toptour_contact_submit'] ) ) {
	$relationship_form_rows = Toptour_Ref_Contact_Relationships::sanitize_relationship_rows( (array) wp_unslash( $_POST['relationship_rows'] ?? [] ) );
}

while ( count( $relationship_form_rows ) < 3 ) {
	$relationship_form_rows[] = Toptour_Ref_Contact_Relationships::get_default_relationship_row();
}

$relationship_contact_options = Toptour_Ref_Contacts::get_contacts_for_selection();

$contact_ids = array_map( 'absint', wp_list_pluck( $contacts, 'id' ) );
$resident_flags = Toptour_Ref_Resident_Profiles::get_profile_flags_for_contacts( $contact_ids );
$interest_names_map = Toptour_Ref_Contact_Interests::get_interest_names_for_contacts( $contact_ids );
$influence_counts_map = Toptour_Ref_Contact_Influence::get_influence_counts_for_contacts( $contact_ids );
$relationship_counts_map = Toptour_Ref_Contact_Relationships::get_relationship_counts_for_contacts( $contact_ids );
?>

<div class="wrap toptour-ref-contacts">
	<h1><?php esc_html_e( 'Kontakty', 'toptour-reference-finder' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Interná evidencia kontaktov a rezidentských profilov pre budúce overovanie reality v destináciách.', 'toptour-reference-finder' ); ?></p>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<?php
		$c = $edit_contact;
		$r = $resident_profile;
		$has_profile = ( null !== $resident_profile );
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['toptour_contact_submit'] ) ) {
			$c = (object) Toptour_Ref_Contacts::sanitize_contact_data( wp_unslash( $_POST ) );
			$r = (object) Toptour_Ref_Resident_Profiles::sanitize_profile_data( wp_unslash( $_POST ) );
			$has_profile = isset( $_POST['has_resident_profile'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['has_resident_profile'] ) );
			$selected_interest_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['interest_ids'] ?? [] ) ) ) ) );
			$influence_form_rows = Toptour_Ref_Contact_Influence::sanitize_records_data( (array) wp_unslash( $_POST['influence_rows'] ?? [] ) );
			$relationship_form_rows = Toptour_Ref_Contact_Relationships::sanitize_relationship_rows( (array) wp_unslash( $_POST['relationship_rows'] ?? [] ) );
			while ( count( $influence_form_rows ) < 3 ) {
				$influence_form_rows[] = Toptour_Ref_Contact_Influence::get_default_record_data();
			}
			while ( count( $relationship_form_rows ) < 3 ) {
				$relationship_form_rows[] = Toptour_Ref_Contact_Relationships::get_default_relationship_row();
			}
		}
		$form_id = $edit_id;
		?>
		<h2><?php echo $form_id ? esc_html__( 'Upraviť kontakt', 'toptour-reference-finder' ) : esc_html__( 'Pridať kontakt', 'toptour-reference-finder' ); ?></h2>
		<form method="post" action="<?php echo esc_url( $base_url ); ?>">
			<?php wp_nonce_field( 'toptour_save_contact' ); ?>
			<input type="hidden" name="toptour_contact_submit" value="1">
			<input type="hidden" name="contact_id" value="<?php echo esc_attr( $form_id ); ?>">

			<table class="form-table">
				<tr>
					<th scope="row"><label for="contact_type"><?php esc_html_e( 'Typ kontaktu', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="contact_type" name="contact_type">
							<?php foreach ( $contact_types as $contact_type ) : ?>
								<option value="<?php echo esc_attr( $contact_type ); ?>" <?php selected( $c->contact_type ?? 'person', $contact_type ); ?>><?php echo esc_html( $contact_type ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="display_name"><?php esc_html_e( 'Meno / názov', 'toptour-reference-finder' ); ?> *</label></th>
					<td><input type="text" id="display_name" name="display_name" maxlength="255" required class="regular-text" value="<?php echo esc_attr( $c->display_name ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="first_name"><?php esc_html_e( 'Meno', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="first_name" name="first_name" class="regular-text" value="<?php echo esc_attr( $c->first_name ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="last_name"><?php esc_html_e( 'Priezvisko', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="last_name" name="last_name" class="regular-text" value="<?php echo esc_attr( $c->last_name ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="organization_name"><?php esc_html_e( 'Organizácia', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="organization_name" name="organization_name" class="regular-text" value="<?php echo esc_attr( $c->organization_name ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="email"><?php esc_html_e( 'Email', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="email" id="email" name="email" class="regular-text" value="<?php echo esc_attr( $c->email ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="phone"><?php esc_html_e( 'Telefón', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="phone" name="phone" class="regular-text" value="<?php echo esc_attr( $c->phone ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="website_url"><?php esc_html_e( 'Web', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="url" id="website_url" name="website_url" class="large-text" value="<?php echo esc_attr( $c->website_url ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="country"><?php esc_html_e( 'Krajina', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="country" name="country" class="regular-text" value="<?php echo esc_attr( $c->country ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="region"><?php esc_html_e( 'Región', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="region" name="region" class="regular-text" value="<?php echo esc_attr( $c->region ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="city"><?php esc_html_e( 'Mesto', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="city" name="city" class="regular-text" value="<?php echo esc_attr( $c->city ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="address"><?php esc_html_e( 'Adresa', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="address" name="address" rows="2" class="large-text"><?php echo esc_textarea( $c->address ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="preferred_language"><?php esc_html_e( 'Preferovaný jazyk', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="preferred_language" name="preferred_language" class="regular-text" value="<?php echo esc_attr( $c->preferred_language ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="status"><?php esc_html_e( 'Status', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="status" name="status">
							<?php foreach ( $statuses as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $c->status ?? 'draft', $status ); ?>><?php echo esc_html( $status ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="trust_level"><?php esc_html_e( 'Dôvera', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="trust_level" name="trust_level">
							<?php foreach ( $trust_levels as $trust_level ) : ?>
								<option value="<?php echo esc_attr( $trust_level ); ?>" <?php selected( $c->trust_level ?? 'unknown', $trust_level ); ?>><?php echo esc_html( $trust_level ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="notes"><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $c->notes ?? '' ); ?></textarea></td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Rezidentský profil', 'toptour-reference-finder' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="has_resident_profile"><?php esc_html_e( 'Rezident', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="has_resident_profile" name="has_resident_profile" value="1" <?php checked( $has_profile ); ?>>
							<?php esc_html_e( 'Kontakt má rezidentský profil', 'toptour-reference-finder' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="resident_type"><?php esc_html_e( 'Typ rezidenta', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="resident_type" name="resident_type">
							<?php foreach ( $resident_types as $resident_type ) : ?>
								<option value="<?php echo esc_attr( $resident_type ); ?>" <?php selected( $r->resident_type ?? 'local_helper', $resident_type ); ?>><?php echo esc_html( $resident_type ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="availability_status"><?php esc_html_e( 'Dostupnosť', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="availability_status" name="availability_status">
							<?php foreach ( $availability_statuses as $availability_status ) : ?>
								<option value="<?php echo esc_attr( $availability_status ); ?>" <?php selected( $r->availability_status ?? 'unknown', $availability_status ); ?>><?php echo esc_html( $availability_status ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="verification_status"><?php esc_html_e( 'Verifikácia', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="verification_status" name="verification_status">
							<?php foreach ( $verification_statuses as $verification_status ) : ?>
								<option value="<?php echo esc_attr( $verification_status ); ?>" <?php selected( $r->verification_status ?? 'unverified', $verification_status ); ?>><?php echo esc_html( $verification_status ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="badge_status"><?php esc_html_e( 'Badge status', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="badge_status" name="badge_status">
							<?php foreach ( $badge_statuses as $badge_status ) : ?>
								<option value="<?php echo esc_attr( $badge_status ); ?>" <?php selected( $r->badge_status ?? 'none', $badge_status ); ?>><?php echo esc_html( $badge_status ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qr_code_token"><?php esc_html_e( 'QR token', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="qr_code_token" name="qr_code_token" class="regular-text" value="<?php echo esc_attr( $r->qr_code_token ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="resident_notes"><?php esc_html_e( 'Rezident poznámky', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="resident_notes" name="resident_notes" rows="3" class="large-text"><?php echo esc_textarea( $r->notes ?? '' ); ?></textarea></td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Záujmy kontaktu', 'toptour-reference-finder' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="interest_ids"><?php esc_html_e( 'Záujmy', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="interest_ids" name="interest_ids[]" multiple="multiple" size="8" style="min-width: 340px;">
							<?php foreach ( $available_interests as $interest ) : ?>
								<option value="<?php echo esc_attr( $interest->id ); ?>" <?php selected( in_array( (int) $interest->id, $selected_interest_ids, true ) ); ?>><?php echo esc_html( $interest->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Vyberte jeden alebo viac aktívnych záujmov. Pri uložení sa nahradia existujúce väzby kontaktu.', 'toptour-reference-finder' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Vplyv / pôsobnosť kontaktu', 'toptour-reference-finder' ); ?></h3>
			<table class="widefat striped" style="max-width: 1200px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Typ cieľa', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'ID cieľa', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Point label', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Typ vplyvu', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Vplyv', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Užitočnosť', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Mutualita', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Evidence note', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Odobrať', 'toptour-reference-finder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $influence_form_rows as $index => $influence_row ) : ?>
						<tr>
							<td>
								<select name="influence_rows[<?php echo esc_attr( $index ); ?>][target_type]">
									<?php foreach ( $influence_target_types as $target_type ) : ?>
										<option value="<?php echo esc_attr( $target_type ); ?>" <?php selected( $influence_row['target_type'] ?? 'general', $target_type ); ?>><?php echo esc_html( $target_type ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<input type="number" min="0" class="small-text" name="influence_rows[<?php echo esc_attr( $index ); ?>][target_id]" value="<?php echo esc_attr( absint( $influence_row['target_id'] ?? 0 ) ); ?>">
							</td>
							<td>
								<input type="text" class="regular-text" name="influence_rows[<?php echo esc_attr( $index ); ?>][point_label]" value="<?php echo esc_attr( $influence_row['point_label'] ?? '' ); ?>">
								<p class="description"><?php echo esc_html( Toptour_Ref_Contact_Influence::get_target_label( $influence_row ) ); ?></p>
							</td>
							<td>
								<select name="influence_rows[<?php echo esc_attr( $index ); ?>][influence_type]">
									<option value=""><?php esc_html_e( '- none -', 'toptour-reference-finder' ); ?></option>
									<?php foreach ( $influence_types as $influence_type ) : ?>
										<option value="<?php echo esc_attr( $influence_type ); ?>" <?php selected( $influence_row['influence_type'] ?? '', $influence_type ); ?>><?php echo esc_html( $influence_type ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="influence_rows[<?php echo esc_attr( $index ); ?>][influence_level]">
									<?php foreach ( $influence_levels as $influence_level ) : ?>
										<option value="<?php echo esc_attr( $influence_level ); ?>" <?php selected( $influence_row['influence_level'] ?? 'unknown', $influence_level ); ?>><?php echo esc_html( $influence_level ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="influence_rows[<?php echo esc_attr( $index ); ?>][usefulness_level]">
									<?php foreach ( $usefulness_levels as $usefulness_level ) : ?>
										<option value="<?php echo esc_attr( $usefulness_level ); ?>" <?php selected( $influence_row['usefulness_level'] ?? 'unknown', $usefulness_level ); ?>><?php echo esc_html( $usefulness_level ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="influence_rows[<?php echo esc_attr( $index ); ?>][mutuality_level]">
									<?php foreach ( $mutuality_levels as $mutuality_level ) : ?>
										<option value="<?php echo esc_attr( $mutuality_level ); ?>" <?php selected( $influence_row['mutuality_level'] ?? 'unknown', $mutuality_level ); ?>><?php echo esc_html( $mutuality_level ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<textarea rows="2" class="large-text" name="influence_rows[<?php echo esc_attr( $index ); ?>][evidence_note]"><?php echo esc_textarea( $influence_row['evidence_note'] ?? '' ); ?></textarea>
							</td>
							<td>
								<textarea rows="2" class="large-text" name="influence_rows[<?php echo esc_attr( $index ); ?>][notes]"><?php echo esc_textarea( $influence_row['notes'] ?? '' ); ?></textarea>
							</td>
							<td style="text-align: center;">
								<label>
									<input type="checkbox" name="influence_rows[<?php echo esc_attr( $index ); ?>][remove]" value="1" <?php checked( ! empty( $influence_row['remove'] ) ); ?>>
								</label>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Môžete upraviť existujúce riadky, pridať nové (voľné riadky) a označiť riadok na odstránenie. Pri uložení kontaktu sa influence záznamy kontaktu nahradia.', 'toptour-reference-finder' ); ?></p>

			<h3><?php esc_html_e( 'Vzťahy kontaktu', 'toptour-reference-finder' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Interná evidencia vzťahov kontaktu s inými kontaktmi. Nejde o verejný adresár, sociálnu sieť ani hodnotenie ľudí.', 'toptour-reference-finder' ); ?></p>
			<table class="widefat striped" style="max-width: 1200px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Súvisiaci kontakt', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Typ vzťahu', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Sila vzťahu', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Mutualita', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Trust note', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Odobrať', 'toptour-reference-finder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $relationship_form_rows as $index => $relationship_row ) : ?>
						<tr>
							<td>
								<select name="relationship_rows[<?php echo esc_attr( $index ); ?>][related_contact_id]">
									<option value="0"><?php esc_html_e( '- Vyber kontakt -', 'toptour-reference-finder' ); ?></option>
									<?php foreach ( $relationship_contact_options as $relationship_contact_option ) : ?>
										<?php if ( $form_id && absint( $relationship_contact_option->id ) === absint( $form_id ) ) {
											continue;
										} ?>
										<option value="<?php echo esc_attr( $relationship_contact_option->id ); ?>" <?php selected( absint( $relationship_row['related_contact_id'] ?? 0 ), absint( $relationship_contact_option->id ) ); ?>><?php echo esc_html( Toptour_Ref_Contact_Relationships::get_contact_label( absint( $relationship_contact_option->id ) ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="relationship_rows[<?php echo esc_attr( $index ); ?>][relationship_type]">
									<?php foreach ( $relationship_types as $relationship_type ) : ?>
										<option value="<?php echo esc_attr( $relationship_type ); ?>" <?php selected( $relationship_row['relationship_type'] ?? 'knows', $relationship_type ); ?>><?php echo esc_html( $relationship_type ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="relationship_rows[<?php echo esc_attr( $index ); ?>][relationship_strength]">
									<?php foreach ( $relationship_strengths as $relationship_strength ) : ?>
										<option value="<?php echo esc_attr( $relationship_strength ); ?>" <?php selected( $relationship_row['relationship_strength'] ?? 'medium', $relationship_strength ); ?>><?php echo esc_html( $relationship_strength ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="relationship_rows[<?php echo esc_attr( $index ); ?>][mutuality_level]">
									<?php foreach ( $relationship_mutuality_levels as $relationship_mutuality_level ) : ?>
										<option value="<?php echo esc_attr( $relationship_mutuality_level ); ?>" <?php selected( $relationship_row['mutuality_level'] ?? 'unknown', $relationship_mutuality_level ); ?>><?php echo esc_html( $relationship_mutuality_level ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<textarea rows="2" class="large-text" name="relationship_rows[<?php echo esc_attr( $index ); ?>][trust_note]"><?php echo esc_textarea( $relationship_row['trust_note'] ?? '' ); ?></textarea>
							</td>
							<td>
								<textarea rows="2" class="large-text" name="relationship_rows[<?php echo esc_attr( $index ); ?>][notes]"><?php echo esc_textarea( $relationship_row['notes'] ?? '' ); ?></textarea>
							</td>
							<td style="text-align: center;">
								<label>
									<input type="checkbox" name="relationship_rows[<?php echo esc_attr( $index ); ?>][remove]" value="1" <?php checked( ! empty( $relationship_row['remove'] ) ); ?>>
								</label>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Môžete upraviť existujúce riadky, pridať nové (voľné riadky) a označiť riadok na odstránenie. Pri uložení kontaktu sa vzťahy kontaktu nahradia.', 'toptour-reference-finder' ); ?></p>

			<?php if ( $form_id && $edit_contact ) : ?>
				<p class="description">
					<?php esc_html_e( 'Vytvorené:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_contact->created_at ); ?></strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Aktualizované:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_contact->updated_at ); ?></strong>
				</p>
			<?php endif; ?>

			<?php submit_button( $form_id ? __( 'Uložiť zmeny', 'toptour-reference-finder' ) : __( 'Pridať kontakt', 'toptour-reference-finder' ) ); ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť', 'toptour-reference-finder' ); ?></a>
		</form>
	<?php else : ?>
		<a href="<?php echo esc_url( add_query_arg( 'toptour_action', 'add', $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridať kontakt', 'toptour-reference-finder' ); ?></a>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="toptour-references-contacts">
			<div style="margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
				<select name="filter_contact_type">
					<option value=""><?php esc_html_e( '- Typ -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $contact_types as $contact_type ) : ?>
						<option value="<?php echo esc_attr( $contact_type ); ?>" <?php selected( $filter_contact_type, $contact_type ); ?>><?php echo esc_html( $contact_type ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_status">
					<option value=""><?php esc_html_e( '- Status -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $statuses as $status ) : ?>
						<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filter_status, $status ); ?>><?php echo esc_html( $status ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_trust_level">
					<option value=""><?php esc_html_e( '- Dôvera -', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $trust_levels as $trust_level ) : ?>
						<option value="<?php echo esc_attr( $trust_level ); ?>" <?php selected( $filter_trust_level, $trust_level ); ?>><?php echo esc_html( $trust_level ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="filter_country" value="<?php echo esc_attr( $filter_country ); ?>" placeholder="<?php esc_attr_e( 'Krajina', 'toptour-reference-finder' ); ?>">
				<input type="text" name="filter_region" value="<?php echo esc_attr( $filter_region ); ?>" placeholder="<?php esc_attr_e( 'Región', 'toptour-reference-finder' ); ?>">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Hľadať...', 'toptour-reference-finder' ); ?>">
				<?php submit_button( __( 'Filtrovať', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
				<?php if ( $filter_contact_type || $filter_status || $filter_trust_level || $filter_country || $filter_region || $search ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť filtre', 'toptour-reference-finder' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<p><?php printf( esc_html__( 'Celkom záznamov: %d', 'toptour-reference-finder' ), $total ); ?></p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Meno / Názov', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Typ', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Email', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Telefón', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Lokalita', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Záujmy', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Vplyv', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Vzťahy', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Rezident', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Dôvera', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Status', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Vytvorené', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $contacts ) : ?>
					<?php foreach ( $contacts as $contact ) :
						$edit_url = wp_nonce_url(
							add_query_arg(
								[
									'toptour_action' => 'edit',
									'contact_id'    => $contact->id,
								],
								$base_url
							),
							'toptour_edit_contact_' . $contact->id
						);
						$archive_url = wp_nonce_url(
							add_query_arg(
								[
									'toptour_action' => 'archive',
									'contact_id'    => $contact->id,
								],
								$base_url
							),
							'toptour_archive_contact_' . $contact->id
						);
						$location = trim( implode( ' / ', array_filter( [ $contact->country, $contact->region, $contact->city ] ) ) );
						$has_profile = isset( $resident_flags[ (int) $contact->id ] ) ? $resident_flags[ (int) $contact->id ] : false;
						$interest_names = isset( $interest_names_map[ (int) $contact->id ] ) ? $interest_names_map[ (int) $contact->id ] : [];
						if ( count( $interest_names ) > 5 ) {
							$interest_names = array_slice( $interest_names, 0, 5 );
							$interest_names[] = '...';
						}
						$interest_label = $interest_names ? implode( ', ', $interest_names ) : '—';
						$influence_count = isset( $influence_counts_map[ (int) $contact->id ] ) ? (int) $influence_counts_map[ (int) $contact->id ] : 0;
						$influence_label = Toptour_Ref_Contact_Influence::get_influence_summary_label( $influence_count );
						$relationship_count = isset( $relationship_counts_map[ (int) $contact->id ] ) ? (int) $relationship_counts_map[ (int) $contact->id ] : 0;
						$relationship_label = Toptour_Ref_Contact_Relationships::get_relationship_summary_label_from_count( $relationship_count );
					?>
					<tr>
						<td><?php echo esc_html( $contact->id ); ?></td>
						<td><?php echo esc_html( $contact->display_name ); ?></td>
						<td><?php echo esc_html( $contact->contact_type ); ?></td>
						<td><?php echo esc_html( $contact->email ); ?></td>
						<td><?php echo esc_html( $contact->phone ); ?></td>
						<td><?php echo esc_html( '' !== $location ? $location : '—' ); ?></td>
						<td><?php echo esc_html( $interest_label ); ?></td>
						<td><?php echo esc_html( $influence_label ); ?></td>
						<td><?php echo esc_html( $relationship_count > 0 ? $relationship_label : '—' ); ?></td>
						<td><?php echo esc_html( $has_profile ? 'áno' : 'nie' ); ?></td>
						<td><?php echo esc_html( $contact->trust_level ); ?></td>
						<td><?php echo esc_html( $contact->status ); ?></td>
						<td><?php echo esc_html( $contact->created_at ); ?></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a>
							<?php if ( 'archived' !== $contact->status ) : ?>
								&nbsp;|&nbsp;
								<a href="<?php echo esc_url( $archive_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Archivovať tento kontakt?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Archivovať', 'toptour-reference-finder' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="14"><?php esc_html_e( 'Žiadne záznamy.', 'toptour-reference-finder' ); ?></td>
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
								'page'                => 'toptour-references-contacts',
								'filter_contact_type' => $filter_contact_type,
								'filter_status'       => $filter_status,
								'filter_trust_level'  => $filter_trust_level,
								'filter_country'      => $filter_country,
								'filter_region'       => $filter_region,
								's'                   => $search,
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
