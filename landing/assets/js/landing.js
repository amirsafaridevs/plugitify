(function () {
	'use strict';

	function revealSections() {
		var elements = document.querySelectorAll('.reveal');

		if (!('IntersectionObserver' in window)) {
			elements.forEach(function (element) {
				element.classList.add('is-visible');
			});
			return;
		}

		var observer = new IntersectionObserver(function (entries, currentObserver) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) {
					return;
				}

				entry.target.classList.add('is-visible');
				currentObserver.unobserve(entry.target);
			});
		}, {
			rootMargin: '0px 0px -8% 0px',
			threshold: 0.1
		});

		elements.forEach(function (element) {
			observer.observe(element);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', revealSections);
	} else {
		revealSections();
	}
}());
