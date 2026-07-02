<?php use App\Core\Csrf; ?>
<main class="min-h-screen px-4 py-10">
    <div class="mx-auto max-w-5xl">
        <header class="mb-8 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand text-2xl font-black text-ink">T</span>
            <h1 class="mt-5 text-3xl font-bold text-white">Install TMPBase</h1>
            <p class="mx-auto mt-2 max-w-xl text-sm text-slate-500">Verify the Hostinger server, configure the application and create the first administrator.</p>
        </header>

        <div class="grid gap-6 lg:grid-cols-[.9fr_1.1fr]">
            <section class="card h-fit p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-white">Server checks</h2>
                        <p class="mt-1 text-sm text-slate-500">Required before installation.</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $ready ? 'bg-emerald-500/10 text-emerald-300' : 'bg-rose-500/10 text-rose-300' ?>">
                        <?= $ready ? 'Ready' : 'Action required' ?>
                    </span>
                </div>
                <div class="mt-5 divide-y divide-line">
                    <?php foreach ($checks as $check): ?>
                    <div class="flex gap-3 py-3">
                        <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-xs <?= $check['passed'] ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' ?>">
                            <?= $check['passed'] ? 'OK' : '!' ?>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm text-slate-200"><?= e($check['label']) ?><?= !$check['required'] ? ' (recommended)' : '' ?></p>
                            <p class="mt-1 break-all text-xs text-slate-600"><?= e($check['value']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$ready): ?>
                <div class="mt-5 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm text-amber-200">
                    Enable the missing PHP extensions or correct directory permissions in hPanel, then reload this page.
                </div>
                <?php endif; ?>
            </section>

            <form method="post" action="/install" class="card p-6">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <h2 class="font-semibold text-white">Application settings</h2>
                <p class="mt-1 text-sm text-slate-500">The installer will create `.env`, initialize SQLite and lock itself.</p>

                <div class="mt-6 grid gap-5 md:grid-cols-2">
                    <label><span class="label">Application name</span><input class="input" name="app_name" value="TMPBase" required maxlength="80"></label>
                    <label><span class="label">Application URL</span><input class="input" type="url" name="app_url" value="<?= e($suggestedUrl) ?>" required></label>
                </div>

                <div class="my-6 border-t border-line"></div>
                <h2 class="font-semibold text-white">Administrator</h2>
                <div class="mt-5 space-y-5">
                    <label><span class="label">Full name</span><input class="input" name="name" required maxlength="100" autocomplete="name"></label>
                    <label><span class="label">Email</span><input class="input" type="email" name="email" required autocomplete="email"></label>
                    <div class="grid gap-5 md:grid-cols-2">
                        <label><span class="label">Password</span><input class="input" type="password" name="password" required minlength="10" autocomplete="new-password"></label>
                        <label><span class="label">Confirm password</span><input class="input" type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
                    </div>
                </div>

                <label class="mt-5 flex items-start gap-3 text-sm text-slate-400">
                    <input class="mt-1" type="checkbox" required>
                    <span>I understand that TMPBase stores project databases and uploaded files on this server.</span>
                </label>

                <button class="btn-primary mt-6 w-full disabled:cursor-not-allowed disabled:opacity-40" <?= $ready ? '' : 'disabled' ?>>
                    Install TMPBase
                </button>
            </form>
        </div>
        <p class="mt-6 text-center text-xs text-slate-600">After installation, `/install` is disabled automatically.</p>
    </div>
</main>
