import { initializeStudentSelection } from './student-selection';

const sidebarStorageKey = 'ujiansmk-sidebar-collapsed';

const initializeSidebar = () => {
    const root = document.documentElement;
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.querySelector('[data-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const closeButton = document.querySelector('[data-sidebar-close]');
    const mobileQuery = window.matchMedia('(max-width: 1023px)');

    if (!toggle || !sidebar) return;

    const updateToggleState = () => {
        const expanded = mobileQuery.matches
            ? root.classList.contains('sidebar-open')
            : !root.classList.contains('sidebar-collapsed');

        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };

    const closeMobileSidebar = () => {
        root.classList.remove('sidebar-open');
        updateToggleState();
    };

    toggle.addEventListener('click', () => {
        if (mobileQuery.matches) {
            root.classList.toggle('sidebar-open');
        } else {
            root.classList.toggle('sidebar-collapsed');

            try {
                localStorage.setItem(
                    sidebarStorageKey,
                    root.classList.contains('sidebar-collapsed') ? '1' : '0',
                );
            } catch (error) {
                // The sidebar still works for the current page.
            }
        }

        updateToggleState();
    });

    closeButton?.addEventListener('click', closeMobileSidebar);
    overlay?.addEventListener('click', closeMobileSidebar);

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileQuery.matches) closeMobileSidebar();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMobileSidebar();
    });

    mobileQuery.addEventListener('change', () => {
        root.classList.remove('sidebar-open');
        updateToggleState();
    });

    updateToggleState();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeSidebar);
} else {
    initializeSidebar();
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle]');
    if (!button) return;

    const input = button.closest('[data-password-field]').querySelector('input');
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.querySelector('[data-password-show]').toggleAttribute('hidden', visible);
    button.querySelector('[data-password-hide]').toggleAttribute('hidden', !visible);
    const label = visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';
    button.setAttribute('aria-label', label);
    button.title = label;
});

if (document.querySelector('[data-question-editor]')) {
    import('./question-editor').then(({initializeQuestionEditors}) => initializeQuestionEditors());
}

initializeStudentSelection();
