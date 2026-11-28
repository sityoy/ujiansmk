(() => {
    const controls = document.querySelector('[data-exam-text-controls]');
    const content = document.getElementById('exam-content');
    if (!controls || !content) return;

    const key = 'ujiansmk-exam-text-size';
    const sizes = [90, 100, 115, 130, 150, 175, 200];
    const normal = sizes.indexOf(100);
    const decrease = controls.querySelector('[data-exam-text-decrease]');
    const increase = controls.querySelector('[data-exam-text-increase]');
    const reset = controls.querySelector('[data-exam-text-reset]');
    const percentage = controls.querySelector('[data-exam-text-scale]');
    let index = normal;

    try {
        const saved = sizes.indexOf(Number(localStorage.getItem(key)));
        if (saved !== -1) index = saved;
    } catch (_) { /* The default is available without storage. */ }

    const update = () => {
        const size = sizes[index];
        content.style.setProperty('--exam-text-scale', String(size / 100));
        percentage.textContent = `${size}%`;
        decrease.disabled = index === 0;
        increase.disabled = index === sizes.length - 1;
        reset.disabled = index === normal;
    };

    const select = next => {
        if (next < 0 || next >= sizes.length || content.hidden || content.disabled) return;
        index = next;
        update();
        try { localStorage.setItem(key, String(sizes[index])); } catch (_) { /* Keep this page usable. */ }
    };

    decrease.addEventListener('click', () => select(index - 1));
    increase.addEventListener('click', () => select(index + 1));
    reset.addEventListener('click', () => select(normal));
    update();
})();
