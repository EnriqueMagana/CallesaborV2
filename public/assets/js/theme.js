(function () {
  'use strict';

  const storageKey = 'callesabor-color-theme';
  const root = document.documentElement;

  function storedTheme() {
    try {
      const value = window.localStorage.getItem(storageKey);
      return value === 'dark' || value === 'light' ? value : null;
    } catch (error) {
      return null;
    }
  }

  function preferredTheme() {
    const saved = storedTheme();
    if (saved) return saved;

    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'dark'
      : 'light';
  }

  function syncControls() {
    const isDark = root.classList.contains('dark-style');

    document.querySelectorAll('[data-theme-toggle]').forEach(function (control) {
      const icon = control.querySelector('[data-theme-toggle-icon]');
      const label = control.querySelector('[data-theme-toggle-label]');
      const actionLabel = isDark ? 'Modo claro' : 'Modo oscuro';

      control.setAttribute('aria-label', 'Cambiar a ' + actionLabel.toLowerCase());
      control.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      control.setAttribute('title', actionLabel);

      if (label) label.textContent = actionLabel;
      if (icon) {
        icon.classList.toggle('bx-moon', !isDark);
        icon.classList.toggle('bx-sun', isDark);
      }
    });
  }

  function applyTheme(theme, persist) {
    const isDark = theme === 'dark';

    root.classList.toggle('dark-style', isDark);
    root.classList.toggle('light-style', !isDark);
    root.setAttribute('data-app-theme', isDark ? 'dark' : 'light');
    root.style.colorScheme = isDark ? 'dark' : 'light';

    if (persist) {
      try {
        window.localStorage.setItem(storageKey, isDark ? 'dark' : 'light');
      } catch (error) {
        // The theme still works when storage is unavailable.
      }
    }

    syncControls();
    window.dispatchEvent(new CustomEvent('app:theme-changed', {
      detail: { theme: isDark ? 'dark' : 'light' }
    }));
  }

  applyTheme(preferredTheme(), false);

  if (!window.__appThemeToggleBound) {
    window.__appThemeToggleBound = true;

    document.addEventListener('click', function (event) {
      const control = event.target.closest('[data-theme-toggle]');
      if (!control) return;

      event.preventDefault();
      applyTheme(root.classList.contains('dark-style') ? 'light' : 'dark', true);
    });

    document.addEventListener('DOMContentLoaded', syncControls);
    document.addEventListener('livewire:navigated', syncControls);

    window.addEventListener('storage', function (event) {
      if (event.key === storageKey && (event.newValue === 'dark' || event.newValue === 'light')) {
        applyTheme(event.newValue, false);
      }
    });
  }

  window.appTheme = {
    apply: function (theme) {
      if (theme === 'dark' || theme === 'light') applyTheme(theme, true);
    },
    current: function () {
      return root.classList.contains('dark-style') ? 'dark' : 'light';
    }
  };
})();
