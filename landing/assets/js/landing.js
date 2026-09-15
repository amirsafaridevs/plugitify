/**
 * پلاگیتی‌فای — Landing Page
 * Single job: reveal elements once, as they enter the viewport.
 */
(function () {
	'use strict';

	var items = document.querySelectorAll('.reveal');

	if (!items.length) {
		return;
	}

	var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// No IntersectionObserver, or the visitor asked for less motion: show everything.
	if (reduced || !('IntersectionObserver' in window)) {
		Array.prototype.forEach.call(items, function (el) {
			el.classList.add('is-in');
		});
		return;
	}

	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			if (!entry.isIntersecting) {
				return;
			}
			entry.target.classList.add('is-in');
			observer.unobserve(entry.target);
		});
	}, {
		rootMargin: '0px 0px -12% 0px',
		threshold: 0.12
	});

	Array.prototype.forEach.call(items, function (el) {
		observer.observe(el);
	});
})();
