# Changelog

All notable changes to Donation Receipts Extra are documented in this file.

## 1.3.1 — 2026-08-04

### Fixed

* Use atomic `CREATE OR REPLACE VIEW` DDL in `DonrecExtraSqlViewTrait` for
  `DonationReceipt`, `DonationReceiptItem`, and `DonationReceiptAudit` API4 entities.
  This avoids the non-idempotent `DROP VIEW` / `CREATE VIEW` cycle in CiviCRM core's
  `Generic\SqlView`, eliminating race condition errors (`Table already exists`) during
  concurrent entity-type cache rebuilds, background workers, and deployment flushes.
* Add container initialization check (`\CRM_Core_Config::isInitializing()`) and try-catch guard in `_on_civi_api4_entityTypes`, preventing container service recursion (`Civi.php` line 51) during early cache flushes.
* Replace direct static property access `CRM_Donrec_Logic_ReceiptItem::$_custom_fields`
  with public getter `CRM_Donrec_Logic_ReceiptItem::getCustomFields()`, avoiding fatal errors
  if Donrec restricts property visibility to `private`/`protected`.
* Initialize typed properties in API4 custom actions (`Generate`, `QueueStatus`, `Summary`)
  with default values, preventing `MagicGetterSetterTrait` infinite recursion during
  `cv flush` under PHP 8.0+.

### Added

* Full French localization (`fr_FR`) for all UI screens, SearchKit/Afform fields, API actions, and CiviRules triggers, harmonized with standard Donrec terminology.

### Changed

* Updated maintainer metadata, repository URLs, and original author attribution in `info.xml`.

## 1.3.0 — 2026-07-26

### Added

* Durable receipt campaigns backed by CiviCRM SQL queues and UserJobs. A
  campaign can be created from explicit contribution IDs, contact IDs with an
  immutable period, or a saved SearchKit search returning Contacts or
  Contributions.
* `DonationReceipt.queue` and `DonationReceipt.queueStatus` API4 actions for
  queue creation and operational status without the Donrec GUI.
* A campaign screen with profile/exporter selection, preview mode, recent-job
  counters, blocking errors and bounded manual resume.
* Reproducible CV worker support through `Queue.run`. The queue retains failed
  work for retry and removes completed work, preventing reissuance on resume.

### Changed

* Donation Receipts Extra configuration is now located beside Donrec's own
  settings and profiles. Audit and campaign operations are exposed in the
  Contributions menu.
* The README now documents SQL queue storage, lifecycle states, saved-search
  snapshots, bounded CV execution and a single-worker `flock` cron pattern.

## 1.2.0 — 2026-07-25

### Added

* A native **Issue receipt** action is added to each eligible row of the
  classic CiviCRM contribution selector. It opens a confirmation form rather
  than immediately issuing a receipt.
* The one-contribution form lets an authorised user choose an active Donrec
  profile and any exporter installed in Donrec (PDF, email, CSV, grouped or
  merged PDF, and optional CiviOffice exporters).
* One-contribution issuing always passes the explicit contribution ID to the
  headless `DonationReceipt.generate` API. Its date range is the contribution
  day, but no other payment from that day can be included; this avoids the
  normal Donrec daily boundary absorbing unrelated payments.
* Administrators can configure **required receipt data per active Donrec
  profile** on the Donation Receipts Extra settings page. Available choices
  include standard donor and address values plus eligible Contact custom
  fields. Donrec receipt-internal custom groups are deliberately excluded.
* Required-data validation is applied at the shared generation point, before
  preview or issuance. It therefore covers the one-contribution form,
  CiviRules and direct API generation. The error identifies each contact and
  its missing data.
* The settings page derives its required-data sections dynamically from active
  Donrec profiles. Updating a profile's Smarty template does not overwrite the
  selected requirements; a new profile starts with none selected.
* `organization_name` is now exposed with the Extra Contact tokens, enabling
  `{$contributor.organization_name}` in Donrec templates.
* The README documents the two supported receipt-download link formats: a
  direct CiviCRM file URL for a `DonationReceipt` SearchKit row, and the stable
  Donrecextra route resolved from a Contribution row.

### Changed

* The audit ledger database schema now uses Civix Entity Framework v2 entity
  definitions instead of legacy install SQL. Fresh installs and upgrades use
  the same generated schema mechanism.
* The donation-receipt audit report has aligned filters and clearer period and
  cutoff handling.
* Audit documentation now explains how to verify the receipt PDF SHA-256 hash
  and the frozen-report selection hash, including what each hash proves and
  does not prove.
* The extension is maintained as **Donation Receipts Extra**, under AGPL-3.0,
  with current maintainer and Donrec compatibility metadata.
* User-facing errors on the stable receipt-download route now use the
  extension translation domain.

### Security and data integrity

* The issue form re-loads the contribution from CiviCRM and validates its
  contact, selected profile, exporter and existing receipt state. URL and form
  values are not trusted.
* Duplicate original receipts are prevented both before the action is shown
  and immediately before generation using Donrec's receipt-item check.
* A required token which Donrec Extra is not configured to expose fails with a
  configuration error instead of silently producing a blank template value.

## 1.1.3 — 2026-06-22

* Imported the preceding DonrecExtra 1.1.3 codebase.
