/**
 * Plugifity Dashboard - Skeleton loading then reveal content
 */
(function () {
  'use strict';

  var wrap = document.querySelector('.plugifity-dashboard');
  if (!wrap) return;

  // Simulate minimal load delay so skeleton is visible, then reveal
  function revealContent() {
    wrap.classList.add('plugifity-dashboard--loaded');
  }

  if (document.readyState === 'complete') {
    setTimeout(revealContent, 400);
  } else {
    window.addEventListener('load', function () {
      setTimeout(revealContent, 400);
    });
  }
})();
