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

(function () {
  // Initialize menu
  //-----------------
  let layoutMenuEl = document.querySelectorAll('#layout-menu');
  layoutMenuEl.forEach(function (element) {
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
if (!window._sneatNavBound) {
  window._sneatNavBound = true;

  document.addEventListener('livewire:navigated', function () {
    const menuInner = document.querySelector('#layout-menu .menu-inner');
    if (!menuInner || !window.PerfectScrollbar) return;

    // Destroy stale PS instance (removes its DOM nodes and event listeners)
    if (window._sneatMenu && window._sneatMenu._scrollbar) {
      try { window._sneatMenu._scrollbar.destroy(); } catch (e) {}
      window._sneatMenu._scrollbar = null;
    }

    // Re-create PS on the (now clean) menu-inner element
    window._sneatMenu._scrollbar = new PerfectScrollbar(menuInner, {
      suppressScrollX: true,
      wheelPropagation: false
    });

    // Scroll sidebar to show the newly active menu item
    if (window.Helpers) {
      window.Helpers.scrollToActive(false);
    }
  });
}
