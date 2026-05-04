<style>
.alya-success *{ldelim}box-sizing:border-box;margin:0;padding:0;{rdelim}
.alya-success{ldelim}max-width:560px;margin:0 auto;padding:2rem 1rem 3rem;color:inherit;{rdelim}
.alya-success[dir="rtl"]{ldelim}text-align:right;{rdelim}
.alya-check-ring{ldelim}width:64px;height:64px;border-radius:50%;background:rgba(5,223,114,.12);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;{rdelim}
.alya-check-ring svg{ldelim}width:28px;height:28px;{rdelim}
.alya-check-ring svg path{ldelim}stroke:#05df72;{rdelim}
.alya-hero-title{ldelim}font-size:22px;font-weight:500;text-align:center;margin-bottom:6px;{rdelim}
.alya-hero-sub{ldelim}font-size:14px;text-align:center;color:#6b7280;margin-bottom:1.5rem;{rdelim}
.alya-card{ldelim}background:#fff;border:0.5px solid rgba(0,0,0,.1);border-radius:12px;padding:1.25rem;margin-bottom:1rem;{rdelim}
.alya-card-label{ldelim}font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:#9ca3af;margin-bottom:1rem;{rdelim}
.alya-order-row{ldelim}display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:6px 0;color:#6b7280;{rdelim}
.alya-order-row.alya-order-row--bordered{ldelim}border-bottom:0.5px solid rgba(0,0,0,.08);{rdelim}
.alya-order-row strong{ldelim}font-weight:500;color:#111;font-size:13px;{rdelim}
.alya-logo-inline{ldelim}height:20px;width:auto;vertical-align:middle;margin-right:4px;{rdelim}
.alya-total-row{ldelim}display:flex;justify-content:space-between;align-items:center;padding-top:12px;margin-top:4px;border-top:0.5px solid rgba(0,0,0,.15);{rdelim}
.alya-total-label{ldelim}font-size:13px;font-weight:500;{rdelim}
.alya-total-amount{ldelim}font-size:20px;font-weight:500;{rdelim}
.alya-install-note{ldelim}font-size:12px;color:#3333cc;background:oklch(94% .04 272.28);border-radius:8px;padding:8px 12px;margin-top:12px;text-align:center;{rdelim}
.alya-timeline{ldelim}position:relative;padding-left:28px;{rdelim}
[dir="rtl"] .alya-timeline{ldelim}padding-left:0;padding-right:28px;{rdelim}
.alya-timeline::before{ldelim}content:'';position:absolute;left:9px;top:8px;bottom:8px;width:1px;background:rgba(0,0,0,.12);{rdelim}
[dir="rtl"] .alya-timeline::before{ldelim}left:auto;right:9px;{rdelim}
.alya-step{ldelim}position:relative;margin-bottom:1.25rem;display:flex;align-items:flex-start;gap:12px;{rdelim}
.alya-step:last-child{ldelim}margin-bottom:0;{rdelim}
.alya-dot{ldelim}position:absolute;left:-28px;top:3px;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;{rdelim}
[dir="rtl"] .alya-dot{ldelim}left:auto;right:-28px;{rdelim}
.alya-dot--paid{ldelim}background:rgba(5,223,114,.15);border:1.5px solid #05df72;{rdelim}
.alya-dot--upcoming{ldelim}background:#f9fafb;border:1.5px solid #d1d5db;{rdelim}
.alya-dot--scheduled{ldelim}background:#f9fafb;border:1.5px dashed #d1d5db;{rdelim}
.alya-dot__inner-paid{ldelim}width:7px;height:7px;border-radius:50%;background:#05df72;{rdelim}
.alya-dot__num{ldelim}font-size:9px;font-weight:500;color:#9ca3af;{rdelim}
.alya-step-body{ldelim}flex:1;display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:4px;{rdelim}
.alya-step-title{ldelim}font-size:13px;font-weight:500;margin-bottom:2px;{rdelim}
.alya-step-date{ldelim}font-size:12px;color:#6b7280;{rdelim}
.alya-step-amount{ldelim}font-size:14px;font-weight:500;text-align:right;{rdelim}
[dir="rtl"] .alya-step-amount{ldelim}text-align:left;{rdelim}
.alya-step-amount--paid{ldelim}color:#05df72;{rdelim}
.alya-step-amount--upcoming{ldelim}color:#111;{rdelim}
.alya-step-amount--scheduled{ldelim}color:#9ca3af;{rdelim}
.alya-badge{ldelim}font-size:10px;font-weight:500;padding:2px 7px;border-radius:6px;margin-top:3px;display:inline-block;{rdelim}
.alya-badge--paid{ldelim}background:rgba(5,223,114,.15);color:#05df72;{rdelim}
.alya-badge--upcoming{ldelim}background:#fefce8;color:#b45309;{rdelim}
.alya-badge--scheduled{ldelim}background:#f3f4f6;color:#9ca3af;{rdelim}
.alya-auto-note{ldelim}font-size:12px;color:#6b7280;margin-top:1rem;text-align:center;display:flex;align-items:center;justify-content:center;gap:6px;{rdelim}
.alya-actions{ldelim}display:flex;gap:10px;margin-top:1.25rem;{rdelim}
.alya-btn{ldelim}flex:1;padding:11px 16px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;border:0.5px solid rgba(0,0,0,.15);text-align:center;text-decoration:none;display:block;background:transparent;color:#111;font-family:inherit;transition:opacity .15s;{rdelim}
.alya-btn:hover{ldelim}opacity:.75;{rdelim}
.alya-btn--primary{ldelim}background:#3333cc;color:#fff;border-color:transparent;{rdelim}
.alya-powered{ldelim}font-size:11px;color:#9ca3af;text-align:center;margin-top:1.5rem;{rdelim}
.alya-powered strong{ldelim}color:#6b7280;font-weight:500;{rdelim}
.alya-method-value{ldelim}display:flex;align-items:center;gap:6px;{rdelim}
</style>

<div class="alya-success" dir="{$alyapay_dir|escape:'htmlall':'UTF-8'}">
    <div class="alya-check-ring">
        <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M6 14.5L11.5 20L22 9" stroke="#05df72" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>

    <h1 class="alya-hero-title">{$alyapay_t.title|escape:'htmlall':'UTF-8'}</h1>
    <p class="alya-hero-sub">{$alyapay_t.sub|escape:'htmlall':'UTF-8'}</p>

    <div class="alya-card">
        <p class="alya-card-label">{$alyapay_t.summary|escape:'htmlall':'UTF-8'}</p>
        <div class="alya-order-row alya-order-row--bordered">
            <span>{$alyapay_t.label_order|escape:'htmlall':'UTF-8'}</span>
            <strong>#{$alyapay_order_id|escape:'htmlall':'UTF-8'}</strong>
        </div>
        <div class="alya-order-row alya-order-row--bordered">
            <span>{$alyapay_t.label_date|escape:'htmlall':'UTF-8'}</span>
            <strong>{$alyapay_order_date|escape:'htmlall':'UTF-8'}</strong>
        </div>
        <div class="alya-order-row">
            <span>{$alyapay_t.label_method|escape:'htmlall':'UTF-8'}</span>
            <strong class="alya-method-value">
                <img src="https://cdn.alyapay.com/alya-logo.svg" alt="AlyaPay" class="alya-logo-inline" />
                {$alyapay_installment_count|escape:'htmlall':'UTF-8'}&times;
            </strong>
        </div>
        <div class="alya-total-row">
            <span class="alya-total-label">{$alyapay_t.label_total|escape:'htmlall':'UTF-8'}</span>
            <span class="alya-total-amount">{$alyapay_order_total|escape:'htmlall':'UTF-8'}</span>
        </div>
    </div>

    {if $alyapay_installment_count > 0 && $alyapay_installments|@count > 0}
    <div class="alya-card">
        <p class="alya-card-label">{$alyapay_t.schedule|escape:'htmlall':'UTF-8'}</p>
        <div class="alya-timeline">
            {foreach from=$alyapay_installments item=row key=idx}
                {assign var="stepNum" value=$idx+1}
                {assign var="status" value=$row.status}
                <div class="alya-step">
                    <div class="alya-dot alya-dot--{$status|escape:'htmlall':'UTF-8'}">
                        {if $status === 'paid'}
                            <div class="alya-dot__inner-paid"></div>
                        {else}
                            <span class="alya-dot__num">{$stepNum|escape:'htmlall':'UTF-8'}</span>
                        {/if}
                    </div>
                    <div class="alya-step-body">
                        <div>
                            <p class="alya-step-title">{$row.label|escape:'htmlall':'UTF-8'}</p>
                            {if $row.date}
                                <p class="alya-step-date">{$row.date|escape:'htmlall':'UTF-8'}</p>
                            {/if}
                        </div>
                        <div>
                            <p class="alya-step-amount alya-step-amount--{$status|escape:'htmlall':'UTF-8'}">{$row.amount|escape:'htmlall':'UTF-8'}</p>
                            <span class="alya-badge alya-badge--{$status|escape:'htmlall':'UTF-8'}">
                                {if $status === 'paid'}
                                    {$alyapay_t.status_paid|escape:'htmlall':'UTF-8'}
                                {elseif $status === 'upcoming'}
                                    {$alyapay_t.status_upcoming|escape:'htmlall':'UTF-8'}
                                {else}
                                    {$alyapay_t.status_scheduled|escape:'htmlall':'UTF-8'}
                                {/if}
                            </span>
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
        <p class="alya-auto-note">{$alyapay_t.auto_note|escape:'htmlall':'UTF-8'}</p>
    </div>
    {/if}

    <div class="alya-actions">
        <a href="{$alyapay_view_order_url|escape:'htmlall':'UTF-8'}" class="alya-btn">{$alyapay_t.btn_view|escape:'htmlall':'UTF-8'}</a>
        <a href="{$alyapay_continue_url|escape:'htmlall':'UTF-8'}" class="alya-btn alya-btn--primary">{$alyapay_t.btn_continue|escape:'htmlall':'UTF-8'}</a>
    </div>

    <p class="alya-powered">{$alyapay_t.powered nofilter}</p>
</div>
