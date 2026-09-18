<?php use App\Core\Csrf; ?>
<main class="relative grid min-h-screen place-items-center overflow-hidden px-4">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -top-40 -left-32 h-96 w-96 rounded-full bg-brand/20 blur-3xl"></div>
        <div class="absolute -bottom-40 -right-32 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="absolute inset-0 opacity-[.03]" style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:26px 26px"></div>
    </div>
    <div class="relative w-full max-w-md animate-[fadeIn_.4s_ease-out]">
        <div class="mb-8 text-center">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-gradient-to-br from-brand to-teal-600 text-2xl font-black text-ink shadow-lg shadow-brand/30">T</span>
            <h1 class="mt-5 text-2xl font-bold text-white">TMPBase</h1>
            <p class="mt-2 text-sm text-slate-500">Panel administrativo privado</p>
        </div>
        <form method="post" action="/login" class="space-y-5 rounded-2xl border border-line/70 bg-panel/80 p-7 shadow-2xl shadow-black/40 backdrop-blur">
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
            <button class="btn-primary w-full py-3 text-base">Iniciar sesión</button>
        </form>
        <p class="mt-6 text-center text-xs text-slate-600">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-line/70 px-3 py-1"><span class="h-1.5 w-1.5 rounded-full bg-brand"></span>PHP + SQLite · Los datos permanecen en tu servidor</span>
        </p>
    </div>
</main>
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
