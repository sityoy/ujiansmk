document.querySelectorAll('[data-question-bank-form]').forEach(form => {
    const type = form.elements.question_type;
    const choices = form.querySelector('[data-question-choice-fields]');
    const keys = [...choices.querySelectorAll('input[type=checkbox]')];
    const manual = form.querySelector('[data-question-manual-points]');
    const points = form.elements.points;
    let pointsEdited = points.hasAttribute('data-points-edited');

    points.addEventListener('input', () => { pointsEdited = true; });
    const validateKeys = () => {
        const missing = type.value === 'multiple_choice' && !keys.some(input => input.checked);
        keys[0].setCustomValidity(missing ? 'Centang minimal satu kunci jawaban.' : '');
    };
    keys.forEach(input => input.addEventListener('change', validateKeys));
    const syncType = () => {
        const multipleChoice = type.value === 'multiple_choice';
        choices.hidden = !multipleChoice;
        choices.querySelectorAll('input[name], select[name], textarea[name]').forEach(field => {
            field.disabled = !multipleChoice;
            const required = multipleChoice && !field.closest('[data-question-file]') && field.type !== 'checkbox';
            field.required = required && !field.dataset.editorInitialized;
            if (field.matches('[data-question-editor]')) field.dataset.editorRequired = String(required);
        });
        validateKeys();
        manual.hidden = multipleChoice;
        points.disabled = multipleChoice;
        if (!multipleChoice && !pointsEdited) points.value = type.value === 'essay' ? '7' : '3';

    };

    const context = form.querySelector('[data-context-select]');
    const contextFields = form.querySelector('[data-context-fields]');
    const existingAttachment = contextFields.querySelector('[data-question-file-saved]');
    const contextCaption = form.elements.context_image_caption;
    let mode = context.value;
    let editCaption = mode === 'edit' ? contextCaption.value : contextFields.dataset.savedCaption;
    let newCaption = mode === 'new' ? contextCaption.value : '';
    const syncContext = () => {
        const next = context.value;
        const editing = next === 'edit';
        const visible = editing || next === 'new';
        if (next !== mode) {
            if (mode === 'edit') editCaption = contextCaption.value;
            if (mode === 'new') newCaption = contextCaption.value;
            contextCaption.value = editing ? editCaption : newCaption;
            const input = form.elements.context_image;
            if (input.value) {
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        contextFields.hidden = !visible;
        contextFields.querySelectorAll('textarea, input').forEach(field => { field.disabled = !visible; });
        if (existingAttachment) {
            existingAttachment.hidden = !editing;
            existingAttachment.querySelector('input').disabled = !editing;
        }
        mode = next;
    };
    context.addEventListener('change', syncContext);
    syncContext();

    type.addEventListener('change', syncType);
    syncType();
});
