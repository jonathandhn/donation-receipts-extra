<?php

/**
 * Append-only audit companion for Donrec receipts.
 */
class CRM_Donrecextra_AuditLedger {

  /**
   * Reconcile every original receipt currently known by Donrec.
   */
  public function reconcileAll(): array {
    return CRM_Donrecextra_DatabaseLogging::disabled(function (): array {
      return $this->doReconcileAll();
    });
  }

  private function doReconcileAll(): array {
    $structure = $this->getDonrecStructure();
    $receiptTable = $structure['receipt_table'];
    $receiptNumber = $structure['receipt']['receipt_id'];
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT r.id
         FROM `$receiptTable` r
        WHERE r.id = (
          SELECT MIN(first_receipt.id)
            FROM `$receiptTable` first_receipt
           WHERE first_receipt.`$receiptNumber` = r.`$receiptNumber`
        )
        ORDER BY r.id"
    );

    $counts = ['scanned' => 0, 'created' => 0, 'updated' => 0];
    while ($dao->fetch()) {
      $counts['scanned']++;
      $result = $this->reconcileReceipt((int) $dao->id, NULL, 'reconciliation');
      if (!empty($result['created'])) {
        $counts['created']++;
      }
      elseif (!empty($result['audit_id'])) {
        $counts['updated']++;
      }
    }
    return $counts;
  }

  public function reconcileReceiptIds(array $receiptIds, ?DateTimeInterface $eventTime = NULL, string $source = 'generation'): void {
    foreach (array_unique(array_map('intval', $receiptIds)) as $receiptId) {
      if ($receiptId > 0) {
        $this->reconcileReceipt($receiptId, $eventTime, $source);
      }
    }
  }

  /**
   * Capture one original receipt and its current lifecycle status.
   */
  public function reconcileReceipt(int $receiptId, ?DateTimeInterface $eventTime = NULL, string $source = 'reconciliation'): array {
    return CRM_Donrecextra_DatabaseLogging::disabled(function () use ($receiptId, $eventTime, $source): array {
      return $this->doReconcileReceipt($receiptId, $eventTime, $source);
    });
  }

  private function doReconcileReceipt(int $receiptId, ?DateTimeInterface $eventTime, string $source): array {
    $receipt = $this->loadReceipt($receiptId);
    if (!$receipt || !$receipt['is_original_record']) {
      return [];
    }

    $now = date('Y-m-d H:i:s');
    $existing = CRM_Core_DAO::executeQuery(
      'SELECT id, current_status, pdf_sha256 FROM civicrm_donrecextra_receipt_audit WHERE donrec_receipt_id = %1',
      [1 => [$receiptId, 'Integer']]
    );
    $created = !$existing->fetch();
    $auditId = $created ? 0 : (int) $existing->id;
    $pdfHash = $created || empty($existing->pdf_sha256) ? $this->getPdfHash((int) $receipt['original_file_id']) : $existing->pdf_sha256;

    if ($created) {
      CRM_Core_DAO::executeQuery(
        'INSERT INTO civicrm_donrecextra_receipt_audit
          (donrec_receipt_id, receipt_number, contact_id, beneficiary_type, receipt_type, current_status,
           issued_at, period_from, period_to, total_amount, non_deductible_amount, currency,
           original_file_id, pdf_sha256, first_seen_at, updated_at)
         VALUES (%1,%2,%3,%4,%5,%6,%7,NULLIF(%8,\'\'),NULLIF(%9,\'\'),%10,%11,%12,NULLIF(%13,0),NULLIF(%14,\'\'),%15,%15)',
        [
          1 => [$receiptId, 'Integer'],
          2 => [$receipt['receipt_number'], 'String'],
          3 => [$receipt['contact_id'], 'Integer'],
          4 => [$receipt['beneficiary_type'], 'String'],
          5 => [$receipt['receipt_type'], 'String'],
          6 => [$receipt['status'], 'String'],
          7 => [$receipt['issued_at'], 'String'],
          8 => [(string) ($receipt['period_from'] ?? ''), 'String'],
          9 => [(string) ($receipt['period_to'] ?? ''), 'String'],
          10 => [$receipt['total_amount'], 'Float'],
          11 => [$receipt['non_deductible_amount'], 'Float'],
          12 => [$receipt['currency'], 'String'],
          13 => [(int) ($receipt['original_file_id'] ?: 0), 'Integer'],
          14 => [(string) ($pdfHash ?? ''), 'String'],
          15 => [$now, 'String'],
        ]
      );
      $auditId = (int) CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()');
      $this->appendEvent($auditId, 'ISSUED', $receipt['issued_at'], 'exact', $source, NULL, [
        'donrec_receipt_id' => $receiptId,
        'receipt_number' => $receipt['receipt_number'],
      ]);
      $this->insertItems($auditId, $receipt['items']);
    }
    else {
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_donrecextra_receipt_audit
            SET current_status = %1,
                pdf_sha256 = COALESCE(pdf_sha256, NULLIF(%2, '')),
                updated_at = %3
          WHERE id = %4",
        [
          1 => [$receipt['status'], 'String'],
          2 => [(string) ($pdfHash ?? ''), 'String'],
          3 => [$now, 'String'],
          4 => [$auditId, 'Integer'],
        ]
      );
      $this->insertItems($auditId, $receipt['items']);
    }

    // Keep reconciliation self-healing if an earlier process stopped between
    // inserting the receipt fact and inserting its event or item rows.
    $this->appendEvent($auditId, 'ISSUED', $receipt['issued_at'], 'exact', $source, NULL, [
      'donrec_receipt_id' => $receiptId,
      'receipt_number' => $receipt['receipt_number'],
    ]);
    $this->insertItems($auditId, $receipt['items']);

    if ($receipt['status'] === 'WITHDRAWN') {
      $occurredAt = $eventTime ? $eventTime->format('Y-m-d H:i:s') : $now;
      $precision = $eventTime ? 'exact' : 'detected';
      $this->appendEvent($auditId, 'WITHDRAWN', $occurredAt, $precision, $source);
    }

    return ['audit_id' => $auditId, 'created' => $created];
  }

  private function loadReceipt(int $receiptId): array {
    $s = $this->getDonrecStructure();
    $r = $s['receipt'];
    $i = $s['item'];
    $rt = $s['receipt_table'];
    $it = $s['item_table'];
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT r.id,
              r.entity_id contact_id,
              r.`{$r['receipt_id']}` receipt_number,
              r.`{$r['status']}` status,
              r.`{$r['type']}` receipt_type,
              r.`{$r['issued_on']}` issued_at,
              r.`{$r['date_from']}` period_from,
              r.`{$r['date_to']}` period_to,
              r.`{$r['contact_type']}` beneficiary_type,
              r.`{$r['original_file']}` original_file_id,
              MIN(same_number.id) original_record_id
         FROM `$rt` r
         JOIN `$rt` same_number ON same_number.`{$r['receipt_id']}` = r.`{$r['receipt_id']}`
        WHERE r.id = %1
        GROUP BY r.id",
      [1 => [$receiptId, 'Integer']]
    );
    if (!$dao->fetch()) {
      return [];
    }
    $receipt = $dao->toArray();
    $receipt['is_original_record'] = (int) $receipt['original_record_id'] === $receiptId;
    $receipt['beneficiary_type'] = $this->normalizeBeneficiaryType($receipt['beneficiary_type']);
    $receipt['items'] = [];
    $receipt['total_amount'] = 0.0;
    $receipt['non_deductible_amount'] = 0.0;
    $receipt['currency'] = 'EUR';

    $items = CRM_Core_DAO::executeQuery(
      "SELECT id donrec_receipt_item_id,
              entity_id contribution_id,
              `{$i['receive_date']}` receive_date,
              `{$i['total_amount']}` total_amount,
              COALESCE(`{$i['non_deductible_amount']}`, 0) non_deductible_amount,
              `{$i['currency']}` currency,
              `{$i['financial_type_id']}` financial_type_id,
              `{$i['contribution_hash']}` contribution_hash
         FROM `$it`
        WHERE `{$i['issued_in']}` = %1",
      [1 => [$receiptId, 'Integer']]
    );
    while ($items->fetch()) {
      $item = $items->toArray();
      $receipt['items'][] = $item;
      $receipt['total_amount'] += (float) $item['total_amount'];
      $receipt['non_deductible_amount'] += (float) $item['non_deductible_amount'];
      $receipt['currency'] = $item['currency'] ?: 'EUR';
    }
    return $receipt;
  }

  private function insertItems(int $auditId, array $items): void {
    foreach ($items as $item) {
      CRM_Core_DAO::executeQuery(
        "INSERT IGNORE INTO civicrm_donrecextra_receipt_item_audit
          (receipt_audit_id, donrec_receipt_item_id, contribution_id, receive_date, total_amount, non_deductible_amount,
           currency, financial_type_id, contribution_hash)
         VALUES (%1,%2,%3,%4,%5,%6,%7,NULLIF(%8,0),NULLIF(%9,''))",
        [
          1 => [$auditId, 'Integer'],
          2 => [$item['donrec_receipt_item_id'], 'Integer'],
          3 => [$item['contribution_id'], 'Integer'],
          4 => [$item['receive_date'], 'String'],
          5 => [$item['total_amount'], 'Float'],
          6 => [$item['non_deductible_amount'], 'Float'],
          7 => [$item['currency'] ?: 'EUR', 'String'],
          8 => [(int) ($item['financial_type_id'] ?: 0), 'Integer'],
          9 => [(string) ($item['contribution_hash'] ?? ''), 'String'],
        ]
      );
    }
  }

  private function appendEvent(int $auditId, string $type, string $occurredAt, string $precision, string $source, ?string $reason = NULL, array $metadata = []): void {
    $recordedAt = date('Y-m-d H:i:s');
    $actor = (int) CRM_Core_Session::singleton()->getLoggedInContactID();
    $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hash = hash('sha256', implode('|', [$auditId, $type, $occurredAt, $recordedAt, $actor, $source, $reason, $metadataJson]));
    CRM_Core_DAO::executeQuery(
      "INSERT IGNORE INTO civicrm_donrecextra_receipt_event
        (receipt_audit_id, event_type, occurred_at, recorded_at, time_precision,
         actor_contact_id, reason, source, metadata, event_hash)
       VALUES (%1,%2,%3,%4,%5,NULLIF(%6,0),NULLIF(%7,''),%8,%9,%10)",
      [
        1 => [$auditId, 'Integer'],
        2 => [$type, 'String'],
        3 => [$occurredAt, 'String'],
        4 => [$recordedAt, 'String'],
        5 => [$precision, 'String'],
        6 => [$actor ?: 0, 'Integer'],
        7 => [(string) ($reason ?? ''), 'String'],
        8 => [$source, 'String'],
        9 => [$metadataJson, 'String'],
        10 => [$hash, 'String'],
      ]
    );
  }

  private function getPdfHash(int $fileId): ?string {
    if (!$fileId) {
      return NULL;
    }
    [$path] = CRM_Core_BAO_File::path($fileId);
    return $path && is_readable($path) ? hash_file('sha256', $path) : NULL;
  }

  private function normalizeBeneficiaryType(?string $type): string {
    return in_array($type, ['Individual', 'Organization'], TRUE) ? $type : 'Unknown';
  }

  private function getDonrecStructure(): array {
    return [
      'receipt_table' => CRM_Donrec_DataStructure::getTableName('zwb_donation_receipt'),
      'item_table' => CRM_Donrec_DataStructure::getTableName('zwb_donation_receipt_item'),
      'receipt' => CRM_Donrec_DataStructure::getCustomFields('zwb_donation_receipt'),
      'item' => CRM_Donrec_Logic_ReceiptItem::getCustomFields() ?? [],
    ];
  }

}
