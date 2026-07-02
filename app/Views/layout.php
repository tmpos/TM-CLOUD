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
    <title><?= e($title ?? 'TMPBase') ?> | TMPBase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {darkMode:'class',theme:{extend:{colors:{ink:'#090d12',panel:'#111820',line:'#22303d',brand:'#2dd4bf'}}}}
    </script>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-ink text-slate-200 antialiased">
<?php if ($authenticated): ?>
<div class="min-h-screen lg:flex">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-line bg-[#0c1218] lg:block">
        <div class="flex h-16 items-center gap-3 border-b border-line px-6">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand font-black text-ink">T</span>
            <div><strong class="block text-white">TMPBase</strong><span class="text-xs text-slate-500">Private backend platform</span></div>
        </div>
        <nav class="space-y-1 p-4 text-sm">
            <a href="/dashboard" class="nav-link">Dashboard</a>
            <a href="/dashboard#projects" class="nav-link">Projects</a>
            <a href="/dashboard#activity" class="nav-link">Activity</a>
            <div class="px-3 pb-2 pt-6 text-xs font-semibold uppercase tracking-widest text-slate-600">Workspace</div>
            <a href="/api-docs" class="nav-link">API Docs</a>
            <a href="/backups" class="nav-link">Backups</a>
            <a href="/storage" class="nav-link">Storage</a>
            <a href="/licenses" class="nav-link">Licenses</a>
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
            <a href="/dashboard#new-project" class="btn-primary">New project</a>
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
