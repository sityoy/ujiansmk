(() => {
    const dialog = document.getElementById('exam-image-dialog');
    if (!dialog) return;

    const content = document.getElementById('exam-content');
    const backdrop = document.getElementById('exam-image-backdrop');
    const close = dialog.querySelector('[data-exam-image-close]');
    const preview = dialog.querySelector('[data-exam-image-preview]');
    const error = dialog.querySelector('[data-exam-image-error]');
    const title = document.getElementById('exam-image-title');
    const nativeDialog = typeof dialog.showModal === 'function';
    let trigger = null;

    const clearPreview = () => {
        if (dialog.hasAttribute('open')) return;
        dialog.hidden = true;
        backdrop.hidden = true;
        preview.removeAttribute('src');
        if (!content.hidden && !window.examSecurity?.isBlocked()) trigger?.focus({ preventScroll: true });
        trigger = null;
    };

    const dismiss = () => {
        if (!dialog.hasAttribute('open')) return;
        if (nativeDialog) {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        clearPreview();
    };

    content.addEventListener('click', event => {
        const clicked = event.target.closest('[data-exam-image]');
        if (!clicked || trigger || content.hidden || window.examSecurity?.isBlocked()) return;

        trigger = clicked;
        const image = trigger.querySelector('img');
        title.textContent = image.alt;
        preview.alt = image.alt;
        preview.hidden = false;
        error.hidden = true;
        preview.src = image.currentSrc || image.src;
        dialog.hidden = false;
        if (nativeDialog) {
            dialog.showModal();
        } else {
            backdrop.hidden = false;
            dialog.setAttribute('open', '');
            close.focus();
        }
    });

    close.addEventListener('click', dismiss);
    dialog.addEventListener('close', clearPreview);
    dialog.addEventListener('keydown', event => {
        if (nativeDialog) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            dismiss();
        } else if (event.key === 'Tab') {
            event.preventDefault();
            close.focus();
        }
    });
    preview.addEventListener('error', () => {
        if (!dialog.hasAttribute('open')) return;
        preview.hidden = true;
        error.hidden = false;
    });
    window.addEventListener('examsecurity:blocked', dismiss);
})();
