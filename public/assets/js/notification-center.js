(function () {
    if (window.AppNotificationSound) return;

    var audioContext = null;
    var unlocked = false;
    var toastTimer = null;

    function context() {
        if (!audioContext) {
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) audioContext = new AudioContext();
        }
        return audioContext;
    }

    function unlock() {
        var ctx = context();
        if (!ctx) return;
        if (ctx.state === 'suspended') ctx.resume();
        unlocked = true;
    }

    function tone(frequency, start, duration, gainValue) {
        var ctx = context();
        if (!ctx || !unlocked) return;
        var oscillator = ctx.createOscillator();
        var gain = ctx.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.001, ctx.currentTime + start);
        gain.gain.exponentialRampToValueAtTime(Math.max(0.001, gainValue), ctx.currentTime + start + 0.025);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + start + duration);
        oscillator.connect(gain);
        gain.connect(ctx.destination);
        oscillator.start(ctx.currentTime + start);
        oscillator.stop(ctx.currentTime + start + duration + 0.03);
    }

    function play(kind, volume) {
        var gain = Math.max(0, Math.min(1, Number(volume || 65) / 100)) * 0.18;
        var patterns = {
            order: [[660, 0, .16], [880, .17, .2]],
            ready: [[740, 0, .14], [988, .14, .14], [1174, .28, .22]],
            delivery: [[523, 0, .14], [784, .16, .24]],
            success: [[659, 0, .12], [988, .13, .2]],
            alert: [[440, 0, .18], [330, .2, .24]]
        };
        (patterns[kind] || patterns.order).forEach(function (part) { tone(part[0], part[1], part[2], gain); });
    }

    function toast(detail) {
        var node = document.getElementById('app-notification-toast');
        if (!node) {
            node = document.createElement('div');
            node.id = 'app-notification-toast';
            node.className = 'app-notification-toast';
            node.setAttribute('role', 'status');
            node.setAttribute('aria-live', 'polite');
            document.body.appendChild(node);
        }
        node.innerHTML = '<span class="app-notification-toast__icon"><i class="bx bx-bell"></i></span>' +
            '<span><strong></strong><small></small></span>' +
            '<button type="button" aria-label="Cerrar"><i class="bx bx-x"></i></button>';
        node.querySelector('strong').textContent = detail.title || 'Nueva notificación';
        node.querySelector('small').textContent = detail.message || '';
        node.querySelector('button').onclick = function () { node.classList.remove('is-visible'); };
        requestAnimationFrame(function () { node.classList.add('is-visible'); });
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { node.classList.remove('is-visible'); }, 6500);
    }

    window.AppNotificationSound = { unlock: unlock, play: play };
    ['pointerdown', 'keydown'].forEach(function (eventName) {
        document.addEventListener(eventName, unlock, { once: true, passive: true });
    });

    window.addEventListener('app-notifications-new', function (event) {
        var detail = event.detail || {};
        toast(detail);
        if (detail.playSound) play(detail.sound, detail.volume);
    });

    document.addEventListener('livewire:navigated', function () {
        if (window.Livewire) window.Livewire.dispatch('notifications-check');
    });
})();
