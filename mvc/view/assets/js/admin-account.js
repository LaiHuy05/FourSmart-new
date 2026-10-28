(() => {
  const form = document.getElementById('password-reset-form');
  const toggle = document.getElementById('password-reset-toggle');

  if (!form || !toggle) return;

  const password = document.getElementById('new-password');
  const confirmation = document.getElementById('confirm-password');
  const submit = document.getElementById('password-reset-submit');

  const checkConfirmation = () => {
    confirmation.setCustomValidity(
      confirmation.value && confirmation.value !== password.value
        ? 'Mật khẩu xác nhận không trùng khớp.'
        : ''
    );
  };

  toggle.addEventListener('click', () => {
    const open = form.hidden;
    form.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.textContent = open ? 'Đóng biểu mẫu' : 'Mở biểu mẫu cấp lại mật khẩu';

    if (open) {
      password.focus();
    } else {
      form.reset();
      confirmation.setCustomValidity('');
    }
  });

  password.addEventListener('input', checkConfirmation);
  confirmation.addEventListener('input', checkConfirmation);

  form.addEventListener('submit', (event) => {
    checkConfirmation();

    if (!form.reportValidity()) {
      event.preventDefault();
      return;
    }

    const message = `Cấp lại mật khẩu cho tài khoản "${form.dataset.accountName}"? Mật khẩu cũ sẽ không còn sử dụng được.`;

    if (!window.confirm(message)) {
      event.preventDefault();
      return;
    }

    submit.disabled = true;
    toggle.disabled = true;
    submit.textContent = 'Đang cấp lại mật khẩu…';
  });

  // A restored page must remain usable after navigating back from a request.
  window.addEventListener('pageshow', () => {
    submit.disabled = false;
    toggle.disabled = false;
    submit.textContent = 'Cấp lại mật khẩu';
  });
})();
