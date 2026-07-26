{crmScope extensionKey='com.ixiam.modules.donrecextra'}
<div class="crm-block crm-form-block crm-donrecextra-queue-form">
  <div class="messages status no-popup">
    {ts}Create a durable receipt campaign. It is stored as a SQL queue and can be resumed from this page or from CV after an interruption.{/ts}
  </div>
  <fieldset>
    <legend>{ts}New campaign{/ts}</legend>
    <div class="crm-section"><div class="label">{$form.selection_mode.label}</div><div class="content">{$form.selection_mode.html}</div><div class="clear"></div></div>
    <div class="crm-section donrecextra-contributions"><div class="label">{$form.contribution_ids.label}</div><div class="content">{$form.contribution_ids.html}<div class="description">{ts}One ID per line, or separated by commas. Only these contributions will be selected.{/ts}</div></div><div class="clear"></div></div>
    <div class="crm-section donrecextra-contacts"><div class="label">{$form.contact_ids.label}</div><div class="content">{$form.contact_ids.html}<div class="description">{ts}One ID per line, or separated by commas. Each contact becomes one resumable task.{/ts}</div></div><div class="clear"></div></div>
    <div class="crm-section donrecextra-saved-search"><div class="label">{$form.saved_search_id.label}</div><div class="content">{$form.saved_search_id.html}<div class="description">{ts}The search is executed once when this campaign is created. Its resulting IDs are frozen in the SQL queue and are never recalculated on resume.{/ts}</div></div><div class="clear"></div></div>
    <div class="crm-section donrecextra-period"><div class="label">{$form.date_from.label}</div><div class="content">{$form.date_from.html}</div><div class="label small">{$form.date_to.label}</div><div class="content">{$form.date_to.html}</div><div class="clear"></div></div>
    <div class="crm-section"><div class="label">{$form.profile_id.label}</div><div class="content">{$form.profile_id.html}</div><div class="clear"></div></div>
    <div class="crm-section"><div class="label">{$form.exporters.label}</div><div class="content">{$form.exporters.html}</div><div class="clear"></div></div>
    <div class="crm-section"><div class="label">{$form.bulk.label}</div><div class="content">{$form.bulk.html}</div><div class="clear"></div></div>
    <div class="crm-section"><div class="label">{$form.dry_run.label}</div><div class="content">{$form.dry_run.html}</div><div class="clear"></div></div>
    <div class="crm-section"><div class="label">{$form.label.label}</div><div class="content">{$form.label.html}</div><div class="clear"></div></div>
  </fieldset>

  <fieldset>
    <legend>{ts}Run or resume{/ts}</legend>
    <div class="crm-section"><div class="label">{$form.queue_job_id.label}</div><div class="content">{$form.queue_job_id.html}</div><div class="clear"></div></div>
    <div class="description">{ts}The button runs at most ten tasks, then reloads this page. An aborted campaign is reactivated before processing. For large runs, use the CV command displayed below instead.{/ts}</div>
    <div class="crm-section"><div class="label">{$form.operation.label}</div><div class="content">{$form.operation.html}</div><div class="clear"></div></div>
  </fieldset>
  <div class="crm-submit-buttons">{$form.buttons.html}</div>
</div>

<h2>{ts}Recent campaigns{/ts}</h2>
{if $jobs}
  <table class="selector-row crm-donrecextra-queue-table">
    <thead><tr><th>{ts}ID{/ts}</th><th>{ts}Campaign{/ts}</th><th>{ts}Status{/ts}</th><th>{ts}Progress{/ts}</th><th>{ts}Errors{/ts}</th><th>{ts}CV command{/ts}</th></tr></thead>
    <tbody>
      {foreach from=$jobs item=job}
        {assign var=processed value=$job.counts.processed|default:0}
        {assign var=total value=$job.counts.total|default:0}
        {assign var=issued value=$job.counts.issued|default:0}
        <tr>
          <td>#{$job.user_job_id}</td>
          <td>{$job.label|escape}<br><small>{$job.created_date|crmDate}</small></td>
          <td>{$job.queue_status|escape}</td>
          <td>{ts 1=$processed 2=$total 3=$issued}Processed %1 / %2 — issued: %3{/ts}<br><small>{ts 1=$job.queue_ready}Ready: %1{/ts}</small></td>
          <td>{if $job.errors}{foreach from=$job.errors key=item item=message}<div><strong>{$item|escape}</strong>: {$message|escape}</div>{/foreach}{else}—{/if}</td>
          <td><code>cv api4 Queue.run queue={$job.queue_name|escape:'html'} maxRequests=100 maxDuration=120</code></td>
        </tr>
      {/foreach}
    </tbody>
  </table>
{else}
  <div class="messages info">{ts}No receipt campaign has been created yet.{/ts}</div>
{/if}

<script type="text/javascript">
{literal}
CRM.$(function($) {
  function toggleSelection() {
    var mode = $('#selection_mode').val(),
      contacts = mode === 'contacts',
      savedSearch = mode === 'saved_search';
    $('.donrecextra-contacts, .donrecextra-period').toggle(contacts);
    $('.donrecextra-contributions').toggle(mode === 'contributions');
    $('.donrecextra-saved-search').toggle(savedSearch);
    if (savedSearch) {
      $('.donrecextra-period').show();
    }
  }
  $('#selection_mode').on('change', toggleSelection);
  toggleSelection();
});
{/literal}
</script>
{/crmScope}
