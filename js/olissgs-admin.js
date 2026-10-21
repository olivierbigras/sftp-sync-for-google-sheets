/**
 * Admin UI for SFTP Sync for Google Sheets.
 */
(function () {
	var config = window.olissgsAdmin || {};

	function onReady(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function copyText(text) {
		if (!text) {
			return;
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text);
		}
	}

	function showStep(step) {
		step = parseInt(step, 10);
		if (step < 1 || step > 3) {
			step = 1;
		}

		document.querySelectorAll('[data-olissgs-panel]').forEach(function (panel) {
			var active = parseInt(panel.getAttribute('data-olissgs-panel'), 10) === step;
			panel.hidden = !active;
		});

		document.querySelectorAll('[data-olissgs-step]').forEach(function (tab) {
			var active = parseInt(tab.getAttribute('data-olissgs-step'), 10) === step;
			tab.classList.toggle('nav-tab-active', active);
			tab.setAttribute('aria-current', active ? 'step' : 'false');
		});

		var stepField = document.getElementById('olissgs_step_field');
		if (stepField) {
			stepField.value = String(step);
		}

		if (window.history && window.history.replaceState) {
			try {
				var url = new URL(window.location.href);
				url.searchParams.set('olissgs_step', String(step));
				window.history.replaceState({}, '', url.toString());
			} catch (e) {
				// Ignore URL update failures.
			}
		}
	}

	onReady(function () {
		showStep(config.initialStep || 1);

		document.querySelectorAll('[data-olissgs-step], [data-olissgs-goto]').forEach(function (el) {
			el.addEventListener('click', function (e) {
				var step = el.getAttribute('data-olissgs-goto') || el.getAttribute('data-olissgs-step');
				if (!step) {
					return;
				}
				e.preventDefault();
				showStep(step);
			});
		});

		var scheduleSelect = document.getElementById('olissgs_schedule');
		var dailyHourRow = document.getElementById('olissgs-daily-hour-row');
		if (scheduleSelect && dailyHourRow) {
			var syncHourRow = function () {
				dailyHourRow.hidden = scheduleSelect.value !== 'daily';
			};
			scheduleSelect.addEventListener('change', syncHourRow);
			syncHourRow();
		}

		document.querySelectorAll('[data-olissgs-copy]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				copyText(btn.getAttribute('data-olissgs-copy') || '');
			});
		});

		document.querySelectorAll('[data-olissgs-confirm]').forEach(function (el) {
			el.addEventListener('click', function (e) {
				var message = el.getAttribute('data-olissgs-confirm') || '';
				if (message && !window.confirm(message)) {
					e.preventDefault();
				}
			});
		});

		document.querySelectorAll('[data-olissgs-show]').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var targetId = btn.getAttribute('data-olissgs-show');
				var target = targetId ? document.getElementById(targetId) : null;
				if (target) {
					target.hidden = false;
					target.classList.add('is-visible');
				}
				btn.hidden = true;
			});
		});
	});
})();
