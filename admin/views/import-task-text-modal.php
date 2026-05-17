<?php
/**
 * Task Text Import Modal for collection-tasks admin page.
 *
 * Renders a modal with text import form and handles submission.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="toptour-import-task-text-modal" class="toptour-modal toptour-modal--hidden">
	<div class="toptour-modal__overlay"></div>
	<div class="toptour-modal__content">
		<div class="toptour-modal__header">
			<h2><?php esc_html_e( 'Importovať úlohu z textu', 'toptour-reference-finder' ); ?></h2>
			<button type="button" class="toptour-modal__close" aria-label="<?php esc_attr_e( 'Zavrieť', 'toptour-reference-finder' ); ?>" data-modal-close>×</button>
		</div>

		<div class="toptour-modal__body">
			<form id="toptour-task-text-import-form" class="toptour-task-import-form">
				<fieldset class="toptour-form-section">
					<legend><?php esc_html_e( 'Formát vstupného textu', 'toptour-reference-finder' ); ?></legend>
					<p class="description">
						<?php esc_html_e( 'Vzor textu. Každý blok je povinný. Môžete importovať viacero úloh naraz (oddeľte prázdnym riadkom).', 'toptour-reference-finder' ); ?>
					</p>
					<pre class="toptour-form-template"><code>TASK TITLE
Názov vašej úlohy

QUERY TEXT
Čo má systém hľadať a prečo. Môže byť viacvrstvový text.

SOURCE HINT
Kde hľadať zdroje. Vrátane platforiem, typov zdrojov, atď.</code></pre>
				</fieldset>

				<fieldset class="toptour-form-section">
					<label for="toptour-task-text-input">
						<?php esc_html_e( 'Vložte text úlohy (alebo viacero úloh)', 'toptour-reference-finder' ); ?>
					</label>
					<textarea
						id="toptour-task-text-input"
						name="task_text"
						class="widefat"
						rows="20"
						placeholder="Sem vložte text v požadovanom formáte..."
						required
					></textarea>
					<p class="description" id="task-text-hint">
						<?php esc_html_e( 'Minimálne 3 sekcie: TASK TITLE, QUERY TEXT, SOURCE HINT.', 'toptour-reference-finder' ); ?>
					</p>
				</fieldset>

				<fieldset class="toptour-form-section">
					<label>
						<input type="checkbox" name="auto_trigger_batch" value="1" checked>
						<?php esc_html_e( 'Po importovaní automaticky spustiť batch spracovanie', 'toptour-reference-finder' ); ?>
					</label>
				</fieldset>

				<div id="toptour-import-result" class="toptour-import-result toptour-import-result--hidden"></div>

				<div class="toptour-modal__actions">
					<button type="submit" class="button button-primary" id="toptour-import-task-submit">
						<?php esc_html_e( 'Importovať úlohu', 'toptour-reference-finder' ); ?>
					</button>
					<button type="button" class="button button-secondary" data-modal-close>
						<?php esc_html_e( 'Zavrieť', 'toptour-reference-finder' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
	.toptour-modal {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 10000;
		flex-direction: column;
		align-items: center;
		justify-content: center;
	}

	.toptour-modal:not(.toptour-modal--hidden) {
		display: flex;
	}

	.toptour-modal--hidden {
		display: none !important;
	}

	.toptour-modal__overlay {
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(0, 0, 0, 0.5);
		z-index: -1;
	}

	.toptour-modal__content {
		background: white;
		border-radius: 4px;
		box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16);
		max-width: 800px;
		width: 90%;
		max-height: 90vh;
		overflow-y: auto;
	}

	.toptour-modal__header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 20px;
		border-bottom: 1px solid #eee;
	}

	.toptour-modal__header h2 {
		margin: 0;
		padding: 0;
		font-size: 1.5em;
	}

	.toptour-modal__close {
		background: none;
		border: none;
		font-size: 2em;
		cursor: pointer;
		padding: 0;
		margin: 0;
		width: 32px;
		height: 32px;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #666;
		transition: color 0.2s;
	}

	.toptour-modal__close:hover {
		color: #000;
	}

	.toptour-modal__body {
		padding: 20px;
	}

	.toptour-form-section {
		margin-bottom: 20px;
		border: none;
		padding: 0;
	}

	.toptour-form-section legend,
	.toptour-form-section label {
		display: block;
		margin-bottom: 8px;
		font-weight: 500;
		color: #333;
	}

	.toptour-form-section textarea {
		font-family: 'Monaco', 'Courier New', monospace;
		font-size: 13px;
		line-height: 1.6;
	}

	.toptour-form-template {
		background: #f5f5f5;
		border: 1px solid #ddd;
		border-radius: 3px;
		padding: 12px;
		overflow-x: auto;
		margin: 0 0 12px 0;
		font-size: 12px;
		line-height: 1.5;
	}

	.toptour-form-template code {
		display: block;
		font-family: 'Monaco', 'Courier New', monospace;
	}

	.toptour-import-result {
		margin-top: 15px;
		padding: 12px;
		border-radius: 3px;
		display: none;
	}

	.toptour-import-result--visible {
		display: block;
	}

	.toptour-import-result.notice-success {
		background: #d4edda;
		border: 1px solid #c3e6cb;
		color: #155724;
	}

	.toptour-import-result.notice-error {
		background: #f8d7da;
		border: 1px solid #f5c6cb;
		color: #721c24;
	}

	.toptour-import-result.notice-info {
		background: #d1ecf1;
		border: 1px solid #bee5eb;
		color: #0c5460;
	}

	.toptour-modal__actions {
		display: flex;
		gap: 10px;
		justify-content: flex-end;
		padding-top: 15px;
		border-top: 1px solid #eee;
	}

	.toptour-modal__actions button {
		margin: 0;
	}
</style>

<script>
(function() {
	'use strict';

	const modal = document.getElementById( 'toptour-import-task-text-modal' );
	const form = document.getElementById( 'toptour-task-text-import-form' );
	const resultDiv = document.getElementById( 'toptour-import-result' );

	// Open modal trigger.
	window.toptourOpenImportModal = function() {
		if ( modal ) {
			modal.classList.remove( 'toptour-modal--hidden' );
		}
	};

	// Close modal.
	function closeModal() {
		if ( modal ) {
			modal.classList.add( 'toptour-modal--hidden' );
		}
	}

	// Close button handlers.
	document.querySelectorAll( '[data-modal-close]' ).forEach( function( btn ) {
		btn.addEventListener( 'click', closeModal );
	} );

	// Overlay click to close.
	const overlay = modal ? modal.querySelector( '.toptour-modal__overlay' ) : null;
	if ( overlay ) {
		overlay.addEventListener( 'click', closeModal );
	}

	// Form submission.
	if ( form ) {
		form.addEventListener( 'submit', async function( e ) {
			e.preventDefault();

			const taskText = document.getElementById( 'toptour-task-text-input' ).value.trim();
			const autoTrigger = document.querySelector( 'input[name="auto_trigger_batch"]' ).checked;

			if ( ! taskText ) {
				showResult( 'Vstupný text je prázdny.', 'error' );
				return;
			}

			// Disable submit button.
			const submitBtn = document.getElementById( 'toptour-import-task-submit' );
			submitBtn.disabled = true;
			submitBtn.textContent = '<?php esc_html_e( 'Importujem...', 'toptour-reference-finder' ); ?>';

			try {
				const response = await fetch( '<?php echo esc_url( rest_url( 'toptour-ref/v1/import-task-text' ) ); ?>', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': '<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>',
					},
					body: JSON.stringify( {
						task_text: taskText,
					} ),
				} );

				const data = await response.json();

				if ( data.ok ) {
					showResult( 
						data.message + (data.task_id ? ' (ID: ' + data.task_id + ')' : ''),
						'success'
					);
					document.getElementById( 'toptour-task-text-input' ).value = '';

					// Auto-trigger batch if enabled.
					if ( autoTrigger && data.task_id ) {
						setTimeout( function() {
							window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=toptour-references-collection' ) ); ?>';
						}, 1500 );
					}
				} else {
					showResult( 
						data.message || '<?php esc_html_e( 'Neznáma chyba', 'toptour-reference-finder' ); ?>',
						'error'
					);
				}
			} catch ( err ) {
				console.error( 'Import error:', err );
				showResult( 
					'<?php esc_html_e( 'Chyba pri požiadavke:', 'toptour-reference-finder' ); ?> ' + err.message,
					'error'
				);
			} finally {
				submitBtn.disabled = false;
				submitBtn.textContent = '<?php esc_html_e( 'Importovať úlohu', 'toptour-reference-finder' ); ?>';
			}
		} );
	}

	function showResult( message, type ) {
		resultDiv.textContent = message;
		resultDiv.className = 'toptour-import-result notice-' + type + ' toptour-import-result--visible';
	}
})();
</script>
