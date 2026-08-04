<?php

namespace Civi\Api4\Action\DonationReceiptAudit;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class Summary extends AbstractAction {

  /** @required */
  protected string $periodFrom = '';

  /** @required */
  protected string $periodTo = '';

  /** @required */
  protected string $asOf = '';

  protected string $granularity = 'month';

  protected string $dateBasis = 'issued';

  public function _run(Result $result): void {
    $result->append((new \CRM_Donrecextra_AuditReport())->calculate(
      $this->periodFrom,
      $this->periodTo,
      $this->asOf,
      $this->granularity,
      $this->dateBasis
    ));
  }

}
