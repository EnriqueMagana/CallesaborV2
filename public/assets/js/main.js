/**
 * Main
 * Modified for Livewire wire:navigate compatibility:
 * - Uses window globals instead of let (prevents redeclaration on re-run)
 * - Uses event delegation for menu toggle (survives Livewire DOM morphing)
 * - Reinitializes PerfectScrollbar after wire:navigate (morphdom deletes PS DOM nodes)
 */

'use strict';

// Window globals prevent "redeclaration of let" when script re-runs on wire:navigate
window._sneatMenu = window._sneatMenu || null;
window._sneatAnimate = window._sneatAnimate || false;

// Parent modules are controlled through delegation so they keep working after
// Livewire replaces the sidebar DOM during wire:navigate transitions.
if (!window._sidebarParentToggleBound) {
  window._sidebarParentToggleBound = true;
  document.addEventListener('click', function (event) {
    const toggle = event.target.closest('#layout-menu .sidebar-parent-toggle');
    if (!toggle) return;

    event.preventDefault();
    event.stopPropagation();

    const item = toggle.closest('.menu-item');
    if (!item || !item.querySelector(':scope > .menu-sub')) return;

    const willOpen = !item.classList.contains('open');
    const siblings = item.parentElement
      ? item.parentElement.querySelectorAll(':scope > .menu-item.open')
      : [];

    if (willOpen) {
      siblings.forEach(function (sibling) {
        if (sibling === item) return;
        sibling.classList.remove('open', 'menu-item-animating', 'menu-item-closing');
        sibling.style.removeProperty('height');
        sibling.style.removeProperty('overflow');
        const siblingToggle = sibling.querySelector(':scope > .sidebar-parent-toggle');
        if (siblingToggle) siblingToggle.setAttribute('aria-expanded', 'false');
      });
    }

    item.classList.remove('menu-item-animating', 'menu-item-closing');
    item.classList.toggle('open', willOpen);
    item.style.removeProperty('height');
    item.style.removeProperty('overflow');
    toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

    if (window._sneatMenu && typeof window._sneatMenu.update === 'function') {
      window._sneatMenu.update();
    }
  }, true);
}

(function () {
  const hasLayoutMenuInner = Boolean(document.querySelector('#layout-menu .menu-inner'));

  const menuConstructor = window.Menu || (typeof Menu !== 'undefined' ? Menu : null);

  if (menuConstructor && menuConstructor.prototype && !menuConstructor._safeManageScrollPatched) {
    const originalManageScroll = menuConstructor.prototype.manageScroll;
    menuConstructor.prototype.manageScroll = function () {
      const menuInner = document.querySelector('.menu-inner');
      if (!menuInner) {
        if (this._scrollbar) {
          try { this._scrollbar.destroy(); } catch (e) {}
          this._scrollbar = null;
        }
        return;
      }

      return originalManageScroll.apply(this, arguments);
    };
    menuConstructor._safeManageScrollPatched = true;
  }

  if (!hasLayoutMenuInner && window._sneatMenu && typeof window._sneatMenu.destroy === 'function') {
    try { window._sneatMenu.destroy(); } catch (e) {}
    window._sneatMenu = null;
    if (window.Helpers) window.Helpers.mainMenu = null;
  }

  // Initialize menu
  //-----------------
  let layoutMenuEl = document.querySelectorAll('#layout-menu');
  layoutMenuEl.forEach(function (element) {
    if (!element.querySelector('.menu-inner')) return;

    window._sneatMenu = new Menu(element, {
      orientation: 'vertical',
      closeChildren: false
    });
    window.Helpers.scrollToActive((window._sneatAnimate = false));
    window.Helpers.mainMenu = window._sneatMenu;
  });

  // Menu toggle — use event delegation so it survives Livewire DOM morphing.
  // Guard prevents duplicate listeners across wire:navigate re-runs.
  if (!window._sneatToggleBound) {
    window._sneatToggleBound = true;
    document.addEventListener('click', function (event) {
      const toggle = event.target.closest('.layout-menu-toggle');
      if (toggle && window.Helpers) {
        event.preventDefault();
        window.Helpers.toggleCollapsed();
      }
    });
  }

  // Display menu toggle button on hover (desktop only)
  if (document.getElementById('layout-menu')) {
    let hoverTimeout = null;
    const layoutMenuEl2 = document.getElementById('layout-menu');

    layoutMenuEl2.onmouseenter = function () {
      if (!Helpers.isSmallScreen()) {
        hoverTimeout = setTimeout(function () {
          const t = document.querySelector('.layout-menu-toggle');
          if (t) t.classList.add('d-block');
        }, 300);
      }
    };
    layoutMenuEl2.onmouseleave = function () {
      const t = document.querySelector('.layout-menu-toggle');
      if (t) t.classList.remove('d-block');
      clearTimeout(hoverTimeout);
    };
  }

  // Scroll shadow on menu inner
  let menuInnerContainer = document.getElementsByClassName('menu-inner'),
    menuInnerShadow = document.getElementsByClassName('menu-inner-shadow')[0];
  if (menuInnerContainer.length > 0 && menuInnerShadow) {
    menuInnerContainer[0].addEventListener('ps-scroll-y', function () {
      if (this.querySelector('.ps__thumb-y').offsetTop) {
        menuInnerShadow.style.display = 'block';
      } else {
        menuInnerShadow.style.display = 'none';
      }
    });
  }

  // Bootstrap Tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Accordion active class
  const accordionActiveFunction = function (e) {
    if (e.type === 'show.bs.collapse') {
      e.target.closest('.accordion-item').classList.add('active');
    } else {
      e.target.closest('.accordion-item').classList.remove('active');
    }
  };
  const accordionTriggerList = [].slice.call(document.querySelectorAll('.accordion'));
  accordionTriggerList.forEach(function (accordionTriggerEl) {
    accordionTriggerEl.addEventListener('show.bs.collapse', accordionActiveFunction);
    accordionTriggerEl.addEventListener('hide.bs.collapse', accordionActiveFunction);
  });

  // Helpers
  window.Helpers.setAutoUpdate(true);
  window.Helpers.initPasswordToggle();
  window.Helpers.initSpeechToText();

  if (!hasLayoutMenuInner) {
    return;
  }

  if (window.Helpers.isSmallScreen()) {
    return;
  }

  window.Helpers.setCollapsed(true, false);
})();

// ── PerfectScrollbar reinit after wire:navigate ────────────────────────────
// When Livewire navigates via wire:navigate it morphs the sidebar DOM, which
// deletes the .ps__rail-y / .ps__thumb-y nodes that PS injected. Without them
// the sidebar loses its scrollbar on every navigation. We destroy the stale
// instance and create a fresh one each time navigation completes.
window._clearNavigationUiLocks = function () {
  document.documentElement.classList.remove('overflow-y-hidden');
  document.body.classList.remove('overflow-y-hidden', 'modal-open');
  document.documentElement.style.removeProperty('overflow');
  document.body.style.removeProperty('overflow');
  document.body.style.removeProperty('padding-right');

  document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach(function (backdrop) {
    backdrop.remove();
  });
};

window._destroySneatMenu = function () {
  if (window._sneatMenu && typeof window._sneatMenu.destroy === 'function') {
    try { window._sneatMenu.destroy(); } catch (e) {}
  }

  window._sneatMenu = null;
  if (window.Helpers) {
    window.Helpers.mainMenu = null;
    window.Helpers.menuPsScroll = null;
  }
};

window._initializeSneatMenu = function () {
  const menuElement = document.getElementById('layout-menu');
  const menuInner = menuElement ? menuElement.querySelector('.menu-inner') : null;
  const menuConstructor = window.Menu || (typeof Menu !== 'undefined' ? Menu : null);

  if (!menuElement || !menuInner || !menuConstructor) return;

  window._destroySneatMenu();
  window._sneatMenu = new menuConstructor(menuElement, {
    orientation: 'vertical',
    closeChildren: false
  });

  if (window.Helpers) {
    window.Helpers.mainMenu = window._sneatMenu;
    window.Helpers.scrollToActive(false);
  }
};

if (!window._sneatNavBound) {
  window._sneatNavBound = true;

  document.addEventListener('livewire:navigating', function () {
    window._clearNavigationUiLocks();
    window._destroySneatMenu();
  });

  document.addEventListener('livewire:navigated', function () {
    window._clearNavigationUiLocks();
    window._initializeSneatMenu();
  });
}
