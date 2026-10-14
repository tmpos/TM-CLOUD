<script>
(() => {
  const form = document.querySelector('[data-checkout-form]');
  if (!form || form.hidden) return;
  const panel = document.querySelector('[data-customer-panel]');
  const message = panel.querySelector('[data-customer-message]');
  const field = name => form.elements.namedItem(name);
  const submit = form.querySelector('[data-submit]');
  const csrf = <?= json_encode(\App\Core\Csrf::token()) ?>;
  const endpoint = <?= json_encode('/api/storefront/' . rawurlencode($store['slug']) . '/checkout/customer') ?>;
  let mode = 'register', busy = false;
  function showMode(next) {
    mode = next;
    panel.querySelector('[data-customer-fields]').hidden = next !== 'register';
    panel.querySelector('[data-customer-lookup]').hidden = next !== 'lookup';
    panel.querySelector('[data-customer-verification]').hidden = next !== 'verify';
    panel.querySelector('[data-customer-tabs]').hidden = next === 'verify';
    panel.querySelectorAll('[data-customer-mode]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.customerMode === next)));
    // Hidden registration fields must not prevent an existing customer from entering a code.
    panel.querySelectorAll('[data-customer-fields] input').forEach(input => input.disabled = next !== 'register');
  }
  panel.querySelectorAll('[data-customer-mode]').forEach(button => button.addEventListener('click', () => {
    if (busy) return;
    message.textContent = '';
    if (button.dataset.customerMode === 'lookup') panel.querySelector('[data-customer-document]').value = field('customer_document').value;
    showMode(button.dataset.customerMode);
  }));
  panel.querySelector('[data-customer-back]').addEventListener('click', () => { if (!busy) { showMode('register'); message.textContent = ''; } });
  async function request(action) {
    if (busy) return;
    const data = { action, _csrf: csrf };
    if (action === 'register') {
      for (const input of panel.querySelectorAll('[data-customer-fields] input')) if (!input.reportValidity()) return;
      Object.assign(data, { nombre: field('customer_name').value, telefono: field('customer_phone').value, email: field('customer_email').value, cedula: field('customer_document').value });
    }
    if (action === 'lookup') {
      data.document = panel.querySelector('[data-customer-document]').value.trim();
      if (!data.document) { message.textContent = 'Escribe tu cédula o RNC.'; panel.querySelector('[data-customer-document]').focus(); return; }
    }
    if (action === 'verify') {
      data.otp = panel.querySelector('[data-customer-otp]').value.trim();
      if (!/^\d{6}$/.test(data.otp)) { message.textContent = 'Escribe el código de 6 dígitos.'; return; }
    }
    busy = true;
    panel.setAttribute('aria-busy', 'true');
    panel.querySelectorAll('button').forEach(button => button.disabled = true);
    message.classList.remove('is-error');
    message.textContent = 'Procesando…';
    try {
      const response = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(data) });
      const result = await response.json();
      if (!response.ok) {
        if (result.pending) showMode('verify');
        throw new Error(result.message || 'No fue posible verificar tu cuenta.');
      }
      if (result.data.verified) {
        showMode('register');
        const customer = result.data.customer;
        for (const key of ['name','phone','email','document']) field('customer_' + key).value = customer[key] || '';
        for (const key of ['name','email','document']) field('customer_' + key).readOnly = true;
        if (!field('delivery_address').value) field('delivery_address').value = customer.address || '';
        form.dataset.customerVerified = '1';
        panel.querySelector('[data-customer-tabs]').hidden = true;
        panel.querySelector('[data-customer-action="register"]').hidden = true;
        panel.querySelector('[data-customer-intro]').textContent = 'Tu cuenta está lista. Revisa la entrega y el pago para finalizar.';
        submit.disabled = submit.dataset.paymentsAvailable !== '1';
        message.textContent = 'Cuenta verificada. Ya puedes confirmar tu pedido.';
        field('delivery_method')?.[0]?.focus();
      } else {
        showMode('verify');
        message.textContent = result.data.message;
        panel.querySelector('[data-customer-otp]').focus();
      }
    } catch (error) {
      message.classList.add('is-error');
      message.textContent = error.message || 'Revisa tu conexión e inténtalo de nuevo.';
    } finally {
      busy = false;
      panel.removeAttribute('aria-busy');
      panel.querySelectorAll('button').forEach(button => button.disabled = false);
    }
  }
  panel.querySelectorAll('[data-customer-action]').forEach(button => button.addEventListener('click', () => request(button.dataset.customerAction)));
  panel.addEventListener('keydown', event => {
    if (event.key === 'Enter' && event.target.tagName === 'INPUT' && form.dataset.customerVerified !== '1') {
      event.preventDefault(); request(mode === 'verify' ? 'verify' : mode);
    }
  });
  if (form.dataset.customerVerified !== '1' && <?= json_encode((bool) ($checkoutPending ?? false)) ?>) {
    showMode('verify'); message.textContent = 'Tienes una verificación pendiente. Introduce el código recibido o solicita uno nuevo.';
  }
})();
</script>
