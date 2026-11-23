(() => {
    const setFeedback = (form, state, text) => {
        const feedback = form.querySelector('[data-attendance-feedback]');
        feedback.dataset.state = state;
        feedback.querySelector('[data-attendance-message]').textContent = text;
    };

    const getLocation = () => new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Browser tidak dapat membaca lokasi. Buka halaman ujian dengan HTTPS atau hubungi pengawas.'));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            result => resolve(result.coords),
            error => reject(new Error(error.code === 1
                ? 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini, lalu coba lagi.'
                : 'Posisi belum terbaca. Aktifkan GPS, lalu coba lagi.')),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    });

    document.querySelectorAll('[data-core-attendance]').forEach(form => {
        const check = form.querySelector('[data-location-check]');
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (check.disabled) return;
            check.disabled = true;
            setFeedback(form, 'checking', 'Membaca lokasi. Izinkan akses lokasi presisi jika diminta.');

            try {
                const coords = await getLocation();
                form.elements.latitude.value = coords.latitude;
                form.elements.longitude.value = coords.longitude;
                form.elements.accuracy_meters.value = coords.accuracy;
                setFeedback(form, 'checking', 'Lokasi terbaca. Sedang memverifikasi absensi.');
                form.submit();
            } catch (error) {
                setFeedback(form, 'error', error.message);
                check.disabled = false;
            }
        });
    });

    const trial = document.querySelector('[data-trial-attendance-url]');
    if (!trial) return;
    const check = trial.querySelector('[data-location-check]');
    const start = trial.querySelector('button[type="submit"]');
    const credentials = [...trial.querySelectorAll('[name="participant_identifier"], [name="access_code"]')];
    credentials.forEach(input => input.addEventListener('input', () => {
        start.disabled = true;
        setFeedback(trial, 'pending', 'Setelah mengisi identitas dan kode, ambil lokasi untuk memverifikasi absensi.');
    }));

    check.addEventListener('click', async () => {
        if (check.disabled || !credentials.every(input => input.reportValidity())) return;

        start.disabled = true;
        check.disabled = true;
        credentials.forEach(input => { input.readOnly = true; });
        setFeedback(trial, 'checking', 'Membaca lokasi. Izinkan akses lokasi presisi jika diminta.');
        try {
            const coords = await getLocation();
            const data = new FormData(trial);
            data.set('latitude', coords.latitude);
            data.set('longitude', coords.longitude);
            data.set('accuracy_meters', coords.accuracy);
            setFeedback(trial, 'checking', 'Lokasi terbaca. Sedang memverifikasi absensi.');

            const controller = typeof AbortController === 'function' ? new AbortController() : null;
            const timeout = setTimeout(() => controller?.abort(), 15000);
            try {
                const response = await fetch(trial.dataset.trialAttendanceUrl, {
                    method: 'POST',
                    headers: {'Accept': 'application/json'},
                    body: data,
                    signal: controller?.signal,
                });
                if (response.status === 419) {
                    throw new Error('Sesi halaman telah berakhir. Muat ulang halaman, lalu absen kembali.');
                }
                const result = await response.json().catch(() => ({}));
                if (!response.ok || result.verified !== true) {
                    const message = Object.values(result.errors ?? {}).flat()[0];
                    throw new Error(message || 'Absensi belum terverifikasi. Periksa koneksi, lalu coba lagi.');
                }
                setFeedback(trial, 'success', result.message || 'Lokasi terverifikasi. Centang pernyataan kesiapan, lalu pilih Mulai ujian.');
                start.disabled = false;
            } finally {
                clearTimeout(timeout);
            }
        } catch (error) {
            const message = error.name === 'AbortError' || error instanceof TypeError
                ? 'Gagal terhubung ke server. Periksa koneksi, lalu coba lagi.'
                : error.message;
            setFeedback(trial, 'error', message);
        } finally {
            check.disabled = false;
            credentials.forEach(input => { input.readOnly = false; });
        }
    });
})();
