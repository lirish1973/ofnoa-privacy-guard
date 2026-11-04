/* Ofnoa Privacy Guard - front end: consent enforcement, universal form layer, cookie banner. */
(function () {
	'use strict';
	var C = window.OPG || {};
	var B = C.banner || {};
	var uid = 0;

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
	}

	function post(data) {
		try {
			var fd = new FormData();
			fd.append('action', 'opg_log_consent');
			fd.append('nonce', C.nonce || '');
			Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
			if (navigator.sendBeacon && navigator.sendBeacon(C.ajax, fd)) { return; }
			var x = new XMLHttpRequest();
			x.open('POST', C.ajax, true);
			x.send(fd);
		} catch (e) { /* never break the page */ }
	}

	/* ------------------------------------------------------------
	 * Form consent
	 * ------------------------------------------------------------ */

	function isCandidate(form) {
		if (!form || form.nodeName !== 'FORM') { return false; }
		if (form.querySelector('.opg-consent')) { return false; }
		if (form.hasAttribute('data-opg-skip') || /(^|\s)opg-skip(\s|$)/.test(form.className)) { return false; }
		// No method check: many lead forms are sent by JS (fetch / WhatsApp) and have no method attribute.
		if (form.getAttribute('role') === 'search' || /search/i.test(form.className + ' ' + (form.id || ''))) { return false; }
		if (form.querySelector('input[type="search"], input[name="s"]')) { return false; }
		if (form.querySelector('input[type="password"]')) { return false; }
		var action = form.getAttribute('action') || '';
		if (/wp-login\.php|wp-admin|add-to-cart|\/cart/i.test(action)) { return false; }
		// Form plugins get the box server-side; if that failed (cache, AJAX render) we add it here.
		if (/woocommerce-cart-form|cart|variations_form|checkout|wc-block|opg-rights-form/i.test(form.className + ' ' + (form.id || ''))) { return false; }
		if (form.closest && form.closest('#wpadminbar, .opg-cookie-banner')) { return false; }
		return !!form.querySelector('input[type="email"], input[type="tel"], input[name*="email" i], input[name*="phone" i]');
	}

	function boxNode() {
		var wrap = document.createElement('div');
		uid++;
		wrap.innerHTML = (C.boxHtml || '').replace(/id="(opg-[^"]+)"/g, 'id="$1-u' + uid + '"').replace(/for="(opg-[^"]+)"/g, 'for="$1-u' + uid + '"');
		return wrap.firstElementChild;
	}

	function inject(form) {
		if (!isCandidate(form)) { return; }
		var node = boxNode();
		if (!node) { return; }
		var submit = form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
		if (submit) {
			var target = submit;
			// Put the box before the wrapping element when the submit sits alone inside it.
			if (target.parentElement && target.parentElement !== form && target.parentElement.children.length === 1) { target = target.parentElement; }
			target.parentNode.insertBefore(node, target);
		} else {
			form.appendChild(node);
		}
	}

	function showError(box) {
		var err = box.querySelector('.opg-error-msg');
		if (!err) {
			err = document.createElement('div');
			err.className = 'opg-error-msg';
			err.setAttribute('role', 'alert');
			err.textContent = C.error || '';
			box.appendChild(err);
		}
		box.classList.add('opg-invalid');
		var cb = box.querySelector('input[name="opg_privacy"]');
		if (cb) { try { cb.focus({ preventScroll: false }); } catch (e) { cb.focus(); } }
	}

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form || !form.querySelector) { return; }
		var box = form.querySelector('.opg-consent');
		if (!box) { return; }
		var cb = box.querySelector('input[name="opg_privacy"]');
		if (cb && !cb.checked) {
			e.preventDefault();
			e.stopImmediatePropagation();
			showError(box);
			return;
		}
		box.classList.remove('opg-invalid');
		if (box.getAttribute('data-opg-consent') === 'universal') {
			var em = form.querySelector('input[type="email"], input[name*="email" i]');
			var mk = box.querySelector('input[name="opg_marketing"]');
			post({
				type: 'form',
				email: em ? em.value : '',
				marketing: mk && mk.checked ? 1 : 0,
				form: (form.id || form.getAttribute('name') || form.className || 'form').toString().slice(0, 120),
				page: location.href
			});
		}
	}, true);

	document.addEventListener('change', function (e) {
		if (e.target && e.target.name === 'opg_privacy' && e.target.checked) {
			var box = e.target.closest('.opg-consent');
			if (box) {
				box.classList.remove('opg-invalid');
				var err = box.querySelector('.opg-error-msg');
				if (err) { err.remove(); }
			}
		}
	});

	function scan(root) {
		if (!C.universal) { return; }
		var forms = (root || document).querySelectorAll ? (root || document).querySelectorAll('form') : [];
		for (var i = 0; i < forms.length; i++) { inject(forms[i]); }
		if (root && root.nodeName === 'FORM') { inject(root); }
	}

	/* ------------------------------------------------------------
	 * Cookie banner
	 * ------------------------------------------------------------ */

	var CATS = ['functional', 'analytics', 'marketing'];

	function readChoice() {
		var m = document.cookie.match(new RegExp('(?:^|; )' + (B.cookie || 'opg_consent') + '=([^;]*)'));
		if (!m) { return null; }
		var parts = decodeURIComponent(m[1]).split('|');
		if (parts[0] !== (B.version || '1')) { return null; }
		return (parts[1] || '').split(',').filter(Boolean);
	}

	function writeChoice(cats) {
		var v = (B.version || '1') + '|' + cats.join(',') + '|' + Date.now();
		var d = new Date(Date.now() + (B.days || 180) * 864e5);
		document.cookie = (B.cookie || 'opg_consent') + '=' + encodeURIComponent(v) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}

	function activateScripts(cats) {
		var held = document.querySelectorAll('script[type="text/plain"][data-opg-category]');
		for (var i = 0; i < held.length; i++) {
			var old = held[i];
			if (cats.indexOf(old.getAttribute('data-opg-category')) === -1) { continue; }
			var s = document.createElement('script');
			for (var a = 0; a < old.attributes.length; a++) {
				var at = old.attributes[a];
				if (at.name !== 'type' && at.name !== 'data-opg-category') { s.setAttribute(at.name, at.value); }
			}
			if (!old.src) { s.text = old.text; }
			old.parentNode.replaceChild(s, old);
		}
		var iframes = document.querySelectorAll('iframe[data-opg-src][data-opg-category]');
		for (var j = 0; j < iframes.length; j++) {
			if (cats.indexOf(iframes[j].getAttribute('data-opg-category')) > -1) { iframes[j].src = iframes[j].getAttribute('data-opg-src'); }
		}
	}

	function applyConsent(cats) {
		var an = cats.indexOf('analytics') > -1 ? 'granted' : 'denied';
		var mk = cats.indexOf('marketing') > -1 ? 'granted' : 'denied';
		if (typeof window.gtag === 'function') {
			window.gtag('consent', 'update', { analytics_storage: an, ad_storage: mk, ad_user_data: mk, ad_personalization: mk });
		}
		if (typeof window.fbq === 'function') {
			try { window.fbq('consent', mk === 'granted' ? 'grant' : 'revoke'); } catch (e) {}
		}
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ event: 'opg_consent', opg_categories: cats.join(',') });
		activateScripts(cats);
		try { window.dispatchEvent(new CustomEvent('opg:consent', { detail: { categories: cats } })); } catch (e) {}
	}

	function el(tag, cls, html) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		if (html !== undefined) { n.innerHTML = html; }
		return n;
	}

	function esc(s) {
		return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}

	var banner = null, reopen = null;

	function closeBanner() {
		if (banner) { banner.remove(); banner = null; }
		showReopen();
	}

	function save(cats) {
		writeChoice(cats);
		applyConsent(cats);
		post({ type: 'cookie', categories: cats.length ? cats.join(',') : 'necessary-only' });
		closeBanner();
	}

	function openBanner(withSettings) {
		if (banner) { banner.remove(); }
		if (reopen) { reopen.style.display = 'none'; }
		var L = B.labels || {};
		var current = readChoice() || [];
		banner = el('div', 'opg-cookie-banner opg-pos-' + (B.position || 'bottom'));
		banner.setAttribute('role', 'dialog');
		banner.setAttribute('aria-live', 'polite');
		banner.setAttribute('aria-label', L.title || 'Cookies');
		banner.setAttribute('dir', document.documentElement.dir || 'rtl');
		var html = '<div class="opg-cb-inner"><div class="opg-cb-text"><strong>' + esc(L.title || '') + '</strong><p>' + (B.text || '') +
			(B.policy ? ' <a href="' + esc(B.policy) + '" target="_blank" rel="noopener">' + esc(L.policy || '') + '</a>' : '') + '</p></div>';
		html += '<div class="opg-cb-settings"' + (withSettings ? '' : ' hidden') + '>';
		html += '<label class="opg-cb-cat"><input type="checkbox" checked disabled> ' + esc(L.necessary || '') + '</label>';
		CATS.forEach(function (c) {
			html += '<label class="opg-cb-cat"><input type="checkbox" data-opg-cat="' + c + '"' + (current.indexOf(c) > -1 ? ' checked' : '') + '> ' + esc(L[c] || c) + '</label>';
		});
		html += '</div><div class="opg-cb-actions">' +
			'<button type="button" class="opg-cb-btn opg-cb-accept" data-act="accept">' + esc(L.accept || 'OK') + '</button>' +
			'<button type="button" class="opg-cb-btn opg-cb-reject" data-act="reject">' + esc(L.reject || 'No') + '</button>' +
			'<button type="button" class="opg-cb-btn opg-cb-link" data-act="settings">' + esc(L.settings || '') + '</button>' +
			'<button type="button" class="opg-cb-btn opg-cb-save" data-act="save"' + (withSettings ? '' : ' hidden') + '>' + esc(L.save || '') + '</button>' +
			'</div></div>';
		banner.innerHTML = html;
		banner.addEventListener('click', function (e) {
			var act = e.target && e.target.getAttribute('data-act');
			if (!act) { return; }
			if (act === 'accept') { save(CATS.slice()); }
			if (act === 'reject') { save([]); }
			if (act === 'settings') {
				banner.querySelector('.opg-cb-settings').hidden = false;
				banner.querySelector('.opg-cb-save').hidden = false;
				e.target.hidden = true;
			}
			if (act === 'save') {
				var cats = [];
				var boxes = banner.querySelectorAll('input[data-opg-cat]');
				for (var i = 0; i < boxes.length; i++) { if (boxes[i].checked) { cats.push(boxes[i].getAttribute('data-opg-cat')); } }
				save(cats);
			}
		});
		document.body.appendChild(banner);
		var first = banner.querySelector('.opg-cb-accept');
		if (first && withSettings) { first.focus(); }
	}

	function showReopen() {
		if (!B.enabled) { return; }
		if (!reopen) {
			reopen = el('button', 'opg-cookie-fab');
			reopen.type = 'button';
			reopen.setAttribute('aria-label', (B.labels && B.labels.reopen) || 'Cookies');
			reopen.title = (B.labels && B.labels.reopen) || 'Cookies';
			reopen.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5zm-3.5 7a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm2 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm5-1a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>';
			reopen.addEventListener('click', function () { openBanner(true); });
			document.body.appendChild(reopen);
		}
		reopen.style.display = '';
	}

	document.addEventListener('click', function (e) {
		var t = e.target && e.target.closest ? e.target.closest('[data-opg-cookie-open]') : null;
		if (t && B.enabled) { e.preventDefault(); openBanner(true); }
	});

	// WooCommerce classic checkout re-renders the payment box via AJAX - keep the ticks.
	function keepCheckoutState() {
		var jq = window.jQuery;
		if (!jq) { return; }
		var state = null;
		jq(document.body).on('update_checkout', function () {
			var p = document.querySelector('form.checkout input[name="opg_privacy"]');
			var m = document.querySelector('form.checkout input[name="opg_marketing"]');
			state = { p: !!(p && p.checked), m: !!(m && m.checked) };
		});
		jq(document.body).on('updated_checkout', function () {
			if (!state) { return; }
			var p = document.querySelector('form.checkout input[name="opg_privacy"]');
			var m = document.querySelector('form.checkout input[name="opg_marketing"]');
			if (p) { p.checked = state.p; }
			if (m) { m.checked = state.m; }
		});
	}

	ready(function () {
		keepCheckoutState();
		scan(document);
		if (C.universal && window.MutationObserver) {
			new MutationObserver(function (muts) {
				muts.forEach(function (m) {
					for (var i = 0; i < m.addedNodes.length; i++) {
						var n = m.addedNodes[i];
						if (n.nodeType === 1) { scan(n); }
					}
				});
			}).observe(document.body, { childList: true, subtree: true });
		}
		if (B.enabled) {
			var choice = readChoice();
			if (choice === null) { openBanner(false); } else { applyConsent(choice); showReopen(); }
		}
	});
})();
