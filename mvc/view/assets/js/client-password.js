(() => {
  const form = document.getElementById('profile-password-form');
  if (!form) return;

  const password = document.getElementById('profile-new-password');
  const confirmation = document.getElementById('profile-confirm-password');
  const submit = document.getElementById('profile-password-submit');

  const validateConfirmation = () => {
    confirmation.setCustomValidity(
      confirmation.value && confirmation.value !== password.value
        ? 'Xác nhận mật khẩu mới không trùng khớp.'
        : ''
    );
  };

  password.addEventListener('input', validateConfirmation);
  confirmation.addEventListener('input', validateConfirmation);

  form.addEventListener('submit', (event) => {
    validateConfirmation();
    if (!form.reportValidity()) {
      event.preventDefault();
      return;
    }
    submit.disabled = true;
    submit.textContent = 'Đang đổi mật khẩu…';
  });

  window.addEventListener('pageshow', (event) => {
    submit.disabled = false;
    submit.textContent = 'Đổi mật khẩu';
    if (event.persisted) {
      form.reset();
      confirmation.setCustomValidity('');
    }
  });
})();
