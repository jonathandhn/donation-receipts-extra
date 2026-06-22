<?php

namespace Civi\Api4\Action\DonationReceiptAudit;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Reconcile the append-only ledger with all original Donrec receipts.
 */
class Reconcile extends AbstractAction {

  public function _run(Result $result): void {
    $result->append((new \CRM_Donrecextra_AuditLedger())->reconcileAll());
  }

}
