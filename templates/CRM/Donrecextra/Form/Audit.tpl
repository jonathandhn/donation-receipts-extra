{crmScope extensionKey='com.ixiam.modules.donrecextra'}
<div class="crm-block crm-form-block crm-donrecextra-audit-filter">
  <div class="crm-section donrecextra-period-range">
    <div class="donrecextra-period-field">
      <div class="label">{$form.period_from.label}</div><div class="content">{$form.period_from.html}</div>
    </div>
    <div class="donrecextra-period-field">
      <div class="label">{$form.period_to.label}</div><div class="content">{$form.period_to.html}</div>
    </div>
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

<style>
.crm-donrecextra-audit-filter .donrecextra-period-range {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 24px;
  align-items: center;
}
.crm-donrecextra-audit-filter .crm-section {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 12px;
}
.crm-donrecextra-audit-filter .crm-section > .label {
  float: none;
  flex: 0 0 130px;
  width: auto;
  margin: 0;
  text-align: right;
}
.crm-donrecextra-audit-filter .crm-section > .content {
  float: none;
  width: auto;
  margin: 0;
}
.crm-donrecextra-audit-filter .crm-section > .clear {
  display: none;
}
.crm-donrecextra-audit-filter .donrecextra-period-field {
  display: flex;
  align-items: center;
  gap: 8px;
}
.crm-donrecextra-audit-filter .donrecextra-period-field .label,
.crm-donrecextra-audit-filter .donrecextra-period-field .content {
  float: none;
  width: auto;
  margin: 0;
}
.crm-donrecextra-audit-filter .donrecextra-period-field:first-child .label {
  flex: 0 0 130px;
  text-align: right;
}
@media (max-width: 640px) {
  .crm-donrecextra-audit-filter .crm-section,
  .crm-donrecextra-audit-filter .donrecextra-period-range,
  .crm-donrecextra-audit-filter .donrecextra-period-field {
    align-items: flex-start;
    flex-direction: column;
    gap: 4px;
  }
  .crm-donrecextra-audit-filter .crm-section > .label,
  .crm-donrecextra-audit-filter .donrecextra-period-field:first-child .label {
    flex-basis: auto;
    text-align: left;
  }
}
</style>
{/crmScope}
