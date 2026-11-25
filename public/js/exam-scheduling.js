document.querySelectorAll('[data-session-window]').forEach(windowFields => {
    const start = windowFields.querySelector('[name="starts_at"]');
    const end = windowFields.querySelector('[name="ends_at"]');
    const output = windowFields.querySelector('[data-session-duration]');
    const min = Number(windowFields.dataset.minDuration);
    const max = Number(windowFields.dataset.maxDuration);

    function updateDuration() {
        const minutes = (end.valueAsNumber - start.valueAsNumber) / 60000;
        let message = '';
        if (start.value && end.value) {
            if (minutes <= 0) {
                message = 'Waktu selesai harus setelah waktu mulai.';
            } else if (start.value.slice(0, 10) !== end.value.slice(0, 10)) {
                message = 'Tanggal selesai harus sama dengan tanggal mulai.';
            } else if (!Number.isInteger(minutes) || minutes < min || minutes > max) {
                message = `Selisih waktu harus ${min}–${max} menit penuh.`;
            }
        }

        end.setCustomValidity(message);
        output.textContent = message || (Number.isFinite(minutes) ? `${minutes} menit` : '—');
    }

    start.addEventListener('input', updateDuration);
    end.addEventListener('input', updateDuration);
    updateDuration();
});
