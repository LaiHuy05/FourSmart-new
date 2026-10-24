'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../view/assets/js/client-password-recovery.js'), 'utf8');

function page(hash = '', context = '0') {
  const elements = {};
  for (const id of ['recovery-reset-form', 'recovery-token', 'recovery-new-password',
    'recovery-confirm-password', 'recovery-submit', 'recovery-link-status']) {
    elements[id] = { value: '', disabled: false, textContent: '', validity: '', listeners: {},
      addEventListener(type, listener) { this.listeners[type] = listener; },
      setCustomValidity(message) { this.validity = message; } };
  }
  elements['recovery-reset-form'].dataset = { hasContext: context };
  elements['recovery-reset-form'].reportValidity = () =>
    !elements['recovery-new-password'].validity && !elements['recovery-confirm-password'].validity;
  const history = [];
  const window = { location: { hash, pathname: '/index.php', search: '?client=resetPassword' },
    history: { replaceState(_state, _title, url) { history.push(url); } },
    listeners: {}, addEventListener(type, listener) { this.listeners[type] = listener; } };
  vm.runInNewContext(source, { document: { getElementById: (id) => elements[id] }, window, URLSearchParams, TextEncoder });
  return { elements, window, history };
}

let passed = 0;
function test(name, run) { run(); passed++; console.log('PASS ' + name); }
test('fragment token is accepted and removed from browser history', () => {
  const { elements, history } = page('#token=' + 'a'.repeat(64));
  assert.equal(elements['recovery-token'].value, 'a'.repeat(64));
  assert.equal(elements['recovery-submit'].disabled, false);
  assert.deepEqual(history, ['/index.php?client=resetPassword']);
});
test('malformed fragment cannot enable submission or inject HTML', () => {
  const { elements } = page('#token=%3Cscript%3E');
  assert.equal(elements['recovery-token'].value, '');
  assert.equal(elements['recovery-submit'].disabled, true);
});
test('hashed server context allows retry without exposing the raw token', () => {
  const { elements } = page('', '1');
  assert.equal(elements['recovery-token'].value, '');
  assert.equal(elements['recovery-submit'].disabled, false);
});
test('UTF-8 byte limit and confirmation mismatch block submission', () => {
  const { elements } = page('', '1');
  elements['recovery-new-password'].value = 'Aa1!' + 'ắ'.repeat(30);
  elements['recovery-confirm-password'].value = 'Different-Password!2026';
  let prevented = false;
  elements['recovery-reset-form'].listeners.submit({ preventDefault() { prevented = true; } });
  assert.equal(prevented, true);
  assert.notEqual(elements['recovery-new-password'].validity, '');
  assert.notEqual(elements['recovery-confirm-password'].validity, '');
});
test('valid submit disables duplicate clicks and pageshow restores the button', () => {
  const { elements, window } = page('', '1');
  elements['recovery-new-password'].value = elements['recovery-confirm-password'].value = 'Valid-Password!2026';
  elements['recovery-reset-form'].listeners.submit({ preventDefault() { assert.fail('Valid submit blocked'); } });
  assert.equal(elements['recovery-submit'].disabled, true);
  window.listeners.pageshow({ persisted: true });
  assert.equal(elements['recovery-submit'].disabled, false);
  assert.equal(elements['recovery-new-password'].value, '');
});
console.log(passed + ' passed, 0 failed (DOM simulation; not a real browser)');
