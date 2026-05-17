<?php
/**
 * Admin view: Debug Tracer Modal for AI Process.
 *
 * Step-by-step process visualization modal for AI batch processing.
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
?>

<div id="toptour-debug-tracer-modal" class="toptour-debug-tracer" style="display: none;">
	<div class="toptour-debug-tracer__overlay"></div>
	<div class="toptour-debug-tracer__container">
		<!-- Header -->
		<div class="toptour-debug-tracer__header">
			<h2 class="toptour-debug-tracer__title">
				<?php esc_html_e( 'Trasovač procesu - AI spracovanie', 'toptour-reference-finder' ); ?>
			</h2>
			<button class="toptour-debug-tracer__close" type="button" aria-label="<?php esc_attr_e( 'Zavrieť', 'toptour-reference-finder' ); ?>">&times;</button>
		</div>

		<!-- Main Content -->
		<div class="toptour-debug-tracer__content">
			<!-- Stage Info -->
			<div class="toptour-debug-tracer__stage-info">
				<div class="toptour-debug-tracer__stage-title" id="tracer-stage-title">
					<?php esc_html_e( 'Čakám na spustenie...', 'toptour-reference-finder' ); ?>
				</div>
				<div class="toptour-debug-tracer__stage-desc" id="tracer-stage-desc"></div>
			</div>

			<div class="toptour-debug-tracer__supplement" id="tracer-supplement-panel" style="display: none;">
				<div class="toptour-debug-tracer__supplement-title">
					<?php esc_html_e( 'Doplnenie zadania pre ďalší pokus', 'toptour-reference-finder' ); ?>
				</div>
				<p class="toptour-debug-tracer__supplement-message" id="tracer-supplement-message">
					<?php esc_html_e( 'Tento krok nevrátil zobraziteľné dáta. Doplň upresnenie zadania.', 'toptour-reference-finder' ); ?>
				</p>
				<textarea id="tracer-supplement-input" class="toptour-debug-tracer__supplement-input" rows="4" placeholder="<?php esc_attr_e( 'Doplň presnejšie zadanie, očakávané výstupy, zdroje alebo konkrétne otázky pre AI.', 'toptour-reference-finder' ); ?>"></textarea>
				<div class="toptour-debug-tracer__supplement-actions">
					<button id="tracer-btn-supplement" type="button" class="button button-secondary">
						<?php esc_html_e( 'Znovu spracovať s doplnením', 'toptour-reference-finder' ); ?>
					</button>
				</div>
			</div>

			<!-- Progress Bar -->
			<div class="toptour-debug-tracer__progress">
				<div class="toptour-debug-tracer__progress-bar">
					<div class="toptour-debug-tracer__progress-fill" id="tracer-progress-fill" style="width: 0%"></div>
				</div>
				<div class="toptour-debug-tracer__progress-text">
					<span id="tracer-progress-current">0</span> / <span id="tracer-progress-total">0</span>
				</div>
			</div>

			<!-- Data Preview Tabs -->
			<div class="toptour-debug-tracer__tabs">
				<button class="toptour-debug-tracer__tab-btn toptour-debug-tracer__tab-btn--active" data-tab="input">
					<?php esc_html_e( 'Vstupné údaje', 'toptour-reference-finder' ); ?>
				</button>
				<button class="toptour-debug-tracer__tab-btn" data-tab="output">
					<?php esc_html_e( 'Výstupné údaje', 'toptour-reference-finder' ); ?>
				</button>
				<button class="toptour-debug-tracer__tab-btn" data-tab="photos">
					<?php esc_html_e( 'Fotodôkazy', 'toptour-reference-finder' ); ?>
				</button>
				<button class="toptour-debug-tracer__tab-btn" data-tab="log">
					<?php esc_html_e( 'Denník', 'toptour-reference-finder' ); ?>
				</button>
			</div>

			<!-- Tab Content -->
			<div class="toptour-debug-tracer__tab-content">
				<!-- Input Data Tab -->
				<div class="toptour-debug-tracer__tab-pane toptour-debug-tracer__tab-pane--active" id="tab-input">
					<pre class="toptour-debug-tracer__data-display" id="tracer-input-data"><?php esc_html_e( 'Čakám na spustenie...', 'toptour-reference-finder' ); ?></pre>
				</div>

				<!-- Output Data Tab -->
				<div class="toptour-debug-tracer__tab-pane" id="tab-output">
					<pre class="toptour-debug-tracer__data-display" id="tracer-output-data"><?php esc_html_e( 'Žiadne výstupné údaje', 'toptour-reference-finder' ); ?></pre>
				</div>

				<!-- Photos Tab -->
				<div class="toptour-debug-tracer__tab-pane" id="tab-photos">
					<div class="toptour-debug-tracer__photos-grid" id="tracer-photos-grid">
						<p><?php esc_html_e( 'Žiadne fotografie', 'toptour-reference-finder' ); ?></p>
					</div>
				</div>

				<!-- Log Tab -->
				<div class="toptour-debug-tracer__tab-pane" id="tab-log">
					<div class="toptour-debug-tracer__log" id="tracer-log">
						<p><?php esc_html_e( 'Denník je prázdny', 'toptour-reference-finder' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Footer / Actions -->
		<div class="toptour-debug-tracer__footer">
			<div class="toptour-debug-tracer__status" id="tracer-status-bar">
				<span id="tracer-status-text">—</span>
			</div>

			<div class="toptour-debug-tracer__actions">
				<button id="tracer-btn-primary" type="button" class="button button-primary" style="display: none;">
					<?php esc_html_e( 'Spustiť trasovanie', 'toptour-reference-finder' ); ?>
				</button>

				<button id="tracer-btn-cancel" type="button" class="button">
					<?php esc_html_e( 'Zatvoriť', 'toptour-reference-finder' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Inline CSS for Tracer Modal -->
<style>
.toptour-debug-tracer {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	z-index: 999999;
	display: flex;
	align-items: center;
	justify-content: center;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

.toptour-debug-tracer__overlay {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.7);
	z-index: -1;
}

.toptour-debug-tracer__container {
	background: white;
	border-radius: 8px;
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
	width: 90%;
	height: 90%;
	max-width: 1000px;
	display: flex;
	flex-direction: column;
	overflow: hidden;
}

.toptour-debug-tracer__header {
	padding: 20px;
	border-bottom: 1px solid #e5e5e5;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.toptour-debug-tracer__title {
	margin: 0;
	font-size: 18px;
	font-weight: 600;
	color: #1d2327;
}

.toptour-debug-tracer__close {
	background: none;
	border: none;
	font-size: 32px;
	cursor: pointer;
	color: #999;
	padding: 0;
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.toptour-debug-tracer__close:hover {
	color: #333;
}

.toptour-debug-tracer__content {
	flex: 1;
	overflow-y: auto;
	padding: 20px;
}

.toptour-debug-tracer__stage-info {
	margin-bottom: 20px;
	padding: 15px;
	background: #f5f5f5;
	border-left: 4px solid #0073aa;
	border-radius: 4px;
}

.toptour-debug-tracer__stage-title {
	font-size: 16px;
	font-weight: 600;
	margin-bottom: 8px;
	color: #1d2327;
}

.toptour-debug-tracer__stage-desc {
	font-size: 14px;
	color: #666;
	line-height: 1.6;
}

.toptour-debug-tracer__supplement {
	margin-bottom: 20px;
	padding: 16px;
	border: 1px solid #dcdcde;
	border-radius: 6px;
	background: #fff8e5;
}

.toptour-debug-tracer__supplement-title {
	font-size: 14px;
	font-weight: 600;
	margin-bottom: 8px;
	color: #1d2327;
}

.toptour-debug-tracer__supplement-message {
	margin: 0 0 10px;
	font-size: 13px;
	color: #50575e;
}

.toptour-debug-tracer__supplement-input {
	width: 100%;
	min-height: 96px;
	padding: 10px 12px;
	font-size: 13px;
	border: 1px solid #8c8f94;
	border-radius: 4px;
	resize: vertical;
	box-sizing: border-box;
	background: #fff;
}

.toptour-debug-tracer__supplement-actions {
	margin-top: 10px;
	display: flex;
	justify-content: flex-end;
}

.toptour-debug-tracer__progress {
	margin-bottom: 20px;
}

.toptour-debug-tracer__progress-bar {
	width: 100%;
	height: 24px;
	background: #e5e5e5;
	border-radius: 4px;
	overflow: hidden;
	margin-bottom: 8px;
}

.toptour-debug-tracer__progress-fill {
	height: 100%;
	background: linear-gradient(90deg, #0073aa, #0096dd);
	transition: width 0.3s ease;
	display: flex;
	align-items: center;
	justify-content: center;
	color: white;
	font-size: 12px;
	font-weight: 600;
}

.toptour-debug-tracer__progress-text {
	font-size: 12px;
	color: #666;
	text-align: right;
}

.toptour-debug-tracer__tabs {
	display: flex;
	gap: 0;
	margin-bottom: 15px;
	border-bottom: 1px solid #e5e5e5;
}

.toptour-debug-tracer__tab-btn {
	flex: 1;
	padding: 12px 16px;
	background: none;
	border: none;
	font-size: 14px;
	cursor: pointer;
	color: #666;
	border-bottom: 2px solid transparent;
	transition: all 0.3s ease;
	margin-bottom: -1px;
}

.toptour-debug-tracer__tab-btn:hover {
	color: #0073aa;
}

.toptour-debug-tracer__tab-btn--active {
	color: #0073aa;
	border-bottom-color: #0073aa;
	font-weight: 600;
}

.toptour-debug-tracer__tab-content {
	min-height: 200px;
	max-height: 400px;
	border: 1px solid #e5e5e5;
	border-radius: 4px;
	background: #fafafa;
	overflow: hidden;
}

.toptour-debug-tracer__tab-pane {
	display: none;
	height: 100%;
	overflow-y: auto;
	padding: 0;
}

.toptour-debug-tracer__tab-pane--active {
	display: block;
}

.toptour-debug-tracer__data-display {
	margin: 0;
	padding: 15px;
	background: white;
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Courier New', monospace;
	font-size: 12px;
	line-height: 1.4;
	overflow-x: auto;
	white-space: pre-wrap;
	word-wrap: break-word;
	color: #333;
}

.toptour-debug-tracer__photos-grid {
	padding: 15px;
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 20px;
	background: white;
	overflow-y: auto;
}

.toptour-debug-tracer__photo-item {
	border: 1px solid #dcdcde;
	border-radius: 4px;
	overflow: hidden;
	background: #fff;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	transition: box-shadow 0.2s ease;
	display: flex;
	flex-direction: column;
}

.toptour-debug-tracer__photo-item:hover {
	box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
}

.toptour-debug-tracer__photo-image {
	width: 100%;
	height: 200px;
	overflow: hidden;
	background: #f5f5f5;
	flex-shrink: 0;
}

.toptour-debug-tracer__photo-image img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}

.toptour-debug-tracer__photo-details {
	padding: 12px;
	font-size: 13px;
	line-height: 1.5;
	border-top: 1px solid #eee;
	flex-grow: 1;
	overflow-y: auto;
}

.toptour-debug-tracer__photo-details p {
	margin: 8px 0;
	padding: 0;
}

.toptour-debug-tracer__photo-details strong {
	display: block;
	color: #333;
	font-weight: 600;
	margin-bottom: 2px;
}

.toptour-debug-tracer__photo-details a {
	color: #0073aa;
	text-decoration: none;
	word-break: break-all;
}

.toptour-debug-tracer__photo-details a:hover {
	text-decoration: underline;
}

.toptour-debug-tracer__log {
	padding: 15px;
	background: white;
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Courier New', monospace;
	font-size: 12px;
	line-height: 1.6;
	color: #333;
	max-height: 100%;
	overflow-y: auto;
}

.toptour-debug-tracer__log-entry {
	margin-bottom: 8px;
	padding: 8px;
	border-left: 2px solid #e5e5e5;
	padding-left: 12px;
}

.toptour-debug-tracer__log-entry--info {
	border-left-color: #0073aa;
	color: #0073aa;
}

.toptour-debug-tracer__log-entry--success {
	border-left-color: #46b450;
	color: #46b450;
}

.toptour-debug-tracer__log-entry--error {
	border-left-color: #dc3545;
	color: #dc3545;
}

.toptour-debug-tracer__log-entry--warning {
	border-left-color: #ff9c00;
	color: #ff9c00;
}

.toptour-debug-tracer__footer {
	padding: 15px 20px;
	border-top: 1px solid #e5e5e5;
	background: #f5f5f5;
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 15px;
}

.toptour-debug-tracer__status {
	flex: 1;
	font-size: 13px;
	color: #666;
}

.toptour-debug-tracer__status--error {
	color: #dc3545;
	font-weight: 600;
}

.toptour-debug-tracer__status--success {
	color: #46b450;
	font-weight: 600;
}

.toptour-debug-tracer__actions {
	display: flex;
	gap: 10px;
}

.toptour-debug-tracer__actions .button {
	margin: 0;
	min-width: 120px;
}
</style>
