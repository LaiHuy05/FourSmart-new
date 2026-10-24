(() => {
  'use strict';
  const form = document.getElementById('recovery-reset-form');
  if (!form) return;
  const token = document.getElementById('recovery-token');
  const password = document.getElementById('recovery-new-password');
  const confirmation = document.getElementById('recovery-confirm-password');
  const submit = document.getElementById('recovery-submit');
  const status = document.getElementById('recovery-link-status');
  const fragment = new URLSearchParams(window.location.hash.slice(1));
  const incoming = fragment.get('token') || '';
  // Remove the bearer token from browser history; never use query strings/storage.
  window.history.replaceState(null, '', window.location.pathname + window.location.search);
  if (/^[0-9a-f]{64}$/.test(incoming)) token.value = incoming;
  const ready = () => Boolean(token.value) || form.dataset.hasContext === '1';
  submit.disabled = !ready();
  if (!ready()) status.textContent = 'Vui lòng mở liên kết trong email hoặc yêu cầu một liên kết mới.';
  const validate = () => {
    password.setCustomValidity(new TextEncoder().encode(password.value).length > 72
      ? 'Mật khẩu không được vượt quá 72 byte.' : '');
    confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value
      ? 'Xác nhận mật khẩu mới không trùng khớp.' : '');
  };
  password.addEventListener('input', validate);
  confirmation.addEventListener('input', validate);
  form.addEventListener('submit', (event) => {
    validate();
    if (!ready() || !form.reportValidity()) {
      event.preventDefault();
      return;
    }
    submit.disabled = true;
    submit.textContent = 'Đang đặt lại mật khẩu…';
  });
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      password.value = '';
      confirmation.value = '';
      validate();
    }
    submit.disabled = !ready();
    submit.textContent = 'Đặt lại mật khẩu';
  });
})();
