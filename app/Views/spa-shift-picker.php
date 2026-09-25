<?php // Included inside the booking form, after its date input. ?>
<div class="spa-shift-picker">
  <label for="spa-turno">Turno disponible <span aria-hidden="true">*</span></label>
  <select id="spa-turno" name="turno" required disabled aria-describedby="spa-disponibilidad">
    <option value="">Selecciona una fecha primero</option>
  </select>
  <p id="spa-disponibilidad" role="status" style="font-size:13px;line-height:1.5;margin:8px 0;color:#63556e">Los cupos se comparten entre todas las reservas del spa.</p>
  <button id="spa-refresh" type="button" hidden style="margin:8px 0;padding:8px;font-size:14px">Reintentar disponibilidad</button>
  <noscript>Activa JavaScript para consultar y seleccionar un turno disponible.</noscript>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const select = document.getElementById('spa-turno');
  const form = select.form;
  const date = form.elements.fecha;
  const status = document.getElementById('spa-disponibilidad');
  const retry = document.getElementById('spa-refresh');
  const submit = form.querySelector('button[type="submit"]');
  let sequence = 0;
  let initial = <?= json_encode((string) ($_POST['turno'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  let submitting = false;
  const syncButton = () => { submit.disabled = select.disabled || !select.value || submitting; submit.style.opacity = submit.disabled ? '.5' : '1'; };
  async function load() {
    const current = ++sequence;
    const selected = select.value || initial;
    initial = '';
    select.disabled = true;
    select.replaceChildren(new Option(date.value ? 'Consultando turnos…' : 'Selecciona una fecha primero', ''));
    retry.hidden = true;
    syncButton();
    if (!date.value) { status.textContent = 'Selecciona el día de tu visita para ver los cupos.'; return; }
    status.textContent = 'Consultando disponibilidad…';
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 12000);
    try {
      const url = new URL(window.location.href);
      url.search = '';
      url.hash = '';
      url.searchParams.set('availability', date.value);
      const response = await fetch(url, { cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal });
      if (!response.ok) throw new Error();
      const { data } = await response.json();
      if (current !== sequence) return;
      if (!data || !Array.isArray(data.shifts) || !data.enabled) throw new Error();
      date.min = data.today;
      select.replaceChildren(new Option('Selecciona tu turno', ''));
      for (const shift of data.shifts) {
        const detail = shift.available ? shift.remaining + (shift.remaining === 1 ? ' cupo disponible' : ' cupos disponibles') : shift.reason;
        const option = new Option(shift.start + '–' + shift.end + ' · ' + detail, shift.id);
        option.disabled = !shift.available;
        select.add(option);
        if (shift.available && selected === shift.id) select.value = shift.id;
      }
      select.disabled = !data.shifts.some(shift => shift.available);
      status.textContent = select.disabled ? 'No hay turnos disponibles en esta fecha. Selecciona otro día.' : 'Elige el horario de tu visita. Tu solicitud ocupa un cupo mientras se confirma. Zona horaria: ' + data.timezone + '.';
    } catch {
      if (current !== sequence) return;
      select.replaceChildren(new Option('Disponibilidad no disponible', ''));
      status.textContent = 'No pudimos consultar los cupos. Reintenta antes de reservar.';
      retry.hidden = false;
    } finally { clearTimeout(timeout); if (current === sequence) syncButton(); }
  }
  date.addEventListener('change', load);
  select.addEventListener('change', syncButton);
  retry.addEventListener('click', load);
  form.addEventListener('submit', event => {
    if (select.disabled || !select.value || submitting) { event.preventDefault(); return; }
    submitting = true;
    syncButton();
  });
  window.addEventListener('pageshow', event => { if (event.persisted) { submitting = false; load(); } });
  load();
});
</script>
