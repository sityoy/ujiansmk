const themeStorageKey = 'ujiansmk-theme';

const currentTheme = () => document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';

const applyTheme = (theme, persist = true) => {
    const selectedTheme = theme === 'light' ? 'light' : 'dark';
    document.documentElement.dataset.theme = selectedTheme;

    if (persist) {
        try {
            localStorage.setItem(themeStorageKey, selectedTheme);
        } catch (error) {
            // Theme still applies for the current page if storage is unavailable.
        }
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const targetTheme = selectedTheme === 'light' ? 'gelap' : 'terang';
        const icon = button.querySelector('[data-theme-icon]');
        const label = button.querySelector('[data-theme-label]');

        if (icon) icon.textContent = selectedTheme === 'light' ? '☾' : '☀';
        if (label) label.textContent = selectedTheme === 'light' ? 'Gelap' : 'Terang';
        button.setAttribute('aria-label', 'Gunakan tema ' + targetTheme);
        button.setAttribute('title', 'Gunakan tema ' + targetTheme);
    });
};

const initializeThemeToggle = () => {
    applyTheme(currentTheme(), false);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            applyTheme(currentTheme() === 'light' ? 'dark' : 'light');
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeThemeToggle);
} else {
    initializeThemeToggle();
}

window.addEventListener('storage', (event) => {
    if (event.key === themeStorageKey && (event.newValue === 'light' || event.newValue === 'dark')) {
        applyTheme(event.newValue, false);
    }
});


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
