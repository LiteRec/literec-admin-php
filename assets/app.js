/*
 * Application entry point, loaded onto every page via the importmap()
 * Twig function in templates/base.html.twig.
 */
import './styles/app.css';
import Alpine from 'alpinejs';
import htmx from 'htmx.org';
import gsap from 'gsap';

// Alpine.js — lightweight reactive UI components driven by x-* attributes.
globalThis.Alpine = Alpine;
Alpine.start();

// HTMX — expose globally so hx-* attributes are processed, and surface
// request failures for the health-check target.
globalThis.htmx = htmx;

// Forward Symfony's CSRF token on every non-GET HTMX request. The token is
// rendered into a <meta name="csrf-token"> tag by base.html.twig under the
// `htmx` intent; Symfony validates it server-side.
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

document.body.addEventListener('htmx:configRequest', (event) => {
    const verb = (event.detail.verb || 'GET').toUpperCase();

    if (SAFE_METHODS.has(verb)) {
        return;
    }

    const meta = document.querySelector('meta[name="csrf-token"]');
    const token = meta?.getAttribute('content') ?? null;

    if (token) {
        event.detail.headers['X-CSRF-TOKEN'] = token;
    }
});

document.body.addEventListener('htmx:afterRequest', (event) => {
    const target = event.detail.target;

    if (event.detail.failed && target && target.id === 'health-result') {
        target.textContent = 'Unable to reach the health endpoint.';
    }
});

// GSAP — expose globally and apply a subtle entrance animation to any
// element marked with data-gsap="card".
globalThis.gsap = gsap;

function animateCards() {
    const prefersReducedMotion = globalThis.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReducedMotion) {
        return;
    }

    const cards = document.querySelectorAll('[data-gsap="card"]');

    if (cards.length > 0) {
        gsap.from(cards, {
            opacity: 0,
            y: 16,
            duration: 0.4,
            ease: 'power2.out',
        });
    }
}

// app.js is a deferred module, so DOMContentLoaded may already have fired.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', animateCards);
} else {
    animateCards();
}
