# Donation Receipts Extra

This extension extends the functionality provided by
[Donation Receipts](https://github.com/systopia/de.systopia.donrec).

Originally developed by iXiam Global Solutions, the extension is now developed
and maintained by Jonathan Dahan.

## Requirements

* PHP 8.1
* CiviCRM 6.14.0 or later

## Configuration

* Please access url `civicrm/admin/donrecextra` to configure available tokens

The **Enable organization receipt fields** option is disabled by default. When
enabled, it creates (if necessary) an Organization custom group containing
SIREN, VAT number, legal form and head-office SIRET fields. It also exposes
portable Smarty tokens which do not depend on instance-specific custom field
IDs:

```Smarty
{$contributor.organization_siren}
{$contributor.organization_vat_number}
{$contributor.organization_legal_form}
{$contributor.organization_head_office_siret}
```

![Config](images/config.png)

## Usage

### Headless receipt generation

`DonationReceipt.generate` wraps Donrec's selector, snapshot and engine so that
jobs and automation can issue receipts without opening the Donrec GUI. The
action defaults to `dryRun=true`; a real receipt is only issued when callers
explicitly set `dryRun=false`.

```php
$preview = \Civi\Api4\DonationReceipt::generate(FALSE)
  ->setContributionIds([123])
  ->setDateFrom('2026-01-01')
  ->setDateTo('2026-12-31')
  ->setProfileId(9)
  ->setDryRun(TRUE)
  ->execute();
```

Callers may use `contactIds` instead of `contributionIds`. The default profile,
EUR currency, grouped receipts and the PDF exporter are used unless explicitly
overridden. Real generation requires the `create and withdraw receipts`
permission and a profile configured to store the original PDF.

### Durable batch generation (SQL queue)

For a large run, create a persistent SQL queue instead of keeping a browser
request open. `DonationReceipt.queue` links the work to a CiviCRM `UserJob`
and returns both identifiers. It supports the two Donrec selection modes and
both receipt modes:

| Selection | `bulk` | Queue task | Result |
| --- | --- | --- | --- |
| Explicit `contributionIds` | `false` | one contribution | one individual receipt |
| Explicit `contributionIds` | `true` | selected contributions of one contact | one grouped receipt for those selected payments |
| `contactIds` + `dateFrom`/`dateTo` | `false` | one contact and an immutable period | individual receipt(s) in that period |
| `contactIds` + `dateFrom`/`dateTo` | `true` | one contact and an immutable period | one grouped/annual receipt |

For explicit contributions, the task passes the exact IDs to Donrec; its date
window is only technical and cannot include another payment. For a contact
run, the period is stored in every queue task at creation, so a resumed annual
run cannot pick up a later contribution.

The action is deliberately a preview by default. Set `dryRun=false` only after
the queue has been checked. It does not run any task when it is created.

```bash
cv api4 DonationReceipt.queue contributionIds='[101,102,103]' profileId=9 exporters='["PDF"]' dryRun=false label='2026 receipts - first batch'
```

For an annual grouped receipt per contact:

```bash
cv api4 DonationReceipt.queue contactIds='[1569,20249]' dateFrom=2025-01-01 dateTo=2025-12-31 profileId=9 exporters='["PDF"]' bulk=true dryRun=false label='Fiscal year 2025'
```

The campaign screen can also use a saved SearchKit search whose primary entity
is `Contact` or `Contribution`. The search is evaluated once when the campaign
is created, and its resulting IDs are written as SQL queue tasks. The UserJob
keeps only the search provenance (ID, name, entity and result count), so a
resume never reruns the saved search or includes later records.

The result contains `user_job_id` and `queue_name`. Run a bounded worker from
cron, Redis worker supervision, or a shell session. It safely stops at either
limit; calling the same command again resumes the remaining SQL queue items.

```bash
cv api4 Queue.run queue=donrecextra_receipt_YYYYMMDDHHMMSS_xxxxxxxx maxRequests=100 maxDuration=120
```

Check progress and the latest blocking validation errors without opening the
Donrec GUI:

```bash
cv api4 DonationReceipt.queueStatus userJobId=123
```

The returned counters distinguish processed contributions, issued receipts,
skipped contributions and failed items. A failed item aborts the queue and is
left in SQL for auditability and retry: correct its data or configuration, then
run the very same `Queue.run` command. Completed work is deleted by CiviCRM's
queue runner and is never reissued by a resume. Do not run two workers for the
same queue until the progress screen in a later release provides worker
coordination and operational guidance.

#### SQL queue model and operations

The extension deliberately does not duplicate CiviCRM's queue storage. Each
campaign uses the following core tables:

| Table | Role |
| --- | --- |
| `civicrm_queue` | Queue identity and lifecycle state. Its name starts with `donrecextra_receipt_`. |
| `civicrm_queue_item` | Durable, serialized work items. A completed item is deleted; an item which errors remains available for retry. |
| `civicrm_user_job` | Campaign label, selected options, counters, the most recent errors and saved-search provenance. |

The UserJob starts as `scheduled`. The first worker marks it `in_progress`.
The CiviCRM queue itself is normally `active`; it becomes `aborted` after a
blocking task error and `completed` once no items remain. Running an aborted
campaign from the campaign screen reactivates it. A completed campaign is
immutable: create a new campaign to process a new selection.

For a cron worker, use one bounded invocation per campaign. `flock` prevents
two cron processes from taking work from the same queue at the same time:

```bash
flock -n /tmp/donrecextra-receipt-123.lock \
  cv api4 Queue.run queue=donrecextra_receipt_YYYYMMDDHHMMSS_xxxxxxxx \
  maxRequests=100 maxDuration=120
```

Replace `123` and the queue name with the values returned by
`DonationReceipt.queue` or shown on the campaign screen. Schedule that command
as frequently as appropriate for the instance. A worker may safely stop at
its request or duration limit; the next invocation continues with the pending
items. Do not run multiple workers for one campaign yet. Multiple independent
campaigns may each have their own lock and worker.

The donation receipts generated by extension `de.systopia.donrec` are based on Smarty, this extension adds new tokens that
can be included in this Smarty templates, for example:

### CiviRules final action

The `Generate Donrec receipt` action is intended as a terminal rule
action. With a Contribution trigger it re-evaluates and receipts only the
payment which triggered the rule. With an Activity trigger it creates the
grouped on-demand receipt; configure that trigger for Target contacts. Delays,
data validation and remediation stay as separate standard CiviRules components.

In Activity mode, the action uses the Activity creation timestamp as an
immutable request boundary and re-evaluates completed EUR contributions from
January 1 through that exact timestamp. In both modes it calls
`DonationReceipt.generate` with explicit contribution IDs, so later payments
cannot be absorbed into an earlier receipt run.

The administrator remains solely responsible for the business or statutory
delay configured in CiviRules. When a Contribution creation trigger has no
delay, the action adds only a one-second queue boundary so Donrec runs after
the contribution transaction and its post-commit cleanup. This technical
boundary does not alter a delay configured in CiviRules.

### Downloading an issued receipt

There are two supported link formats. Choose the one which matches the entity
displayed by the page or SearchKit.

#### 1. Direct file link from a `DonationReceipt` row

Use this for a SearchKit whose primary entity is `DonationReceipt`. Select the
following API4 fields: `original_file_id`, `contact_id`, `file_uri` and
`file_mime_type`. Render the link only when all file fields are non-empty and
the receipt status is `ORIGINAL`:

```text
/civicrm/file?reset=1&id=[original_file_id]&eid=[contact_id]&filename=[file_uri]&mime-type=[file_mime_type]
```

In a nested Contribution → Contact → DonationReceipt SearchKit, use the
machine tokens generated by that SearchKit, for example:

```text
/civicrm/file?reset=1&id=[Contribution_Contact_contact_id_01_Contact_DonationReceipt_contact_id_01.original_file_id]&eid=[Contribution_Contact_contact_id_01_Contact_DonationReceipt_contact_id_01.contact_id]&filename=[Contribution_Contact_contact_id_01_Contact_DonationReceipt_contact_id_01.file_uri]&mime-type=[Contribution_Contact_contact_id_01_Contact_DonationReceipt_contact_id_01.file_mime_type]
```

This is the standard CiviCRM file route. It is appropriate when the SearchKit
has already identified the precise receipt record and its stored original PDF.

#### 2. Stable link from a Contribution row

Use this for a contribution list, a contribution detail screen, or a portal
button where the receipt row is not already part of the search. Donrec Extra
resolves the latest original receipt item for that one contribution and then
redirects to the standard CiviCRM file route:

```text
/civicrm/donrecextra/receipt/download?reset=1&id=[contribution_id]&cid=[contact_id]
```

For a SearchKit whose primary entity is `Contribution`, the usual placeholders
are simply:

```text
/civicrm/donrecextra/receipt/download?reset=1&id=[id]&cid=[contact_id]
```

The route verifies that the contribution belongs to the supplied contact and
uses CiviCRM contact-view permission. If no original file is available, it
returns to the contribution with a warning instead of exposing another
contact's receipt.

### Receipt audit and as-of reports

Donrec Extra maintains an append-only companion ledger for original receipts,
their contribution lines, PDF SHA-256 hashes and withdrawal events. The CRM
page **Contributions > Donation receipt audit** produces translatable reports
by day, month or year, with an exact `as of` cutoff in the Europe/Paris
timezone. A report can be frozen as an immutable JSON snapshot with a selection
hash.

The report date basis can be either the receipt issue date or the contribution
date. Contribution-date mode supports fiscal reporting for, for example, a
receipt issued in 2026 for donations received in 2025. The period boundaries
remain explicit inputs, so the extension does not impose one country's fiscal
calendar on other deployments.

The `DonationReceiptAudit` API4 entity exposes the ledger to SearchKit. Direct
Donrec API withdrawals are recorded with an exact timestamp; withdrawals which
predate installation are marked as detected during reconciliation and are
called out in the report. Incomplete Donrec rows with no contribution item stay
visible for diagnosis but are excluded from fiscal totals.

Run or schedule a full reconciliation with:

```bash
cv api4 DonationReceiptAudit.reconcile
```

### Verifying receipt and report integrity

The audit ledger stores two independent SHA-256 checksums which answer two
different questions. Neither checksum is a digital signature or a replacement
for a retention policy; it is an integrity control which must be checked
against the stored value.

#### 1. Verify a receipt PDF

`DonrecextraReceiptAudit.pdf_sha256` is the SHA-256 checksum of the original
receipt PDF. To verify it, load the audit record, resolve `original_file_id`
through CiviCRM (do not assume that `civicrm_file.uri` is an absolute path),
and compare the stored checksum with a newly calculated checksum:

```php
$audit = \Civi\Api4\DonrecextraReceiptAudit::get(FALSE)
  ->addSelect('original_file_id', 'pdf_sha256')
  ->addWhere('id', '=', $auditId)
  ->execute()
  ->single();

[$path] = CRM_Core_BAO_File::path((int) $audit['original_file_id']);
$actual = hash_file('sha256', $path);

if (!hash_equals((string) $audit['pdf_sha256'], $actual)) {
  throw new RuntimeException('The stored receipt PDF does not match its audit checksum.');
}
```

A match proves that the bytes of the accessible PDF are the same bytes which
were hashed by the audit ledger. A mismatch means that the file is missing,
has changed, or that the ledger was populated before the final file was
available; investigate it without replacing the historical checksum.

#### 2. Verify a frozen audit report

When **Freeze this report when applying** is selected, the extension stores the
complete report JSON and `selection_hash` in `DonationReceiptAuditReport`.
`selection_hash` is the SHA-256 checksum of the ordered receipt/contribution
selection used for the report: audit receipt ID, receipt number, contribution
ID, contribution date, amount and status at the selected cutoff.

To verify a frozen report, load its stored parameters and recompute the report
with the exact same period, cutoff, granularity and date basis. Compare the
stored and recalculated hashes with `hash_equals()`:

```php
$snapshot = \Civi\Api4\DonationReceiptAuditReport::get(FALSE)
  ->addSelect('period_from', 'period_to', 'as_of', 'granularity', 'metrics_json', 'selection_hash')
  ->addWhere('id', '=', $snapshotId)
  ->execute()
  ->single();

$storedReport = json_decode($snapshot['metrics_json'], TRUE, 512, JSON_THROW_ON_ERROR);
$liveReport = (new CRM_Donrecextra_AuditReport())->calculate(
  $snapshot['period_from'],
  $snapshot['period_to'],
  $snapshot['as_of'],
  $snapshot['granularity'],
  $storedReport['date_basis']
);

if (!hash_equals((string) $snapshot['selection_hash'], $liveReport['selection_hash'])) {
  throw new RuntimeException('The current ledger selection differs from the frozen report.');
}
```

A match proves that the current audit ledger produces the same selected lines
and lifecycle status for that cutoff. A mismatch is not automatically proof of
tampering: it can result from a later reconciliation, a corrected withdrawal,
or an audit-ledger repair. Preserve the frozen JSON, investigate the relevant
receipt/event records, and create a new report instead of overwriting the old
snapshot.

The report selection hash does not currently include each receipt's
`pdf_sha256`, and receipt events are not hash-chained or digitally signed.
For a stronger legal archive, store the frozen report as a PDF, hash that PDF,
and retain it in immutable object storage together with the ledger snapshot.


* Example for contributions details

  ```smarty
  {foreach from=$lines item=line}
    {$line.receive_date}
    {$line.custom_xx}
  {/foreach}
  ```

* Example for a contribution sum from a custom field

  ```smarty
  {foreach from=$lines item=line key=tasknum  name=foo}
    {assign var="sum_all_foreach" value=$sum_all_foreach+$line.custom_23}
  {/foreach}
  {$sum_all_foreach|string_format:"%.2f"}
  ```

* Example for contacts' custom fields

  ```Smarty
  {$contributor.custom_xx}
  ```

## Special tokens

```Smarty
{$contributor.individual_prefix}
```

* Address, with criteria admin configuration page

```Smarty
{$address.state_province_name}
{$address.state_province_abbreviation}
{$address.country}
{$address.state_province_id}
{$address.supplemental_address_1}
{$address.supplemental_address_2}
{$address.city}
{$address.postal_code}
{$address.id}
{$address.custom_xx}
```

* Available Contribution Tokens

```Smarty
{$line.accounting_code}
{$line.amount_level}
{$line.cancel_date}
{$line.cancel_reason}
{$line.campaign_id}
{$line.check_number}
{$line.contribution_batch}
{$line.contribution_campaign_id}
{$line.contribution_campaign_title}
{$line.contribution_cancel_date}
{$line.contribution_check_number}
{$line.contribution_id}
{$line.contribution_note}
{$line.contribution_recur_id}
{$line.contribution_recur_status}
{$line.contribution_source}
{$line.contribution_status}
{$line.contribution_status_id}
{$line.contribution_type_id}
{$line.currency}
{$line.custom_xx}
{$line.fee_amount}
{$line.financial_account_id}
{$line.financial_type}
{$line.financial_type_id}
{$line.instrument_id}
{$line.invoice_id}
{$line.invoice_number}
{$line.is_pay_later}
{$line.is_test}
{$line.net_amount}
{$line.non_deductible_amount}
{$line.payment_instrument}
{$line.payment_instrument_id}
{$line.receive_date}
{$line.receipt_date}
{$line.thankyou_date}
{$line.total_amount}
{$line.trxn_id}
```
