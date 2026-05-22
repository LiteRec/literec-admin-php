/*
 * Application entry point, loaded onto every page via the importmap()
 * Twig function in templates/base.html.twig.
 */
import './styles/app.css';
import Alpine from 'alpinejs';
import htmx from 'htmx.org';
import gsap from 'gsap';

// Alpine.js — lightweight reactive UI components driven by x-* attributes.
window.Alpine = Alpine;
Alpine.start();

// HTMX — expose globally so hx-* attributes are processed.
window.htmx = htmx;

// GSAP — expose globally and apply a subtle entrance animation to any
// element marked with data-gsap="card".
window.gsap = gsap;

function animateCards() {
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
