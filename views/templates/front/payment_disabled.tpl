{literal}
<script>
(function () {
    var script = document.currentScript;
    function disableAlyaOption() {
        var container = script ? script.closest('.payment-option, [id^="payment-option"]') : null;
        if (!container) {
            container = script && script.parentElement ? script.parentElement.closest('li, .payment-option-item, [class*="payment"]') : null;
        }
        var input = container ? container.querySelector('input[type="radio"]') : null;
        if (!input) {
            var allInputs = document.querySelectorAll('input[type="radio"][name="payment-option"]');
            input = allInputs.length ? allInputs[allInputs.length - 1] : null;
        }
        if (!input) return;
        input.disabled = true;
        if (input.checked) {
            input.checked = false;
            var first = document.querySelector('input[type="radio"][name="payment-option"]:not([disabled])');
            if (first) { first.checked = true; first.dispatchEvent(new Event('change', {bubbles: true})); }
        }
        var option = input.closest('li, .payment-option-item, [class*="payment-option"]') || input.parentElement;
        if (option) { option.style.opacity = '0.5'; option.style.pointerEvents = 'none'; }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', disableAlyaOption);
    } else {
        disableAlyaOption();
    }
})();
</script>
{/literal}
