/* Ofnoa Privacy Guard - admin */
(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		// Settings tabs.
		var tabs = document.querySelectorAll('[data-opg-tab]');
		if (tabs.length) {
			var save = document.querySelector('.opg-save');
			var activate = function (key) {
				var found = false;
				tabs.forEach(function (t) {
					var on = t.getAttribute('data-opg-tab') === key;
					t.classList.toggle('nav-tab-active', on);
					found = found || on;
				});
				if (!found) { key = tabs[0].getAttribute('data-opg-tab'); tabs[0].classList.add('nav-tab-active'); }
				document.querySelectorAll('.opg-tab').forEach(function (p) { p.classList.toggle('is-active', p.id === 'tab-' + key); });
				if (save) { save.style.display = key === 'transfer' ? 'none' : ''; }
			};
			tabs.forEach(function (t) {
				t.addEventListener('click', function (e) {
					e.preventDefault();
					var k = t.getAttribute('data-opg-tab');
					history.replaceState(null, '', '#tab-' + k);
					activate(k);
				});
			});
			activate((location.hash || '').replace('#tab-', '') || tabs[0].getAttribute('data-opg-tab'));
		}
		// Hide passing checks.
		var hide = document.querySelector('[data-opg-hide-pass]');
		if (hide) {
			var wrap = document.querySelector('.opg-wrap');
			try { hide.checked = localStorage.getItem('opgHidePass') === '1'; } catch (e) {}
			var apply = function () {
				wrap.classList.toggle('opg-hide-pass', hide.checked);
				try { localStorage.setItem('opgHidePass', hide.checked ? '1' : '0'); } catch (e) {}
			};
			hide.addEventListener('change', apply);
			apply();
		}
	});
})();
