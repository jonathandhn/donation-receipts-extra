{crmScope extensionKey='com.ixiam.modules.donrecextra'}
<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block">
  <div class="help">{$ruleActionHelp}</div>
  <div class="crm-section">
    <div class="label">{$form.delivery_mode.label}</div>
    <div class="content">{$form.delivery_mode.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.individual_profile_id.label}</div>
    <div class="content">{$form.individual_profile_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.organization_profile_id.label}</div>
    <div class="content">{$form.organization_profile_id.html}</div>
    <div class="clear"></div>
  </div>
</div>
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{/crmScope}
