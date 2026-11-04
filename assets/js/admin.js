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
		// Cookie button: color pickers + live preview.
		var demo = document.getElementById('opg-fab-demo');
		var val = function (k) { var el = document.querySelector('[name="opg[' + k + ']"]'); return el ? el.value : ''; };
		var preview = function () {
			if (!demo) { return; }
			var pos = val('fab_position'), x = Math.min(60, parseInt(val('fab_offset_x') || 0, 10)), y = Math.min(60, parseInt(val('fab_offset_y') || 0, 10));
			var size = Math.max(24, Math.min(80, parseInt(val('fab_size') || 38, 10)));
			demo.style.cssText = '';
			demo.style.display = pos === 'hidden' ? 'none' : 'flex';
			demo.style.width = demo.style.height = size + 'px';
			demo.style.background = val('fab_bg') || '#fff';
			demo.style.color = val('fab_color') || '#1d4ed8';
			if (val('fab_border')) { demo.style.boxShadow = '0 0 0 2px ' + val('fab_border') + ',0 2px 10px rgba(0,0,0,.2)'; }
			var svg = demo.querySelector('svg'); svg.setAttribute('width', Math.round(size * 0.52)); svg.setAttribute('height', Math.round(size * 0.52));
			if (pos.indexOf('middle') === 0) { demo.style.top = '50%'; demo.style.transform = 'translateY(-50%)'; }
			else if (pos.indexOf('top') === 0) { demo.style.top = y + 'px'; } else { demo.style.bottom = y + 'px'; }
			if (pos.indexOf('right') > -1) { demo.style.right = x + 'px'; } else { demo.style.left = x + 'px'; }
		};
		// Typed hex without "#" (e.g. ff6600) is fixed on blur, so the picker and the preview accept it.
		document.querySelectorAll('.opg-color').forEach(function (inp) {
			inp.addEventListener('blur', function () {
				var v = inp.value.trim();
				if (/^([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v)) {
					v = '#' + v;
					if (window.jQuery && jQuery.fn.wpColorPicker) { jQuery(inp).wpColorPicker('color', v); } else { inp.value = v; }
					preview();
				}
			});
		});
		if (window.jQuery && jQuery.fn.wpColorPicker) {
			jQuery('.opg-color').wpColorPicker({ change: function () { setTimeout(preview, 0); }, clear: function () { setTimeout(preview, 0); } });
		}
		['fab_position', 'fab_offset_x', 'fab_offset_y', 'fab_size'].forEach(function (k) {
			var el = document.querySelector('[name="opg[' + k + ']"]');
			if (el) { el.addEventListener('input', preview); el.addEventListener('change', preview); }
		});
		preview();

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
