document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
    document.querySelector('#sidebar')?.classList.toggle('hidden');
});
document.querySelectorAll('[data-dialog-open]').forEach(button => {
    button.addEventListener('click', () => document.querySelector(button.dataset.dialogOpen)?.showModal());
});
document.querySelectorAll('[data-dialog-close]').forEach(button => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});
document.querySelectorAll('[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (!confirm(form.dataset.confirm)) event.preventDefault();
    });
});
document.querySelectorAll('[data-copy]').forEach(button => {
    button.addEventListener('click', async () => {
        await navigator.clipboard.writeText(button.dataset.copy);
        const old = button.textContent;
        button.textContent = 'Copied';
        setTimeout(() => button.textContent = old, 1200);
    });
});
document.querySelectorAll('[data-secret-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const target = document.querySelector(button.dataset.secretToggle);
        target.type = target.type === 'password' ? 'text' : 'password';
        button.textContent = target.type === 'password' ? 'Show' : 'Hide';
    });
});
setTimeout(() => document.querySelectorAll('[data-toast]').forEach(el => el.remove()), 6000);

const selectAllHeader = document.getElementById('select-all-header');
const selectAll = document.getElementById('select-all');
const rowCheckboxes = document.querySelectorAll('.row-checkbox');
const deleteSelected = document.getElementById('delete-selected');
const bulkForm = document.getElementById('bulk-form');

function updateBulkState() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    deleteSelected.disabled = checked.length === 0;
    if (selectAllHeader) selectAllHeader.checked = rowCheckboxes.length > 0 && checked.length === rowCheckboxes.length;
    if (selectAll) selectAll.checked = rowCheckboxes.length > 0 && checked.length === rowCheckboxes.length;
}

function toggleAll(checked) {
    rowCheckboxes.forEach(cb => cb.checked = checked);
    updateBulkState();
}

if (selectAllHeader) {
    selectAllHeader.addEventListener('change', () => toggleAll(selectAllHeader.checked));
}
if (selectAll) {
    selectAll.addEventListener('change', () => toggleAll(selectAll.checked));
}
rowCheckboxes.forEach(cb => cb.addEventListener('change', updateBulkState));

if (deleteSelected && bulkForm) {
    deleteSelected.addEventListener('click', (e) => {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        if (checked.length === 0) {
            e.preventDefault();
            return;
        }
        if (!confirm('Delete ' + checked.length + ' selected record' + (checked.length > 1 ? 's' : '') + ' permanently?')) {
            e.preventDefault();
            return;
        }
        bulkForm.submit();
    });
}

const autoSyncCheckbox = document.getElementById('auto-sync');
const syncUrl = autoSyncCheckbox ? autoSyncCheckbox.dataset.syncUrl : '';
const storageKey = autoSyncCheckbox ? 'tmpbase_autosync_' + autoSyncCheckbox.dataset.syncKey : '';
const wsProject = autoSyncCheckbox ? autoSyncCheckbox.dataset.wsProject : '';
const wsToken = autoSyncCheckbox ? autoSyncCheckbox.dataset.wsToken : '';
const wsMeta = document.querySelector('meta[name="ws-url"]');
const wsUrl = wsMeta ? wsMeta.getAttribute('content') : '';

let syncInterval = null;
let ws = null;
let wsReconnectTimer = null;

function getLastSync() {
    return localStorage.getItem(storageKey + '_last') || '';
}

function setLastSync(time) {
    localStorage.setItem(storageKey + '_last', time);
}

function doSync() {
    if (!syncUrl) return;
    const from = getLastSync();
    const url = from ? syncUrl + '?from=' + encodeURIComponent(from) : syncUrl + '?from=2000-01-01 00:00:00';
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(res => {
            if (res.server_time) setLastSync(res.server_time);
            const records = res.data || [];
            if (records.length > 0) {
                showSyncBadge(records.length);
                maybeReload();
            }
        })
        .catch(() => {});
}

function showSyncBadge(count) {
    const badge = document.getElementById('sync-badge');
    if (badge) badge.textContent = count + ' new';
}

function maybeReload() {
    const tbody = document.querySelector('.data-table tbody');
    if (tbody && !document.querySelector('[name="search"]')?.value) {
        location.reload();
    }
}

function connectWs() {
    if (!wsUrl || !wsProject || !wsToken) return;
    try {
        ws = new WebSocket(wsUrl);
        ws.onopen = () => {
            ws.send(JSON.stringify({ type: 'subscribe', project: wsProject, token: wsToken }));
        };
        ws.onmessage = (event) => {
            try {
                const msg = JSON.parse(event.data);
                if (msg.type === 'event') {
                    showSyncBadge('Live');
                    maybeReload();
                }
            } catch (e) {}
        };
        ws.onclose = () => {
            ws = null;
            if (autoSyncCheckbox?.checked) {
                wsReconnectTimer = setTimeout(connectWs, 5000);
            }
        };
        ws.onerror = () => {
            ws?.close();
        };
    } catch (e) {
        ws = null;
    }
}

function disconnectWs() {
    if (wsReconnectTimer) {
        clearTimeout(wsReconnectTimer);
        wsReconnectTimer = null;
    }
    if (ws) {
        ws.onclose = null;
        ws.close();
        ws = null;
    }
}

function startSync() {
    stopSync();
    connectWs();
    doSync();
    syncInterval = setInterval(doSync, 10000);
}

function stopSync() {
    if (syncInterval) { clearInterval(syncInterval); syncInterval = null; }
    disconnectWs();
}

if (autoSyncCheckbox) {
    const stored = localStorage.getItem(storageKey);
    if (stored === '1') {
        autoSyncCheckbox.checked = true;
        startSync();
    }
    autoSyncCheckbox.addEventListener('change', () => {
        localStorage.setItem(storageKey, autoSyncCheckbox.checked ? '1' : '0');
        if (autoSyncCheckbox.checked) {
            startSync();
        } else {
            stopSync();
        }
    });
}
