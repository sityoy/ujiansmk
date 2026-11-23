(() => {
    const key = 'ujiansmk-theme';
    const root = document.documentElement;
    const fallback = root.dataset.theme === 'light' ? 'light' : 'dark';
    const system = window.matchMedia?.('(prefers-color-scheme: dark)');
    const labels = { system: 'Sistem', light: 'Terang', dark: 'Gelap' };
    const valid = value => Object.prototype.hasOwnProperty.call(labels, value);
    let preferred = 'system';

    const readPreference = () => {
        try {
            const value = localStorage.getItem(key);
            return valid(value) ? value : 'system';
        } catch (_) {
            return 'system';
        }
    };

    const apply = value => {
        preferred = value;
        root.dataset.theme = value === 'system' ? (system ? (system.matches ? 'dark' : 'light') : fallback) : value;
        document.querySelectorAll('[data-theme-label]').forEach(label => { label.textContent = labels[value]; });
        document.querySelectorAll('[data-theme-choice]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.themeChoice === value));
        });
    };

    apply(readPreference());
    document.addEventListener('DOMContentLoaded', () => apply(preferred));

    document.addEventListener('click', event => {
        const choice = event.target.closest('[data-theme-choice]');
        if (choice) {
            const value = choice.dataset.themeChoice;
            if (!valid(value)) return;
            apply(value);
            try { localStorage.setItem(key, value); } catch (_) { /* Keep the selection for this page. */ }
            const picker = choice.closest('[data-theme-picker]');
            picker.open = false;
            picker.querySelector('summary').focus({ preventScroll: true });
        }
        document.querySelectorAll('[data-theme-picker][open]').forEach(picker => {
            if (!picker.contains(event.target)) picker.open = false;
        });
    });

    document.addEventListener('keydown', event => {
        const picker = event.target.closest('[data-theme-picker][open]');
        if (event.key === 'Escape' && picker) {
            event.preventDefault();
            picker.open = false;
            picker.querySelector('summary').focus();
        }
    });

    const followSystem = () => { if (preferred === 'system') apply(preferred); };
    if (system?.addEventListener) system.addEventListener('change', followSystem);
    else system?.addListener?.(followSystem);

    window.addEventListener('storage', event => {
        if (event.key === key || event.key === null) apply(readPreference());
    });
})();
