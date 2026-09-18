<?php

use App\Core\Auth;
use App\Core\Csrf;

$authenticated = Auth::check();
$flashes = $flashes ?? [];
?>
<!doctype html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="ws-url" content="<?= e(getenv('REALTIME_WS_URL') ?: 'ws://127.0.0.1:8080') ?>">
    <title><?= e($title ?? 'TMPBase') ?> | TMPBase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {darkMode:'class',theme:{extend:{colors:{ink:'#090d12',panel:'#111820',line:'#22303d',brand:'#2dd4bf'}}}}
    </script>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-ink text-slate-200 antialiased">
<div style="position:fixed;top:0;left:0;right:0;z-index:99999;background:#ff2d55;color:#fff;text-align:center;padding:6px;font:700 13px system-ui;letter-spacing:.03em">MARCADOR DE VERSION &mdash; <?= e(gmdate('Y-m-d H:i:s')) ?> UTC &mdash; build 7f395d9-debug</div>
<div style="height:28px"></div>
<?php if ($authenticated): ?>
<div class="min-h-screen lg:flex">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-line bg-[#0c1218] lg:block">
        <div class="flex h-16 items-center gap-3 border-b border-line px-6">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand font-black text-ink">T</span>
            <div><strong class="block text-white">TMPBase</strong><span class="text-xs text-slate-500">Private backend platform</span></div>
        </div>
        <nav class="space-y-1 p-4 text-sm">
            <?php if (Auth::hasComponent('projects')): ?>
            <a href="/dashboard" class="nav-link">Dashboard</a>
            <a href="/dashboard#projects" class="nav-link">Projects</a>
            <a href="/dashboard#activity" class="nav-link">Activity</a>
            <a href="/sistema" class="nav-link">Sistema TMPOS</a>
            <?php endif; ?>
            <?php if (Auth::hasComponent('sistemas_web')): ?><a href="/system-apps" class="nav-link">Sistemas web</a><?php endif; ?>
            <div class="px-3 pb-2 pt-6 text-xs font-semibold uppercase tracking-widest text-slate-600">Workspace</div>
            <?php if (Auth::hasComponent('api_docs')): ?><a href="/api-docs" class="nav-link">API Docs</a><?php endif; ?>
            <?php if (Auth::hasComponent('backups')): ?><a href="/backups" class="nav-link">Backups</a><?php endif; ?>
            <?php if (Auth::hasComponent('storage')): ?><a href="/storage" class="nav-link">Storage</a><?php endif; ?>
            <?php if (Auth::hasComponent('space_usage')): ?><a href="/space-usage" class="nav-link">Uso de espacio</a><?php endif; ?>
            <?php if (Auth::hasComponent('apk_files')): ?><a href="/apk-files" class="nav-link">Archivos APK</a><?php endif; ?>
            <?php if (Auth::hasComponent('mail_settings')): ?><a href="/mail-settings" class="nav-link">Correo OTP</a><?php endif; ?>
            <?php if (Auth::hasComponent('licenses')): ?><a href="/licenses" class="nav-link">Licenses</a><?php endif; ?>
            <?php if (Auth::hasComponent('onboarding_links')): ?><a href="/onboarding-links" class="nav-link">Enlaces de registro</a><?php endif; ?>
            <?php if (Auth::hasComponent('projects')): ?><a href="/projects/trash" class="nav-link">Papelera</a><?php endif; ?>
            <?php if (Auth::isAdmin()): ?>
            <div class="px-3 pb-2 pt-6 text-xs font-semibold uppercase tracking-widest text-slate-600">Administracion</div>
            <a href="/users" class="nav-link">Usuarios</a>
            <?php endif; ?>
        </nav>
        <div class="absolute inset-x-4 bottom-4 rounded-xl border border-line bg-panel p-3 text-xs text-slate-400">
            Signed in as <strong class="mt-1 block truncate text-slate-200"><?= e(Auth::user()['email'] ?? '') ?></strong>
            <form method="post" action="/logout" class="mt-3"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="text-rose-400 hover:text-rose-300">Sign out</button></form>
        </div>
    </aside>
    <main class="min-w-0 flex-1 lg:ml-64">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-line bg-ink/90 px-4 backdrop-blur md:px-8">
            <button class="rounded-lg border border-line p-2 lg:hidden" data-sidebar-toggle>Menu</button>
            <div><h1 class="font-semibold text-white"><?= e($title ?? 'TMPBase') ?></h1></div>
            <?php if (Auth::hasComponent('projects')): ?><a href="/dashboard#new-project" class="btn-primary">New project</a><?php endif; ?>
        </header>
        <div class="mx-auto max-w-[1600px] p-4 md:p-8">
<?php endif; ?>
            <?php foreach ($flashes as $flash): ?>
                <div data-toast class="mb-4 rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'error' ? 'border-rose-500/30 bg-rose-500/10 text-rose-300' : (($flash['type'] ?? '') === 'warning' ? 'border-amber-500/30 bg-amber-500/10 text-amber-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300') ?>">
                    <?= e($flash['message'] ?? '') ?>
                </div>
            <?php endforeach; ?>
            <?= $content ?>
<?php if ($authenticated): ?>
        </div>
    </main>
</div>
<?php endif; ?>
<script src="/assets/js/app.js"></script>
</body>
</html>
