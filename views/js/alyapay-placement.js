/**
 * AlyaPay placement - ensures alya-placement custom element attributes are set correctly.
 * For PrestaShop, the web component is rendered server-side in Smarty templates.
 * This script handles dynamic price updates on product pages with variants.
 */
(function () {
    'use strict';

    function updatePlacements(newPrice) {
        var placements = document.querySelectorAll('alya-placement[key="credit-promotion"]');
        placements.forEach(function (el) {
            el.setAttribute('price', parseFloat(newPrice).toFixed(2));
        });
    }

    // PrestaShop emits this event when a product combination changes price
    if (typeof prestashop !== 'undefined') {
        prestashop.on('updatedProduct', function (event) {
            if (event && event.product_price) {
                updatePlacements(event.product_price);
            }
        });
    }
})();
