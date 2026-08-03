(() => {
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;
        const modal = document.querySelector('.reservation-modal');
        if (!modal || getComputedStyle(modal).display === 'none') return;

        const focusable = [...modal.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]')]
            .filter((element) => element.getClientRects().length > 0 && element.getAttribute('tabindex') !== '-1');
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
    });

    const carousel = document.querySelector('[data-gallery-carousel]');
    if (!carousel) return;

    const track = carousel.querySelector('.home-gallery__track');
    const slides = [...track.children];
    const previous = document.querySelector('[data-gallery-prev]');
    const next = document.querySelector('[data-gallery-next]');
    const status = document.querySelector('[data-gallery-status]');
    let current = 0;

    const show = (index) => {
        current = Math.max(0, Math.min(index, slides.length - 1));
        track.style.transform = `translateX(-${current * 100}%)`;
        status.textContent = `${current + 1} / ${slides.length}`;
        previous.disabled = current === 0;
        next.disabled = current === slides.length - 1;
    };

    previous.addEventListener('click', () => show(current - 1));
    next.addEventListener('click', () => show(current + 1));
    carousel.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') show(current - 1);
        if (event.key === 'ArrowRight') show(current + 1);
    });
    show(0);
})();
