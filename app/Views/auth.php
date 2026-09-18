<?php use App\Core\Csrf; ?>
<div class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
    <div class="relative hidden overflow-hidden bg-[#070b10] lg:flex lg:flex-col lg:justify-between lg:p-14">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-24 -left-24 h-[26rem] w-[26rem] rounded-full bg-brand/25 blur-[100px]"></div>
            <div class="absolute bottom-0 right-0 h-[22rem] w-[22rem] rounded-full bg-indigo-500/20 blur-[100px]"></div>
            <div class="absolute inset-0 opacity-[.04]" style="background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:44px 44px"></div>
        </div>
        <div class="relative flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-brand to-teal-600 text-lg font-black text-ink shadow-lg shadow-brand/30">T</span>
            <strong class="text-lg font-bold text-white">TMPBase</strong>
        </div>
        <div class="relative max-w-md">
            <h1 class="text-4xl font-bold leading-tight text-white">Todo tu backend,<br>en un solo lugar.</h1>
            <p class="mt-4 text-base text-slate-400">Proyectos aislados, licencias, respaldos y control de acceso para todo el ecosistema TMPOS, desde un panel privado.</p>
            <ul class="mt-10 space-y-5">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></span>
                    <div><strong class="block text-sm font-semibold text-white">Proyectos aislados</strong><span class="text-sm text-slate-500">Una base SQLite y claves propias por cada cliente.</span></div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 19h16"/></svg></span>
                    <div><strong class="block text-sm font-semibold text-white">Respaldos automáticos</strong><span class="text-sm text-slate-500">Backups programados con verificación integrada.</span></div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
                    <div><strong class="block text-sm font-semibold text-white">Acceso por secciones</strong><span class="text-sm text-slate-500">Invita personas y dales permiso solo a lo que necesitan.</span></div>
                </li>
            </ul>
        </div>
        <p class="relative text-xs text-slate-600">&copy; <?= date('Y') ?> TMPBase · PHP + SQLite, los datos permanecen en tu servidor</p>
    </div>

    <div class="relative flex items-center justify-center overflow-hidden bg-ink p-6 sm:p-10">
        <div class="pointer-events-none absolute inset-0 lg:hidden" aria-hidden="true">
            <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-brand/20 blur-3xl"></div>
        </div>
        <div class="relative w-full max-w-sm animate-[fadeIn_.4s_ease-out]">
            <div class="mb-8 flex flex-col items-center gap-3 text-center lg:hidden">
                <span class="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-brand to-teal-600 text-xl font-black text-ink shadow-lg shadow-brand/30">T</span>
                <strong class="text-xl font-bold text-white">TMPBase</strong>
            </div>
            <h2 class="text-2xl font-bold text-white">Bienvenido de nuevo</h2>
            <p class="mt-2 text-sm text-slate-500">Inicia sesión para continuar en tu panel privado.</p>
            <form method="post" action="/login" class="mt-8 space-y-5">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <label class="block">
                    <span class="label">Correo</span>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                        <input class="input pl-9" type="email" name="email" required autocomplete="email" autofocus placeholder="tucorreo@empresa.com">
                    </div>
                </label>
                <label class="block">
                    <span class="label">Contraseña</span>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        <input id="auth-password" class="input pl-9 pr-10" type="password" name="password" required autocomplete="current-password" placeholder="••••••••••">
                        <button type="button" data-toggle-password="#auth-password" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300" aria-label="Mostrar contraseña">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </label>
                <button class="group btn-primary flex w-full items-center justify-center gap-2 py-3 text-base">
                    Iniciar sesión
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                </button>
            </form>
            <p class="mt-8 text-center text-xs text-slate-600 lg:hidden">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-line/70 px-3 py-1"><span class="h-1.5 w-1.5 rounded-full bg-brand"></span>PHP + SQLite · Los datos permanecen en tu servidor</span>
            </p>
        </div>
    </div>
</div>
<style>@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}</style>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.querySelector(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
    });
});
</script>
