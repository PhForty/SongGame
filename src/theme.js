/*
 * Theme toggle. The theme itself is already applied by the inline bootstrap in
 * the page head (see partials.php) — this only handles the button and keeps the
 * choice in sync across open tabs.
 */
(function () {
    'use strict';

    var root = document.documentElement;

    function current() {
        return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function apply(theme, persist) {
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = theme;
        if (persist) {
            try { localStorage.setItem('theme', theme); } catch (e) {}
        }
        var btn = document.getElementById('themeToggle');
        if (btn) {
            btn.textContent = theme === 'dark' ? '☀️' : '🌙';
            btn.setAttribute('aria-label', theme === 'dark' ? 'Light mode' : 'Dark mode');
            btn.title = btn.getAttribute('aria-label');
        }
    }

    apply(current(), false);

    // Enable the colour transition only after the first paint.
    requestAnimationFrame(function () { document.body.classList.add('theme-ready'); });

    var btn = document.getElementById('themeToggle');
    if (btn) {
        btn.addEventListener('click', function () {
            apply(current() === 'dark' ? 'light' : 'dark', true);
        });
    }

    window.addEventListener('storage', function (ev) {
        if (ev.key === 'theme' && (ev.newValue === 'dark' || ev.newValue === 'light')) {
            apply(ev.newValue, false);
        }
    });
})();
