/**
 * AlyaPay placement - ensures alya-placement custom element attributes are set correctly.
 * For PrestaShop, the web component is rendered server-side in Smarty templates.
 * This script handles dynamic price updates on product pages with variants,
 * and re-injects the cart widget after AJAX cart updates.
 */
(function () {
    'use strict';

    function updatePlacements(newPrice) {
        var placements = document.querySelectorAll('alya-placement[key="credit-promotion"]');
        placements.forEach(function (el) {
            el.setAttribute('price', parseFloat(newPrice).toFixed(2));
        });
    }

    // Store cart widget HTML on first load so we can re-inject after AJAX updates.
    var cartWidgetHtml = null;
    var cartWidgetContainer = null;

    function captureCartWidget() {
        var wrapper = document.querySelector('.alya-cart-promo-wrapper');
        if (wrapper && wrapper.parentNode) {
            cartWidgetHtml = wrapper.outerHTML;
            cartWidgetContainer = wrapper.parentNode;
        }
    }

    function restoreCartWidget() {
        if (!cartWidgetHtml) return;
        // Widget already present — nothing to do.
        if (document.querySelector('.alya-cart-promo-wrapper')) return;
        // Try to find the same parent container after DOM update.
        var container = cartWidgetContainer || document.querySelector('#cart-subtotal-products');
        if (container && container.parentNode) {
            var tmp = document.createElement('div');
            tmp.innerHTML = cartWidgetHtml;
            var node = tmp.firstChild;
            if (node) {
                container.parentNode.insertBefore(node, container.nextSibling);
            }
        }
    }

    function moveLogoFirst() {
        var img = document.querySelector('img[src*="cdn.alyapay.com/alya-logo.svg"]');
        if (img && img.parentNode && img !== img.parentNode.firstChild) {
            img.parentNode.insertBefore(img, img.parentNode.firstChild);
        }
    }

    if (typeof prestashop !== 'undefined') {
        document.addEventListener('DOMContentLoaded', function () {
            captureCartWidget();
            moveLogoFirst();
        });

        // Re-inject after AJAX cart update.
        prestashop.on('updatedCart', function () {
            captureCartWidget();
            restoreCartWidget();
        });

        // Update price on product variant change.
        prestashop.on('updatedProduct', function (event) {
            if (event && event.product_price) {
                updatePlacements(event.product_price);
            }
        });
    }
})();
