<?php

use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Donation receipt audit report with a reproducible as-of cutoff.
 */
class CRM_Donrecextra_Form_Audit extends CRM_Core_Form {

  private array $filters = [];

  public function preProcess() {
    parent::preProcess();
    $today = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
    $this->filters = [
      'period_from' => CRM_Utils_Request::retrieveValue('period_from', 'String', $today->format('Y-01-01')),
      'period_to' => CRM_Utils_Request::retrieveValue('period_to', 'String', $today->format('Y-m-d')),
      'as_of' => CRM_Utils_Request::retrieveValue('as_of', 'String', $today->format('Y-m-d H:i:s')),
      'granularity' => CRM_Utils_Request::retrieveValue('granularity', 'String', 'month'),
      'date_basis' => CRM_Utils_Request::retrieveValue('date_basis', 'String', 'issued'),
    ];
    CRM_Utils_System::setTitle(E::ts('Donation receipt audit'));
  }

  public function buildQuickForm() {
    $this->add('datepicker', 'period_from', E::ts('Period from'), [], TRUE, ['time' => FALSE]);
    $this->add('datepicker', 'period_to', E::ts('Period to'), [], TRUE, ['time' => FALSE]);
    $this->add('datepicker', 'as_of', E::ts('As of'), [], TRUE, ['time' => TRUE]);
    $this->add('select', 'granularity', E::ts('Granularity'), [
      'day' => E::ts('Day'),
      'month' => E::ts('Month'),
      'year' => E::ts('Year'),
    ], TRUE);
    $this->add('select', 'date_basis', E::ts('Date basis'), [
      'issued' => E::ts('Receipt issue date'),
      'contribution' => E::ts('Contribution date (fiscal period)'),
    ], TRUE);
    $this->add('checkbox', 'freeze_report', E::ts('Freeze this report when applying'));
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Apply'),
        'isDefault' => TRUE,
      ],
    ]);

    $report = (new CRM_Donrecextra_AuditReport())->calculate(
      $this->filters['period_from'],
      $this->filters['period_to'],
      $this->filters['as_of'],
      $this->filters['granularity'],
      $this->filters['date_basis']
    );
    $this->assign('auditReport', $report);
    $this->assign('auditLabels', $this->labels($report));
    parent::buildQuickForm();
  }

  public function setDefaultValues() {
    return $this->filters;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $reportService = new CRM_Donrecextra_AuditReport();
    $report = $reportService->calculate(
      $values['period_from'],
      $values['period_to'],
      $values['as_of'],
      $values['granularity'],
      $values['date_basis']
    );
    if (!empty($values['freeze_report'])) {
      $snapshotId = $reportService->freeze($report);
      CRM_Core_Session::setStatus(
        E::ts('Frozen audit report #%1 was created.', [1 => $snapshotId]),
        E::ts('Audit report frozen'),
        'success'
      );
    }
    $query = http_build_query([
      'reset' => 1,
      'period_from' => $report['period_from'],
      'period_to' => $report['period_to'],
      'as_of' => $report['as_of'],
      'granularity' => $report['granularity'],
      'date_basis' => $report['date_basis'],
    ]);
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/admin/donrecextra/audit', $query));
  }

  private function labels(array $report): array {
    return [
      'title' => E::ts('Donation receipt audit report'),
      'basis' => E::ts('Reporting basis'),
      'period' => E::ts('Period'),
      'as_of' => E::ts('As of'),
      'timezone' => E::ts('Timezone'),
      'date_basis' => E::ts('Date basis'),
      'basis_issued' => E::ts('Receipt issue date'),
      'basis_contribution' => E::ts('Contribution date (fiscal period)'),
      'receipts_issued' => E::ts('Receipts issued'),
      'receipts_withdrawn' => E::ts('Receipts withdrawn'),
      'receipts_valid' => E::ts('Valid receipts'),
      'contributions_issued' => E::ts('Contributions on issued receipts'),
      'contributions_withdrawn' => E::ts('Contributions on withdrawn receipts'),
      'contributions_valid' => E::ts('Contributions on valid receipts'),
      'beneficiaries_valid' => E::ts('Distinct beneficiaries'),
      'individuals_valid' => E::ts('Individuals'),
      'organizations_valid' => E::ts('Organizations'),
      'beneficiaries_unknown' => E::ts('Unknown beneficiary type'),
      'amount_issued' => E::ts('Amount issued'),
      'amount_withdrawn' => E::ts('Amount withdrawn'),
      'amount_valid' => E::ts('Valid amount'),
      'breakdown' => E::ts('Breakdown'),
      'period_column' => E::ts('Reporting period'),
      'cancellations' => E::ts('Withdrawals included at this cutoff'),
      'receipt_number' => E::ts('Receipt number'),
      'contact_id' => E::ts('Beneficiary ID'),
      'beneficiary_type' => E::ts('Beneficiary type'),
      'issued_at' => E::ts('Issued at'),
      'withdrawn_at' => E::ts('Withdrawn at'),
      'precision' => E::ts('Time precision'),
      'exact' => E::ts('Exact'),
      'detected' => E::ts('Detected during reconciliation'),
      'historical_warning' => E::ts('One withdrawal predates the audit ledger. Its displayed date is the reconciliation date, not the legally effective withdrawal date. Verify this record before using a historical cutoff.', [
        'plural' => '%count withdrawals predate the audit ledger. Their displayed date is the reconciliation date, not the legally effective withdrawal date. Verify these records before using a historical cutoff.',
        'count' => $report['detected_withdrawal_count'],
      ]),
      'amount' => E::ts('Amount'),
      'no_cancellations' => E::ts('No withdrawals are included for this period and cutoff.'),
      'selection_hash' => E::ts('Selection SHA-256'),
      'print' => E::ts('Print report'),
    ];
  }

}
