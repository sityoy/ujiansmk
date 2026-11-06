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
