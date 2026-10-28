(() => {
    const root = document.getElementById('exam-security');
    if (!root) return;
    const panel = document.getElementById('security-panel');
    const title = document.getElementById('security-title');
    const message = document.getElementById('security-message');
    const network = document.getElementById('security-network');
    const button = document.getElementById('security-continue');
    const content = document.getElementById('exam-content');
    const counter = document.getElementById('security-count');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const storageKey = `exam-security:${root.dataset.attemptId}`;
    const allowedCategories = [
        'tab_hidden', 'fullscreen_exit', 'window_blur', 'system_gesture',
        'viewport_change', 'restricted_action', 'navigation_attempt',
    ];
    const fullscreenElement = () => document.fullscreenElement || document.webkitFullscreenElement || null;
    const fullscreenRequest = document.documentElement.requestFullscreen || document.documentElement.webkitRequestFullscreen;
    const fullscreenSupported = !!fullscreenRequest;
    const viewportSnapshot = () => ({
        viewport_width: Math.round(window.visualViewport?.width ?? window.innerWidth ?? 0),
        viewport_height: Math.round(window.visualViewport?.height ?? window.innerHeight ?? 0),
        screen_width: Math.round(window.screen?.availWidth ?? window.screen?.width ?? 0),
        screen_height: Math.round(window.screen?.availHeight ?? window.screen?.height ?? 0),
        pixel_ratio: Number((window.devicePixelRatio || 1).toFixed(3)),
    });
    let locked = root.dataset.locked === '1';
    let accepted = false;
    let armed = false;
    let paused = false;
    let busy = null;
    let queue = [];
    try {
        const restored = JSON.parse(localStorage.getItem(storageKey) || '[]');
        if (Array.isArray(restored)) queue = restored.filter(item => item && typeof item.event_id === 'string'
            && allowedCategories.includes(item.category)).slice(0, 2);
    } catch (_) { /* Browser storage may be disabled. In-memory delivery remains available. */ }
    const persist = () => {
        try { localStorage.setItem(storageKey, JSON.stringify(queue)); } catch (_) {}
    };
    const gate = (heading, text) => {
        accepted = false;
        armed = false;
        panel.hidden = false;
        panel.style.display = 'block';
        if (content) { content.hidden = true; content.disabled = true; }
        title.textContent = heading;
        message.textContent = text;
    };
    const applyState = state => {
        if (!['in_progress', 'submitted', 'terminated'].includes(state.status)) throw new Error('Status server tidak valid.');
        if (state.status !== 'in_progress') {
            window.examAutoSubmit = true;
            queue = [];
            persist();
            window.location.replace(root.dataset.indexUrl);
            return;
        }
        const remaining = Date.parse(state.deadline) - Date.parse(state.server_time);
        if (Number.isFinite(remaining)) window.examServerClock = { remainingMs: Math.max(0, remaining), at: performance.now() };
        const wasLocked = locked;
        locked = !!state.locked;
        if (counter) counter.textContent = `Pelanggaran ${state.violations}/2`;
        if (locked) {
            gate('Ujian terkunci — batas 2 pelanggaran', 'Jawaban yang sudah tersimpan tetap aman. Hubungi pengawas untuk pemeriksaan. Waktu tidak berhenti; saat habis jawaban dikumpulkan otomatis.');
            button.textContent = 'Periksa izin pengawas';
        } else if (wasLocked) {
            if (!content) { window.location.reload(); return; }
            gate('Pengawas mengizinkan lanjut', 'Hitungan tetap 2/2. Kejadian berikutnya langsung mengunci kembali. Waktu dan jawaban tidak direset.');
            button.textContent = 'Kembali ke mode ujian';
        } else if (!accepted && state.violations > 0 && !queue.length) {
            gate(`Peringatan ${state.violations}/2`, 'Aktivitas di luar mode ujian terdeteksi. Tutup bubble/notifikasi dan kembali ke layar penuh. Jika terjadi lagi hingga batas dua, ujian dikunci untuk pengawas.');
            button.textContent = 'Saya paham, kembali ke ujian';
        }
    };
    const request = async (url, options = {}) => {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 12000);
        try {
            const response = await fetch(url, { ...options, signal: controller.signal, cache: 'no-store',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf } });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Sesi login atau koneksi perlu diperiksa.');
            return result;
        } finally { clearTimeout(timeout); }
    };
    const check = () => {
        if (busy) return busy;
        busy = (async () => {
            try {
                while (queue.length) {
                    const state = await request(root.dataset.eventUrl, { method: 'POST', body: JSON.stringify(queue[0]), keepalive: true });
                    queue.shift();
                    persist();
                    applyState(state);
                    if (window.examAutoSubmit) return false;
                }
                const state = await request(root.dataset.stateUrl);
                applyState(state);
                network.textContent = '';
                return !locked && !window.examAutoSubmit;
            } catch (_) {
                gate('Sinkronisasi pengawasan tertunda', 'Jawaban yang sudah diterima server tetap tersimpan. Periksa koneksi dan hubungi pengawas jika masalah berlanjut.');
                network.textContent = 'Belum tersambung ke server. Klik tombol untuk mencoba lagi. Waktu tetap berjalan.';
                return false;
            }
        })().finally(() => { busy = null; });
        return busy;
    };
    const report = category => {
        if (!armed || paused || window.examAutoSubmit || locked) return;
        // Disarm immediately: visibilitychange + fullscreenchange are one departure.
        gate('Perubahan mode ujian terdeteksi', 'Menyinkronkan catatan ke pengawas. Tunggu sampai pemeriksaan server selesai.');
        queue.push({ category, event_id: crypto.randomUUID(), context: viewportSnapshot() });
        persist();
        check();
    };
    const returnToExam = () => {
        paused = false;
        if (fullscreenSupported && !fullscreenElement()) {
            gate('Kembali ke layar penuh', 'Konfirmasi selesai. Tekan tombol untuk kembali ke mode ujian.');
        } else if (accepted && !locked) armed = true;
    };
    let baselineViewport = viewportSnapshot();
    let resizeTimer = null;
    let focusTimer = null;
    let orientationGraceUntil = 0;
    let edgeGesture = null;
    let historyGuarded = false;
    const textEntryActive = () => ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
    const guardNavigation = () => {
        if (historyGuarded || !window.history?.pushState) return;
        window.history.pushState({ examGuard: true }, '', window.location.href);
        historyGuarded = true;
    };
    const markOrientationChange = () => {
        orientationGraceUntil = Date.now() + 2000;
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { baselineViewport = viewportSnapshot(); }, 1200);
    };
    const inspectViewport = () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (!armed || paused || locked || textEntryActive() || Date.now() < orientationGraceUntil) return;
            const current = viewportSnapshot();
            const widthLoss = baselineViewport.viewport_width - current.viewport_width;
            const heightLoss = baselineViewport.viewport_height - current.viewport_height;
            const widthSuspicious = widthLoss >= Math.max(160, baselineViewport.viewport_width * 0.25);
            const heightSuspicious = heightLoss >= Math.max(220, baselineViewport.viewport_height * 0.35);
            if (widthSuspicious || heightSuspicious) report('viewport_change');
        }, 650);
    };
    window.examSecurity = {
        isBlocked: () => locked || !accepted || queue.length > 0,
        check,
        pause: () => { paused = true; armed = false; },
        returnToExam,
    };
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            // Request fullscreen during the click's user activation, before network awaits.
            if (!locked && fullscreenSupported && !fullscreenElement()) {
                try { await fullscreenRequest.call(document.documentElement); }
                catch (_) { network.textContent = 'Layar penuh belum diizinkan. Coba lagi atau hubungi pengawas.'; return; }
            }
            if (!await check() || !content || document.hidden) return;
            if (fullscreenSupported && !fullscreenElement()) {
                network.textContent = 'Klik sekali lagi untuk masuk layar penuh.';
                return;
            }
            accepted = true;
            armed = true;
            paused = false;
            baselineViewport = viewportSnapshot();
            guardNavigation();
            panel.hidden = true;
            panel.style.display = 'none';
            content.hidden = false;
            content.disabled = false;
            window.dispatchEvent(new Event('examsecurity:ready'));
        } finally { button.disabled = false; }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) report('tab_hidden');
        else if (!window.examAutoSubmit) check();
    });
    const handleFullscreenChange = () => {
        if (fullscreenSupported && !fullscreenElement()) report('fullscreen_exit');
    };
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);

    // A bubble, system overlay, or notification panel commonly removes focus without hiding the page.
    window.addEventListener('blur', () => {
        clearTimeout(focusTimer);
        focusTimer = setTimeout(() => {
            const stillBlurred = typeof document.hasFocus !== 'function' || !document.hasFocus();
            if (stillBlurred && !document.hidden) report('window_blur');
        }, 250);
    });
    window.addEventListener('focus', () => clearTimeout(focusTimer));

    // Detect a downward gesture beginning at the physical top edge. A cancelled edge
    // gesture is also suspicious because Android may take control of the touch stream.
    document.addEventListener('touchstart', event => {
        if (!armed || paused || locked) return;
        if (event.touches?.length > 1) {
            if (event.cancelable) event.preventDefault();
            report('restricted_action');
            return;
        }
        const touch = event.touches?.[0];
        edgeGesture = touch && touch.clientY <= 32
            ? { startY: touch.clientY, startedAt: Date.now() }
            : null;
    }, { passive: false });
    document.addEventListener('touchmove', event => {
        if (!edgeGesture || !armed) return;
        const touch = event.touches?.[0];
        if (touch && touch.clientY - edgeGesture.startY >= 90) {
            edgeGesture = null;
            report('system_gesture');
        }
    }, { passive: true });
    document.addEventListener('touchcancel', () => {
        if (edgeGesture && Date.now() - edgeGesture.startedAt <= 1500) report('system_gesture');
        edgeGesture = null;
    });
    document.addEventListener('touchend', () => { edgeGesture = null; });

    window.addEventListener('resize', inspectViewport);
    window.visualViewport?.addEventListener('resize', inspectViewport);
    window.addEventListener('orientationchange', markOrientationChange);
    window.screen?.orientation?.addEventListener('change', markOrientationChange);

    document.addEventListener('keydown', event => {
        const key = String(event.key || '').toLowerCase();
        const blocked = key === 'f12' || key === 'f5' || key === 'printscreen'
            || (event.ctrlKey && ['p', 's', 'u', 'r'].includes(key))
            || (event.ctrlKey && event.shiftKey && ['i', 'j', 'c'].includes(key));
        if (!blocked) return;
        event.preventDefault();
        report('restricted_action');
    });
    document.addEventListener('keyup', event => {
        if (String(event.key || '').toLowerCase() === 'printscreen') report('restricted_action');
    });
    window.addEventListener('popstate', () => {
        if (!armed || paused || window.examAutoSubmit) return;
        window.history?.pushState({ examGuard: true }, '', window.location.href);
        report('navigation_attempt');
    });
    window.addEventListener('beforeunload', event => {
        if (!accepted || paused || locked || window.examAutoSubmit) return;
        event.preventDefault();
        event.returnValue = '';
    });

    if (document.documentElement.style) document.documentElement.style.overscrollBehavior = 'none';
    if (document.body) document.body.style.overscrollBehavior = 'none';
    // These are deterrents only, not proof of cheating or an OS-level device lock.
    ['copy', 'cut', 'paste', 'contextmenu'].forEach(name => document.addEventListener(name, event => {
        if (accepted && content?.contains(event.target)) event.preventDefault();
    }));
    window.addEventListener('online', check);
    setInterval(() => { if (!document.hidden && !window.examAutoSubmit) check(); }, 15000);
    if (!fullscreenSupported && !locked) {
        message.textContent += ' Browser ini tidak mendukung fullscreen; pengawasan perpindahan halaman tetap aktif.';
    }
    if (locked || queue.length) check();
})();
