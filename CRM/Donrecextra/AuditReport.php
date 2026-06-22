<?php

use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Reproducible receipt metrics for a period and an as-of timestamp.
 */
class CRM_Donrecextra_AuditReport {

  public function calculate(string $periodFrom, string $periodTo, string $asOf, string $granularity = 'month', string $dateBasis = 'issued'): array {
    $from = $this->date($periodFrom, 'period_from');
    $to = $this->date($periodTo, 'period_to');
    $cutoff = $this->dateTime($asOf, 'as_of');
    if ($from > $to) {
      throw new CRM_Core_Exception(E::ts('The report start date must not be later than the end date.'));
    }
    if (!in_array($granularity, ['day', 'month', 'year'], TRUE)) {
      throw new CRM_Core_Exception(E::ts('Unsupported report granularity.'));
    }
    if (!in_array($dateBasis, ['issued', 'contribution'], TRUE)) {
      throw new CRM_Core_Exception(E::ts('Unsupported report date basis.'));
    }

    $params = [
      1 => [$from->format('Y-m-d 00:00:00'), 'String'],
      2 => [$to->format('Y-m-d 23:59:59'), 'String'],
      3 => [$cutoff->format('Y-m-d H:i:s'), 'String'],
    ];
    $itemDateWhere = $dateBasis === 'contribution'
      ? 'WHERE receive_date BETWEEN %1 AND %2'
      : '';
    $where = $dateBasis === 'contribution'
      ? 'r.issued_at <= %3'
      : 'r.issued_at BETWEEN %1 AND %2 AND r.issued_at <= %3';
    // Joining the aggregated item selection excludes incomplete Donrec rows
    // and, in contribution-date mode, restricts amounts to the fiscal period.
    $itemsJoin = "JOIN (
      SELECT receipt_audit_id,
             COUNT(DISTINCT contribution_id) contribution_count,
             SUM(total_amount) total_amount,
             SUM(non_deductible_amount) non_deductible_amount
        FROM civicrm_donrecextra_receipt_item_audit
        $itemDateWhere
       GROUP BY receipt_audit_id
    ) items ON items.receipt_audit_id = r.id";
    $join = "LEFT JOIN civicrm_donrecextra_receipt_event withdrawn
      ON withdrawn.receipt_audit_id = r.id
     AND withdrawn.event_type = 'WITHDRAWN'
     AND withdrawn.occurred_at <= %3";

    $summaryDao = CRM_Core_DAO::executeQuery(
      "SELECT
         COUNT(DISTINCT r.id) receipts_issued,
         COUNT(DISTINCT CASE WHEN withdrawn.id IS NOT NULL THEN r.id END) receipts_withdrawn,
         COUNT(DISTINCT CASE WHEN withdrawn.id IS NULL THEN r.id END) receipts_valid,
         COALESCE(SUM(items.contribution_count), 0) contributions_issued,
         COALESCE(SUM(CASE WHEN withdrawn.id IS NOT NULL THEN items.contribution_count ELSE 0 END), 0) contributions_withdrawn,
         COALESCE(SUM(CASE WHEN withdrawn.id IS NULL THEN items.contribution_count ELSE 0 END), 0) contributions_valid,
         COUNT(DISTINCT CASE WHEN withdrawn.id IS NULL THEN r.contact_id END) beneficiaries_valid,
         COUNT(DISTINCT CASE WHEN withdrawn.id IS NULL AND r.beneficiary_type = 'Individual' THEN r.contact_id END) individuals_valid,
         COUNT(DISTINCT CASE WHEN withdrawn.id IS NULL AND r.beneficiary_type = 'Organization' THEN r.contact_id END) organizations_valid,
         COUNT(DISTINCT CASE WHEN withdrawn.id IS NULL AND r.beneficiary_type = 'Unknown' THEN r.contact_id END) beneficiaries_unknown,
         COALESCE(SUM(items.total_amount), 0) amount_issued,
         COALESCE(SUM(CASE WHEN withdrawn.id IS NOT NULL THEN items.total_amount ELSE 0 END), 0) amount_withdrawn,
         COALESCE(SUM(CASE WHEN withdrawn.id IS NULL THEN items.total_amount ELSE 0 END), 0) amount_valid,
         COALESCE(SUM(CASE WHEN withdrawn.id IS NULL THEN items.non_deductible_amount ELSE 0 END), 0) non_deductible_valid
       FROM civicrm_donrecextra_receipt_audit r
       $itemsJoin
       $join
       WHERE $where",
      $params
    );
    $summaryDao->fetch();
    $summary = $summaryDao->toArray();
    foreach ($summary as $key => $value) {
      $summary[$key] = str_contains($key, 'amount') || str_contains($key, 'deductible')
        ? (float) $value
        : (int) $value;
    }

    $breakdownDate = $dateBasis === 'contribution' ? 'i.receive_date' : 'r.issued_at';
    $periodExpression = [
      'day' => "DATE_FORMAT($breakdownDate, '%Y-%m-%d')",
      'month' => "DATE_FORMAT($breakdownDate, '%Y-%m')",
      'year' => "DATE_FORMAT($breakdownDate, '%Y')",
    ][$granularity];
    $breakdownItemWhere = $dateBasis === 'contribution'
      ? 'AND i.receive_date BETWEEN %1 AND %2'
      : '';
    $breakdownDao = CRM_Core_DAO::executeQuery(
      "SELECT $periodExpression period_key,
              COUNT(DISTINCT r.id) receipts_issued,
              COUNT(DISTINCT CASE WHEN withdrawn.id IS NOT NULL THEN r.id END) receipts_withdrawn,
              COUNT(DISTINCT CASE WHEN withdrawn.id IS NULL THEN r.id END) receipts_valid,
              COUNT(DISTINCT CASE WHEN withdrawn.id IS NULL THEN i.contribution_id END) contributions_valid,
              COUNT(DISTINCT CASE WHEN withdrawn.id IS NULL THEN r.contact_id END) beneficiaries_valid,
              COUNT(DISTINCT CASE WHEN withdrawn.id IS NULL AND r.beneficiary_type = 'Individual' THEN r.contact_id END) individuals_valid,
              COUNT(DISTINCT CASE WHEN withdrawn.id IS NULL AND r.beneficiary_type = 'Organization' THEN r.contact_id END) organizations_valid,
              COALESCE(SUM(CASE WHEN withdrawn.id IS NULL THEN i.total_amount ELSE 0 END), 0) amount_valid
         FROM civicrm_donrecextra_receipt_audit r
         JOIN civicrm_donrecextra_receipt_item_audit i ON i.receipt_audit_id = r.id
         $join
        WHERE $where $breakdownItemWhere
        GROUP BY period_key
        ORDER BY period_key",
      $params
    );
    $breakdown = [];
    while ($breakdownDao->fetch()) {
      $row = $breakdownDao->toArray();
      foreach ($row as $key => $value) {
        if ($key !== 'period_key') {
          $row[$key] = $key === 'amount_valid' ? (float) $value : (int) $value;
        }
      }
      $breakdown[] = $row;
    }

    $cancellationsDao = CRM_Core_DAO::executeQuery(
      "SELECT r.id audit_receipt_id, r.receipt_number, r.contact_id, r.beneficiary_type,
              r.issued_at, items.total_amount, r.currency, withdrawn.occurred_at withdrawn_at,
              withdrawn.time_precision, withdrawn.source
         FROM civicrm_donrecextra_receipt_audit r
         $itemsJoin
         JOIN civicrm_donrecextra_receipt_event withdrawn
           ON withdrawn.receipt_audit_id = r.id
          AND withdrawn.event_type = 'WITHDRAWN'
          AND withdrawn.occurred_at <= %3
        WHERE $where
        ORDER BY withdrawn.occurred_at DESC, r.id DESC",
      $params
    );
    $cancellations = [];
    while ($cancellationsDao->fetch()) {
      $row = $cancellationsDao->toArray();
      $row['audit_receipt_id'] = (int) $row['audit_receipt_id'];
      $row['contact_id'] = (int) $row['contact_id'];
      $row['total_amount'] = (float) $row['total_amount'];
      $cancellations[] = $row;
    }
    $detectedWithdrawalCount = count(array_filter(
      $cancellations,
      static fn(array $row): bool => $row['time_precision'] !== 'exact'
    ));

    return [
      'period_from' => $from->format('Y-m-d'),
      'period_to' => $to->format('Y-m-d'),
      'as_of' => $cutoff->format('Y-m-d H:i:s'),
      'timezone' => 'Europe/Paris',
      'granularity' => $granularity,
      'date_basis' => $dateBasis,
      'currency' => 'EUR',
      'summary' => $summary,
      'breakdown' => $breakdown,
      'cancellations' => $cancellations,
      'detected_withdrawal_count' => $detectedWithdrawalCount,
      'selection_hash' => $this->selectionHash($params, $where, $join, $dateBasis),
    ];
  }

  public function freeze(array $report): int {
    return CRM_Donrecextra_DatabaseLogging::disabled(function () use ($report): int {
      return $this->doFreeze($report);
    });
  }

  private function doFreeze(array $report): int {
    $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $createdBy = (int) CRM_Core_Session::singleton()->getLoggedInContactID();
    CRM_Core_DAO::executeQuery(
      'INSERT INTO civicrm_donrecextra_audit_report
        (period_from, period_to, as_of, granularity, metrics_json, selection_hash, created_at, created_by)
       VALUES (%1,%2,%3,%4,%5,%6,%7,NULLIF(%8,0))',
      [
        1 => [$report['period_from'], 'String'],
        2 => [$report['period_to'], 'String'],
        3 => [$report['as_of'], 'String'],
        4 => [$report['granularity'], 'String'],
        5 => [$json, 'String'],
        6 => [$report['selection_hash'], 'String'],
        7 => [date('Y-m-d H:i:s'), 'String'],
        8 => [$createdBy ?: 0, 'Integer'],
      ]
    );
    return (int) CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()');
  }

  private function selectionHash(array $params, string $where, string $join, string $dateBasis): string {
    $context = hash_init('sha256');
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT r.id, r.receipt_number, i.contribution_id, i.receive_date, i.total_amount,
              CASE WHEN withdrawn.id IS NULL THEN 'VALID' ELSE 'WITHDRAWN' END status_as_of
         FROM civicrm_donrecextra_receipt_audit r
         JOIN civicrm_donrecextra_receipt_item_audit i ON i.receipt_audit_id = r.id
         $join
        WHERE $where " . ($dateBasis === 'contribution' ? 'AND i.receive_date BETWEEN %1 AND %2' : '') . "
        ORDER BY r.id, i.contribution_id",
      $params
    );
    while ($dao->fetch()) {
      hash_update($context, implode('|', [$dao->id, $dao->receipt_number, $dao->contribution_id, $dao->receive_date, $dao->total_amount, $dao->status_as_of]) . "\n");
    }
    return hash_final($context);
  }

  private function date(string $value, string $field): DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Paris'));
    if (!$date || $date->format('Y-m-d') !== $value) {
      throw new CRM_Core_Exception(E::ts('Invalid date for %1.', [1 => $field]));
    }
    return $date;
  }

  private function dateTime(string $value, string $field): DateTimeImmutable {
    try {
      return new DateTimeImmutable($value, new DateTimeZone('Europe/Paris'));
    }
    catch (Throwable $e) {
      throw new CRM_Core_Exception(E::ts('Invalid date and time for %1.', [1 => $field]));
    }
  }

}
