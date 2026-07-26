<?php

namespace Civi\Api4\Action\DonationReceipt;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Put explicit contributions or contacts in a durable SQL receipt queue.
 */
class Queue extends AbstractAction {

  /**
   * Explicit contribution IDs to process. This is mutually exclusive with
   * contactIds. With bulk=false there is one task per contribution; with
   * bulk=true contributions are grouped into one task per contact.
   *
   * @var array
   */
  protected array $contributionIds = [];

  /**
   * Contact IDs to process for the supplied date range. This is mutually
   * exclusive with contributionIds. There is one task per contact.
   *
   * @var array
   */
  protected array $contactIds = [];

  /**
   * Saved SearchKit search to resolve once and freeze into the queue.
   *
   * @var int|null
   */
  protected ?int $savedSearchId = NULL;

  /**
   * First contribution date, inclusive. Required when using contactIds.
   *
   * @var string|null
   */
  protected ?string $dateFrom = NULL;

  /**
   * Last contribution date, inclusive. Required when using contactIds.
   *
   * @var string|null
   */
  protected ?string $dateTo = NULL;

  /**
   * Donrec profile ID. Uses Donrec's active default profile when omitted.
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
   * Donrec exporter IDs, for example ["PDF"] or ["EMAIL"].
   *
   * @var array
   */
  protected array $exporters = ['PDF'];

  /**
   * Group eligible contributions into one receipt per contact.
   *
   * @var bool
   */
  protected bool $bulk = TRUE;

  /**
   * Validate the selection without issuing a receipt.
   *
   * @var bool
   */
  protected bool $dryRun = TRUE;

  /**
   * Contact recorded by Donrec as the receipt issuer.
   *
   * @var int|null
   */
  protected ?int $creatorContactId = NULL;

  /**
   * Optional human-readable label saved on the CiviCRM UserJob.
   *
   * @var string|null
   */
  protected ?string $label = NULL;

  public function _run(Result $result): void {
    $result->append((new \CRM_Donrecextra_ReceiptQueue())->create([
      'contribution_ids' => $this->contributionIds,
      'contact_ids' => $this->contactIds,
      'saved_search_id' => $this->savedSearchId,
      'date_from' => $this->dateFrom,
      'date_to' => $this->dateTo,
      'profile_id' => $this->profileId,
      'currency' => $this->currency,
      'exporters' => $this->exporters,
      'bulk' => $this->bulk,
      'dry_run' => $this->dryRun,
      'creator_contact_id' => $this->creatorContactId,
      'label' => $this->label,
    ]));
  }

}
