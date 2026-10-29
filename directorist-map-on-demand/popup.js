/* Ticket 7833: resolve one native popup only after the map opens it. */
(function () {
    'use strict';
    const selector = '.gm-style-iw .bbq7833-popup, .leaflet-popup-content .bbq7833-popup';

    async function load(popup) {
        if (popup.dataset.bbq7833Loading) return;
        popup.dataset.bbq7833Loading = '1';
        const fallback = popup.querySelector('a');
        try {
            const url = new URL(window.BBQ7833Popup.endpoint, window.location.href);
            url.searchParams.set('action', 'bbq7833_map_popup');
            url.searchParams.set('listing_id', popup.dataset.bbq7833Id);
            url.searchParams.set('provider', popup.dataset.bbq7833Provider);
            const response = await fetch(url, { credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok || !result.success || !result.data.html) throw new Error('Popup unavailable');
            if (!popup.isConnected) return;
            const template = document.createElement('template');
            template.innerHTML = result.data.html;
            const card = template.content.querySelector('.map-listing-card-single');
            if (!card) throw new Error('Popup unavailable');
            card.classList.add('map-info-wrapper--show');
            card.style.display = 'block';
            card.querySelectorAll('img').forEach(image => {
                image.loading = 'lazy';
                image.decoding = 'async';
            });
            card.dataset.bbq7833Loaded = '1';
            const map = popup.closest('[data-directorist-map-instance]');
            if (map) {
                // Keep the complete card usable inside short maps and narrow mobile layouts.
                card.style.maxWidth = `${Math.max(120, Math.min(280, map.clientWidth - 70))}px`;
                card.style.maxHeight = `${Math.max(100, Math.min(270, map.clientHeight - 80))}px`;
                card.style.overflowY = 'auto';
            }
            const bridge = window.DirectoristMapBridge;
            // Let the native map recalculate popup size and pan after replacing the placeholder.
            if (map && bridge && typeof bridge.openPopup === 'function' &&
                bridge.openPopup(map.dataset.directoristMapInstance, popup.dataset.bbq7833Id, card.outerHTML)) {
                return;
            }
            popup.replaceWith(card);
        } catch (error) {
            if (!popup.isConnected) return;
            delete popup.dataset.bbq7833Loading;
            const message = document.createElement('p');
            message.textContent = 'Could not load listing details. You can open the listing or try again.';
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.textContent = 'Try again';
            retry.addEventListener('click', () => load(popup), { once: true });
            popup.replaceChildren(message);
            if (fallback) popup.appendChild(fallback);
            popup.appendChild(retry);
        }
    }

    function scan(root) {
        if (root.nodeType !== 1) return;
        if (root.matches(selector)) load(root);
        root.querySelectorAll(selector).forEach(load);
    }

    function start() {
        scan(document.body);
        new MutationObserver(records => {
            records.forEach(record => record.addedNodes.forEach(scan));
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
