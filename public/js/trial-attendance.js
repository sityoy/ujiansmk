(() => {
    const form = document.querySelector('[data-trial-attendance-url]');
    if (!form) return;

    const check = form.querySelector('[data-location-check]');
    const status = form.querySelector('[data-location-status]');
    const start = form.querySelector('button[type="submit"]');
    const credentials = [...form.querySelectorAll('[name="participant_identifier"], [name="access_code"]')];
    const reset = () => {
        start.disabled = true;
        status.textContent = '';
    };
    credentials.forEach(input => input.addEventListener('input', reset));

    check.addEventListener('click', async () => {
        if (!credentials.every(input => input.reportValidity())) return;
        reset();

        if (!navigator.geolocation) {
            status.textContent = 'Lokasi tidak tersedia di browser ini. Pastikan halaman dibuka lewat HTTPS dan hubungi pengawas.';
            return;
        }

        const data = new FormData(form);
        check.disabled = true;
        credentials.forEach(input => { input.readOnly = true; });
        status.textContent = 'Membaca lokasi… Izinkan akses lokasi presisi jika diminta.';
        try {
            const coords = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(
                result => resolve(result.coords),
                error => reject(new Error(error.code === 1
                    ? 'Izin lokasi ditolak. Izinkan lokasi untuk situs ini, lalu coba lagi.'
                    : 'Posisi belum terbaca. Pastikan layanan lokasi aktif, lalu coba lagi.')),
                {enableHighAccuracy: true, timeout: 15000, maximumAge: 0},
            ));
            data.set('latitude', coords.latitude);
            data.set('longitude', coords.longitude);
            data.set('accuracy_meters', coords.accuracy);
            status.textContent = 'Memverifikasi absensi…';

            const controller = typeof AbortController === 'function' ? new AbortController() : null;
            const timeout = setTimeout(() => controller?.abort(), 15000);
            try {
                const response = await fetch(form.dataset.trialAttendanceUrl, {
                    method: 'POST',
                    headers: {'Accept': 'application/json'},
                    body: data,
                    signal: controller?.signal,
                });
                if (response.status === 419) {
                    throw new Error('Sesi halaman telah berakhir. Muat ulang sebelum mulai, lalu absen kembali.');
                }
                const result = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = Object.values(result.errors ?? {}).flat()[0];
                    throw new Error(message || 'Absensi belum terverifikasi. Periksa koneksi dan coba lagi.');
                }
                status.textContent = result.message;
                start.disabled = false;
            } finally {
                clearTimeout(timeout);
            }
        } catch (error) {
            status.textContent = error.name === 'AbortError'
                ? 'Server belum merespons. Periksa koneksi, lalu coba lagi.'
                : error.message;
        } finally {
            check.disabled = false;
            credentials.forEach(input => { input.readOnly = false; });
        }
    });
})();
