(function () {
	'use strict';

	var config = window.ofoghCallBtn || {};
	var root = document.getElementById('ofogh-call-btn');
	var link = root ? root.querySelector('[data-ofogh-call-btn-track]') : null;

	if (!root || !link) {
		return;
	}

	function storageGet(key) {
		try {
			return window.sessionStorage ? window.sessionStorage.getItem(key) || '' : '';
		} catch (error) {
			return '';
		}
	}

	function storageSet(key, value) {
		try {
			if (window.sessionStorage && !window.sessionStorage.getItem(key)) {
				window.sessionStorage.setItem(key, value || '');
			}
		} catch (error) {}
	}

	storageSet('ofoghCallBtnLandingUrl', window.location.href);
	storageSet('ofoghCallBtnReferrerUrl', document.referrer || '');

	function trackClick(event) {
		if (event.defaultPrevented || !config.tracking || !config.endpoint) {
			return;
		}

		var payload = new URLSearchParams();
		payload.set('nonce', config.nonce || '');
		payload.set('phone', link.getAttribute('data-phone') || '');
		payload.set('page_id', String(config.pageId || 0));
		payload.set('page_url', window.location.href);
		payload.set('landing_url', storageGet('ofoghCallBtnLandingUrl') || window.location.href);
		payload.set('referrer_url', storageGet('ofoghCallBtnReferrerUrl') || document.referrer || '');
		payload.set('is_mobile', String(config.isMobile || 0));

		if (navigator.sendBeacon) {
			navigator.sendBeacon(config.endpoint, payload);
			return;
		}

		window.fetch(config.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: payload.toString(),
			credentials: 'same-origin',
			keepalive: true
		}).catch(function () {});
	}

	link.addEventListener('click', trackClick);

	if (!config.drag || !window.PointerEvent) {
		return;
	}

	var startX = 0;
	var startY = 0;
	var originX = 0;
	var originY = 0;
	var dragging = false;
	var didMove = false;

	root.addEventListener('pointerdown', function (event) {
		if (event.button !== 0) {
			return;
		}

		var rect = root.getBoundingClientRect();
		dragging = true;
		didMove = false;
		startX = event.clientX;
		startY = event.clientY;
		originX = rect.left;
		originY = rect.top;
		root.classList.add('is-dragging');
		root.setPointerCapture(event.pointerId);
	});

	root.addEventListener('pointermove', function (event) {
		if (!dragging) {
			return;
		}

		var deltaX = event.clientX - startX;
		var deltaY = event.clientY - startY;
		var nextX = Math.max(0, Math.min(window.innerWidth - root.offsetWidth, originX + deltaX));
		var nextY = Math.max(0, Math.min(window.innerHeight - root.offsetHeight, originY + deltaY));

		if (Math.abs(deltaX) > 5 || Math.abs(deltaY) > 5) {
			didMove = true;
		}

		root.style.left = nextX + 'px';
		root.style.top = nextY + 'px';
	});

	function stopDrag(event) {
		if (!dragging) {
			return;
		}

		dragging = false;
		root.classList.remove('is-dragging');

		if (root.hasPointerCapture && root.hasPointerCapture(event.pointerId)) {
			root.releasePointerCapture(event.pointerId);
		}
	}

	root.addEventListener('pointerup', stopDrag);
	root.addEventListener('pointercancel', stopDrag);

	link.addEventListener('click', function (event) {
		if (!didMove) {
			return;
		}

		event.preventDefault();
		didMove = false;
	}, true);
}());
