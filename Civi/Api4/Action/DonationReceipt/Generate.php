<?php

namespace Civi\Api4\Action\DonationReceipt;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Generate donation receipts through Donrec without using its GUI.
 */
class Generate extends AbstractAction {

  /**
   * Contact IDs whose eligible contributions should be selected.
   *
   * @var array
   */
  protected array $contactIds = [];

  /**
   * Explicit contribution IDs to select instead of contacts.
   *
   * @var array
   */
  protected array $contributionIds = [];

  /**
   * First eligible contribution date, inclusive.
   *
   * @var string
   * @required
   */
  protected string $dateFrom = '';

  /**
   * Last eligible contribution date, inclusive.
   *
   * @var string
   * @required
   */
  protected string $dateTo = '';

  /**
   * Donrec profile ID. Uses the active default profile when omitted.
   *
   * @var int|null
   */
  protected ?int $profileId = NULL;

  /**
   * Receipt currency.
   *
   * @var string
   */
  protected string $currency = 'EUR';

  /**
   * Group all selected contributions per contact into one receipt.
   *
   * @var bool
   */
  protected bool $bulk = TRUE;

  /**
   * Donrec exporter IDs. PDF is suitable for portal downloads.
   *
   * @var array
   */
  protected array $exporters = ['PDF'];

  /**
   * Validate and preview selection, then discard the temporary snapshot.
   * Explicitly set to false to issue real receipts.
   *
   * @var bool
   */
  protected bool $dryRun = TRUE;

  /**
   * Contact recorded as snapshot creator and receipt issuer.
   * Defaults to the current user, then the domain contact for cron calls.
   *
   * @var int|null
   */
  protected ?int $creatorContactId = NULL;

  public function _run(Result $result): void {
    $generator = new \CRM_Donrecextra_ReceiptGenerator();
    $result->append($generator->run([
      'contact_ids' => $this->contactIds,
      'contribution_ids' => $this->contributionIds,
      'date_from' => $this->dateFrom,
      'date_to' => $this->dateTo,
      'profile_id' => $this->profileId,
      'currency' => $this->currency,
      'bulk' => $this->bulk,
      'exporters' => $this->exporters,
      'dry_run' => $this->dryRun,
      'creator_contact_id' => $this->creatorContactId,
    ]));
  }

}
