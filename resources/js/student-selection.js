export function initializeStudentSelection(root = document, ask = message => window.confirm(message)) {
    const deletion = root.getElementById('student-bulk-delete');
    const room = root.getElementById('student-room-bulk');
    if (!deletion && !room) return;
    const students = [...root.querySelectorAll('[data-room-student]')];
    const all = root.querySelector('[data-room-select-all]');
    const counts = [...root.querySelectorAll('[data-room-selection-count], [data-student-selection-count]')];
    const deleteButton = deletion?.querySelector('[data-bulk-delete-submit]');
    const roomButton = room?.querySelector('button');
    const ids = deletion?.querySelector('[data-selected-students]');
    const warning = root.querySelector('[data-student-selection-warning]');
    let submitting = false;
    const selected = () => students.filter(student => student.checked && !student.disabled);
    const update = () => {
        const checked = selected();
        counts.forEach(count => { count.textContent = `${checked.length} siswa terpilih.`; });
        const disabled = checked.length === 0 || checked.length > 500;
        if (deleteButton) deleteButton.disabled = disabled || submitting;
        if (roomButton) roomButton.disabled = disabled || submitting;
        if (warning) warning.hidden = checked.length <= 500;
        if (all) {
            const eligible = students.filter(student => !student.disabled);
            all.checked = eligible.length > 0 && checked.length === eligible.length;
            all.indeterminate = checked.length > 0 && checked.length < eligible.length;
            all.disabled = eligible.length === 0;
        }
        if (ids) {
            ids.replaceChildren();
            if (!disabled) checked.forEach(student => {
                const input = root.createElement('input');
                input.type = 'hidden'; input.name = 'student_ids[]'; input.value = student.value;
                ids.appendChild(input);
            });
        }
    };
    all?.addEventListener('change', () => {
        students.forEach(student => { if (!student.disabled) student.checked = all.checked; });
        update();
    });
    students.forEach(student => student.addEventListener('change', update));
    deletion?.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        update();
        const count = selected().length;
        if (count === 0 || count > 500 || !ask(`Hapus permanen ${count} siswa yang dicentang beserta akun login, ruangan, absensi, nilai, serta riwayat ujian terkait? Tindakan ini tidak dapat dibatalkan.`)) {
            event.preventDefault(); return;
        }
        submitting = true;
        update();
    });
    root.defaultView?.addEventListener('pageshow', () => { submitting = false; update(); });
    update();
}
