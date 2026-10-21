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

	onReady(function () {
		var notice = document.querySelector('.olissgs-donate-notice');
		if (notice && config.ajaxUrl) {
			notice.addEventListener('click', function (e) {
				if (!e.target.classList.contains('notice-dismiss')) {
					return;
				}
				var body = new FormData();
				body.append('action', 'olissgs_dismiss_donate');
				body.append('nonce', config.donateNonce || '');
				if (window.fetch) {
					fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' });
				}
			});
		}

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
