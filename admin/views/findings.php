<?php
/**
 * Admin view: Findings.
 *
 * Internal evidence records.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_toptour_references' ) ) {
	wp_die( esc_html__( 'Nemáte oprávnenie na túto stránku.', 'toptour-reference-finder' ) );
}

$message    = '';
$error      = '';
$form_id    = isset( $_GET['finding_id'] ) ? absint( $_GET['finding_id'] ) : 0;
$show_form  = isset( $_GET['action'] ) && in_array( $_GET['action'], [ 'add', 'edit' ], true );

// ── Archive action ─────────────────────────────────────────────────────────────
if ( isset( $_GET['action'] ) && $_GET['action'] === 'archive' && isset( $_GET['finding_id'] ) ) {
	$arch_id = absint( $_GET['finding_id'] );
	check_admin_referer( 'toptour_archive_finding_' . $arch_id );
	$archived = Toptour_Ref_Findings::archive_finding( $arch_id );
	$message  = $archived ? __( 'Zistenie bolo archivované.', 'toptour-reference-finder' ) : '';
	$error    = ! $archived ? __( 'Archivovanie zlyhalo.', 'toptour-reference-finder' ) : '';
	$show_form = false;
}

// ── Save action (POST) ─────────────────────────────────────────────────────────
if ( isset( $_POST['toptour_finding_nonce'] ) ) {
	check_admin_referer( 'toptour_save_finding', 'toptour_finding_nonce' );

	$raw  = wp_unslash( $_POST );
	$data = Toptour_Ref_Findings::sanitize_finding_data( $raw );
	$validation = Toptour_Ref_Findings::validate_finding_data( $data );

	if ( $validation !== true ) {
		$error     = implode( ' | ', $validation );
		$show_form = true;
	} else {
		$post_id = absint( $raw['finding_id'] ?? 0 );
		if ( $post_id > 0 ) {
			$ok      = Toptour_Ref_Findings::update_finding( $post_id, $data );
			$message = $ok ? __( 'Zistenie bolo uložené.', 'toptour-reference-finder' ) : '';
			$error   = ! $ok ? __( 'Ukladanie zlyhalo.', 'toptour-reference-finder' ) : '';
			$form_id   = $post_id;
			$show_form = true;
		} else {
			$new_id = Toptour_Ref_Findings::create_finding( $data );
			if ( $new_id ) {
				$message = __( 'Zistenie bolo vytvorené.', 'toptour-reference-finder' );
				wp_redirect( add_query_arg( [
					'page'       => 'toptour-references-findings',
					'action'     => 'edit',
					'finding_id' => $new_id,
					'saved'      => 1,
				], admin_url( 'admin.php' ) ) );
				exit;
			} else {
				$error     = __( 'Vytvorenie zlyhalo.', 'toptour-reference-finder' );
				$show_form = true;
			}
		}
	}
}

// Redirect notice after create
if ( isset( $_GET['saved'] ) && absint( $_GET['saved'] ) === 1 ) {
	$message = __( 'Zistenie bolo vytvorené.', 'toptour-reference-finder' );
}

// ── Load edit record ───────────────────────────────────────────────────────────
$finding = null;
if ( $show_form && $form_id > 0 ) {
	$finding = Toptour_Ref_Findings::get_finding( $form_id );
	if ( ! $finding ) {
		$error    = __( 'Zistenie nenájdené.', 'toptour-reference-finder' );
		$show_form = false;
	}
}

// ── Helper: decode POST re-population ─────────────────────────────────────────
function toptour_finding_field( $field, $finding, $post_data, $default = '' ) {
	if ( isset( $post_data['toptour_finding_nonce'] ) ) {
		return sanitize_text_field( wp_unslash( (string) ( $post_data[ $field ] ?? $default ) ) );
	}
	if ( $finding && isset( $finding->$field ) ) {
		return (string) $finding->$field;
	}
	return $default;
}

function toptour_finding_textarea( $field, $finding, $post_data, $default = '' ) {
	if ( isset( $post_data['toptour_finding_nonce'] ) ) {
		return sanitize_textarea_field( wp_unslash( (string) ( $post_data[ $field ] ?? $default ) ) );
	}
	if ( $finding && isset( $finding->$field ) ) {
		return (string) $finding->$field;
	}
	return $default;
}

if ( ! function_exists( 'toptour_finding_format_datetime' ) ) {
	function toptour_finding_format_datetime( $value ) {
		if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
			return '—';
		}
		$timestamp = strtotime( (string) $value );
		if ( ! $timestamp ) {
			return '—';
		}
		return date_i18n( 'd.m.Y H:i', $timestamp );
	}
}

if ( ! function_exists( 'toptour_finding_translate_placeholder_text' ) ) {
	function toptour_finding_translate_placeholder_text( $text ) {
		$clean = sanitize_textarea_field( (string) $text );
		$map = [
			'Internal run placeholder created for lifecycle verification.' => 'Testovací záznam vytvorený na overenie životného cyklu úlohy.',
			'Internal analysis placeholder for task #' => 'Testovacie analytické zistenie pre úlohu #',
			'Internal placeholder analysis only. No external scraping or citation storage.' => 'Testovací analytický záznam. Externý zber ešte nie je zapnutý.',
			'No automatic POI extraction in this phase.' => 'Automatická extrakcia bodov záujmu zatiaľ nie je aktívna.',
			'Testovaci analyticky zaznam. Externy zber este nie je zapnuty.' => 'Testovací analytický záznam. Externý zber ešte nie je zapnutý.',
			'Testovacie analyticke zistenie pre ulohu #' => 'Testovacie analytické zistenie pre úlohu #',
			'Automaticka extrakcia bodov zaujmu zatial nie je aktivna.' => 'Automatická extrakcia bodov záujmu zatiaľ nie je aktívna.',
			'Testovaci zaznam vytvoreny na overenie zivotneho cyklu ulohy.' => 'Testovací záznam vytvorený na overenie životného cyklu úlohy.',
		];

		if ( isset( $map[ $clean ] ) ) {
			return $map[ $clean ];
		}

		if ( strpos( $clean, 'Internal analysis placeholder for task #' ) === 0 ) {
			return str_replace( 'Internal analysis placeholder for task #', 'Testovacie analytické zistenie pre úlohu #', $clean );
		}

		if ( strpos( $clean, 'Testovacie analyticke zistenie pre ulohu #' ) === 0 ) {
			return str_replace( 'Testovacie analyticke zistenie pre ulohu #', 'Testovacie analytické zistenie pre úlohu #', $clean );
		}

		return $clean;
	}
}

?>
<div class="wrap">
<h1>
	<?php esc_html_e( 'Zistenia', 'toptour-reference-finder' ); ?>
	<?php if ( ! $show_form ) : ?>
		<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'toptour-references-findings', 'action' => 'add' ], admin_url( 'admin.php' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Pridať nové', 'toptour-reference-finder' ); ?></a>
	<?php endif; ?>
</h1>

<?php if ( $message ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
<?php endif; ?>
<?php if ( $error ) : ?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
<?php endif; ?>

<?php if ( $show_form ) : ?>
<!-- ════════════════ FORM ════════════════ -->
<?php
$f_title      = toptour_finding_field( 'finding_title', $finding, $_POST );
$f_source_id  = toptour_finding_field( 'source_id', $finding, $_POST, '0' );
$f_sp_id      = toptour_finding_field( 'signal_pattern_id', $finding, $_POST, '0' );
$f_ttype      = toptour_finding_field( 'target_type', $finding, $_POST, 'general' );
$f_tid        = toptour_finding_field( 'target_id', $finding, $_POST, '0' );
$f_ftype      = toptour_finding_field( 'finding_type', $finding, $_POST, 'neutral' );
$f_farea      = toptour_finding_field( 'finding_area', $finding, $_POST, '' );
$f_strength   = toptour_finding_field( 'signal_strength', $finding, $_POST, 'medium' );
$f_repetition = toptour_finding_field( 'repetition_level', $finding, $_POST, 'single' );
$f_vstatus    = toptour_finding_field( 'verification_status', $finding, $_POST, 'new' );
$f_etype      = toptour_finding_field( 'evidence_type', $finding, $_POST, 'text' );
$f_excerpt    = toptour_finding_textarea( 'evidence_excerpt', $finding, $_POST );
$f_eurl       = toptour_finding_field( 'evidence_url', $finding, $_POST );
$f_observed   = toptour_finding_field( 'observed_at', $finding, $_POST );
$f_r_name     = toptour_finding_field( 'reviewer_name', $finding, $_POST );
$f_r_origin   = toptour_finding_field( 'reviewer_origin', $finding, $_POST );
$f_language   = toptour_finding_field( 'language', $finding, $_POST );
$f_task_id    = toptour_finding_field( 'related_collection_task_id', $finding, $_POST, '0' );
$f_notes      = toptour_finding_textarea( 'notes', $finding, $_POST );

$sources  = Toptour_Ref_Findings::get_active_sources_for_select();
$patterns = Toptour_Ref_Findings::get_active_signal_patterns_for_select();
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=toptour-references-findings' ) ); ?>">
<?php wp_nonce_field( 'toptour_save_finding', 'toptour_finding_nonce' ); ?>
<input type="hidden" name="finding_id" value="<?php echo esc_attr( $form_id ); ?>">

<!-- Section 1: Základ zistenia -->
<h2><?php esc_html_e( 'Základ zistenia', 'toptour-reference-finder' ); ?></h2>
<table class="form-table">
	<tr>
		<th><label for="finding_title"><?php esc_html_e( 'Názov zistenia', 'toptour-reference-finder' ); ?> *</label></th>
		<td><input type="text" name="finding_title" id="finding_title" value="<?php echo esc_attr( $f_title ); ?>" class="regular-text" required></td>
	</tr>
</table>

<!-- Section 2: Zdroj a cieľ -->
<h2><?php esc_html_e( 'Zdroj a cieľ', 'toptour-reference-finder' ); ?></h2>
<table class="form-table">
	<tr>
		<th><label for="source_id"><?php esc_html_e( 'Zdroj (source_id)', 'toptour-reference-finder' ); ?></label></th>
		<td>
			<select name="source_id" id="source_id">
				<option value="0"><?php esc_html_e( '— bez zdroja —', 'toptour-reference-finder' ); ?></option>
				<?php foreach ( $sources as $src ) : ?>
					<option value="<?php echo esc_attr( $src->id ); ?>" <?php selected( (int) $f_source_id, (int) $src->id ); ?>><?php echo esc_html( $src->source_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="target_type"><?php esc_html_e( 'Typ cieľa', 'toptour-reference-finder' ); ?></label></th>
		<td>
			<select name="target_type" id="target_type">
				<?php foreach ( Toptour_Ref_Findings::get_allowed_target_types() as $tt ) : ?>
					<option value="<?php echo esc_attr( $tt ); ?>" <?php selected( $f_ttype, $tt ); ?>><?php echo esc_html( Toptour_Ref_Labels::target_type_label( $tt ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="target_id"><?php esc_html_e( 'Cieľ ID', 'toptour-reference-finder' ); ?></label></th>
		<td><input type="number" name="target_id" id="target_id" value="<?php echo esc_attr( $f_tid ); ?>" min="0" class="small-text">
		<?php if ( $form_id > 0 && $finding && $finding->target_type !== 'general' ) : ?>
			<span class="description"><?php echo esc_html( Toptour_Ref_Findings::get_target_label( $finding->target_type, $finding->target_id ) ); ?></span>
		<?php endif; ?></td>
	</tr>
</table>

<!-- Section 3: Klasifikácia signálu -->
<h2><?php esc_html_e( 'Klasifikácia signálu', 'toptour-reference-finder' ); ?></h2>
<table class="form-table">
	<tr>
		<th><label for="signal_pattern_id"><?php esc_html_e( 'Signal pattern', 'toptour-reference-finder' ); ?></label></th>
		<td>
			<select name="signal_pattern_id" id="signal_pattern_id">
				<option value="0"><?php esc_html_e( '— žiadny —', 'toptour-reference-finder' ); ?></option>
				<?php foreach ( $patterns as $pat ) : ?>
					<option value="<?php echo esc_attr( $pat->id ); ?>" <?php selected( (int) $f_sp_id, (int) $pat->id ); ?>><?php echo esc_html( $pat->name ?: $pat->pattern_key ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="finding_type"><?php esc_html_e( 'Typ zistenia', 'toptour-reference-finder' ); ?></label></th>
		<td>
			<select name="finding_type" id="finding_type">
				<?php foreach ( Toptour_Ref_Findings::get_allowed_finding_types() as $ft ) : ?>
					<option value="<?php echo esc_attr( $ft ); ?>" <?php selected( $f_ftype, $ft ); ?>><?php echo esc_html( Toptour_Ref_Labels::finding_type_label( $ft ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="finding_area"><?php esc_html_e( 'Oblasť', 'toptour-reference-finder' ); ?></label></th>
		<td>
			<select name="finding_area" id="finding_area">
				<option value=""><?php esc_html_e( '— neurčená —', 'toptour-reference-finder' ); ?></option>
				<?php foreach ( Toptour_Ref_Findings::get_allowed_finding_areas() as $fa ) : ?>
					<option value="<?php echo esc_attr( $fa ); ?>" <?php selected( $f_farea, $fa ); ?>><?php echo esc_html( Toptour_Ref_Labels::finding_area_label( $fa ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="signal_strength"><?php esc_html_e( 'Sila signálu', 'toptour-reference-finder' ); ?></label></th>
		<td>
			<select name="signal_strength" id="signal_strength">
				<?php foreach ( Toptour_Ref_Findings::get_allowed_signal_strengths() as $ss ) : ?>
					<option value="<?php echo esc_attr( $ss ); ?>" <?php selected( $f_strength, $ss ); ?>><?php echo esc_html( Toptour_Ref_Labels::signal_strength_label( $ss ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="repetition_level"><?php esc_html_e( 'Opakovanie', 'toptour-reference-finder' ); ?></label></th>
		<td>
			<select name="repetition_level" id="repetition_level">
				<?php foreach ( Toptour_Ref_Findings::get_allowed_repetition_levels() as $rl ) : ?>
					<option value="<?php echo esc_attr( $rl ); ?>" <?php selected( $f_repetition, $rl ); ?>><?php echo esc_html( Toptour_Ref_Labels::repetition_level_label( $rl ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="verification_status"><?php esc_html_e( 'Stav overenia', 'toptour-reference-finder' ); ?></label></th>
		<td>
			<select name="verification_status" id="verification_status">
				<?php foreach ( Toptour_Ref_Findings::get_allowed_verification_statuses() as $vs ) : ?>
					<option value="<?php echo esc_attr( $vs ); ?>" <?php selected( $f_vstatus, $vs ); ?>><?php echo esc_html( Toptour_Ref_Labels::verification_status_label( $vs ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
</table>

<!-- Section 4: Dôkaz -->
<h2><?php esc_html_e( 'Dôkaz', 'toptour-reference-finder' ); ?></h2>
<table class="form-table">
	<tr>
		<th><label for="evidence_type"><?php esc_html_e( 'Typ dôkazu', 'toptour-reference-finder' ); ?></label></th>
		<td>
			<select name="evidence_type" id="evidence_type">
				<?php foreach ( Toptour_Ref_Findings::get_allowed_evidence_types() as $et ) : ?>
					<option value="<?php echo esc_attr( $et ); ?>" <?php selected( $f_etype, $et ); ?>><?php echo esc_html( Toptour_Ref_Labels::evidence_type_label( $et ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="evidence_excerpt"><?php esc_html_e( 'Výňatok / citácia', 'toptour-reference-finder' ); ?></label></th>
		<td><textarea name="evidence_excerpt" id="evidence_excerpt" class="large-text" rows="4"><?php echo esc_textarea( $f_excerpt ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Interná poznámka – nie je verejná.', 'toptour-reference-finder' ); ?></p></td>
	</tr>
	<tr>
		<th><label for="evidence_url"><?php esc_html_e( 'URL dôkazu', 'toptour-reference-finder' ); ?></label></th>
		<td><input type="url" name="evidence_url" id="evidence_url" value="<?php echo esc_attr( $f_eurl ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="observed_at"><?php esc_html_e( 'Dátum pozorovania', 'toptour-reference-finder' ); ?></label></th>
		<td><input type="datetime-local" name="observed_at" id="observed_at" value="<?php echo esc_attr( $f_observed ); ?>"></td>
	</tr>
	<tr>
		<th><label for="reviewer_name"><?php esc_html_e( 'Meno recenzenta', 'toptour-reference-finder' ); ?></label></th>
		<td><input type="text" name="reviewer_name" id="reviewer_name" value="<?php echo esc_attr( $f_r_name ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="reviewer_origin"><?php esc_html_e( 'Pôvod recenzenta', 'toptour-reference-finder' ); ?></label></th>
		<td><input type="text" name="reviewer_origin" id="reviewer_origin" value="<?php echo esc_attr( $f_r_origin ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'napr. Booking, Google, vlastný klient', 'toptour-reference-finder' ); ?>"></td>
	</tr>
	<tr>
		<th><label for="language"><?php esc_html_e( 'Jazyk', 'toptour-reference-finder' ); ?></label></th>
		<td><input type="text" name="language" id="language" value="<?php echo esc_attr( $f_language ); ?>" class="small-text" placeholder="<?php esc_attr_e( 'sk, cs, en, de...', 'toptour-reference-finder' ); ?>"></td>
	</tr>
	<tr>
		<th><label for="related_collection_task_id"><?php esc_html_e( 'Súvisiaci task ID', 'toptour-reference-finder' ); ?></label></th>
		<td><input type="number" name="related_collection_task_id" id="related_collection_task_id" value="<?php echo esc_attr( $f_task_id ); ?>" min="0" class="small-text">
		<?php if ( $form_id > 0 && $finding && (int) $finding->related_collection_task_id > 0 ) : ?>
			<span class="description"><?php echo esc_html( Toptour_Ref_Findings::get_collection_task_label( (int) $finding->related_collection_task_id ) ); ?></span>
		<?php endif; ?></td>
	</tr>
</table>

<!-- Section 5: Poznámky -->
<h2><?php esc_html_e( 'Poznámky', 'toptour-reference-finder' ); ?></h2>
<table class="form-table">
	<tr>
		<th><label for="notes"><?php esc_html_e( 'Interné poznámky', 'toptour-reference-finder' ); ?></label></th>
		<td><textarea name="notes" id="notes" class="large-text" rows="5"><?php echo esc_textarea( $f_notes ); ?></textarea></td>
	</tr>
</table>

<?php submit_button( $form_id > 0 ? __( 'Uložiť zmeny', 'toptour-reference-finder' ) : __( 'Vytvoriť zistenie', 'toptour-reference-finder' ) ); ?>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=toptour-references-findings' ) ); ?>" class="button"><?php esc_html_e( 'Zrušiť', 'toptour-reference-finder' ); ?></a>
</form>

<?php else : ?>
<!-- ════════════════ LIST ════════════════ -->
<?php
$filter_type     = isset( $_GET['finding_type'] ) ? sanitize_text_field( $_GET['finding_type'] ) : '';
$filter_area     = isset( $_GET['finding_area'] ) ? sanitize_text_field( $_GET['finding_area'] ) : '';
$filter_strength = isset( $_GET['signal_strength'] ) ? sanitize_text_field( $_GET['signal_strength'] ) : '';
$filter_rep      = isset( $_GET['repetition_level'] ) ? sanitize_text_field( $_GET['repetition_level'] ) : '';
$filter_vstatus  = isset( $_GET['verification_status'] ) ? sanitize_text_field( $_GET['verification_status'] ) : '';
$filter_etype    = isset( $_GET['evidence_type'] ) ? sanitize_text_field( $_GET['evidence_type'] ) : '';
$filter_ttype    = isset( $_GET['target_type'] ) ? sanitize_text_field( $_GET['target_type'] ) : '';
$filter_source   = isset( $_GET['source_id'] ) ? absint( $_GET['source_id'] ) : 0;
$filter_pattern  = isset( $_GET['signal_pattern_id'] ) ? absint( $_GET['signal_pattern_id'] ) : 0;
$search          = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$current_page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result = Toptour_Ref_Findings::get_findings( [
	'finding_type'        => $filter_type,
	'finding_area'        => $filter_area,
	'signal_strength'     => $filter_strength,
	'repetition_level'    => $filter_rep,
	'verification_status' => $filter_vstatus,
	'evidence_type'       => $filter_etype,
	'target_type'         => $filter_ttype,
	'source_id'           => $filter_source > 0 ? $filter_source : '',
	'signal_pattern_id'   => $filter_pattern > 0 ? $filter_pattern : '',
	'search'              => $search,
	'page'                => $current_page,
	'per_page'            => 20,
] );

$findings    = $result['findings'];
$total       = $result['total'];
$total_pages = (int) ceil( $total / 20 );
$base_url    = admin_url( 'admin.php?page=toptour-references-findings' );
?>

<!-- Filter row -->
<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
<input type="hidden" name="page" value="toptour-references-findings">
<div style="display:flex;flex-wrap:wrap;gap:6px;margin:12px 0;">
	<select name="finding_type">
		<option value=""><?php esc_html_e( 'Všetky typy', 'toptour-reference-finder' ); ?></option>
		<?php foreach ( Toptour_Ref_Findings::get_allowed_finding_types() as $ft ) : ?>
			<option value="<?php echo esc_attr( $ft ); ?>" <?php selected( $filter_type, $ft ); ?>><?php echo esc_html( Toptour_Ref_Labels::finding_type_label( $ft ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="finding_area">
		<option value=""><?php esc_html_e( 'Všetky oblasti', 'toptour-reference-finder' ); ?></option>
		<?php foreach ( Toptour_Ref_Findings::get_allowed_finding_areas() as $fa ) : ?>
			<option value="<?php echo esc_attr( $fa ); ?>" <?php selected( $filter_area, $fa ); ?>><?php echo esc_html( Toptour_Ref_Labels::finding_area_label( $fa ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="signal_strength">
		<option value=""><?php esc_html_e( 'Sila signálu', 'toptour-reference-finder' ); ?></option>
		<?php foreach ( Toptour_Ref_Findings::get_allowed_signal_strengths() as $ss ) : ?>
			<option value="<?php echo esc_attr( $ss ); ?>" <?php selected( $filter_strength, $ss ); ?>><?php echo esc_html( Toptour_Ref_Labels::signal_strength_label( $ss ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="repetition_level">
		<option value=""><?php esc_html_e( 'Opakovanie', 'toptour-reference-finder' ); ?></option>
		<?php foreach ( Toptour_Ref_Findings::get_allowed_repetition_levels() as $rl ) : ?>
			<option value="<?php echo esc_attr( $rl ); ?>" <?php selected( $filter_rep, $rl ); ?>><?php echo esc_html( Toptour_Ref_Labels::repetition_level_label( $rl ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="verification_status">
		<option value=""><?php esc_html_e( 'Stav overenia', 'toptour-reference-finder' ); ?></option>
		<?php foreach ( Toptour_Ref_Findings::get_allowed_verification_statuses() as $vs ) : ?>
			<option value="<?php echo esc_attr( $vs ); ?>" <?php selected( $filter_vstatus, $vs ); ?>><?php echo esc_html( Toptour_Ref_Labels::verification_status_label( $vs ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="evidence_type">
		<option value=""><?php esc_html_e( 'Typ dôkazu', 'toptour-reference-finder' ); ?></option>
		<?php foreach ( Toptour_Ref_Findings::get_allowed_evidence_types() as $et ) : ?>
			<option value="<?php echo esc_attr( $et ); ?>" <?php selected( $filter_etype, $et ); ?>><?php echo esc_html( Toptour_Ref_Labels::evidence_type_label( $et ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="target_type">
		<option value=""><?php esc_html_e( 'Typ cieľa', 'toptour-reference-finder' ); ?></option>
		<?php foreach ( Toptour_Ref_Findings::get_allowed_target_types() as $tt ) : ?>
			<option value="<?php echo esc_attr( $tt ); ?>" <?php selected( $filter_ttype, $tt ); ?>><?php echo esc_html( Toptour_Ref_Labels::target_type_label( $tt ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<input type="number" name="source_id" min="0" placeholder="<?php esc_attr_e( 'Source ID', 'toptour-reference-finder' ); ?>" value="<?php echo $filter_source > 0 ? esc_attr( $filter_source ) : ''; ?>" style="width:90px;">
	<input type="number" name="signal_pattern_id" min="0" placeholder="<?php esc_attr_e( 'Pattern ID', 'toptour-reference-finder' ); ?>" value="<?php echo $filter_pattern > 0 ? esc_attr( $filter_pattern ) : ''; ?>" style="width:100px;">
	<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Hľadať...', 'toptour-reference-finder' ); ?>" class="regular-text">
	<?php submit_button( __( 'Filtrovať', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
	<?php if ( $filter_type || $filter_area || $filter_strength || $filter_rep || $filter_vstatus || $filter_etype || $filter_ttype || $filter_source > 0 || $filter_pattern > 0 || $search ) : ?>
		<a href="<?php echo esc_url( $base_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Zrušiť filtre', 'toptour-reference-finder' ); ?></a>
	<?php endif; ?>
</div>
</form>

<!-- Results summary -->
<p><?php printf( esc_html__( 'Celkom: %d zistení', 'toptour-reference-finder' ), $total ); ?></p>

<?php if ( $findings ) : ?>
<style>
	.toptour-findings-table .column-finding {
		width: 42%;
	}
	.toptour-findings-table .finding-title {
		display: block;
		font-weight: 600;
		margin-bottom: 4px;
		overflow-wrap: break-word;
		word-break: normal;
	}
	.toptour-findings-table .finding-summary {
		display: block;
		margin-bottom: 4px;
		color: #2c3338;
		overflow-wrap: break-word;
		word-break: normal;
	}
	.toptour-findings-table .finding-meta {
		display: block;
		color: #646970;
		font-size: 12px;
		overflow-wrap: break-word;
		word-break: normal;
	}
	.toptour-findings-table .column-actions .row-actions {
		position: static;
		visibility: visible;
		color: #50575e;
	}
</style>
<table class="wp-list-table widefat fixed striped toptour-findings-table">
<thead>
<tr>
	<th class="column-finding"><?php esc_html_e( 'Zistenie', 'toptour-reference-finder' ); ?></th>
	<th style="width:120px;"><?php esc_html_e( 'Typ', 'toptour-reference-finder' ); ?></th>
	<th style="width:140px;"><?php esc_html_e( 'Cieľ', 'toptour-reference-finder' ); ?></th>
	<th style="width:140px;"><?php esc_html_e( 'Signál', 'toptour-reference-finder' ); ?></th>
	<th style="width:120px;"><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></th>
	<th style="width:140px;"><?php esc_html_e( 'Vytvorené', 'toptour-reference-finder' ); ?></th>
	<th class="column-actions" style="width:120px;"><?php esc_html_e( 'Akcie', 'toptour-reference-finder' ); ?></th>
</tr>
</thead>
<tbody>
<?php foreach ( $findings as $row ) :
	$target_label  = Toptour_Ref_Findings::get_target_label( $row->target_type, (int) $row->target_id );
	$pattern_label = Toptour_Ref_Findings::get_signal_pattern_label( (int) $row->signal_pattern_id );
	$edit_url      = add_query_arg( [ 'page' => 'toptour-references-findings', 'action' => 'edit', 'finding_id' => $row->id ], admin_url( 'admin.php' ) );
	$archive_url   = wp_nonce_url( add_query_arg( [ 'page' => 'toptour-references-findings', 'action' => 'archive', 'finding_id' => $row->id ], admin_url( 'admin.php' ) ), 'toptour_archive_finding_' . $row->id );
	$finding_summary = '';
	if ( ! empty( $row->analysis_summary ) ) {
		$finding_summary = (string) $row->analysis_summary;
	} elseif ( ! empty( $row->evidence_excerpt ) ) {
		$finding_summary = (string) $row->evidence_excerpt;
	} elseif ( ! empty( $row->excerpt ) ) {
		$finding_summary = (string) $row->excerpt;
	}
	$finding_summary = toptour_finding_translate_placeholder_text( $finding_summary );
	if ( mb_strlen( $finding_summary ) > 180 ) {
		$finding_summary = mb_substr( $finding_summary, 0, 180 ) . '…';
	}

	$meta_line = implode(
		' · ',
		array_filter(
			[
				Toptour_Ref_Labels::finding_type_label( $row->finding_type ),
				$row->finding_area ? Toptour_Ref_Labels::finding_area_label( $row->finding_area ) : '',
				Toptour_Ref_Labels::signal_strength_label( $row->signal_strength ),
				Toptour_Ref_Labels::repetition_level_label( $row->repetition_level ),
			]
		)
	);

	$signal_label = '—';
	if ( (int) $row->signal_pattern_id > 0 && $pattern_label !== '—' ) {
		$signal_label = $pattern_label;
	} elseif ( ! empty( $row->signal_strength ) || ! empty( $row->repetition_level ) ) {
		$signal_label = Toptour_Ref_Labels::signal_strength_label( $row->signal_strength ) . ' · ' . Toptour_Ref_Labels::repetition_level_label( $row->repetition_level );
	}
	$finding_title_display = toptour_finding_translate_placeholder_text( (string) $row->finding_title );
?>
<tr>
	<td class="column-finding">
		<a class="finding-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $finding_title_display ); ?></a>
		<?php if ( $finding_summary !== '' ) : ?>
			<span class="finding-summary"><?php echo esc_html( $finding_summary ); ?></span>
		<?php endif; ?>
		<?php if ( $meta_line !== '' ) : ?>
			<span class="finding-meta"><?php echo esc_html( $meta_line ); ?></span>
		<?php endif; ?>
	</td>
	<td><?php echo esc_html( Toptour_Ref_Labels::finding_type_label( $row->finding_type ) ); ?></td>
	<td><?php echo esc_html( $target_label ); ?></td>
	<td><?php echo esc_html( $signal_label ); ?></td>
	<td><?php echo esc_html( Toptour_Ref_Labels::verification_status_label( $row->verification_status ) ); ?></td>
	<td><?php echo esc_html( toptour_finding_format_datetime( $row->created_at ) ); ?></td>
	<td class="column-actions">
		<div class="row-actions">
			<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Upraviť', 'toptour-reference-finder' ); ?></a></span>
		<?php if ( $row->verification_status !== 'archived' ) : ?>
			 | <span class="trash"><a href="<?php echo esc_url( $archive_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Naozaj archivovať?', 'toptour-reference-finder' ); ?>')"><?php esc_html_e( 'Archivovať', 'toptour-reference-finder' ); ?></a></span>
		<?php endif; ?>
		</div>
	</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php if ( $total_pages > 1 ) : ?>
<div class="tablenav bottom">
<div class="tablenav-pages">
<?php
$pagination_args = [
	'base'    => add_query_arg( 'paged', '%#%', $base_url ),
	'format'  => '',
	'current' => $current_page,
	'total'   => $total_pages,
];
if ( $filter_type )    { $pagination_args['base'] = add_query_arg( 'finding_type', $filter_type, $pagination_args['base'] ); }
if ( $filter_area )    { $pagination_args['base'] = add_query_arg( 'finding_area', $filter_area, $pagination_args['base'] ); }
if ( $filter_strength ){ $pagination_args['base'] = add_query_arg( 'signal_strength', $filter_strength, $pagination_args['base'] ); }
if ( $filter_rep )     { $pagination_args['base'] = add_query_arg( 'repetition_level', $filter_rep, $pagination_args['base'] ); }
if ( $filter_vstatus ) { $pagination_args['base'] = add_query_arg( 'verification_status', $filter_vstatus, $pagination_args['base'] ); }
if ( $filter_etype )   { $pagination_args['base'] = add_query_arg( 'evidence_type', $filter_etype, $pagination_args['base'] ); }
if ( $filter_ttype )   { $pagination_args['base'] = add_query_arg( 'target_type', $filter_ttype, $pagination_args['base'] ); }
if ( $filter_source > 0 ) { $pagination_args['base'] = add_query_arg( 'source_id', $filter_source, $pagination_args['base'] ); }
if ( $filter_pattern > 0 ){ $pagination_args['base'] = add_query_arg( 'signal_pattern_id', $filter_pattern, $pagination_args['base'] ); }
if ( $search )         { $pagination_args['base'] = add_query_arg( 's', rawurlencode( $search ), $pagination_args['base'] ); }
echo wp_kses_post( paginate_links( $pagination_args ) );
?>
</div>
</div>
<?php endif; ?>

<?php else : ?>
<p><?php esc_html_e( 'Žiadne zistenia.', 'toptour-reference-finder' ); ?></p>
<?php endif; ?>

<?php endif; // end list/form ?>
</div>
