function countUnansweredExamQuestions(inputs, questionCount) {
    const answered = new Set();
    inputs.forEach(input => {
        if (input.type === 'radio' ? input.checked : input.value.trim() !== '') {
            answered.add(input.dataset.saveUrl);
        }
    });

    return questionCount - answered.size;
}

window.bindExamSections = ({ sections, submitForm, flushAnswers, pending, toolbar }) => {
    if (sections.length < 2) return;

    const countUnanswered = section => countUnansweredExamQuestions(
        section.querySelectorAll('[data-save-url]'),
        Number(section.dataset.questionCount),
    );
    const firstUnfinished = sections.findIndex(section => countUnanswered(section) > 0);
    let active = firstUnfinished === -1 ? sections.length - 1 : firstUnfinished;
    const blocked = () => window.examAutoSubmit || window.examSecurity?.isBlocked();

    const showSection = (index, focus = false) => {
        active = index;
        sections.forEach((section, position) => { section.hidden = position !== index; });
        submitForm.hidden = index !== sections.length - 1;
        if (!focus) return;

        const heading = sections[index].querySelector('[data-exam-section-heading]');
        heading.focus({ preventScroll: true });
        const top = window.scrollY + heading.getBoundingClientRect().top - toolbar.getBoundingClientRect().height - 24;
        window.scrollTo(0, Math.max(0, top));
    };

    sections.forEach((section, index) => {
        const back = section.querySelector('[data-exam-section-back]');
        const next = section.querySelector('[data-exam-section-next]');
        const message = section.querySelector('[data-exam-section-message]');
        back?.addEventListener('click', () => {
            if (!blocked()) showSection(index - 1, true);
        });
        next?.addEventListener('click', async () => {
            if (blocked()) return;
            const unanswered = countUnanswered(section);
            if (unanswered > 0) {
                message.textContent = `Masih ada ${unanswered} soal yang belum dijawab di bagian ini.`;
                message.hidden = false;
                return;
            }

            message.hidden = true;
            next.disabled = true;
            try {
                await flushAnswers();
                if (blocked() || index !== active) return;
                if (pending.size) {
                    message.textContent = 'Ada jawaban yang belum tersimpan. Periksa koneksi, lalu coba lanjut lagi.';
                    message.hidden = false;
                    return;
                }

                showSection(index + 1, true);
            } finally {
                next.disabled = false;
            }
        });
    });

    showSection(active);
};

window.bindExamSubmission = ({ form, inputs = [], flushAnswers, pending, allowBlocked = false }) => {
    const dialog = document.getElementById('exam-submit-dialog');
    const backdrop = document.getElementById('exam-submit-backdrop');
    const message = document.getElementById('exam-submit-message');
    const opener = form.querySelector('[type="submit"]');
    const cancel = dialog.querySelector('[data-submit-cancel]');
    const confirm = dialog.querySelector('[data-submit-confirm]');
    const questionCount = Number(form.dataset.questionCount || 0);
    const supportsModal = typeof dialog.showModal === 'function';
    let submitting = false;

    const canSubmit = () => !submitting && !window.examAutoSubmit
        && (allowBlocked || !window.examSecurity?.isBlocked());

    const showMessage = text => {
        message.textContent = text;
        message.hidden = !text;
    };

    const closeDialog = (restoreFocus = true) => {
        if (dialog.hidden) return;
        if (supportsModal) dialog.close();
        else dialog.removeAttribute('open');
        dialog.hidden = true;
        backdrop.hidden = true;
        if (restoreFocus) opener.focus({ preventScroll: true });
    };

    const cancelSubmission = event => {
        event.preventDefault();
        closeDialog();
    };

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!canSubmit() || !dialog.hidden) return;

        const unanswered = countUnansweredExamQuestions(inputs, questionCount);
        if (unanswered > 0) {
            showMessage(`Masih ada ${unanswered} soal yang belum terjawab. Lengkapi semua jawaban sebelum mengumpulkan.`);
            return;
        }

        showMessage('');
        dialog.hidden = false;
        if (supportsModal) dialog.showModal();
        else {
            backdrop.hidden = false;
            dialog.setAttribute('open', '');
        }
        cancel.focus({ preventScroll: true });
    });

    cancel.addEventListener('click', cancelSubmission);
    dialog.addEventListener('cancel', cancelSubmission);
    if (!supportsModal) {
        dialog.addEventListener('keydown', event => {
            if (event.key === 'Escape') cancelSubmission(event);
            if (event.key === 'Tab') {
                event.preventDefault();
                (document.activeElement === cancel ? confirm : cancel).focus();
            }
        });
    }
    if (!allowBlocked) window.addEventListener('examsecurity:blocked', () => closeDialog(false));

    confirm.addEventListener('click', async () => {
        if (dialog.hidden || !canSubmit()) return;
        closeDialog();
        submitting = true;
        opener.disabled = true;
        inputs.forEach(input => { input.disabled = true; });

        if (flushAnswers) await flushAnswers();
        if (window.examAutoSubmit) return;
        if (pending?.size || (!allowBlocked && window.examSecurity?.isBlocked())) {
            submitting = false;
            opener.disabled = false;
            inputs.forEach(input => { input.disabled = false; });
            if (pending?.size) showMessage('Ada jawaban yang belum tersimpan. Tekan Coba simpan lagi, lalu kumpulkan jawaban.');
            return;
        }

        window.examAutoSubmit = true;
        form.submit();
    });
};
