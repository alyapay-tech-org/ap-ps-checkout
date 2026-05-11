{literal}<script>if(!window._alyaJs){window._alyaJs=1;var s=document.createElement('script');s.src='https://cdn.alyapay.com/js/alya-placement.js';document.head.appendChild(s);}</script>{/literal}
<div id="alyapay-cart-promo" class="alyapay-credit-promo" style="display:none">
    <alya-placement key="cart"
                    price="{$alyapay_price|escape:'htmlall':'UTF-8'}"
                    currency="{$alyapay_currency|escape:'htmlall':'UTF-8'}"
                    lang="{$alyapay_lang|escape:'htmlall':'UTF-8'}"
                    installments="4"
                    theme="{$alyapay_theme|escape:'htmlall':'UTF-8'}"
                    variant="{$alyapay_variant|escape:'htmlall':'UTF-8'}"
                    detail="{$alyapay_detail|escape:'htmlall':'UTF-8'}"
                    logo-position="{$alyapay_logo_position|escape:'htmlall':'UTF-8'}"
                    {if $alyapay_full_width}full-width="true"{/if}
                    {if $alyapay_margin_x neq ''}margin-x="{$alyapay_margin_x|escape:'htmlall':'UTF-8'}"{/if}
                    {if $alyapay_margin_y neq ''}margin-y="{$alyapay_margin_y|escape:'htmlall':'UTF-8'}"{/if}
                    {if $alyapay_padding_x neq ''}padding-x="{$alyapay_padding_x|escape:'htmlall':'UTF-8'}"{/if}
                    {if $alyapay_padding_y neq ''}padding-y="{$alyapay_padding_y|escape:'htmlall':'UTF-8'}"{/if}
                    {if $alyapay_min_amount neq ''}min-amount="{$alyapay_min_amount|escape:'htmlall':'UTF-8'}" min-display="{$alyapay_min_display|escape:'htmlall':'UTF-8'}"{/if}
    ></alya-placement>
</div>
{literal}
<script>
(function () {
    function place() {
        var widget = document.getElementById('alyapay-cart-promo');
        if (!widget) return;
        var anchor = document.querySelector('.cart-summary-totals')
                  || document.querySelector('.cart-summary-subtotals-container')
                  || document.querySelector('.cart-summary');
        if (anchor) {
            anchor.parentNode.insertBefore(widget, anchor.nextSibling);
        }
        widget.style.display = '';
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', place);
    } else {
        place();
    }
})();
</script>
{/literal}
