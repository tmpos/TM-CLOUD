<?php use App\Core\Csrf; ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold text-white">Subir archivo APK</h2>
        <p class="mt-1 text-sm text-slate-500">Arrastre el APK al recuadro o selecciónelo desde su equipo.</p>
    </div>
    <a href="/apk-files" class="btn-secondary">Ver archivos</a>
</div>

<form id="apk-upload-form" class="card mx-auto max-w-3xl p-6" method="post" enctype="multipart/form-data" action="/apk-files/upload">
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <label id="apk-drop-zone" class="group flex min-h-72 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-line bg-black/10 px-6 text-center transition hover:border-brand/70 hover:bg-brand/5">
        <span class="grid h-16 w-16 place-items-center rounded-2xl bg-brand/10 text-3xl text-brand">&#8593;</span>
        <strong id="apk-file-name" class="mt-5 text-lg text-white">Suelte aquí su archivo APK</strong>
        <span class="mt-2 text-sm text-slate-500">o haga clic para buscarlo</span>
        <span class="mt-4 rounded-full border border-line px-3 py-1 text-xs text-slate-400">Máximo <?= e((string) $maxUploadMb) ?> MB</span>
        <input id="apk-file-input" class="sr-only" type="file" name="file" accept=".apk,application/vnd.android.package-archive" required>
    </label>
    <div class="mt-5 flex items-center justify-between gap-4">
        <p class="text-xs text-slate-500">Se guarda directamente en <code>storage/apk-files</code>.</p>
        <button id="apk-submit" class="btn-primary" type="submit" disabled>Subir APK</button>
    </div>
    <div id="apk-progress-wrap" class="mt-5 hidden" aria-live="polite">
        <div class="mb-2 flex items-center justify-between gap-3 text-sm">
            <span id="apk-progress-status" class="text-slate-300">Preparando subida...</span>
            <strong id="apk-progress-percent" class="text-brand">0%</strong>
        </div>
        <div class="h-3 overflow-hidden rounded-full border border-line bg-black/30">
            <div id="apk-progress-bar" class="h-full w-0 rounded-full bg-brand transition-[width] duration-150" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
        </div>
        <p id="apk-progress-size" class="mt-2 text-right text-xs text-slate-500">0 MB de 0 MB</p>
    </div>
</form>

<div class="card mx-auto mt-6 max-w-3xl p-6">
    <h3 class="font-semibold text-white">Descarga desde otros sistemas</h3>
    <p class="mt-2 text-sm text-slate-500">La subida de esta página está habilitada con su sesión administrativa. Otros sistemas pueden descargar siempre el APK más reciente desde:</p>
    <div class="mt-4 flex gap-2">
        <input class="input min-w-0 font-mono text-xs" readonly value="<?= e($baseUrl) ?>/downloads/apk/latest">
        <button class="btn-secondary" type="button" data-copy="<?= e($baseUrl) ?>/downloads/apk/latest">Copiar</button>
    </div>
</div>

<script>
(() => {
    const zone = document.getElementById('apk-drop-zone');
    const input = document.getElementById('apk-file-input');
    const name = document.getElementById('apk-file-name');
    const submit = document.getElementById('apk-submit');
    const form = document.getElementById('apk-upload-form');
    const progressWrap = document.getElementById('apk-progress-wrap');
    const progressBar = document.getElementById('apk-progress-bar');
    const progressPercent = document.getElementById('apk-progress-percent');
    const progressStatus = document.getElementById('apk-progress-status');
    const progressSize = document.getElementById('apk-progress-size');
    const megabytes = bytes => (bytes / 1048576).toFixed(1) + ' MB';
    const updateProgress = (percent, loaded, total) => {
        const value = Math.max(0, Math.min(100, Math.round(percent)));
        progressBar.style.width = value + '%';
        progressBar.setAttribute('aria-valuenow', String(value));
        progressPercent.textContent = value + '%';
        progressSize.textContent = megabytes(loaded) + ' de ' + megabytes(total);
    };
    const select = file => {
        if (!file) return;
        if (!file.name.toLowerCase().endsWith('.apk')) {
            alert('Solo se permiten archivos APK.');
            input.value = '';
            submit.disabled = true;
            return;
        }
        name.textContent = file.name + ' · ' + (file.size / 1048576).toFixed(1) + ' MB';
        submit.disabled = false;
        zone.classList.add('border-brand', 'bg-brand/5');
    };
    input.addEventListener('change', () => select(input.files[0]));
    ['dragenter', 'dragover'].forEach(eventName => zone.addEventListener(eventName, event => {
        event.preventDefault();
        zone.classList.add('border-brand', 'bg-brand/10');
    }));
    ['dragleave', 'drop'].forEach(eventName => zone.addEventListener(eventName, event => {
        event.preventDefault();
        zone.classList.remove('bg-brand/10');
    }));
    zone.addEventListener('drop', event => {
        const file = event.dataTransfer.files[0];
        if (!file) return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        select(file);
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        const file = input.files[0];
        if (!file) return;

        submit.disabled = true;
        input.disabled = false;
        progressWrap.classList.remove('hidden');
        progressStatus.textContent = 'Subiendo ' + file.name;
        updateProgress(0, 0, file.size);

        const request = new XMLHttpRequest();
        request.open('POST', form.action, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.upload.addEventListener('progress', progress => {
            if (!progress.lengthComputable) return;
            updateProgress((progress.loaded / progress.total) * 100, progress.loaded, progress.total);
        });
        request.upload.addEventListener('load', () => {
            updateProgress(100, file.size, file.size);
            progressStatus.textContent = 'Carga completada. Verificando y guardando el APK...';
        });
        request.addEventListener('load', () => {
            progressStatus.textContent = 'Proceso terminado. Abriendo el listado...';
            window.location.href = request.responseURL || '/apk-files';
        });
        request.addEventListener('error', () => {
            progressStatus.textContent = 'No se pudo completar la subida. Revise su conexión e intente nuevamente.';
            progressStatus.classList.add('text-rose-400');
            submit.disabled = false;
        });
        request.addEventListener('abort', () => {
            progressStatus.textContent = 'La subida fue cancelada.';
            submit.disabled = false;
        });
        request.send(new FormData(form));
    });
})();
</script>
