<?php $customerVerified = is_array($checkoutCustomer ?? null); ?>
<section class="panel" data-customer-panel>
  <h2>Tus datos</h2>
  <p data-customer-intro><?= $customerVerified ? 'Tu cuenta está lista. Revisa la entrega y el pago para finalizar.' : 'Regístrate aquí para completar tu compra o busca tu cuenta si ya eres cliente.' ?></p>
  <div class="customer-tabs" data-customer-tabs <?= $customerVerified ? 'hidden' : '' ?>>
    <button type="button" class="customer-tab" data-customer-mode="register" aria-pressed="true">Soy cliente nuevo</button>
    <button type="button" class="customer-tab" data-customer-mode="lookup" aria-pressed="false">Ya soy cliente</button>
  </div>
  <div data-customer-lookup hidden>
    <label class="field"><span>Cédula / RNC</span><input class="input" data-customer-document maxlength="50" autocomplete="off" placeholder="Escribe tu cédula o RNC"></label>
    <button type="button" class="customer-action" data-customer-action="lookup">Buscar mi cuenta</button>
    <p class="customer-help">Verificaremos tu acceso con un código enviado al correo registrado.</p>
  </div>
  <div data-customer-fields>
    <div class="grid">
      <label class="field"><span>Nombre completo *</span><input class="input" name="customer_name" maxlength="120" minlength="2" required autocomplete="name" value="<?= e($customerName) ?>" <?= $customerVerified ? 'readonly' : '' ?>></label>
      <label class="field"><span>Teléfono *</span><input class="input" type="tel" name="customer_phone" maxlength="40" required autocomplete="tel" value="<?= e($customerPhone) ?>"></label>
      <label class="field"><span>Correo electrónico *</span><input class="input" type="email" name="customer_email" required maxlength="160" autocomplete="email" value="<?= e($customerEmail) ?>" <?= $customerVerified ? 'readonly' : '' ?>></label>
      <label class="field"><span>Cédula / RNC *</span><input class="input" name="customer_document" maxlength="50" minlength="5" required value="<?= e($customerDocument) ?>" <?= $customerVerified ? 'readonly' : '' ?>></label>
    </div>
    <button type="button" class="customer-action" data-customer-action="register" <?= $customerVerified ? 'hidden' : '' ?>>Registrarme y verificar correo</button>
  </div>
  <div data-customer-verification hidden>
    <label class="field"><span>Código de verificación</span><input class="input" data-customer-otp inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="6 dígitos"></label>
    <div class="customer-actions"><button type="button" class="customer-action" data-customer-action="verify">Verificar y continuar</button><button type="button" class="customer-link" data-customer-action="resend">Reenviar código</button></div>
    <button type="button" class="customer-link" data-customer-back>Usar otros datos</button>
  </div>
  <p class="customer-message" data-customer-message role="status" aria-live="polite"></p>
</section>
