<?php
/**
 * TOPTOUR Reference Finder - Reference Sources View.
 *
 * Internal admin screen for manual source capture and credibility workflow.
 *
 * @package Toptour_Ref
 * @version 0.1.13
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
}

$base_url    = admin_url( 'admin.php?page=toptour-references-sources' );
$action      = isset( $_GET['toptour_action'] ) ? sanitize_text_field( wp_unslash( $_GET['toptour_action'] ) ) : '';
$edit_id     = isset( $_GET['source_id'] ) ? absint( $_GET['source_id'] ) : 0;
$notice      = '';
$notice_type = 'success';

if ( $action === 'edit' && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_edit_source_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}
}

if ( $action === 'archive' && $edit_id ) {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'toptour_archive_source_' . $edit_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$archived = Toptour_Ref_Reference_Sources::archive_source( $edit_id );
	$notice = $archived
		? __( 'Zdroj bol archivovaný.', 'toptour-reference-finder' )
		: __( 'Archivácia zlyhala.', 'toptour-reference-finder' );
	$notice_type = $archived ? 'success' : 'error';
	$action = '';
	$edit_id = 0;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_source_submit'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_save_source' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	}

	$post_id = absint( $_POST['source_id'] ?? 0 );
	$data = Toptour_Ref_Reference_Sources::sanitize_source_data( wp_unslash( $_POST ) );
	$valid = Toptour_Ref_Reference_Sources::validate_source_data( $data );

	if ( $valid === true ) {
		if ( $post_id > 0 ) {
			$ok = Toptour_Ref_Reference_Sources::update_source( $post_id, $data );
			$saved_id = $post_id;
		} else {
			$created = Toptour_Ref_Reference_Sources::create_source( $data );
			$ok = (bool) $created;
			$saved_id = (int) $created;
		}

		$notice = $ok
			? __( 'Zdroj bol uložený.', 'toptour-reference-finder' )
			: __( 'Zdroj sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = $ok ? 'success' : 'error';
		if ( $ok ) {
			$action = 'edit';
			$edit_id = $saved_id;
		} else {
			$action = $post_id ? 'edit' : 'add';
			$edit_id = $post_id;
		}
	} else {
		$notice = __( 'Zdroj sa nepodarilo uložiť. Skontrolujte povinné polia.', 'toptour-reference-finder' );
		$notice_type = 'error';
		$action = $post_id ? 'edit' : 'add';
		$edit_id = $post_id;
	}
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_source_mail_submit'] ) ) {
	$source_id = absint( $_POST['source_id'] ?? 0 );
	if ( $source_id <= 0 ) {
		$notice = __( 'Neplatný zdroj pre mail akciu.', 'toptour-reference-finder' );
		$notice_type = 'error';
	} elseif ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'toptour_source_mail_' . $source_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'toptour-reference-finder' ) );
	} else {
		$source = Toptour_Ref_Reference_Sources::get_source( $source_id );
		$template_key = sanitize_key( wp_unslash( $_POST['mail_template_key'] ?? '' ) );
		$recipient_email = sanitize_email( wp_unslash( $_POST['recipient_email'] ?? '' ) );
		$mail_action = sanitize_text_field( wp_unslash( $_POST['mail_action'] ?? '' ) );
		$rendered = $source ? Toptour_Ref_Mail_Templates::render_template(
			$template_key,
			[
				'source_title'                => $source->source_title,
				'source_url'                  => $source->source_url,
				'credibility_level'           => $source->credibility_level,
				'suggested_credibility_level' => $source->suggested_credibility_level,
				'suggestion_reason'           => $source->suggestion_reason,
				'access_status'               => $source->access_status,
				'search_priority'             => $source->search_priority,
				'next_action'                 => $source->next_action,
			]
		) : false;

		if ( ! $source || ! $rendered ) {
			$notice = __( 'Mail akciu sa nepodarilo vykonať. Chýba zdroj alebo šablóna.', 'toptour-reference-finder' );
			$notice_type = 'error';
		} elseif ( $mail_action === 'create_draft' ) {
			$mail_id = Toptour_Ref_Mail_Queue::create_mail(
				[
					'template_key'    => $template_key,
					'related_type'    => 'source',
					'related_id'      => $source_id,
					'recipient_email' => $recipient_email,
					'subject'         => $rendered['subject'],
					'body'            => $rendered['body'],
					'mail_status'     => 'draft',
				]
			);
			$notice = $mail_id
				? __( 'Mail draft bol vytvorený.', 'toptour-reference-finder' )
				: __( 'Mail draft sa nepodarilo vytvoriť.', 'toptour-reference-finder' );
			$notice_type = $mail_id ? 'success' : 'error';
		} elseif ( $mail_action === 'send_test' ) {
			if ( ! is_email( $recipient_email ) ) {
				$notice = __( 'Pre testovací mail musí byť zadaný platný recipient_email.', 'toptour-reference-finder' );
				$notice_type = 'error';
			} else {
				$mail = Toptour_Ref_Mail_Queue::get_last_mail_for_related( 'source', $source_id, $template_key );
				if ( $mail ) {
					Toptour_Ref_Mail_Queue::update_mail(
						(int) $mail->id,
						[
							'recipient_email' => $recipient_email,
							'subject'         => $rendered['subject'],
							'body'            => $rendered['body'],
							'mail_status'     => 'ready',
						]
					);
					$mail_id = (int) $mail->id;
				} else {
					$mail_id = Toptour_Ref_Mail_Queue::create_mail(
						[
							'template_key'    => $template_key,
							'related_type'    => 'source',
							'related_id'      => $source_id,
							'recipient_email' => $recipient_email,
							'subject'         => $rendered['subject'],
							'body'            => $rendered['body'],
							'mail_status'     => 'ready',
						]
					);
				}

				if ( ! $mail_id ) {
					$notice = __( 'Testovací mail sa nepodarilo pripraviť.', 'toptour-reference-finder' );
					$notice_type = 'error';
				} else {
					$send_result = Toptour_Ref_Mail_Queue::send_test_mail( $mail_id );
					if ( ! empty( $send_result['success'] ) ) {
						$notice = __( 'Testovací mail bol odoslaný.', 'toptour-reference-finder' );
						$notice_type = 'success';
					} else {
						$notice = __( 'Testovací mail sa nepodarilo odoslať.', 'toptour-reference-finder' );
						$notice_type = 'error';
					}
				}
			}
		}

		$action = 'edit';
		$edit_id = $source_id;
	}
}

$edit_source = null;
if ( in_array( $action, [ 'edit', 'add' ], true ) && $edit_id > 0 ) {
	$edit_source = Toptour_Ref_Reference_Sources::get_source( $edit_id );
}

$filter_source_type                 = isset( $_GET['filter_source_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_source_type'] ) ) : '';
$filter_source_origin               = isset( $_GET['filter_source_origin'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_source_origin'] ) ) : '';
$filter_target_type                 = isset( $_GET['filter_target_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_target_type'] ) ) : '';
$filter_credibility_level           = isset( $_GET['filter_credibility_level'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_credibility_level'] ) ) : '';
$filter_suggested_credibility_level = isset( $_GET['filter_suggested_credibility_level'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_suggested_credibility_level'] ) ) : '';
$filter_suggestion_status           = isset( $_GET['filter_suggestion_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_suggestion_status'] ) ) : '';
$filter_search_priority             = isset( $_GET['filter_search_priority'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_search_priority'] ) ) : '';
$filter_next_action                 = isset( $_GET['filter_next_action'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_next_action'] ) ) : '';
$filter_validation_status           = isset( $_GET['filter_validation_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_validation_status'] ) ) : '';
$filter_access_status               = isset( $_GET['filter_access_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_access_status'] ) ) : '';
$filter_source_platform             = isset( $_GET['filter_source_platform'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_source_platform'] ) ) : '';
$search                             = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page                       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result = Toptour_Ref_Reference_Sources::get_sources(
	[
		'source_type'                 => $filter_source_type,
		'source_origin'               => $filter_source_origin,
		'target_type'                 => $filter_target_type,
		'credibility_level'           => $filter_credibility_level,
		'suggested_credibility_level' => $filter_suggested_credibility_level,
		'suggestion_status'           => $filter_suggestion_status,
		'search_priority'             => $filter_search_priority,
		'next_action'                 => $filter_next_action,
		'validation_status'           => $filter_validation_status,
		'access_status'               => $filter_access_status,
		'source_platform'             => $filter_source_platform,
		'search'                      => $search,
		'page'                        => $current_page,
		'per_page'                    => 20,
	]
);

$sources = $result['sources'];
$total = $result['total'];
$total_pages = (int) ceil( $total / 20 );

$allowed_source_types = Toptour_Ref_Reference_Sources::get_allowed_source_types();
$allowed_source_origins = Toptour_Ref_Reference_Sources::get_allowed_source_origins();
$allowed_target_types = Toptour_Ref_Reference_Sources::get_allowed_target_types();
$allowed_credibility_levels = Toptour_Ref_Reference_Sources::get_allowed_credibility_levels();
$allowed_suggested_levels = Toptour_Ref_Reference_Sources::get_allowed_suggested_credibility_levels();
$allowed_verification_methods = Toptour_Ref_Reference_Sources::get_allowed_verification_methods();
$allowed_suggestion_statuses = Toptour_Ref_Reference_Sources::get_allowed_suggestion_statuses();
$allowed_search_priorities = Toptour_Ref_Reference_Sources::get_allowed_search_priorities();
$allowed_next_actions = Toptour_Ref_Reference_Sources::get_allowed_next_actions();
$allowed_validation_statuses = Toptour_Ref_Reference_Sources::get_allowed_validation_statuses();
$allowed_access_statuses = Toptour_Ref_Reference_Sources::get_allowed_access_statuses();

$mail_template_options = [
	'source_credibility_review_request' => 'source_credibility_review_request',
	'source_access_problem'             => 'source_access_problem',
	'source_priority_review'            => 'source_priority_review',
];
?>

<div class="wrap toptour-ref-sources">
	<h1><?php esc_html_e( 'Referenčné zdroje', 'toptour-reference-finder' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Interná evidencia zdrojov, dôveryhodnosti a manuálneho mailového workflow bez automatického odosielania.', 'toptour-reference-finder' ); ?></p>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $action === 'add' || $action === 'edit' ) : ?>
		<?php
		$s = $edit_source;
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_source_submit'] ) ) {
			$s = (object) Toptour_Ref_Reference_Sources::sanitize_source_data( wp_unslash( $_POST ) );
		}
		$form_id = $edit_id;
		?>
		<h2><?php echo $form_id ? esc_html__( 'Upraviť zdroj', 'toptour-reference-finder' ) : esc_html__( 'Pridať zdroj', 'toptour-reference-finder' ); ?></h2>
		<form method="post" action="<?php echo esc_url( $base_url ); ?>">
			<?php wp_nonce_field( 'toptour_save_source' ); ?>
			<input type="hidden" name="toptour_source_submit" value="1">
			<input type="hidden" name="source_id" value="<?php echo esc_attr( $form_id ); ?>">

			<h3><?php esc_html_e( '1. Základ zdroja', 'toptour-reference-finder' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="source_title"><?php esc_html_e( 'Názov zdroja', 'toptour-reference-finder' ); ?> *</label></th>
					<td><input type="text" id="source_title" name="source_title" class="large-text" maxlength="255" required value="<?php echo esc_attr( $s->source_title ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="source_url"><?php esc_html_e( 'URL zdroja', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="url" id="source_url" name="source_url" class="large-text" value="<?php echo esc_attr( $s->source_url ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="source_platform"><?php esc_html_e( 'Platforma', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="source_platform" name="source_platform" class="regular-text" maxlength="120" value="<?php echo esc_attr( $s->source_platform ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="source_type"><?php esc_html_e( 'Typ zdroja', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="source_type" name="source_type">
							<?php foreach ( $allowed_source_types as $source_type ) : ?>
								<option value="<?php echo esc_attr( $source_type ); ?>" <?php selected( $s->source_type ?? 'review', $source_type ); ?>><?php echo esc_html( $source_type ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="source_origin"><?php esc_html_e( 'Pôvod zdroja', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="source_origin" name="source_origin">
							<?php foreach ( $allowed_source_origins as $source_origin ) : ?>
								<option value="<?php echo esc_attr( $source_origin ); ?>" <?php selected( $s->source_origin ?? 'unknown', $source_origin ); ?>><?php echo esc_html( $source_origin ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="language"><?php esc_html_e( 'Jazyk', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="language" name="language" class="regular-text" maxlength="50" value="<?php echo esc_attr( $s->language ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="captured_at"><?php esc_html_e( 'Captured at', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="captured_at" name="captured_at" class="regular-text" value="<?php echo esc_attr( $s->captured_at ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="source_date"><?php esc_html_e( 'Source date', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="source_date" name="source_date" class="regular-text" value="<?php echo esc_attr( $s->source_date ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="external_rating"><?php esc_html_e( 'External rating', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="external_rating" name="external_rating" class="regular-text" maxlength="80" value="<?php echo esc_attr( $s->external_rating ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="external_review_count"><?php esc_html_e( 'External review count', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" min="0" id="external_review_count" name="external_review_count" class="small-text" value="<?php echo esc_attr( absint( $s->external_review_count ?? 0 ) ); ?>"></td>
				</tr>
			</table>

			<h3><?php esc_html_e( '2. Cieľ zdroja', 'toptour-reference-finder' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="target_type"><?php esc_html_e( 'Target type', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="target_type" name="target_type">
							<?php foreach ( $allowed_target_types as $target_type ) : ?>
								<option value="<?php echo esc_attr( $target_type ); ?>" <?php selected( $s->target_type ?? 'general', $target_type ); ?>><?php echo esc_html( $target_type ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="target_id"><?php esc_html_e( 'Target ID', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" min="0" id="target_id" name="target_id" class="small-text" value="<?php echo esc_attr( absint( $s->target_id ?? 0 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="collection_task_id"><?php esc_html_e( 'Collection task ID', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" min="0" id="collection_task_id" name="collection_task_id" class="small-text" value="<?php echo esc_attr( absint( $s->collection_task_id ?? 0 ) ); ?>"></td>
				</tr>
			</table>

			<h3><?php esc_html_e( '3. Dôveryhodnosť a overenie', 'toptour-reference-finder' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="credibility_level"><?php esc_html_e( 'Credibility level', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="credibility_level" name="credibility_level">
							<?php foreach ( $allowed_credibility_levels as $credibility_level ) : ?>
								<option value="<?php echo esc_attr( $credibility_level ); ?>" <?php selected( $s->credibility_level ?? 'unknown', $credibility_level ); ?>><?php echo esc_html( $credibility_level ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="credibility_reason"><?php esc_html_e( 'Credibility reason', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="credibility_reason" name="credibility_reason" rows="3" class="large-text"><?php echo esc_textarea( $s->credibility_reason ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="credibility_updated_at"><?php esc_html_e( 'Credibility updated at', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="credibility_updated_at" name="credibility_updated_at" class="regular-text" value="<?php echo esc_attr( $s->credibility_updated_at ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="verification_method"><?php esc_html_e( 'Verification method', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="verification_method" name="verification_method">
							<?php foreach ( $allowed_verification_methods as $verification_method ) : ?>
								<option value="<?php echo esc_attr( $verification_method ); ?>" <?php selected( $s->verification_method ?? 'manual', $verification_method ); ?>><?php echo esc_html( $verification_method ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="verification_notes"><?php esc_html_e( 'Verification notes', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="verification_notes" name="verification_notes" rows="3" class="large-text"><?php echo esc_textarea( $s->verification_notes ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="last_verified_at"><?php esc_html_e( 'Last verified at', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="last_verified_at" name="last_verified_at" class="regular-text" value="<?php echo esc_attr( $s->last_verified_at ?? '' ); ?>"></td>
				</tr>
			</table>

			<h3><?php esc_html_e( '4. Návrh zmeny dôveryhodnosti', 'toptour-reference-finder' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="suggested_credibility_level"><?php esc_html_e( 'Suggested credibility level', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="suggested_credibility_level" name="suggested_credibility_level">
							<?php foreach ( $allowed_suggested_levels as $suggested_level ) : ?>
								<option value="<?php echo esc_attr( $suggested_level ); ?>" <?php selected( $s->suggested_credibility_level ?? '', $suggested_level ); ?>><?php echo esc_html( $suggested_level === '' ? '—' : $suggested_level ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="suggestion_reason"><?php esc_html_e( 'Suggestion reason', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="suggestion_reason" name="suggestion_reason" rows="3" class="large-text"><?php echo esc_textarea( $s->suggestion_reason ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="suggestion_status"><?php esc_html_e( 'Suggestion status', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="suggestion_status" name="suggestion_status">
							<?php foreach ( $allowed_suggestion_statuses as $suggestion_status ) : ?>
								<option value="<?php echo esc_attr( $suggestion_status ); ?>" <?php selected( $s->suggestion_status ?? 'none', $suggestion_status ); ?>><?php echo esc_html( $suggestion_status ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="suggestion_created_at"><?php esc_html_e( 'Suggestion created at', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="suggestion_created_at" name="suggestion_created_at" class="regular-text" value="<?php echo esc_attr( $s->suggestion_created_at ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="suggestion_resolved_at"><?php esc_html_e( 'Suggestion resolved at', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="suggestion_resolved_at" name="suggestion_resolved_at" class="regular-text" value="<?php echo esc_attr( $s->suggestion_resolved_at ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="suggestion_reviewed_by"><?php esc_html_e( 'Suggestion reviewed by (user ID)', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" min="0" id="suggestion_reviewed_by" name="suggestion_reviewed_by" class="small-text" value="<?php echo esc_attr( absint( $s->suggestion_reviewed_by ?? 0 ) ); ?>"></td>
				</tr>
			</table>

			<h3><?php esc_html_e( '5. Priorita a ďalší krok', 'toptour-reference-finder' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="search_priority"><?php esc_html_e( 'Search priority', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="search_priority" name="search_priority">
							<?php foreach ( $allowed_search_priorities as $search_priority ) : ?>
								<option value="<?php echo esc_attr( $search_priority ); ?>" <?php selected( $s->search_priority ?? 'normal', $search_priority ); ?>><?php echo esc_html( $search_priority ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="next_action"><?php esc_html_e( 'Next action', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="next_action" name="next_action">
							<?php foreach ( $allowed_next_actions as $next_action ) : ?>
								<option value="<?php echo esc_attr( $next_action ); ?>" <?php selected( $s->next_action ?? 'review_source', $next_action ); ?>><?php echo esc_html( $next_action ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="validation_status"><?php esc_html_e( 'Validation status', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="validation_status" name="validation_status">
							<?php foreach ( $allowed_validation_statuses as $validation_status ) : ?>
								<option value="<?php echo esc_attr( $validation_status ); ?>" <?php selected( $s->validation_status ?? 'new', $validation_status ); ?>><?php echo esc_html( $validation_status ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="access_status"><?php esc_html_e( 'Access status', 'toptour-reference-finder' ); ?></label></th>
					<td>
						<select id="access_status" name="access_status">
							<?php foreach ( $allowed_access_statuses as $access_status ) : ?>
								<option value="<?php echo esc_attr( $access_status ); ?>" <?php selected( $s->access_status ?? 'unknown', $access_status ); ?>><?php echo esc_html( $access_status ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( '6. Poznámky', 'toptour-reference-finder' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="notes"><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></label></th>
					<td><textarea id="notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $s->notes ?? '' ); ?></textarea></td>
				</tr>
			</table>

			<?php if ( $form_id > 0 && $edit_source ) : ?>
				<p class="description">
					<?php esc_html_e( 'Vytvorené:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_source->created_at ); ?></strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Aktualizované:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $edit_source->updated_at ); ?></strong>
				</p>
			<?php endif; ?>

			<?php submit_button( $form_id ? __( 'Uložiť zmeny', 'toptour-reference-finder' ) : __( 'Pridať zdroj', 'toptour-reference-finder' ) ); ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Späť na zoznam', 'toptour-reference-finder' ); ?></a>
		</form>

		<?php if ( $form_id > 0 ) : ?>
			<hr>
			<h3><?php esc_html_e( 'Manažérske upozornenie', 'toptour-reference-finder' ); ?></h3>
			<form method="post" action="<?php echo esc_url( $base_url ); ?>">
				<?php wp_nonce_field( 'toptour_source_mail_' . $form_id ); ?>
				<input type="hidden" name="toptour_source_mail_submit" value="1">
				<input type="hidden" name="source_id" value="<?php echo esc_attr( $form_id ); ?>">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="mail_template_key"><?php esc_html_e( 'Šablóna', 'toptour-reference-finder' ); ?></label></th>
						<td>
							<select id="mail_template_key" name="mail_template_key">
								<?php foreach ( $mail_template_options as $template_key => $template_label ) : ?>
									<option value="<?php echo esc_attr( $template_key ); ?>"><?php echo esc_html( $template_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="recipient_email"><?php esc_html_e( 'Recipient email', 'toptour-reference-finder' ); ?></label></th>
						<td><input type="email" id="recipient_email" name="recipient_email" class="regular-text" value=""></td>
					</tr>
				</table>
				<button type="submit" name="mail_action" value="create_draft" class="button button-secondary"><?php esc_html_e( 'Vytvoriť mail draft', 'toptour-reference-finder' ); ?></button>
				<button type="submit" name="mail_action" value="send_test" class="button button-primary"><?php esc_html_e( 'Odoslať testovací mail', 'toptour-reference-finder' ); ?></button>
			</form>
		<?php endif; ?>

	<?php else : ?>

		<a href="<?php echo esc_url( add_query_arg( 'toptour_action', 'add', $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridať zdroj', 'toptour-reference-finder' ); ?></a>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="toptour-references-sources">
			<div style="margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
				<select name="filter_source_type">
					<option value=""><?php esc_html_e( '— Typ —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_source_types as $source_type ) : ?>
						<option value="<?php echo esc_attr( $source_type ); ?>" <?php selected( $filter_source_type, $source_type ); ?>><?php echo esc_html( $source_type ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_source_origin">
					<option value=""><?php esc_html_e( '— Pôvod —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_source_origins as $source_origin ) : ?>
						<option value="<?php echo esc_attr( $source_origin ); ?>" <?php selected( $filter_source_origin, $source_origin ); ?>><?php echo esc_html( $source_origin ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_target_type">
					<option value=""><?php esc_html_e( '— Cieľ —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_target_types as $target_type ) : ?>
						<option value="<?php echo esc_attr( $target_type ); ?>" <?php selected( $filter_target_type, $target_type ); ?>><?php echo esc_html( $target_type ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_credibility_level">
					<option value=""><?php esc_html_e( '— Dôveryhodnosť —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_credibility_levels as $credibility_level ) : ?>
						<option value="<?php echo esc_attr( $credibility_level ); ?>" <?php selected( $filter_credibility_level, $credibility_level ); ?>><?php echo esc_html( $credibility_level ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_suggested_credibility_level">
					<option value=""><?php esc_html_e( '— Návrh dôveryhodnosti —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_suggested_levels as $suggested_level ) : if ( $suggested_level === '' ) { continue; } ?>
						<option value="<?php echo esc_attr( $suggested_level ); ?>" <?php selected( $filter_suggested_credibility_level, $suggested_level ); ?>><?php echo esc_html( $suggested_level ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_suggestion_status">
					<option value=""><?php esc_html_e( '— Stav návrhu —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_suggestion_statuses as $suggestion_status ) : ?>
						<option value="<?php echo esc_attr( $suggestion_status ); ?>" <?php selected( $filter_suggestion_status, $suggestion_status ); ?>><?php echo esc_html( $suggestion_status ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_search_priority">
					<option value=""><?php esc_html_e( '— Priorita —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_search_priorities as $search_priority ) : ?>
						<option value="<?php echo esc_attr( $search_priority ); ?>" <?php selected( $filter_search_priority, $search_priority ); ?>><?php echo esc_html( $search_priority ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_next_action">
					<option value=""><?php esc_html_e( '— Ďalší krok —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_next_actions as $next_action ) : ?>
						<option value="<?php echo esc_attr( $next_action ); ?>" <?php selected( $filter_next_action, $next_action ); ?>><?php echo esc_html( $next_action ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_validation_status">
					<option value=""><?php esc_html_e( '— Validácia —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_validation_statuses as $validation_status ) : ?>
						<option value="<?php echo esc_attr( $validation_status ); ?>" <?php selected( $filter_validation_status, $validation_status ); ?>><?php echo esc_html( $validation_status ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="filter_access_status">
					<option value=""><?php esc_html_e( '— Prístup —', 'toptour-reference-finder' ); ?></option>
					<?php foreach ( $allowed_access_statuses as $access_status ) : ?>
						<option value="<?php echo esc_attr( $access_status ); ?>" <?php selected( $filter_access_status, $access_status ); ?>><?php echo esc_html( $access_status ); ?></option>
					<?php endforeach; ?>
				</select>

				<input type="text" name="filter_source_platform" value="<?php echo esc_attr( $filter_source_platform ); ?>" placeholder="<?php esc_attr_e( 'Platforma', 'toptour-reference-finder' ); ?>" style="width:140px">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Hľadať...', 'toptour-reference-finder' ); ?>">
				<?php submit_button( __( 'Filtrovať', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
				<?php if ( $filter_source_type || $filter_source_origin || $filter_target_type || $filter_credibility_level || $filter_suggested_credibility_level || $filter_suggestion_status || $filter_search_priority || $filter_next_action || $filter_validation_status || $filter_access_status || $filter_source_platform || $search ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Zrušiť filtre', 'toptour-reference-finder' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<p><?php printf( esc_html__( 'Celkom záznamov: %d', 'toptour-reference-finder' ), $total ); ?></p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 50px;"><?php esc_html_e( 'ID', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Názov zdroja', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Typ', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Pôvod', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Platforma', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Cieľ', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Dôveryhodnosť', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Návrh', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Priorita', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Ďalší krok', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Validácia', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Prístup', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Zachytené', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $sources ) : ?>
					<?php foreach ( $sources as $source ) :
						$edit_url = wp_nonce_url(
							add_query_arg(
								[
									'toptour_action' => 'edit',
									'source_id'      => $source->id,
								],
								$base_url
							),
							'toptour_edit_source_' . $source->id
						);
						$archive_url = wp_nonce_url(
							add_query_arg(
								[
									'toptour_action' => 'archive',
									'source_id'      => $source->id,
								],
								$base_url
							),
							'toptour_archive_source_' . $source->id
						);
						$target_label = Toptour_Ref_Reference_Sources::get_target_label( $source->target_type, (int) $source->target_id );
						$suggestion_label = trim( $source->suggested_credibility_level . ' / ' . $source->suggestion_status, ' /' );
						if ( $suggestion_label === '' ) {
							$suggestion_label = '—';
						} elseif ( $source->suggestion_status === 'manager_review' ) {
							$suggestion_label .= ' (čaká na manažéra)';
						}
					?>
						<tr>
							<td><?php echo esc_html( $source->id ); ?></td>
							<td><?php echo esc_html( $source->source_title ); ?></td>
							<td><?php echo esc_html( $source->source_type ); ?></td>
							<td><?php echo esc_html( $source->source_origin ); ?></td>
							<td><?php echo esc_html( $source->source_platform ); ?></td>
							<td><?php echo esc_html( $target_label ); ?></td>
							<td><?php echo esc_html( $source->credibility_level ); ?></td>
							<td><?php echo esc_html( $suggestion_label ); ?></td>
							<td><?php echo esc_html( $source->search_priority ); ?></td>
							<td><?php echo esc_html( $source->next_action ); ?></td>
							<td><?php echo esc_html( $source->validation_status ); ?></td>
							<td><?php echo esc_html( $source->access_status ); ?></td>
							<td><?php echo esc_html( $source->captured_at ? $source->captured_at : '—' ); ?></td>
							<td>
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a>
								<?php if ( $source->validation_status !== 'archived' ) : ?>
									&nbsp;|&nbsp;
									<a href="<?php echo esc_url( $archive_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Archivovať tento zdroj?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Archivovať', 'toptour-reference-finder' ); ?></a>
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
								'page'                              => 'toptour-references-sources',
								'filter_source_type'                => $filter_source_type,
								'filter_source_origin'              => $filter_source_origin,
								'filter_target_type'                => $filter_target_type,
								'filter_credibility_level'          => $filter_credibility_level,
								'filter_suggested_credibility_level'=> $filter_suggested_credibility_level,
								'filter_suggestion_status'          => $filter_suggestion_status,
								'filter_search_priority'            => $filter_search_priority,
								'filter_next_action'                => $filter_next_action,
								'filter_validation_status'          => $filter_validation_status,
								'filter_access_status'              => $filter_access_status,
								'filter_source_platform'            => $filter_source_platform,
								's'                                 => $search,
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
