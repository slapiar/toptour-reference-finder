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
		steps: [],
		importModuleOrder: [
			'sources',
			'facilities',
			'destinations',
			'points_of_interest',
			'contacts',
			'interests',
			'findings',
			'photo_evidence'
		],
		importResultData: null,

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

			// Primary action button
			document.getElementById('tracer-btn-primary').addEventListener('click', 
				() => this.processNextStep());

			// Tab switching
			this.modal.querySelectorAll('.toptour-debug-tracer__tab-btn').forEach(btn => {
				btn.addEventListener('click', (e) => this.switchTab(e.target.dataset.tab));
			});
		},

		open(taskId) {
			this.taskId = taskId;
			this.currentStep = 0;
			this.steps = this.buildSteps();
			this.totalSteps = this.steps.length;
			this.logs = [];
			this.stepData = {};
			this.batchId = null;
			this.tracerRunId = null;
			this.isProcessing = false;
			this.importResultData = null;

			this.modal.style.display = 'flex';
			this.updateUI();
			this.addLog('info', `Trasovač spustený pre úlohu #${taskId}`);
		},

		buildSteps() {
			const baseSteps = [
				{
					key: 'initialize',
					title: 'Inicializácia',
					description: 'Príprava trasovacieho prostredia a načítavanie konfigurácie.'
				},
				{
					key: 'generate_batch',
					title: 'Generovanie batchu',
					description: 'Zber vstupných dát a zostavenie batch payloadu pre AI.'
				},
				{
					key: 'process_ai',
					title: 'Spracovanie AI',
					description: 'Odoslanie batchu do AI a prevzatie štruktúrovaného výstupu.'
				},
				{
					key: 'start_import',
					title: 'Spustenie importu',
					description: 'Spustenie importera, ktorý rozloží AI výstup do interných modulov.'
				}
			];

			const importSteps = this.importModuleOrder.map((moduleKey) => {
				const meta = this.getImportModuleMeta(moduleKey);
				return {
					key: `module_${moduleKey}`,
					title: meta.title,
					description: meta.description,
					moduleKey
				};
			});

			return baseSteps.concat(importSteps);
		},

		getImportModuleMeta(moduleKey) {
			const meta = {
				sources: {
					title: 'Import zdrojov',
					description: 'Mapovanie kandidátnych zdrojov do modulu Reference Sources.'
				},
				facilities: {
					title: 'Import zariadení',
					description: 'Spracovanie kandidátnych zariadení a ich aktualizácií.'
				},
				destinations: {
					title: 'Import destinácií',
					description: 'Spracovanie kandidátnych destinácií z AI odpovede.'
				},
				points_of_interest: {
					title: 'Import bodov záujmu',
					description: 'Import kandidátnych bodov záujmu a ich väzieb.'
				},
				contacts: {
					title: 'Import kontaktov',
					description: 'Spracovanie kandidátnych kontaktov a vzťahov.'
				},
				interests: {
					title: 'Import záujmov',
					description: 'Import kandidátnych záujmov odvodených z AI dát.'
				},
				findings: {
					title: 'Import zistení',
					description: 'Zápis pending findings a previazaní na zdroje.'
				},
				photo_evidence: {
					title: 'Import fotodôkazov',
					description: 'Zápis photo evidence kandidátov a previazaní na zistenia.'
				}
			};

			return meta[moduleKey] || {
				title: moduleKey,
				description: 'Import modulových dát.'
			};
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
			this.syncActionButtons();
		},

		updateStatus() {
			const currentStepMeta = this.steps[this.currentStep] || null;
			const status = currentStepMeta ? currentStepMeta.title : 'Hotovo';

			document.getElementById('tracer-stage-title').textContent = status;
			document.getElementById('tracer-status-text').textContent = status;
			if (currentStepMeta) {
				document.getElementById('tracer-stage-desc').textContent = currentStepMeta.description;
			}
		},

		syncActionButtons() {
			const primaryButton = document.getElementById('tracer-btn-primary');

			if (this.isProcessing || this.currentStep >= this.totalSteps) {
				primaryButton.style.display = 'none';
				return;
			}

			if (this.currentStep === 0) {
				primaryButton.textContent = 'Spustiť trasovanie';
				primaryButton.style.display = 'inline-block';
				return;
			}

			primaryButton.textContent = 'Pokračovať';
			primaryButton.style.display = 'inline-block';
		},

		async processNextStep() {
			if (this.isProcessing) return;

			this.isProcessing = true;
			this.syncActionButtons();
			const currentStepMeta = this.steps[this.currentStep] || null;

			try {
				switch (currentStepMeta?.key) {
					case 'initialize':
						await this.stepInitialize();
						break;
					case 'generate_batch':
						await this.stepGenerateBatch();
						break;
					case 'process_ai':
						await this.stepProcessAI();
						break;
					case 'start_import':
						await this.stepImportResults();
						break;
					default:
						if (currentStepMeta?.moduleKey) {
							this.showImportModuleSummary(currentStepMeta.moduleKey);
						} else {
							this.addLog('success', 'Všetky kroky boli dokončené!');
						}
				}

				this.currentStep++;
				this.updateUI();
			} catch (error) {
				this.addLog('error', `Chyba: ${error.message}`);
				document.getElementById('tracer-status-bar').classList.add('toptour-debug-tracer__status--error');
				this.syncActionButtons();
			} finally {
				this.isProcessing = false;
				this.syncActionButtons();
			}
		},

		showImportModuleSummary(moduleKey) {
			const moduleMetrics = this.importResultData?.module_metrics?.[moduleKey] || {};
			const moduleMeta = this.getImportModuleMeta(moduleKey);
			const created = Number(moduleMetrics.created || 0);
			const updated = Number(moduleMetrics.updated || 0);
			const errors = Number(moduleMetrics.errors || 0);

			document.getElementById('tracer-stage-desc').textContent = moduleMeta.description;
			this.addLog('info', `${moduleMeta.title}: created=${created}, updated=${updated}, errors=${errors}`);

			if (moduleKey === 'photo_evidence' && this.importResultData?.photos?.length) {
				this.displayPhotos(this.importResultData.photos);
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
			const outputDiv = document.getElementById('tracer-output-data');
			outputDiv.textContent = JSON.stringify(data.ai_response || {}, null, 2);

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
				this.importResultData = data;

				// Load photos if available
				if (data.photos && data.photos.length > 0) {
					this.displayPhotos(data.photos);
				}

				this.addLog('success', 'Import dokončený');
				if (data.import_message) {
					this.addLog('info', data.import_message);
				}
				if (data.import_metrics) {
					this.addLog('info', `Import sumár: found=${data.import_metrics.found_count || 0}, new=${data.import_metrics.new_count || 0}, updated=${data.import_metrics.duplicate_count || 0}, errors=${data.import_metrics.error_count || 0}`);
				}
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

		grid.innerHTML = photos.map((photo, idx) => {
			const photoUrl = photo.thumbnail_url || photo.photo_url || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22150%22%3E%3Crect fill=%22%23ddd%22 width=%22200%22 height=%22150%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22sans-serif%22 font-size=%2214%22 fill=%22%23999%22%3EFoto ${idx + 1}%3C/text%3E%3C/svg%3E';
			return `<div class="toptour-debug-tracer__photo-item">
				<div class="toptour-debug-tracer__photo-image">
					<img src="${this.escapeHtml(photoUrl)}" alt="Photo ${idx + 1}" />
				</div>
				<div class="toptour-debug-tracer__photo-details">
					${photo.url ? `<p><strong>URL:</strong> <a href="${this.escapeHtml(photo.url)}" target="_blank" rel="noopener">Otvoriť</a></p>` : ''}
					${photo.description ? `<p><strong>Popis:</strong> ${this.escapeHtml(photo.description)}</p>` : ''}
					${photo.destination ? `<p><strong>Destinácia:</strong> ${this.escapeHtml(photo.destination)}</p>` : ''}
					${photo.facility ? `<p><strong>Zariadenie:</strong> ${this.escapeHtml(photo.facility)}</p>` : ''}
					${photo.source ? `<p><strong>Zdroj:</strong> ${this.escapeHtml(photo.source)}</p>` : ''}
					${photo.finding ? `<p><strong>Zistenie:</strong> ${this.escapeHtml(photo.finding)}</p>` : ''}
				</div>
			</div>`;
		}).join('');
		
		// Switch to photos tab if there are photos
		if (photos.length > 0) {
			this.switchTab('photos');
		}
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
		},

		escapeHtml(value) {
			return String(value)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
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
