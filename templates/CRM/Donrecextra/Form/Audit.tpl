{crmScope extensionKey='com.ixiam.modules.donrecextra'}
<div class="crm-block crm-form-block crm-donrecextra-audit-filter">
  <div class="crm-section">
    <div class="label">{$form.period_from.label}</div><div class="content">{$form.period_from.html}</div><div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.period_to.label}</div><div class="content">{$form.period_to.html}</div><div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.as_of.label}</div><div class="content">{$form.as_of.html}</div><div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.date_basis.label}</div><div class="content">{$form.date_basis.html}</div><div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.granularity.label}</div><div class="content">{$form.granularity.html}</div><div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.freeze_report.label}</div><div class="content">{$form.freeze_report.html}</div><div class="clear"></div>
  </div>
  <div class="crm-submit-buttons">{$form.buttons.html}</div>
</div>

{include file="CRM/Donrecextra/AuditReport.tpl"}
{/crmScope}
