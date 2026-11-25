document.querySelectorAll('[data-question-bank-form]').forEach(form => {
    const type = form.elements.question_type;
    const choices = form.querySelector('[data-question-choice-fields]');
    const manual = form.querySelector('[data-question-manual-points]');
    const points = form.elements.points;
    let pointsEdited = points.hasAttribute('data-points-edited');

    points.addEventListener('input', () => { pointsEdited = true; });
    const syncType = () => {
        const multipleChoice = type.value === 'multiple_choice';
        choices.hidden = !multipleChoice;
        choices.querySelectorAll('input[name], select[name], textarea[name]').forEach(field => {
            field.disabled = !multipleChoice;
            const required = multipleChoice && field.type !== 'file';
            field.required = required && !field.dataset.editorInitialized;
            if (field.matches('[data-question-editor]')) field.dataset.editorRequired = String(required);
        });
        manual.hidden = multipleChoice;
        points.disabled = multipleChoice;
        if (!multipleChoice && !pointsEdited) points.value = type.value === 'essay' ? '7' : '3';

        if (form.hasAttribute('data-core-question-form')) {
            const statement = form.querySelector('[data-question-statement]');
            const attachment = form.querySelector('[data-question-attachment]');
            const prompt = form.querySelector('[data-question-text]');
            statement.hidden = !multipleChoice;
            statement.querySelector('textarea').disabled = !multipleChoice;
            if (multipleChoice) attachment.after(prompt);
            else attachment.before(prompt);
        }
    };

    type.addEventListener('change', syncType);
    syncType();
});
