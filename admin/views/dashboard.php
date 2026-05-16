<?php
/**
 * TOPTOUR Reference Finder Dashboard View
 *
 * Main dashboard page showing plugin overview and status.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
}
?>

<div class="wrap toptour-ref-dashboard">
	<h1><?php esc_html_e( 'TOPTOUR Reference Finder', 'toptour-reference-finder' ); ?></h1>
	
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Interný nástroj na zber referencií', 'toptour-reference-finder' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'TOPTOUR Reference Finder zbiera dôkazy z reálnych skúseností hostí. Hodnotenie vzniká až neskôr, z opakujúcich sa signálov reality.', 'toptour-reference-finder' ); ?>
		</p>
	</div>

	<h2><?php esc_html_e( 'Základná veta', 'toptour-reference-finder' ); ?></h2>
	<blockquote>
		<p style="font-style: italic; border-left: 4px solid #0073aa; padding-left: 10px;">
			<?php esc_html_e( 'Najprv zber referencií. Až potom kritériá hodnotenia.', 'toptour-reference-finder' ); ?>
		</p>
	</blockquote>

	<h2><?php esc_html_e( 'Účel pluginu', 'toptour-reference-finder' ); ?></h2>
	<ul>
		<li><?php esc_html_e( 'Evidovať zariadenia a ubytovanie', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Evidovať destinácie a lokality', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Evidovať ponuky a dealy', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Zbierať referenčné zdroje (platformy, články, odkazy)', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Zapisovať zistenia z recenzií a fotiek hostí', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Rozlišovať pozitíva, riziká a rozpory', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Hľadať opakujúce sa signály reality', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Pripraviť základ pre neskoršie hodnotenie', 'toptour-reference-finder' ); ?></li>
	</ul>

	<h2><?php esc_html_e( 'Status', 'toptour-reference-finder' ); ?></h2>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'MVP (Minimum Viable Product)', 'toptour-reference-finder' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'Aktuálna verzia je projektový skeleton. Dátové tabuľky a biznis logika sa budú implementovať v ďalších fázach.', 'toptour-reference-finder' ); ?>
		</p>
	</div>

	<h2><?php esc_html_e( 'Mimo rozsahu MVP', 'toptour-reference-finder' ); ?></h2>
	<ul>
		<li><?php esc_html_e( 'Web scraping', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'AI sumarizácia', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Automatické hodnotenie', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Verejné skóre alebo certifikáty', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Verejný frontend', 'toptour-reference-finder' ); ?></li>
		<li><?php esc_html_e( 'Integrácia s Booking, TripAdvisor, Google Reviews', 'toptour-reference-finder' ); ?></li>
	</ul>

	<h2><?php esc_html_e( 'Vzťah k TOPTOUR Core', 'toptour-reference-finder' ); ?></h2>
	<p>
		<?php esc_html_e( 'TOPTOUR Reference Finder je samostatný plugin. TOPTOUR Core zostáva jadrom pre ponuky, zákazníkov, požiadavky a manažérov.', 'toptour-reference-finder' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Reference Finder bude v budúcnosti poskytovať referenčné dáta pre ponuky a destinácie v TOPTOUR Core.', 'toptour-reference-finder' ); ?>
	</p>

	<h2><?php esc_html_e( 'Navigácia', 'toptour-reference-finder' ); ?></h2>
	<p>
		<?php esc_html_e( 'Použite menu TOPTOUR References v bočnom paneli na prístup k jednotlivým sekciám:', 'toptour-reference-finder' ); ?>
	</p>
	<ul>
		<li><strong><?php esc_html_e( 'Zariadenia', 'toptour-reference-finder' ); ?></strong> - <?php esc_html_e( 'Evidencia ubytovacích priestorov', 'toptour-reference-finder' ); ?></li>
		<li><strong><?php esc_html_e( 'Destinácie', 'toptour-reference-finder' ); ?></strong> - <?php esc_html_e( 'Evidencia lokalít', 'toptour-reference-finder' ); ?></li>
		<li><strong><?php esc_html_e( 'Ponuky', 'toptour-reference-finder' ); ?></strong> - <?php esc_html_e( 'Evidencia dealov a ponúk', 'toptour-reference-finder' ); ?></li>
		<li><strong><?php esc_html_e( 'Referenčné zdroje', 'toptour-reference-finder' ); ?></strong> - <?php esc_html_e( 'Platformy, články, odkazy', 'toptour-reference-finder' ); ?></li>
		<li><strong><?php esc_html_e( 'Zistenia', 'toptour-reference-finder' ); ?></strong> - <?php esc_html_e( 'Extrahované poznatky z recenzií', 'toptour-reference-finder' ); ?></li>
		<li><strong><?php esc_html_e( 'Fotodôkazy', 'toptour-reference-finder' ); ?></strong> - <?php esc_html_e( 'Vizuálne pozorovania a fotky', 'toptour-reference-finder' ); ?></li>
		<li><strong><?php esc_html_e( 'Zber referencií', 'toptour-reference-finder' ); ?></strong> - <?php esc_html_e( 'Pracovný dashboard zberu', 'toptour-reference-finder' ); ?></li>
		<li><strong><?php esc_html_e( 'Nastavenia', 'toptour-reference-finder' ); ?></strong> - <?php esc_html_e( 'Konfigurácia pluginu', 'toptour-reference-finder' ); ?></li>
	</ul>

	<div class="notice notice-info" style="margin-top: 30px;">
		<p>
			<strong><?php esc_html_e( 'Verzia:', 'toptour-reference-finder' ); ?></strong>
			<?php echo esc_html( TOPTOUR_REF_VERSION ); ?>
		</p>
	</div>
</div>

<style>
	.toptour-ref-dashboard blockquote {
		background-color: #f8f9fa;
		padding: 15px;
		border-radius: 4px;
	}
	
	.toptour-ref-dashboard ul {
		list-style-type: disc;
		margin-left: 20px;
	}
	
	.toptour-ref-dashboard li {
		margin-bottom: 8px;
	}
</style>
