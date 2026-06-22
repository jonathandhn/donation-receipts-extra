<?php

require_once 'api/Wrapper.php';

/**
 * Capture the original file before Donrec deletes it and timestamp withdrawals.
 */
class CRM_Donrecextra_ApiWrapper_DonationReceiptWithdraw implements API_Wrapper {

  public function fromApiInput($apiRequest) {
    $receiptId = (int) ($apiRequest['params']['rid'] ?? 0);
    if ($receiptId) {
      try {
        (new CRM_Donrecextra_AuditLedger())->reconcileReceipt($receiptId, NULL, 'withdraw_preflight');
      }
      catch (Throwable $e) {
        Civi::log()->error('Donrecextra audit preflight failed; receipt withdrawal will continue.', [
          'receipt_id' => $receiptId,
          'exception' => $e,
        ]);
      }
      $apiRequest['donrecextra_withdraw_started_at'] = date('Y-m-d H:i:s');
    }
    return $apiRequest;
  }

  public function toApiOutput($apiRequest, $result) {
    $receiptId = (int) ($apiRequest['params']['rid'] ?? 0);
    if ($receiptId && empty($result['is_error'])) {
      $eventTime = new DateTimeImmutable($apiRequest['donrecextra_withdraw_started_at'] ?? 'now');
      try {
        (new CRM_Donrecextra_AuditLedger())->reconcileReceipt($receiptId, $eventTime, 'DonationReceipt.withdraw');
      }
      catch (Throwable $e) {
        Civi::log()->error('Donrecextra audit reconciliation failed after receipt withdrawal.', [
          'receipt_id' => $receiptId,
          'exception' => $e,
        ]);
      }
    }
    return $result;
  }

}
