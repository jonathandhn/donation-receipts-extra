# Changelog

All notable changes to Donation Receipts Extra are documented in this file.

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

