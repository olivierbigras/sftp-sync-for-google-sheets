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

	function announceCopied() {
		var live = document.getElementById('olissgs-copy-status');
		var text = config.copied || 'Copied to clipboard';
		if (live) {
			live.textContent = text;
		}
		document.querySelectorAll('.olissgs-copy-feedback').forEach(function (el) {
			el.hidden = false;
			el.textContent = text;
		});
	}

	function copyText(text, markCopied) {
		if (!text) {
			return;
		}
		var done = function () {
			announceCopied();
			if (markCopied && config.ajaxUrl) {
				var body = new FormData();
				body.append('action', 'olissgs_mark_script_copied');
				body.append('nonce', config.nonce || '');
				if (window.fetch) {
					fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' });
				}
			}
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done).catch(done);
			return;
		}
		done();
	}

	function updateFilenamePreview() {
		var preview = document.getElementById('olissgs-filename-preview');
		var baseInput = document.getElementById('olissgs_base_filename');
		var modeInput = document.getElementById('olissgs_filename_mode');
		var formatInput = document.getElementById('olissgs_export_format');
		if (!preview || !baseInput || !modeInput || !formatInput) {
			return;
		}
		var base = baseInput.value || 'sheet_export';
		var name = base;
		if (modeInput.value === 'dated') {
			name += '_' + (config.previewStamp || '2026-10-21_020000');
		}
		preview.textContent = name + '.' + formatInput.value;
	}

	onReady(function () {
		var scheduleSelect = document.getElementById('olissgs_schedule');
		var dailyHourRow = document.getElementById('olissgs-daily-hour-row');
		if (scheduleSelect && dailyHourRow) {
			var syncHourRow = function () {
				dailyHourRow.hidden = scheduleSelect.value !== 'daily';
			};
			scheduleSelect.addEventListener('change', syncHourRow);
			syncHourRow();
		}

		['olissgs_base_filename', 'olissgs_filename_mode', 'olissgs_export_format'].forEach(function (id) {
			var el = document.getElementById(id);
			if (el) {
				el.addEventListener('input', updateFilenamePreview);
				el.addEventListener('change', updateFilenamePreview);
			}
		});
		updateFilenamePreview();

		document.querySelectorAll('[data-olissgs-copy]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				copyText(btn.getAttribute('data-olissgs-copy') || '', false);
			});
		});

		document.querySelectorAll('[data-olissgs-copy-target]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var target = document.getElementById(btn.getAttribute('data-olissgs-copy-target') || '');
				copyText(target ? target.value : '', btn.getAttribute('data-olissgs-mark-copied') === '1');
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
	});
})();
