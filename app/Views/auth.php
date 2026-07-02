<?php use App\Core\Csrf; ?>
<main class="grid min-h-screen place-items-center px-4">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand text-2xl font-black text-ink">T</span>
            <h1 class="mt-5 text-2xl font-bold text-white">TMPBase</h1>
            <p class="mt-2 text-sm text-slate-500">Sign in to your private backend workspace.</p>
        </div>
        <form method="post" action="/login" class="card space-y-5 p-6 shadow-2xl shadow-black/30">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <label><span class="label">Email</span><input class="input" type="email" name="email" required autocomplete="email"></label>
            <label><span class="label">Password</span><input class="input" type="password" name="password" required autocomplete="current-password"></label>
            <button class="btn-primary w-full">Sign in</button>
        </form>
        <p class="mt-5 text-center text-xs text-slate-600">PHP + SQLite | Data stays on your server</p>
    </div>
</main>
