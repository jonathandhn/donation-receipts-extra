{* HEADER *}

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="top"}
</div>

{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">
      {$form.$elementName.html}
      {if $elementName eq 'donrecextra_enable_organization_receipts'}
        <div class="description">{$organizationReceiptsDescription}</div>
      {/if}
      {if !empty($profileRequirementDescriptions[$elementName])}
        <div class="description">{$profileRequirementDescriptions[$elementName]}</div>
      {/if}
    </div>
    <div class="clear"></div>
  </div>
{/foreach}

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
