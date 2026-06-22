<div id="donrecextra-audit-report" class="crm-block crm-content-block">
  <div class="donrecextra-report-actions">
    <button type="button" class="crm-button" onclick="window.print()">{$auditLabels.print}</button>
  </div>
  <h2>{$auditLabels.title}</h2>
  <table class="report-layout">
    <tr><th>{$auditLabels.period}</th><td>{$auditReport.period_from|escape} – {$auditReport.period_to|escape}</td></tr>
    <tr><th>{$auditLabels.as_of}</th><td>{$auditReport.as_of|escape}</td></tr>
    <tr><th>{$auditLabels.date_basis}</th><td>{if $auditReport.date_basis eq 'contribution'}{$auditLabels.basis_contribution}{else}{$auditLabels.basis_issued}{/if}</td></tr>
    <tr><th>{$auditLabels.timezone}</th><td>{$auditReport.timezone|escape}</td></tr>
  </table>

  <div class="donrecextra-kpis">
    <div><strong>{$auditReport.summary.receipts_issued}</strong><span>{$auditLabels.receipts_issued}</span></div>
    <div><strong>{$auditReport.summary.receipts_withdrawn}</strong><span>{$auditLabels.receipts_withdrawn}</span></div>
    <div><strong>{$auditReport.summary.receipts_valid}</strong><span>{$auditLabels.receipts_valid}</span></div>
    <div><strong>{$auditReport.summary.contributions_valid}</strong><span>{$auditLabels.contributions_valid}</span></div>
    <div><strong>{$auditReport.summary.beneficiaries_valid}</strong><span>{$auditLabels.beneficiaries_valid}</span></div>
    <div><strong>{$auditReport.summary.amount_valid|string_format:"%.2f"} {$auditReport.currency}</strong><span>{$auditLabels.amount_valid}</span></div>
  </div>

  <h3>{$auditLabels.basis}</h3>
  <table class="selector row-highlight">
    <thead><tr><th></th><th>{$auditLabels.receipts_issued}</th><th>{$auditLabels.contributions_issued}</th><th>{$auditLabels.amount}</th></tr></thead>
    <tbody>
      <tr><th>{$auditLabels.receipts_issued}</th><td>{$auditReport.summary.receipts_issued}</td><td>{$auditReport.summary.contributions_issued}</td><td>{$auditReport.summary.amount_issued|string_format:"%.2f"} {$auditReport.currency}</td></tr>
      <tr><th>{$auditLabels.receipts_withdrawn}</th><td>{$auditReport.summary.receipts_withdrawn}</td><td>{$auditReport.summary.contributions_withdrawn}</td><td>{$auditReport.summary.amount_withdrawn|string_format:"%.2f"} {$auditReport.currency}</td></tr>
      <tr><th>{$auditLabels.receipts_valid}</th><td>{$auditReport.summary.receipts_valid}</td><td>{$auditReport.summary.contributions_valid}</td><td>{$auditReport.summary.amount_valid|string_format:"%.2f"} {$auditReport.currency}</td></tr>
    </tbody>
  </table>

  <h3>{$auditLabels.beneficiary_type}</h3>
  <table class="selector row-highlight"><tbody>
    <tr><th>{$auditLabels.individuals_valid}</th><td>{$auditReport.summary.individuals_valid}</td></tr>
    <tr><th>{$auditLabels.organizations_valid}</th><td>{$auditReport.summary.organizations_valid}</td></tr>
    <tr><th>{$auditLabels.beneficiaries_unknown}</th><td>{$auditReport.summary.beneficiaries_unknown}</td></tr>
  </tbody></table>

  <h3>{$auditLabels.breakdown}</h3>
  <table class="selector row-highlight">
    <thead><tr><th>{$auditLabels.period_column}</th><th>{$auditLabels.receipts_issued}</th><th>{$auditLabels.receipts_withdrawn}</th><th>{$auditLabels.receipts_valid}</th><th>{$auditLabels.contributions_valid}</th><th>{$auditLabels.individuals_valid}</th><th>{$auditLabels.organizations_valid}</th><th>{$auditLabels.amount_valid}</th></tr></thead>
    <tbody>{foreach from=$auditReport.breakdown item=row}
      <tr><td>{$row.period_key|escape}</td><td>{$row.receipts_issued}</td><td>{$row.receipts_withdrawn}</td><td>{$row.receipts_valid}</td><td>{$row.contributions_valid}</td><td>{$row.individuals_valid}</td><td>{$row.organizations_valid}</td><td>{$row.amount_valid|string_format:"%.2f"} {$auditReport.currency}</td></tr>
    {/foreach}</tbody>
  </table>

  <h3>{$auditLabels.cancellations}</h3>
  {if $auditReport.detected_withdrawal_count}
    <div class="messages status warning no-popup">{$auditLabels.historical_warning}</div>
  {/if}
  {if $auditReport.cancellations}
    <table class="selector row-highlight">
      <thead><tr><th>{$auditLabels.receipt_number}</th><th>{$auditLabels.contact_id}</th><th>{$auditLabels.beneficiary_type}</th><th>{$auditLabels.issued_at}</th><th>{$auditLabels.withdrawn_at}</th><th>{$auditLabels.precision}</th><th>{$auditLabels.amount}</th></tr></thead>
      <tbody>{foreach from=$auditReport.cancellations item=row}
        <tr><td>{$row.receipt_number|escape}</td><td>{$row.contact_id}</td><td>{$row.beneficiary_type|escape}</td><td>{$row.issued_at|escape}</td><td>{$row.withdrawn_at|escape}</td><td>{if $row.time_precision eq 'exact'}{$auditLabels.exact}{else}{$auditLabels.detected}{/if}</td><td>{$row.total_amount|string_format:"%.2f"} {$row.currency|escape}</td></tr>
      {/foreach}</tbody>
    </table>
  {else}<p>{$auditLabels.no_cancellations}</p>{/if}

  <p class="description"><strong>{$auditLabels.selection_hash}:</strong> <code>{$auditReport.selection_hash|escape}</code></p>
</div>

<style>
.donrecextra-report-actions { float: right; }
.donrecextra-kpis { display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:20px 0; }
.donrecextra-kpis div { padding:16px;background:#f3f6f8;border-left:4px solid #1f6b99; }
.donrecextra-kpis strong,.donrecextra-kpis span { display:block; }
.donrecextra-kpis strong { font-size:1.45rem; }
@media print { .crm-donrecextra-audit-filter,.donrecextra-report-actions,#civicrm-menu,#printer-friendly { display:none!important; } #donrecextra-audit-report { border:0; } }
</style>
