<?php

namespace Civi\Api4\Action\DonationReceipt;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Inspect a durable SQL receipt queue and its associated UserJob.
 */
class QueueStatus extends AbstractAction {

  /**
   * CiviCRM UserJob ID returned by DonationReceipt.queue.
   *
   * @var int
   * @required
   */
  protected int $userJobId;

  public function _run(Result $result): void {
    $result->append((new \CRM_Donrecextra_ReceiptQueue())->status($this->userJobId));
  }

}
