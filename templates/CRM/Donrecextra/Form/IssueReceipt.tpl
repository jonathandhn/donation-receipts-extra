{crmScope extensionKey='com.ixiam.modules.donrecextra'}
<div class="crm-block crm-form-block crm-donrecextra-issue-receipt-form">
  <div class="messages status no-popup">
    {ts}This will issue a receipt for this contribution only. Other payments made on the same day or later will not be included.{/ts}
  </div>
  <table class="form-layout-compressed">
    <tr><th>{ts}Contribution{/ts}</th><td>#{$contribution.id} — {$contribution.total_amount|string_format:"%.2f"} {$contribution.currency|escape}</td></tr>
    <tr><th>{ts}Contribution date{/ts}</th><td>{$contribution.receive_date|crmDate}</td></tr>
    <tr><th>{ts}Donrec technical period{/ts}</th><td>{$periodDate|crmDate} – {$periodDate|crmDate}</td></tr>
    <tr class="crm-donrecextra-issue-profile"><th>{$form.profile_id.label}</th><td>{$form.profile_id.html}</td></tr>
    <tr class="crm-donrecextra-issue-exporter"><th>{$form.exporter.label}</th><td>{$form.exporter.html}</td></tr>
  </table>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
{/crmScope}
