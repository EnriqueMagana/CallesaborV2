(() => {
    'use strict';

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const trapReservationFocus = (event) => {
        if (event.key !== 'Tab') return;

        const modal = document.querySelector('.reservation-modal');
        if (!modal || getComputedStyle(modal).display === 'none') return;

        const focusable = [...modal.querySelectorAll(
            'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]'
        )].filter((element) => element.getClientRects().length > 0 && element.getAttribute('tabindex') !== '-1');

        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    const initializeHeroCarousel = () => {
        const hero = document.querySelector('[data-home-hero]');
        if (!hero) return;

        const slides = [...hero.querySelectorAll('[data-home-hero-slide]')];
        let current = 0;
        let timer = null;
        let interactionPaused = false;

        if (slides.length < 2) {
            return;
        }

        const show = (index) => {
            current = (index + slides.length) % slides.length;

            slides.forEach((slide, slideIndex) => {
                const isActive = slideIndex === current;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });
        };

        const stopTimer = () => {
            window.clearTimeout(timer);
            timer = null;
        };

        const schedule = () => {
            stopTimer();
            if (reducedMotion || interactionPaused || document.hidden) return;

            timer = window.setTimeout(() => {
                show(current + 1);
                schedule();
            }, 6500);
        };
        hero.addEventListener('pointerenter', () => {
            interactionPaused = true;
            stopTimer();
        });
        hero.addEventListener('pointerleave', () => {
            interactionPaused = false;
            schedule();
        });
        hero.addEventListener('focusin', () => {
            interactionPaused = true;
            stopTimer();
        });
        hero.addEventListener('focusout', (event) => {
            if (hero.contains(event.relatedTarget)) return;
            interactionPaused = false;
            schedule();
        });
        document.addEventListener('visibilitychange', schedule);

        show(0);
        schedule();
    };

    const initializeGalleryCarousel = () => {
        const carousel = document.querySelector('[data-gallery-carousel]');
        if (!carousel) return;

        const track = carousel.querySelector('.home-gallery__track');
        const slides = [...track.children];
        const previous = document.querySelector('[data-gallery-prev]');
        const next = document.querySelector('[data-gallery-next]');
        const status = document.querySelector('[data-gallery-status]');
        let current = 0;
        let timer = null;

        const updateStatus = () => {
            if (status) status.textContent = `${current + 1} / ${slides.length}`;
            if (previous) previous.disabled = current === 0;
            if (next) next.disabled = current === slides.length - 1;
        };

        const show = (index) => {
            current = Math.max(0, Math.min(index, slides.length - 1));
            track.style.transform = `translateX(-${current * 100}%)`;
            updateStatus();
        };

        const stop = () => window.clearInterval(timer);
        const rotate = () => {
            stop();
            if (reducedMotion || slides.length < 2) return;
            timer = window.setInterval(() => show(current + 1 === slides.length ? 0 : current + 1), 7000);
        };

        previous?.addEventListener('click', () => {
            show(current - 1);
            rotate();
        });
        next?.addEventListener('click', () => {
            show(current + 1);
            rotate();
        });

        carousel.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                show(current - 1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                show(current + 1);
            }
        });

        carousel.addEventListener('pointerenter', stop);
        carousel.addEventListener('pointerleave', rotate);
        carousel.addEventListener('focusin', stop);
        carousel.addEventListener('focusout', (event) => {
            if (!carousel.contains(event.relatedTarget)) rotate();
        });

        show(0);
        rotate();
    };

    const initializeRevealMotion = () => {
        const sections = [...document.querySelectorAll('[data-home-reveal]')];
        if (!sections.length || reducedMotion || !('IntersectionObserver' in window)) {
            sections.forEach((section) => section.classList.add('is-visible'));
            return;
        }

        document.body.classList.add('home-motion-ready');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });

        sections.forEach((section) => observer.observe(section));
    };

    document.addEventListener('keydown', trapReservationFocus);
    initializeHeroCarousel();
    initializeGalleryCarousel();
    initializeRevealMotion();
})();
