/*
 * Application entry point, loaded onto every page via the importmap()
 * Twig function in templates/base.html.twig.
 */
import './styles/app.css';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import htmx from 'htmx.org';
import gsap from 'gsap';

// Alpine.js — lightweight reactive UI components driven by x-* attributes.
// The focus plugin powers the $focus magic used by the main-nav dropdowns and
// the x-trap directive used by the header account menu (focus the first item on
// open, restore focus to the trigger on close).
globalThis.Alpine = Alpine;
Alpine.plugin(focus);
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

    if (event.detail.failed && target?.id === 'health-result') {
        target.textContent = 'Unable to reach the health endpoint.';
    }
});

// Accessible status announcements (LRA-153). HTMX success responses carry
// HX-Trigger events that bubble to the body; the ones that represent a
// completed in-place action are mapped to a short message and written to the
// shared #lr-live polite region so screen-reader users hear the outcome
// without a focus change. Events that carry a payload (memberLoaded) are
// handled separately so the announcement can name the selected row.
const LIVE_REGION_MESSAGES = {
    inventoryItemSaved: 'Inventory item saved.',
    stockReceived: 'Stock received.',
    stockAdjusted: 'Stock adjusted.',
    comboSaved: 'Combo saved.',
    groupSaved: 'Group saved.',
    linkSaved: 'Link saved.',
    poSent: 'Purchase order marked as sent.',
    poLineReceived: 'Purchase order line received.',
    poVerified: 'Purchase order delivery verified.',
    profileSaved: 'Profile saved.',
};

function announceStatus(message) {
    const region = document.getElementById('lr-live');

    if (!region || !message) {
        return;
    }

    // Clear first so the region is seen to change even when the same message
    // repeats; assistive tech only announces an actual content change.
    region.textContent = '';
    globalThis.requestAnimationFrame(() => {
        region.textContent = message;
    });
}

Object.entries(LIVE_REGION_MESSAGES).forEach(([eventName, message]) => {
    document.body.addEventListener(eventName, () => announceStatus(message));
});

document.body.addEventListener('memberLoaded', (event) => {
    const name = event.detail?.name;
    announceStatus(name ? `Showing ${name}.` : 'Member updated.');
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
