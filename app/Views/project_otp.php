<?php
/** @var array $project */
/** @var array{mode:string, fixedCode:string, intervalSeconds:int, code:string, secondsRemaining:int}|null $otp */
$otp ??= ['mode' => 'variable', 'fixedCode' => '', 'intervalSeconds' => 60, 'code' => '----', 'secondsRemaining' => 60];
$isFixed = ($otp['mode'] ?? '') === 'fixed';
$interval = max(30, (int) ($otp['intervalSeconds'] ?? 60));
?>
<section class="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
    <div class="card p-6">
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-white">OTP del proyecto</h3>
            <p class="mt-1 text-sm text-slate-500">Mismo codigo que "OTP Local" en TMPOS. Autoriza eliminaciones y es el codigo de acceso de Soporte a esta empresa.</p>
        </div>
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-line bg-black/20 p-8">
            <div id="project-otp-code" class="font-mono text-6xl font-bold tracking-[0.3em] text-teal-300" aria-live="polite"><?= e($otp['code'] ?? '----') ?></div>
            <?php if ($isFixed): ?>
                <p class="text-sm text-amber-300">Modo fijo: el codigo no cambia hasta que se configure otro en TMPOS.</p>
            <?php else: ?>
                <div class="h-2 w-full max-w-xs overflow-hidden rounded-full bg-slate-800" aria-hidden="true">
                    <div id="project-otp-bar" class="h-full bg-teal-400 transition-all duration-1000" style="width: <?= (int) round(((int) $otp['secondsRemaining']) / $interval * 100) ?>%"></div>
                </div>
                <p class="text-sm text-slate-400">Cambia en <strong id="project-otp-seconds" class="text-slate-200"><?= (int) $otp['secondsRemaining'] ?></strong> s · cada <?= $interval ?> s · el codigo anterior sigue valido un intervalo mas</p>
            <?php endif; ?>
            <button type="button" id="project-otp-copy" class="btn-primary">Copiar codigo</button>
        </div>
    </div>

    <aside class="card p-6">
        <h3 class="font-semibold text-white">Entrar como Soporte</h3>
        <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-400">
            <li><strong class="text-slate-200">Por PIN:</strong> escribe este codigo en el campo PIN del login de TMPOS (escritorio o <a class="text-brand" href="/sistema/<?= e(rawurlencode((string) $project['slug'])) ?>/login" target="_blank" rel="noopener">web</a>).</li>
            <li><strong class="text-slate-200">Por usuario:</strong> usuario <code>soporte</code> y este codigo como contraseña.</li>
            <li><strong class="text-slate-200">Aplicacion bloqueada</strong> por 3 PIN incorrectos: "Desbloquear con codigo de soporte".</li>
        </ol>
        <p class="mt-4 text-xs text-slate-500">Modo, intervalo y regeneracion del secreto se configuran en TMPOS &gt; Configuracion &gt; OTP Local.</p>
        <div class="mt-6 rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-xs leading-5 text-rose-200">
            Da acceso de Soporte a esta empresa. No lo compartas; cada consulta de esta pestaña queda en Logs.
        </div>
    </aside>
</section>

<script>
(() => {
    const codeEl = document.getElementById('project-otp-code')
    const secondsEl = document.getElementById('project-otp-seconds')
    const barEl = document.getElementById('project-otp-bar')
    const interval = <?= $interval ?>
    let remaining = <?= (int) ($otp['secondsRemaining'] ?? 0) ?>

    async function refresh() {
        try {
            const res = await fetch('/projects/<?= e(rawurlencode((string) $project['uid'])) ?>/otp', { credentials: 'same-origin', cache: 'no-store' })
            if (!res.ok) return
            const { data } = await res.json()
            if (data?.code) codeEl.textContent = data.code
            remaining = Number(data?.secondsRemaining || interval)
        } catch (_) { /* keep the last code; the next tick retries */ }
    }

    if (secondsEl && barEl) {
        setInterval(() => {
            remaining -= 1
            if (remaining <= 0) { remaining = interval; refresh() }
            secondsEl.textContent = String(remaining)
            barEl.style.width = `${Math.round(remaining / interval * 100)}%`
        }, 1000)
    }

    const copyBtn = document.getElementById('project-otp-copy')
    copyBtn?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(codeEl.textContent.trim())
            copyBtn.textContent = 'Copiado'
            setTimeout(() => { copyBtn.textContent = 'Copiar codigo' }, 1500)
        } catch (_) { /* clipboard blocked; the code is visible anyway */ }
    })
})()
</script>
