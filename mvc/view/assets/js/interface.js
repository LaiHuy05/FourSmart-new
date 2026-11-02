/* Progressive UI enhancements; form payloads and navigation stay native. */
(() => {
  'use strict';

  const current = new URL(window.location.href);
  const adminSection = (action) => {
    const group = /^(product|category|order|account|comment)/.exec(action || 'home');
    return group ? group[1] : 'home';
  };
  document.querySelectorAll('.client-nav a, .admin-menu a').forEach((link) => {
    const target = new URL(link.href, current);
    const admin = current.searchParams.get('act') === 'admin';
    const matches = admin
      ? target.searchParams.get('act') === 'admin'
        && adminSection(target.searchParams.get('admin')) === adminSection(current.searchParams.get('admin'))
      : target.searchParams.get('act') !== 'admin'
        && target.searchParams.get('client') === (current.searchParams.get('client') || 'home');
    if (matches) {
      const exact = (target.searchParams.get('admin') || 'home') === (current.searchParams.get('admin') || 'home');
      link.setAttribute('aria-current', admin && !exact ? 'location' : 'page');
    }
  });

  // Connect existing unassociated labels without replacing backend/JS IDs.
  document.querySelectorAll('label:not([for])').forEach((label, index) => {
    const field = label.nextElementSibling;
    if (!field || !field.matches('input:not([type="hidden"]), select, textarea')) return;
    if (!field.id) field.id = `fs-field-${index}`;
    label.htmlFor = field.id;
  });

  document.querySelectorAll('.product-card-image img').forEach((img) => {
    img.loading = 'lazy';
    img.decoding = 'async';
  });

  document.querySelectorAll('.product-card-image img, .product-image-box img').forEach((img) => {
    const showFallback = () => {
      if (!img.isConnected) return;
      const fallback = document.createElement('div');
      fallback.className = 'product-no-image';
      fallback.textContent = 'Hình ảnh đang cập nhật';
      fallback.setAttribute('role', 'img');
      fallback.setAttribute('aria-label', img.alt || fallback.textContent);
      img.replaceWith(fallback);
    };
    img.addEventListener('error', showFallback, { once: true });
    if (img.complete && img.naturalWidth === 0) showFallback();
  });

  // Visual feedback only: do not disable submitters or change their name/value.
  document.addEventListener('submit', (event) => {
    const form = event.target;
    const submitter = event.submitter;
    if (!(form instanceof HTMLFormElement) || !submitter) return;
    window.setTimeout(() => {
      if (event.defaultPrevented || !submitter.isConnected) return;
      submitter.setAttribute('aria-busy', 'true');
      window.setTimeout(() => submitter.removeAttribute('aria-busy'), 10000);
    }, 0);
  });
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('[aria-busy="true"]').forEach((node) => node.removeAttribute('aria-busy'));
  });
})();
