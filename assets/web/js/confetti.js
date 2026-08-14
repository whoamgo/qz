/**
 * confetti.js — a tiny, self-contained celebration effect.
 *
 * No external library and no network calls: it draws firework-style bursts on
 * a throwaway <canvas> laid over the page, then removes itself once every
 * particle has fallen. Honours prefers-reduced-motion.
 *
 * Usage:  window.WConfetti.celebrate();
 */
(function (window, document) {
    'use strict';

    var COLORS = ['#f59e0b', '#fbbf24', '#dc2626', '#16a34a', '#2563eb', '#ec4899', '#8b5cf6', '#ffffff'];

    function rand(a, b) { return a + Math.random() * (b - a); }

    function celebrate(opts) {
        opts = opts || {};

        // Respect users who asked for reduced motion — no decorative movement.
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

        var duration = opts.duration || 4200;
        var dpr = window.devicePixelRatio || 1;

        var canvas = document.createElement('canvas');
        canvas.setAttribute('aria-hidden', 'true');
        canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:2000';
        document.body.appendChild(canvas);
        var ctx = canvas.getContext('2d');

        function W() { return window.innerWidth; }
        function H() { return window.innerHeight; }
        function resize() {
            canvas.width = W() * dpr; canvas.height = H() * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        resize();
        window.addEventListener('resize', resize);

        var particles = [];

        // A firecracker burst: particles shoot outward from (x,y), then gravity
        // pulls them down and they fade out.
        function burst(x, y, count, power) {
            for (var i = 0; i < count; i++) {
                var angle = rand(0, Math.PI * 2);
                var speed = rand(power * 0.35, power);
                particles.push({
                    x: x, y: y,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed - rand(2, 6),
                    g: rand(0.12, 0.22),
                    size: rand(6, 12),
                    color: COLORS[(Math.random() * COLORS.length) | 0],
                    rot: rand(0, Math.PI * 2), vr: rand(-0.2, 0.2),
                    shape: Math.random() < 0.5 ? 'rect' : 'circle',
                    life: 1, decay: rand(0.006, 0.012)
                });
            }
        }

        // Opening double-burst from the bottom corners (the "patake"), plus a
        // pop in the upper middle.
        burst(W() * 0.12, H() * 0.88, 90, 16);
        burst(W() * 0.88, H() * 0.88, 90, 16);
        burst(W() * 0.50, H() * 0.35, 70, 14);

        // A few more random pops over the first ~1.5 seconds.
        var pops = 0;
        var popTimer = setInterval(function () {
            pops++;
            burst(rand(W() * 0.15, W() * 0.85), rand(H() * 0.20, H() * 0.60), 60, rand(12, 18));
            if (pops >= 5) { clearInterval(popTimer); }
        }, 300);

        var start = Date.now();
        (function frame() {
            ctx.clearRect(0, 0, W(), H());

            for (var i = particles.length - 1; i >= 0; i--) {
                var p = particles[i];
                p.vy += p.g; p.x += p.vx; p.y += p.vy; p.vx *= 0.99; p.rot += p.vr;
                p.life -= p.decay;

                if (p.life <= 0 || p.y > H() + 24) { particles.splice(i, 1); continue; }

                ctx.save();
                ctx.globalAlpha = Math.max(0, p.life);
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.fillStyle = p.color;
                if (p.shape === 'rect') {
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                } else {
                    ctx.beginPath();
                    ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2);
                    ctx.fill();
                }
                ctx.restore();
            }

            // Keep animating until the timer is up AND every particle has gone.
            if (Date.now() - start < duration || particles.length) {
                window.requestAnimationFrame(frame);
            } else {
                clearInterval(popTimer);
                window.removeEventListener('resize', resize);
                canvas.remove();
            }
        })();
    }

    window.WConfetti = { celebrate: celebrate };

})(window, document);
