/**
 * AI Debug Tracer Controller
 * 
 * Handles step-by-step process execution and visualization.
 */

(function() {
	'use strict';

	const TracerController = {
		modal: null,
		currentStep: 0,
		totalSteps: 0,
		taskId: 0,
		batchId: null,
		tracerRunId: null,
		isProcessing: false,
		logs: [],
		stepData: {},

		init() {
			this.modal = document.getElementById('toptour-debug-tracer-modal');
			if (!this.modal) return;

			this.attachEventListeners();
		},

		attachEventListeners() {
			// Close button
			this.modal.querySelector('.toptour-debug-tracer__close').addEventListener('click', 
				() => this.close());

			// Cancel button
			document.getElementById('tracer-btn-cancel').addEventListener('click', 
				() => this.close());

			// Process button
			document.getElementById('tracer-btn-process').addEventListener('click', 
				() => this.processNextStep());

			// Continue button
			document.getElementById('tracer-btn-continue').addEventListener('click', 
				() => this.processNextStep());

			// Tab switching
			this.modal.querySelectorAll('.toptour-debug-tracer__tab-btn').forEach(btn => {
				btn.addEventListener('click', (e) => this.switchTab(e.target.dataset.tab));
			});
		},

		open(taskId) {
			this.taskId = taskId;
			this.currentStep = 0;
			this.totalSteps = 4; // 1. Generate, 2. Validate, 3. Process, 4. Import
			this.logs = [];
			this.stepData = {};
			this.batchId = null;
			this.tracerRunId = null;
			this.isProcessing = false;

			this.modal.style.display = 'flex';
			this.updateUI();
			this.addLog('info', `Trasovač spustený pre úlohu #${taskId}`);
			this.showProcessButton();
		},

		close() {
			this.modal.style.display = 'none';
		},

		addLog(type, message) {
			const timestamp = new Date().toLocaleTimeString();
			this.logs.push({ type, message, timestamp });
			this.updateLogDisplay();
		},

		updateLogDisplay() {
			const logDiv = document.getElementById('tracer-log');
			if (this.logs.length === 0) {
				logDiv.innerHTML = '<p>' + this.escapeHtml('Denník je prázdny') + '</p>';
				return;
			}

			logDiv.innerHTML = this.logs.map(entry => {
				return `<div class="toptour-debug-tracer__log-entry toptour-debug-tracer__log-entry--${entry.type}">
					<strong>[${entry.timestamp}]</strong> ${this.escapeHtml(entry.message)}
				</div>`;
			}).join('');

			logDiv.scrollTop = logDiv.scrollHeight;
		},

		switchTab(tabName) {
			// Update buttons
			this.modal.querySelectorAll('.toptour-debug-tracer__tab-btn').forEach(btn => {
				btn.classList.toggle('toptour-debug-tracer__tab-btn--active', btn.dataset.tab === tabName);
			});

			// Update panes
			this.modal.querySelectorAll('.toptour-debug-tracer__tab-pane').forEach(pane => {
				pane.classList.toggle('toptour-debug-tracer__tab-pane--active', pane.id === `tab-${tabName}`);
			});
		},

		updateUI() {
			// Progress
			const progressPercent = (this.currentStep / this.totalSteps) * 100;
			document.getElementById('tracer-progress-fill').style.width = progressPercent + '%';
			document.getElementById('tracer-progress-current').textContent = this.currentStep;
			document.getElementById('tracer-progress-total').textContent = this.totalSteps;

			// Status
			this.updateStatus();
		},

		updateStatus() {
			const steps = [
				'Inicializácia',
				'Generovanie batchu',
				'Spracovanie AI',
				'Import výsledkov'
			];

			const status = this.currentStep < steps.length 
				? steps[this.currentStep] 
				: 'Hotovo';

			document.getElementById('tracer-stage-title').textContent = status;
			document.getElementById('tracer-status-text').textContent = status;
		},

		showProcessButton() {
			document.getElementById('tracer-btn-process').style.display = 'block';
			document.getElementById('tracer-btn-continue').style.display = 'none';
		},

		showContinueButton() {
			document.getElementById('tracer-btn-process').style.display = 'none';
			document.getElementById('tracer-btn-continue').style.display = 'block';
		},

		async processNextStep() {
			if (this.isProcessing) return;

			this.isProcessing = true;
			this.showContinueButton();

			try {
				switch (this.currentStep) {
					case 0:
						await this.stepInitialize();
						break;
					case 1:
						await this.stepGenerateBatch();
						break;
					case 2:
						await this.stepProcessAI();
						break;
					case 3:
						await this.stepImportResults();
						break;
					default:
						this.addLog('success', 'Všetky kroky boli dokončené!');
				}

				this.currentStep++;
				this.updateUI();
			} catch (error) {
				this.addLog('error', `Chyba: ${error.message}`);
				document.getElementById('tracer-status-bar').classList.add('toptour-debug-tracer__status--error');
			} finally {
				this.isProcessing = false;
			}
		},

		async stepInitialize() {
			this.addLog('info', 'Inicializácia trasovača...');
			document.getElementById('tracer-stage-desc').textContent = 
				'Príprava trasovacieho prostredia a načítavanie konfigurácie.';

			try {
				const response = await fetch(this._getRestUrl('tracer/initialize'), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this._getNonce(),
					},
					body: JSON.stringify({ task_id: this.taskId })
				});

				if (!response.ok) {
					throw new Error(`HTTP ${response.status}: ${response.statusText}`);
				}

				const data = await response.json();
				if (!data.success) throw new Error(data.message || 'Inicializácia zlyhala');

				this.tracerRunId = data.tracer_run_id;
				this.stepData[0] = data;

				document.getElementById('tracer-input-data').textContent = 
					JSON.stringify(data.config, null, 2);

				this.addLog('success', `Trasovač inicializovaný. Run ID: ${this.tracerRunId}`);
			} catch (error) {
				this.addLog('error', `Chyba inicializácie: ${error.message}`);
				throw error;
			}
		},

		async stepGenerateBatch() {
			this.addLog('info', 'Generovanie batchu...');
			document.getElementById('tracer-stage-desc').textContent = 
				'Čítam data z úlohy a generujem JSON batch pre AI.';

			try {
				const response = await fetch(this._getRestUrl('tracer/generate-batch'), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this._getNonce(),
					},
					body: JSON.stringify({
						task_id: this.taskId,
						tracer_run_id: this.tracerRunId
					})
				});

				if (!response.ok) {
					throw new Error(`HTTP ${response.status}: ${response.statusText}`);
				}

				const data = await response.json();
				if (!data.success) throw new Error(data.message || 'Generovanie zlyhalo');

				this.batchId = data.batch_id;
				this.stepData[1] = data;

				document.getElementById('tracer-input-data').textContent = 
					JSON.stringify(data.batch_payload, null, 2);

				this.addLog('success', `Batch vygenerovaný. ID: ${this.batchId}`);
				this.addLog('info', `Počet záznamov: ${data.record_count || 0}`);
			} catch (error) {
				this.addLog('error', `Chyba generovania batchu: ${error.message}`);
				throw error;
			}
		},

		async stepProcessAI() {
			this.addLog('info', 'Spracovanie AI...');
			document.getElementById('tracer-stage-desc').textContent = 
				'Odoslanie batchu na spracovanie do AI. Čakám na odpoveď...';

			try {
				const response = await fetch(this._getRestUrl('tracer/process-ai'), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this._getNonce(),
					},
					body: JSON.stringify({
						task_id: this.taskId,
						batch_id: this.batchId,
						tracer_run_id: this.tracerRunId
					})
				});

				if (!response.ok) {
					throw new Error(`HTTP ${response.status}: ${response.statusText}`);
				}

				const data = await response.json();
				if (!data.success) throw new Error(data.message || 'Spracovanie zlyhalo');

				this.stepData[2] = data;

			// Display AI response in Output tab
			document.getElementById('tracer-output-data').textContent = 
				JSON.stringify(data.ai_response || {}, null, 2);

			// Auto-switch to Output tab to show results
			this.switchTab('output');

			this.addLog('success', 'AI spracovanie dokončené');
			this.addLog('info', `AI model: ${data.ai_model || 'unknown'}`);
			this.addLog('info', `Tokeny: ${data.tokens_used || 0}`);
			if (data.ai_response && data.ai_response.processing_time_ms) {
				this.addLog('info', `Čas spracovania: ${data.ai_response.processing_time_ms}ms`);
			}
			} catch (error) {
				this.addLog('error', `Chyba AI spracovania: ${error.message}`);
				throw error;
			}
		},

		async stepImportResults() {
			this.addLog('info', 'Import výsledkov...');
			document.getElementById('tracer-stage-desc').textContent = 
				'Import AI výsledkov do príslušných modulov (Zistenia, Fotodôkazy, atď.).';

			try {
				const response = await fetch(this._getRestUrl('tracer/import-results'), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this._getNonce(),
					},
					body: JSON.stringify({
						task_id: this.taskId,
						batch_id: this.batchId,
						tracer_run_id: this.tracerRunId
					})
				});

				if (!response.ok) {
					throw new Error(`HTTP ${response.status}: ${response.statusText}`);
				}

				const data = await response.json();
				if (!data.success) throw new Error(data.message || 'Import zlyhalo');

				this.stepData[3] = data;

				// Load photos if available
				if (data.photos && data.photos.length > 0) {
					this.displayPhotos(data.photos);
				}

				this.addLog('success', 'Import dokončený');
				this.addLog('info', `Vytvorené zistenia: ${data.findings_created || 0}`);
				this.addLog('info', `Vytvorené fotodôkazy: ${data.photos_created || 0}`);
				this.addLog('info', `Zdroje: ${data.sources_processed || 0}`);
			} catch (error) {
				this.addLog('error', `Chyba importu: ${error.message}`);
				throw error;
			}
		},

		displayPhotos(photos) {
			const grid = document.getElementById('tracer-photos-grid');
			if (!photos || photos.length === 0) {
				grid.innerHTML = '<p>' + this.escapeHtml('Žiadne fotografie') + '</p>';
				return;
			}

			grid.innerHTML = photos.map(photo => {
				const photoUrl = photo.thumbnail_url || photo.photo_url;
				return `<div class="toptour-debug-tracer__photo-item" 
					title="${this.escapeHtml(photo.description || '')}">
					<img src="${this.escapeHtml(photoUrl)}" alt="Photo" />
				</div>`;
			}).join('');
		},

		escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		},

		_getRestUrl(endpoint) {
			// Try multiple ways to get REST root
			let restRoot = '';
			
			// Try meta tag first
			const metaTag = document.querySelector('link[rel="https://api.w.org/"]');
			if (metaTag) {
				restRoot = metaTag.href;
			}
			
			// Fall back to global object
			if (!restRoot && typeof wp !== 'undefined' && wp.apiFetch) {
				// wp.apiFetch is available - use default /wp-json/
				restRoot = '/wp-json/';
			}
			
			// If still no root, assume it
			if (!restRoot) {
				restRoot = '/wp-json/';
			}
			
			// Ensure trailing slash
			if (restRoot.charAt(restRoot.length - 1) !== '/') {
				restRoot = restRoot + '/';
			}
			
			return restRoot + 'toptour/v1/' + endpoint;
		},

		_getNonce() {
			// Try multiple ways to get the nonce
			let nonce = document.querySelector('input[name="_wpnonce"]')?.value || 
				document.querySelector('input[name="_wp_nonce"]')?.value || 
				(window.toptourTracerNonce || '') ||
				(window.toptour_ref_data?.rest_nonce || '');
			
			if (!nonce) {
				// Fall back to generating a new nonce if available
				nonce = (typeof wp !== 'undefined' && wp.rest?.nonce) || '';
			}
			
			return nonce;
		}
	};

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => TracerController.init());
	} else {
		TracerController.init();
	}

	// Expose globally for other scripts
	window.ToptourTracerController = TracerController;
})();
