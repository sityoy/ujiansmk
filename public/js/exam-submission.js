function examAnswerValue(input) {
    if (input.type !== 'checkbox') return input.value;

    const group = input.closest('[data-multiple-answer]');
    const selections = [...group.querySelectorAll('input[type="checkbox"]:checked')];
    const overLimit = selections.length > Number(group.dataset.choiceLimit);
    group.querySelector('[data-choice-limit-message]').hidden = !overLimit;
    if (overLimit) {
        if (input.checked) input.checked = false;
        return null;
    }

    const answer = selections.map(choice => choice.value).sort().join('');
    const value = group.querySelector('[data-choice-value]');
    if (value) value.value = answer;

    return answer;
}

function unansweredExamQuestionNumbers(questions, answerSelector = '[data-save-url]') {
    return Array.from(questions).filter(question => {
        const inputs = Array.from(question.querySelectorAll(answerSelector));
        const choices = inputs.filter(input => input.type === 'checkbox' && input.checked);
        if (choices.length > 0) {
            const group = choices[0].closest('[data-multiple-answer]');
            return choices.length > Number(group.dataset.choiceLimit);
        }

        return !inputs.some(input => input.type === 'radio' ? input.checked
            : input.type !== 'checkbox' && input.value.trim() !== '');
    }).map(question => question.dataset.examQuestionNumber);
}

function unansweredExamMessage(numbers) {
    return `Masih ada ${numbers.length} soal yang belum dijawab. Silakan kerjakan soal nomor ${numbers.join(', ')} terlebih dahulu.`;
}

window.bindExamSections = ({ sections, submitPanel, flushAnswers, pending, toolbar, answerSelector = '[data-save-url]' }) => {
    if (sections.length < 2) return;

    const unansweredIn = section => unansweredExamQuestionNumbers(
        section.querySelectorAll('[data-exam-question-number]'), answerSelector,
    );
    const firstUnfinished = sections.findIndex(section => unansweredIn(section).length > 0);
    let active = firstUnfinished === -1 ? sections.length - 1 : firstUnfinished;
    const blocked = () => window.examAutoSubmit || window.examSecurity?.isBlocked();

    const showSection = (index, focus = false) => {
        active = index;
        sections.forEach((section, position) => { section.hidden = position !== index; });
        submitPanel.hidden = index !== sections.length - 1;
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
            const unanswered = unansweredIn(section);
            if (unanswered.length > 0) {
                message.textContent = unansweredExamMessage(unanswered);
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
    const questions = document.querySelectorAll('[data-exam-question-number]');
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

        const unanswered = unansweredExamQuestionNumbers(questions);
        if (unanswered.length > 0) {
            showMessage(unansweredExamMessage(unanswered));
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
