/**
 * Plugifity Dashboard - Skeleton loading, Tools API Token (copy + generate)
 */
(function () {
  'use strict';

  var wrap = document.querySelector('.plugifity-dashboard');
  if (!wrap) return;

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

  // Tools API Token: copy and generate
  var inputEl = document.getElementById('plugitify-tools-api-key');
  var copyBtn = wrap.querySelector('.plugifity-tools-key-copy');
  var generateBtn = document.getElementById('plugitify-generate-tools-key');

  if (copyBtn && inputEl) {
    copyBtn.addEventListener('click', function () {
      var val = inputEl.value;
      if (!val) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(val).then(function () {
          copyBtn.setAttribute('title', 'Copied!');
          setTimeout(function () {
            copyBtn.setAttribute('title', 'Copy to clipboard');
          }, 2000);
        });
      } else {
        inputEl.select();
        document.execCommand('copy');
        copyBtn.setAttribute('title', 'Copied!');
        setTimeout(function () {
          copyBtn.setAttribute('title', 'Copy to clipboard');
        }, 2000);
      }
    });
  }

  if (generateBtn && inputEl && typeof plugitifyDashboard !== 'undefined' && plugitifyDashboard.generateToolsKeyNonce) {
    generateBtn.addEventListener('click', function () {
      if (generateBtn.disabled) return;
      generateBtn.disabled = true;

      var formData = new FormData();
      formData.append('action', 'plugitify_generate_tools_key');
      formData.append('nonce', plugitifyDashboard.generateToolsKeyNonce);

      fetch(ajaxurl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          generateBtn.disabled = false;
          if (data.success && data.data && data.data.key) {
            inputEl.value = data.data.key;
            inputEl.select();
          }
        })
        .catch(function () {
          generateBtn.disabled = false;
        });
    });
  }
})();
