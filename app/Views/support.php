<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div><a href="/projects/<?= e($project['uid']) ?>" class="text-sm text-slate-500 hover:text-brand">&lt;- <?= e($project['name']) ?></a><h2 class="mt-2 text-2xl font-bold text-white">Soporte remoto</h2><p class="mt-1 text-sm text-slate-500">Conecta a una estacion para ver su pantalla y controlarla, con su autorizacion.</p></div>
    <span id="support-conn-badge" class="rounded-full bg-slate-500/15 px-2.5 py-1 text-xs font-semibold text-slate-400">Desconectado</span>
</div>

<?php if (!$wsUrl): ?>
<div class="card p-8 text-center text-sm text-slate-500">El servicio de tiempo real esta deshabilitado. Habilita REALTIME_ENABLED para usar soporte remoto.</div>
<?php else: ?>

<section class="grid gap-6 xl:grid-cols-[280px_1fr]">
    <div class="card overflow-hidden">
        <div class="border-b border-line p-4"><h3 class="text-sm font-semibold text-white">Estaciones en linea</h3></div>
        <div id="support-station-list" class="divide-y divide-line/70">
            <p id="support-station-empty" class="p-6 text-center text-xs text-slate-600">Ninguna estacion conectada.</p>
        </div>
    </div>

    <div class="card p-4">
        <div id="support-idle" class="flex h-[520px] items-center justify-center text-sm text-slate-600">Selecciona una estacion para iniciar una sesion.</div>
        <div id="support-waiting" class="hidden flex h-[520px] flex-col items-center justify-center gap-3 text-sm text-slate-400">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-brand border-t-transparent"></div>
            <p>Esperando autorizacion en <strong id="support-waiting-name" class="text-white"></strong>&hellip;</p>
            <button id="support-cancel" class="btn-secondary">Cancelar</button>
        </div>
        <div id="support-denied" class="hidden flex h-[520px] items-center justify-center text-sm text-rose-300">La estacion rechazo la solicitud de soporte.</div>
        <div id="support-active" class="hidden">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm text-slate-400">Conectado a <strong id="support-active-name" class="text-white"></strong></p>
                <button id="support-end" class="btn-danger">Finalizar sesion</button>
            </div>
            <video id="support-video" class="w-full rounded-xl border border-line bg-black" autoplay playsinline tabindex="0"></video>
        </div>
    </div>
</section>

<script>
(function () {
    const projectUid = <?= json_encode($project['uid']) ?>;
    const tokenUrl = '/projects/' + projectUid + '/support/token';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const els = {
        badge: document.getElementById('support-conn-badge'),
        list: document.getElementById('support-station-list'),
        empty: document.getElementById('support-station-empty'),
        idle: document.getElementById('support-idle'),
        waiting: document.getElementById('support-waiting'),
        waitingName: document.getElementById('support-waiting-name'),
        denied: document.getElementById('support-denied'),
        active: document.getElementById('support-active'),
        activeName: document.getElementById('support-active-name'),
        video: document.getElementById('support-video'),
        cancelBtn: document.getElementById('support-cancel'),
        endBtn: document.getElementById('support-end'),
    };

    let socket = null;
    let clientId = null;
    let stations = {};
    // Session state for the station currently targeted (request pending or active).
    let session = null; // { deviceId, deviceName, sessionId, pc, dataChannel }

    function setPanel(name) {
        ['idle', 'waiting', 'denied', 'active'].forEach((key) => {
            els[key].classList.toggle('hidden', key !== name);
        });
    }

    function renderStations() {
        const ids = Object.keys(stations);
        els.empty.classList.toggle('hidden', ids.length > 0);
        els.list.querySelectorAll('[data-station-row]').forEach((row) => row.remove());
        ids.forEach((deviceId) => {
            const row = document.createElement('div');
            row.setAttribute('data-station-row', '');
            row.className = 'flex items-center justify-between p-4';
            row.innerHTML = '<span class="text-sm text-slate-200"></span>';
            row.querySelector('span').textContent = stations[deviceId] || deviceId;
            const btn = document.createElement('button');
            btn.className = 'btn-secondary text-xs px-2 py-1.5';
            btn.textContent = 'Conectar';
            btn.addEventListener('click', () => requestSession(deviceId, stations[deviceId] || deviceId));
            row.appendChild(btn);
            els.list.appendChild(row);
        });
    }

    async function connect() {
        const res = await fetch(tokenUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrfToken } });
        if (!res.ok) { els.badge.textContent = 'Error de token'; return; }
        const body = await res.json();
        const token = body.data.token;

        socket = new WebSocket(body.data.ws_url);
        socket.addEventListener('open', () => {
            socket.send(JSON.stringify({ type: 'subscribe', project: projectUid, token, role: 'admin' }));
        });
        socket.addEventListener('message', (event) => {
            let payload;
            try { payload = JSON.parse(event.data); } catch { return; }
            handleMessage(payload);
        });
        socket.addEventListener('close', () => {
            els.badge.textContent = 'Desconectado';
            els.badge.className = 'rounded-full bg-slate-500/15 px-2.5 py-1 text-xs font-semibold text-slate-400';
            stations = {}; renderStations();
            setTimeout(connect, 3000);
        });
    }

    function handleMessage(payload) {
        if (payload.type === 'subscribed') {
            clientId = payload.client_id;
            els.badge.textContent = 'Conectado';
            els.badge.className = 'rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-300';
        } else if (payload.type === 'presence_snapshot') {
            stations = {};
            (payload.stations || []).forEach((s) => { stations[s.device_id] = s.device_name; });
            renderStations();
        } else if (payload.type === 'presence') {
            if (payload.action === 'online') stations[payload.device_id] = payload.device_name;
            else delete stations[payload.device_id];
            renderStations();
        } else if (payload.type === 'signal') {
            handleSignal(payload);
        }
    }

    function sendSignal(toDeviceId, sessionId, payload) {
        socket.send(JSON.stringify({ type: 'signal', to_device_id: toDeviceId, session_id: sessionId, payload }));
    }

    function requestSession(deviceId, deviceName) {
        if (session) return;
        const sessionId = 'sup_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        session = { deviceId, deviceName, sessionId, pc: null, dataChannel: null };
        els.waitingName.textContent = deviceName;
        setPanel('waiting');
        sendSignal(deviceId, sessionId, { kind: 'request' });
    }

    function endSession(notifyPeer) {
        if (!session) return;
        if (notifyPeer) sendSignal(session.deviceId, session.sessionId, { kind: 'end' });
        session.pc?.close();
        els.video.srcObject = null;
        session = null;
        setPanel('idle');
    }

    els.cancelBtn.addEventListener('click', () => endSession(true));
    els.endBtn.addEventListener('click', () => endSession(true));

    async function handleSignal(payload) {
        if (!session || payload.session_id !== session.sessionId) return;
        const kind = payload.payload && payload.payload.kind;

        if (kind === 'deny') {
            setPanel('denied');
            session = null;
        } else if (kind === 'end') {
            endSession(false);
        } else if (kind === 'offer') {
            const pc = createPeerConnection();
            session.pc = pc;
            await pc.setRemoteDescription({ type: 'offer', sdp: payload.payload.sdp });
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            sendSignal(session.deviceId, session.sessionId, { kind: 'answer', sdp: answer.sdp });
            els.activeName.textContent = session.deviceName;
            setPanel('active');
        } else if (kind === 'ice' && session.pc) {
            try { await session.pc.addIceCandidate(payload.payload.candidate); } catch {}
        }
    }

    function createPeerConnection() {
        const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
        pc.addEventListener('track', (event) => { els.video.srcObject = event.streams[0]; });
        pc.addEventListener('icecandidate', (event) => {
            if (event.candidate) sendSignal(session.deviceId, session.sessionId, { kind: 'ice', candidate: event.candidate });
        });
        pc.addEventListener('datachannel', (event) => {
            session.dataChannel = event.channel;
            wireControlEvents();
        });
        pc.addEventListener('connectionstatechange', () => {
            if (['failed', 'closed'].includes(pc.connectionState)) endSession(false);
        });
        return pc;
    }

    function sendControl(msg) {
        if (session && session.dataChannel && session.dataChannel.readyState === 'open') {
            session.dataChannel.send(JSON.stringify(msg));
        }
    }

    // Mouse/keyboard capture is normalized to fractions of the video element's
    // rendered size; the station maps them back to its real screen resolution.
    function wireControlEvents() {
        const video = els.video;
        video.addEventListener('mousemove', (e) => {
            const rect = video.getBoundingClientRect();
            sendControl({ type: 'mouse_move', x: (e.clientX - rect.left) / rect.width, y: (e.clientY - rect.top) / rect.height });
        });
        video.addEventListener('mousedown', (e) => { e.preventDefault(); sendControl({ type: 'mouse_down', button: e.button }); });
        video.addEventListener('mouseup', (e) => { e.preventDefault(); sendControl({ type: 'mouse_up', button: e.button }); });
        video.addEventListener('wheel', (e) => { e.preventDefault(); sendControl({ type: 'scroll', deltaX: e.deltaX, deltaY: e.deltaY }); }, { passive: false });
        video.addEventListener('contextmenu', (e) => e.preventDefault());
        video.addEventListener('keydown', (e) => { e.preventDefault(); sendControl({ type: 'key_down', key: e.key, code: e.code }); });
        video.addEventListener('keyup', (e) => { e.preventDefault(); sendControl({ type: 'key_up', key: e.key, code: e.code }); });
    }

    connect();
})();
</script>
<?php endif; ?>
