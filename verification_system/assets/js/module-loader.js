(function () {
  const currentScript = document.currentScript;
  const logoSrc = currentScript ? currentScript.dataset.logo : '';
  const delayMs = Number(currentScript ? currentScript.dataset.delay : '') || 500;
  let isNavigating = false;
  let overlay = null;

  function getOverlay() {
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.className = 'module-loader';
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.innerHTML = [
      '<img class="module-loader__logo" src="' + logoSrc + '" alt="Loading">'
    ].join('');
    document.body.appendChild(overlay);
    return overlay;
  }

  function shouldHandleLink(link, event) {
    if (!link || isNavigating) return false;
    if (event.defaultPrevented || event.button !== 0) return false;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (link.target && link.target !== '_self') return false;
    if (link.hasAttribute('download')) return false;
    if (link.classList.contains('active-link')) return false;

    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) return false;

    const targetUrl = new URL(link.href, window.location.href);
    if (targetUrl.origin !== window.location.origin) return false;

    const currentUrl = new URL(window.location.href);
    currentUrl.hash = '';
    targetUrl.hash = '';

    return targetUrl.href !== currentUrl.href;
  }

  function showLoaderAndNavigate(url) {
    isNavigating = true;
    document.body.classList.add('module-loader-lock');
    requestAnimationFrame(() => getOverlay().classList.add('is-visible'));
    window.setTimeout(() => {
      window.location.href = url;
    }, delayMs);
  }

  document.addEventListener('click', (event) => {
    const link = event.target.closest('.menu-slider a, .verification-choice');
    if (!shouldHandleLink(link, event)) return;

    event.preventDefault();
    showLoaderAndNavigate(link.href);
  });
})();
