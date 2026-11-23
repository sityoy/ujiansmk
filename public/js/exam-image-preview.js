(() => {
    const dialog = document.getElementById('exam-image-dialog');
    if (!dialog) return;

    const content = document.getElementById('exam-content');
    const root = content ?? document;
    const backdrop = document.getElementById('exam-image-backdrop');
    const close = dialog.querySelector('[data-exam-image-close]');
    const preview = dialog.querySelector('[data-exam-image-preview]');
    const error = dialog.querySelector('[data-exam-image-error]');
    const viewport = dialog.querySelector('[data-exam-image-viewport]');
    const zoomOut = dialog.querySelector('[data-exam-image-zoom-out]');
    const zoomIn = dialog.querySelector('[data-exam-image-zoom-in]');
    const zoomReset = dialog.querySelector('[data-exam-image-zoom-reset]');
    const scale = dialog.querySelector('[data-exam-image-scale]');
    const title = document.getElementById('exam-image-title');
    const nativeDialog = typeof dialog.showModal === 'function';
    const fitScale = 100;
    const maxScale = 300;
    const zoomStep = 50;
    let trigger = null;
    let loaded = false;
    let currentScale = fitScale;
    let fittedWidth = 0;

    const updateZoomControls = () => {
        zoomOut.disabled = !loaded || currentScale === fitScale;
        zoomIn.disabled = !loaded || currentScale === maxScale;
        zoomReset.disabled = zoomOut.disabled;
        scale.textContent = `${currentScale}%`;
    };

    const fitImage = () => {
        currentScale = fitScale;
        fittedWidth = 0;
        preview.removeAttribute('data-enlarged');
        preview.style.removeProperty('width');
        viewport.scrollLeft = 0;
        viewport.scrollTop = 0;
        updateZoomControls();
    };

    const changeZoom = requestedScale => {
        if (!loaded || !dialog.hasAttribute('open') || window.examSecurity?.isBlocked()) return;

        const next = Math.min(maxScale, Math.max(fitScale, requestedScale));
        if (next === fitScale) {
            fitImage();
        } else {
            if (currentScale === fitScale) fittedWidth = preview.clientWidth;
            if (!fittedWidth) return;
            const ratio = next / currentScale;
            const left = viewport.scrollLeft * ratio;
            const top = viewport.scrollTop * ratio;
            preview.setAttribute('data-enlarged', '');
            preview.style.width = `${Math.round(fittedWidth * next / fitScale)}px`;
            currentScale = next;
            viewport.scrollLeft = left;
            viewport.scrollTop = top;
            updateZoomControls();
        }

        // Keep keyboard focus inside the viewer when the control reaches a limit.
        if ([zoomIn, zoomOut, zoomReset].some(button => button === document.activeElement && button.disabled)) {
            viewport.focus({ preventScroll: true });
        }
    };

    const clearPreview = () => {
        if (dialog.hasAttribute('open')) return;
        dialog.hidden = true;
        backdrop.hidden = true;
        loaded = false;
        fitImage();
        preview.removeAttribute('src');
        if (!content?.hidden && !window.examSecurity?.isBlocked()) trigger?.focus({ preventScroll: true });
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

    root.addEventListener('click', event => {
        const clicked = event.target.closest('[data-exam-image]');
        if (!clicked || trigger || content?.hidden || window.examSecurity?.isBlocked()) return;

        const image = clicked.querySelector('img');
        const source = image.src || image.currentSrc;
        if (!source) return;
        trigger = clicked;
        title.textContent = image.alt;
        preview.alt = image.alt;
        preview.hidden = false;
        error.hidden = true;
        loaded = false;
        fitImage();
        dialog.hidden = false;
        if (nativeDialog) {
            dialog.showModal();
        } else {
            backdrop.hidden = false;
            dialog.setAttribute('open', '');
            close.focus();
        }
        preview.src = source;
    });

    if (!content && window.URL?.createObjectURL) {
        const previews = new Map();
        const clearUpload = input => {
            const previous = previews.get(input);
            if (!previous) return;
            previous.panel.hidden = true;
            previous.image.removeAttribute('src');
            window.URL.revokeObjectURL(previous.url);
            previews.delete(input);
        };

        document.addEventListener('change', event => {
            const input = event.target;
            if (input.type !== 'file') return;
            const field = input.closest('[data-question-file]');
            if (!field) return;

            clearUpload(input);
            const file = input.files?.[0];
            if (!file) return;
            const isImage = file.type
                ? /^image\/(jpeg|png|webp)$/i.test(file.type)
                : /\.(jpe?g|png|webp)$/i.test(file.name);
            if (!isImage) return;

            const panel = field.querySelector('[data-question-file-preview]');
            const image = panel.querySelector('img');
            const url = window.URL.createObjectURL(file);
            previews.set(input, { panel, image, url });
            image.src = url;
            panel.hidden = false;
        });
        document.addEventListener('reset', event => {
            for (const input of previews.keys()) {
                if (input.form === event.target) clearUpload(input);
            }
        });
        window.addEventListener('pagehide', event => {
            if (!event.persisted) previews.forEach(({ url }) => window.URL.revokeObjectURL(url));
        });
    }

    close.addEventListener('click', dismiss);
    zoomOut.addEventListener('click', () => changeZoom(currentScale - zoomStep));
    zoomIn.addEventListener('click', () => changeZoom(currentScale + zoomStep));
    zoomReset.addEventListener('click', () => changeZoom(fitScale));
    dialog.addEventListener('close', clearPreview);
    // Older Safari versions also expose pinch gestures outside of touch-action.
    for (const type of ['gesturestart', 'gesturechange']) {
        dialog.addEventListener(type, event => { if (event.cancelable) event.preventDefault(); });
    }
    dialog.addEventListener('keydown', event => {
        if (nativeDialog) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            dismiss();
        } else if (event.key === 'Tab') {
            const controls = [...dialog.querySelectorAll('button:not(:disabled), [data-exam-image-viewport]')];
            const current = controls.indexOf(document.activeElement);
            const direction = event.shiftKey ? -1 : 1;
            const next = current < 0 ? (event.shiftKey ? controls.length - 1 : 0)
                : (current + direction + controls.length) % controls.length;
            event.preventDefault();
            controls[next].focus();
        }
    });
    preview.addEventListener('load', () => {
        if (!dialog.hasAttribute('open')) return;
        loaded = true;
        updateZoomControls();
    });
    preview.addEventListener('error', () => {
        if (!dialog.hasAttribute('open')) return;
        loaded = false;
        fitImage();
        preview.hidden = true;
        error.hidden = false;
    });
    window.addEventListener('examsecurity:blocked', dismiss);
})();
