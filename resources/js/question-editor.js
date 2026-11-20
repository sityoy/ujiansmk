import Quill from 'quill';

const editors = new WeakMap();
const formats = ['bold', 'italic', 'underline', 'strike', 'script', 'list', 'blockquote'];
const toolbarMarkup = `
    <span class="ql-formats">
        <button type="button" class="ql-bold" aria-label="Tebal (Ctrl+B)" title="Tebal (Ctrl+B)"></button>
        <button type="button" class="ql-italic" aria-label="Miring (Ctrl+I)" title="Miring (Ctrl+I)"></button>
        <button type="button" class="ql-underline" aria-label="Garis bawah (Ctrl+U)" title="Garis bawah (Ctrl+U)"></button>
        <button type="button" class="ql-strike" aria-label="Coret" title="Coret"></button>
    </span>
    <span class="ql-formats">
        <button type="button" class="ql-script" value="super" aria-label="Pangkat" title="Pangkat"></button>
        <button type="button" class="ql-script" value="sub" aria-label="Indeks bawah" title="Indeks bawah"></button>
        <button type="button" class="ql-list" value="ordered" aria-label="Daftar bernomor" title="Daftar bernomor"></button>
        <button type="button" class="ql-list" value="bullet" aria-label="Daftar poin" title="Daftar poin"></button>
        <button type="button" class="ql-blockquote" aria-label="Kutipan" title="Kutipan"></button>
    </span>
    <span class="ql-formats">
        <button type="button" class="ql-undo" aria-label="Urungkan" title="Urungkan">↶</button>
        <button type="button" class="ql-redo" aria-label="Ulangi" title="Ulangi">↷</button>
        <button type="button" class="ql-clean" aria-label="Hapus format" title="Hapus format"></button>
    </span>
`;

const plainToHtml = (value) => {
    const paragraph = document.createElement('p');
    paragraph.textContent = value;
    return paragraph.outerHTML.replace(/\r?\n/g, '<br>');
};

const plainText = (html) => {
    const documentBody = new DOMParser().parseFromString(html, 'text/html').body;
    documentBody.querySelectorAll('br').forEach((node) => node.replaceWith('\n'));
    documentBody.querySelectorAll('p, div, li, blockquote').forEach((node) => node.append('\n'));
    return documentBody.textContent.replace(/\u00a0/g, ' ').trim();
};

const reportError = (field, message) => {
    const error = field.closest('[data-question-editor-field]').querySelector('[data-editor-error]');
    error.textContent = message;
    error.hidden = !message;
    editors.get(field)?.root.setAttribute('aria-invalid', message ? 'true' : 'false');
};

const initializeEditor = (field) => {
    const wrapper = field.closest('[data-question-editor-field]');
    const toolbar = document.createElement('div');
    toolbar.innerHTML = toolbarMarkup;
    const surface = document.createElement('div');
    wrapper.insertBefore(toolbar, field);
    wrapper.insertBefore(surface, field);

    const editor = new Quill(surface, {
        theme: 'snow',
        formats,
        placeholder: field.placeholder,
        modules: {
            toolbar: {
                container: toolbar,
                handlers: {
                    undo() { this.quill.history.undo(); },
                    redo() { this.quill.history.redo(); },
                },
            },
            history: {userOnly: true},
        },
    });
    if (field.dataset.textFormat === 'html') {
        editor.setContents(editor.clipboard.convert({html: field.value}), 'silent');
    } else {
        editor.setText(field.value, 'silent');
    }
    editor.history.clear();
    editor.root.setAttribute('role', 'textbox');
    editor.root.setAttribute('aria-multiline', 'true');
    editor.root.setAttribute('aria-label', field.getAttribute('aria-label'));
    editor.root.setAttribute('aria-required', String(field.required));
    field.dataset.editorRequired = String(field.required);
    field.dataset.editorLimit = String(field.maxLength);
    field.dataset.editorInitialized = 'true';
    field.required = false;
    field.removeAttribute('maxlength');
    field.hidden = true;
    wrapper.classList.add('question-editor-enhanced');
    editors.set(field, editor);

    const syncDisabled = () => {
        const disabled = field.matches(':disabled');
        editor.enable(!disabled);
        toolbar.querySelectorAll('button').forEach((button) => { button.disabled = disabled; });
        editor.root.setAttribute('aria-disabled', String(disabled));
    };
    new MutationObserver(syncDisabled).observe(field, {attributes: true, attributeFilter: ['disabled']});
    editor.on('text-change', () => reportError(field, ''));
    syncDisabled();
};

const initializeVisibleEditors = () => {
    document.querySelectorAll('[data-question-editor]').forEach((field) => {
        if (!editors.has(field) && !field.matches(':disabled') && field.getClientRects().length > 0) {
            initializeEditor(field);
        }
    });
};

const prepareSubmission = (event) => {
    const form = event.target;
    const fields = [...form.querySelectorAll('[data-question-editor]')].filter((field) => !field.matches(':disabled'));
    if (fields.length === 0) return;

    let firstInvalid = null;
    const prepared = fields.map((field) => {
        const editor = editors.get(field);
        const html = editor
            ? editor.getSemanticHTML().replaceAll('&nbsp;', ' ')
            : (field.dataset.textFormat === 'html' ? field.value : plainToHtml(field.value));
        const text = editor ? editor.getText().replace(/\n$/, '').trim() : plainText(html);
        const limit = Number(field.dataset.editorLimit ?? field.maxLength);
        const required = editor ? field.dataset.editorRequired === 'true' : field.required;
        let message = '';
        if (required && !text) message = 'Kolom ini wajib diisi.';
        if ([...text].length > limit) message = 'Teks maksimal ' + limit + ' karakter.';
        if (html.length > 20000) message = 'Format teks terlalu panjang. Gunakan tombol Hapus format untuk merapikannya.';
        reportError(field, message);
        if (message && !firstInvalid) firstInvalid = field;

        return {field, value: text ? html : ''};
    });

    if (firstInvalid) {
        event.preventDefault();
        const editor = editors.get(firstInvalid);
        if (editor) editor.focus();
        else firstInvalid.focus();
        firstInvalid.closest('[data-question-editor-field]').scrollIntoView({block: 'center'});
        return;
    }

    prepared.forEach(({field, value}) => {
        field.value = value;
        field.dataset.textFormat = 'html';
    });
    form.querySelector('[name="text_format"]').value = 'html';
};

export function initializeQuestionEditors() {
    initializeVisibleEditors();
    document.addEventListener('submit', prepareSubmission, true);
    document.addEventListener('change', (event) => {
        if (event.target.matches('#question-type, .edit-question-type')) initializeVisibleEditors();
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-edit-toggle]')) initializeVisibleEditors();
    });
}
