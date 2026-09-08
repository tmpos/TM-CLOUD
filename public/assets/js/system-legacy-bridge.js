(() => {
    'use strict';

    const context = window.__TMPBASE_SYSTEM_CONTEXT__ || {};
    const slug = String(context.slug || '');
    const projectUid = String(context.project_uid || '');
    const systemPath = '/sistema/' + encodeURIComponent(slug);
    const runtimeUrl = '/api/system/' + encodeURIComponent(slug) + '/runtime';

    if (!slug || !projectUid || !context.csrf) {
        console.error('No se pudo inicializar el puente web del sistema.');
        return;
    }

    // Las compilaciones antiguas guardan usuarioLocal sin separar proyectos.
    // Se aisla el almacenamiento antes de que Vue y su router sean cargados.
    const browserStorage = window.localStorage;
    const prefix = 'tmpos-project:' + projectUid + ':';
    const scopedStorage = {
        get length() {
            let count = 0;
            for (let index = 0; index < browserStorage.length; index++) {
                if ((browserStorage.key(index) || '').startsWith(prefix)) count++;
            }
            return count;
        },
        key(position) {
            const keys = [];
            for (let index = 0; index < browserStorage.length; index++) {
                const key = browserStorage.key(index) || '';
                if (key.startsWith(prefix)) keys.push(key.slice(prefix.length));
            }
            return keys[position] ?? null;
        },
        getItem(key) { return browserStorage.getItem(prefix + key); },
        setItem(key, value) { browserStorage.setItem(prefix + key, String(value)); },
        removeItem(key) { browserStorage.removeItem(prefix + key); },
        clear() {
            const keys = [];
            for (let index = 0; index < browserStorage.length; index++) {
                const key = browserStorage.key(index) || '';
                if (key.startsWith(prefix)) keys.push(key);
            }
            keys.forEach(key => browserStorage.removeItem(key));
        },
    };
    Object.defineProperty(window, 'localStorage', { configurable: true, value: scopedStorage });

    const user = context.user && typeof context.user === 'object' ? context.user : {};
    if (!localStorage.getItem('usuarioLocal')) localStorage.setItem('usuarioLocal', JSON.stringify([user]));
    localStorage.setItem('tmpos_web_project_name', String(context.project_name || ''));
    sessionStorage.setItem('tmpos_web_project', projectUid);
    sessionStorage.setItem('mr_session_authenticated', '1');
    window.__systemProjectApiBase = location.origin + '/api/' + encodeURIComponent(projectUid);

    async function request(channel, args) {
        const response = await fetch(runtimeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': String(context.csrf),
            },
            body: JSON.stringify({ action: 'invoke', data: { channel, args } }),
        });
        const result = await response.json().catch(() => ({}));
        if (response.status === 401) {
            location.assign(systemPath + '/login');
            throw new Error('La sesion expiro.');
        }
        if (!response.ok) throw new Error(result.error || result.message || ('HTTP ' + response.status));
        return result;
    }

    const invoke = (channel, ...args) => {
        channel = String(channel || '');
        if (channel === 'rutas-permitidas' || channel === 'getPrinters') return Promise.resolve([]);
        if (channel === 'revisarActualizacionDisponible') return Promise.resolve({ available: false, version: 'Web' });
        if (channel === 'nombrePC') return Promise.resolve({ nombre: 'Navegador web', version: 'Web' });
        if (channel === 'actualizarjson') {
            localStorage.setItem('legacy_system_config', JSON.stringify(args[0] || {}));
            return Promise.resolve({ success: true });
        }
        if (channel === 'tm-cloud-config:get') {
            try { return Promise.resolve(JSON.parse(localStorage.getItem('tm-cloud-config') || 'null')); }
            catch (_) { return Promise.resolve(null); }
        }
        if (channel === 'tm-cloud-config:save') {
            localStorage.setItem('tm-cloud-config', JSON.stringify(args[0] || {}));
            return Promise.resolve({ success: true });
        }
        if (['devtools', 'descargar-e-instalar', 'play-sound', 'abrirpuerta'].includes(channel)) {
            return Promise.resolve({ success: false, web: true });
        }
        return request(channel, args);
    };
    const ipcRenderer = {
        invoke,
        send() {},
        on() { return () => {}; },
        once() { return () => {}; },
        removeListener() {},
        removeAllListeners() {},
    };
    window.electron = {
        invoke,
        ipcRenderer,
        send: ipcRenderer.send,
        on: ipcRenderer.on,
    };
    window.__isElectron = false;
    window.__isCapacitor = false;
    window.__isNetworkClient = true;
    window.__isServerSystem = true;
})();
