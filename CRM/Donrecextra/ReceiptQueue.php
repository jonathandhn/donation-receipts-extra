<?php

use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Durable, resumable Donrec runs.
 *
 * Queue items live in civicrm_queue_item and are linked to a UserJob. A task
 * takes either a contribution work item or a contact work item. Contribution
 * work items retain explicit IDs; contact work items retain an immutable date
 * range. Both forms are safe to resume after a stopped worker.
 */
class CRM_Donrecextra_ReceiptQueue {

  private const QUEUE_PREFIX = 'donrecextra_receipt_';

  /**
   * Create a persistent SQL queue and its CiviCRM UserJob.
   */
  public function create(array $params): array {
    $contributionIds = $this->normalizeIds($params['contribution_ids'] ?? []);
    $contactIds = $this->normalizeIds($params['contact_ids'] ?? []);
    $savedSearchId = (int) ($params['saved_search_id'] ?? 0);
    $selectedSources = (int) (bool) $contributionIds + (int) (bool) $contactIds + (int) ($savedSearchId > 0);
    if (!$selectedSources) {
      throw new CRM_Core_Exception(E::ts('At least one contact ID, contribution ID or saved search is required.'));
    }
    if ($selectedSources > 1) {
      throw new CRM_Core_Exception(E::ts('Use contact IDs, contribution IDs or a saved search, not a combination.'));
    }
    $savedSearch = NULL;
    if ($savedSearchId) {
      $savedSearch = $this->resolveSavedSearch($savedSearchId);
      if ($savedSearch['api_entity'] === 'Contribution') {
        $contributionIds = $savedSearch['ids'];
      }
      else {
        $contactIds = $savedSearch['ids'];
      }
    }
    // Queue rows are the durable frozen worklist. Keep only auditable search
    // provenance in UserJob metadata; never duplicate 35,000 IDs in JSON.
    $savedSearchMetadata = $savedSearch;
    unset($savedSearchMetadata['ids']);

    $queueName = self::QUEUE_PREFIX . gmdate('YmdHis') . '_' . bin2hex(random_bytes(4));
    $creatorContactId = $this->resolveCreatorContactId((int) ($params['creator_contact_id'] ?? 0));
    $options = [
      'profile_id' => !empty($params['profile_id']) ? (int) $params['profile_id'] : NULL,
      'currency' => strtoupper(trim((string) ($params['currency'] ?? 'EUR'))),
      'exporters' => $this->normalizeExporters($params['exporters'] ?? ['PDF']),
      'bulk' => !empty($params['bulk']),
      'dry_run' => !empty($params['dry_run']),
      'creator_contact_id' => $creatorContactId,
    ];
    if (!preg_match('/^[A-Z]{3}$/', $options['currency'])) {
      throw new CRM_Core_Exception(E::ts('Currency must be a three-letter ISO code.'));
    }

    // Validate exporter names before creating a run which can never succeed.
    // ReceiptGenerator performs the complete profile validation at execution.
    $this->assertGeneratorOptions($options);

    $queue = Civi::queue($queueName, [
      'type' => 'Sql',
      'runner' => 'task',
      'error' => 'abort',
    ]);

    $workItems = $contributionIds
      ? $this->buildContributionWorkItems($contributionIds, $options['bulk'])
      : $this->buildContactWorkItems($contactIds, (string) ($params['date_from'] ?? ''), (string) ($params['date_to'] ?? ''));

    $label = trim((string) ($params['label'] ?? ''));
    if ($label === '') {
      $label = E::ts('Donation receipt run (%1 tasks)', [1 => count($workItems)]);
    }

    $metadata = [
      'donrecextra_receipt_run' => TRUE,
      'options' => $options,
      'source' => $contributionIds ? 'contributions' : 'contacts',
      'saved_search' => $savedSearchMetadata,
      'counts' => [
        'total' => count($workItems),
        'processed' => 0,
        'issued' => 0,
        'skipped' => 0,
        'failed' => 0,
      ],
      // Kept intentionally small: queue rows remain the full durable worklist.
      'errors' => [],
    ];

    $userJob = \Civi\Api4\UserJob::create(FALSE)
      ->setValues([
        'name' => $queueName,
        'label' => $label,
        // UserJob currently has no generic "receipt generation" type. This
        // established core type is only used for its persistent job lifecycle.
        'job_type' => 'contact_import',
        'status_id:name' => 'scheduled',
        'queue_id.name' => $queueName,
        'created_id' => $creatorContactId,
        'metadata' => $metadata,
      ])
      ->execute()
      ->single();
    $userJobId = (int) $userJob['id'];

    foreach ($workItems as $workItem) {
      $queue->createItem(new CRM_Queue_Task(
        [self::class, 'processWorkItem'],
        [$userJobId, $workItem, $options],
        $this->getWorkItemTitle($workItem)
      ));
    }

    return [
      'user_job_id' => $userJobId,
      'queue_name' => $queueName,
      'source' => $contributionIds ? 'contributions' : 'contacts',
      'saved_search_id' => $savedSearch['id'] ?? NULL,
      'work_item_count' => count($workItems),
      'contribution_count' => count($contributionIds),
      'contact_count' => count($contactIds),
      'bulk' => $options['bulk'],
      'dry_run' => $options['dry_run'],
      'run_command' => sprintf(
        'cv api4 Queue.run queue=%s maxRequests=100 maxDuration=120',
        escapeshellarg($queueName)
      ),
    ];
  }

  /**
   * Execute one durable task. Called only by CiviCRM's queue runner.
   */
  public static function processWorkItem(CRM_Queue_TaskContext $context, int $userJobId, array $workItem, array $options): bool {
    $service = new self();
    $service->markStarted($userJobId);

    try {
      $generatorParams = [
        'date_from' => $workItem['date_from'],
        'date_to' => $workItem['date_to'],
        'profile_id' => $options['profile_id'] ?? NULL,
        'currency' => $options['currency'] ?? 'EUR',
        'exporters' => $options['exporters'] ?? ['PDF'],
        'dry_run' => !empty($options['dry_run']),
        'bulk' => !empty($options['bulk']),
        'creator_contact_id' => $options['creator_contact_id'] ?? NULL,
      ];
      if ($workItem['source'] === 'contact') {
        $generatorParams['contact_ids'] = [$workItem['contact_id']];
      }
      else {
        $generatorParams['contribution_ids'] = $workItem['contribution_ids'];
      }
      $result = (new CRM_Donrecextra_ReceiptGenerator())->run($generatorParams);

      $service->recordResult($userJobId, $result);
      return TRUE;
    }
    catch (Throwable $e) {
      $service->recordError($userJobId, $service->getWorkItemKey($workItem), $e->getMessage());
      // The SQL queue uses error=abort. Leaving this item in place is the
      // resume contract: fix the data, then run the same queue again.
      throw $e;
    }
  }

  /**
   * Return durable queue statistics and UserJob counters for CV or an Afform.
   */
  public function status(int $userJobId): array {
    $job = \Civi\Api4\UserJob::get(FALSE)
      ->addSelect('id', 'name', 'label', 'created_date', 'start_date', 'end_date', 'status_id:name', 'metadata', 'queue_id.name')
      ->addWhere('id', '=', $userJobId)
      ->execute()
      ->first();
    if (!$job || empty($job['metadata']['donrecextra_receipt_run'])) {
      throw new CRM_Core_Exception(E::ts('Donation receipt queue job %1 was not found.', [1 => $userJobId]));
    }

    $queueName = $job['queue_id.name'] ?? $job['name'];
    $queue = Civi::queue($queueName);
    return [
      'user_job_id' => (int) $job['id'],
      'label' => $job['label'],
      'queue_name' => $queueName,
      'job_status' => $job['status_id:name'],
      'queue_status' => $queue->getStatus(),
      'queue_total' => (int) $queue->getStatistic('total'),
      'queue_ready' => (int) $queue->getStatistic('ready'),
      'queue_blocked' => (int) $queue->getStatistic('blocked'),
      'created_date' => $job['created_date'],
      'start_date' => $job['start_date'],
      'end_date' => $job['end_date'],
      'counts' => $job['metadata']['counts'] ?? [],
      'errors' => $job['metadata']['errors'] ?? [],
    ];
  }

  /**
   * List recent Donrec Extra queue jobs for the campaign screen.
   */
  public function listJobs(int $limit = 25): array {
    $jobs = \Civi\Api4\UserJob::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', 'LIKE', self::QUEUE_PREFIX . '%')
      ->addOrderBy('created_date', 'DESC')
      ->setLimit($limit)
      ->execute();
    $result = [];
    foreach ($jobs as $job) {
      try {
        $result[] = $this->status((int) $job['id']);
      }
      catch (Throwable $e) {
        Civi::log()->warning('Unable to read Donrec Extra receipt queue {id}: {message}', [
          'id' => $job['id'],
          'message' => $e->getMessage(),
        ]);
      }
    }
    return $result;
  }

  /**
   * Reactivate an aborted queue and run a deliberately small web batch.
   * Cron and CV users should call Queue.run directly with their own limits.
   */
  public function run(int $userJobId, int $maxRequests = 10, int $maxDuration = 20): array {
    $status = $this->status($userJobId);
    if ($status['queue_status'] === 'completed') {
      return $status;
    }
    $queue = Civi::queue($status['queue_name']);
    if ($queue->getStatus() !== 'active') {
      $queue->setStatus('active');
    }
    \Civi\Api4\Queue::run(FALSE)
      ->setQueue($status['queue_name'])
      ->setMaxRequests(max(1, min(50, $maxRequests)))
      ->setMaxDuration(max(1, min(60, $maxDuration)))
      ->execute();
    return $this->status($userJobId);
  }

  private function assertGeneratorOptions(array $options): void {
    // A dry run of a non-existent contribution would reach selection, so use
    // the public generator validation path with no task-specific data only at
    // execution time. Here we validate exporters without issuing anything.
    $available = CRM_Donrec_Logic_Exporter::listExporters();
    foreach ($options['exporters'] as $exporter) {
      if (!in_array($exporter, $available, TRUE)) {
        throw new CRM_Core_Exception(E::ts('Unknown Donrec exporter: %1', [1 => $exporter]));
      }
    }
  }

  private function markStarted(int $userJobId): void {
    $job = \Civi\Api4\UserJob::get(FALSE)
      ->addSelect('id', 'start_date')
      ->addWhere('id', '=', $userJobId)
      ->execute()
      ->first();
    if ($job && empty($job['start_date'])) {
      \Civi\Api4\UserJob::update(FALSE)
        ->addWhere('id', '=', $userJobId)
        ->addValue('status_id:name', 'in_progress')
        ->addValue('start_date', 'now')
        ->execute();
    }
  }

  private function recordResult(int $userJobId, array $result): void {
    $this->updateMetadata($userJobId, function (array &$metadata) use ($result): void {
      $counts = &$metadata['counts'];
      $counts['processed'] = (int) ($counts['processed'] ?? 0) + 1;
      if (($result['status'] ?? NULL) === 'generated') {
        $counts['issued'] = (int) ($counts['issued'] ?? 0) + count($result['receipt_ids'] ?? []);
      }
      else {
        $counts['skipped'] = (int) ($counts['skipped'] ?? 0) + 1;
      }
    });
  }

  private function recordError(int $userJobId, string $workItemKey, string $message): void {
    $this->updateMetadata($userJobId, function (array &$metadata) use ($workItemKey, $message): void {
      $metadata['errors'] ??= [];
      if (count($metadata['errors']) < 50 || isset($metadata['errors'][$workItemKey])) {
        $metadata['errors'][$workItemKey] = $message;
      }
      $metadata['counts']['failed'] = count($metadata['errors']);
    });
  }

  /**
   * Serialize counter updates when two workers are active on one queue.
   */
  private function updateMetadata(int $userJobId, callable $mutator): void {
    $transaction = new CRM_Core_Transaction();
    try {
      CRM_Core_DAO::executeQuery('SELECT id FROM civicrm_user_job WHERE id = %1 FOR UPDATE', [
        1 => [$userJobId, 'Integer'],
      ]);
      $job = \Civi\Api4\UserJob::get(FALSE)
        ->addSelect('metadata')
        ->addWhere('id', '=', $userJobId)
        ->execute()
        ->first();
      if (!$job) {
        throw new CRM_Core_Exception(E::ts('Donation receipt queue job %1 was not found.', [1 => $userJobId]));
      }
      $metadata = $job['metadata'] ?? [];
      $mutator($metadata);
      \Civi\Api4\UserJob::update(FALSE)
        ->addWhere('id', '=', $userJobId)
        ->addValue('metadata', $metadata)
        ->execute();
      $transaction->commit();
    }
    catch (Throwable $e) {
      $transaction->rollback();
      throw $e;
    }
  }

  private function normalizeIds($ids): array {
    if (!is_array($ids)) {
      $ids = [$ids];
    }
    return array_values(array_unique(array_filter(array_map('intval', $ids))));
  }

  /**
   * Resolve a Contact or Contribution SavedSearch once. Its immutable output,
   * not the search definition, is what is queued and later resumed.
   */
  private function resolveSavedSearch(int $savedSearchId): array {
    $savedSearch = \Civi\Api4\SavedSearch::get(FALSE)
      ->addSelect('id', 'name', 'label', 'api_entity', 'api_params')
      ->addWhere('id', '=', $savedSearchId)
      ->execute()
      ->first();
    if (!$savedSearch || !in_array($savedSearch['api_entity'], ['Contact', 'Contribution'], TRUE)) {
      throw new CRM_Core_Exception(E::ts('The selected saved search must search Contacts or Contributions.'));
    }
    $apiParams = (array) ($savedSearch['api_params'] ?? []);
    // A receipt campaign always needs the complete frozen result. Do not
    // inherit a SearchKit display pager, its selected columns or ACL bypass.
    unset($apiParams['limit'], $apiParams['offset'], $apiParams['orderBy'], $apiParams['checkPermissions']);
    $apiParams['select'] = ['id'];
    $apiParams['checkPermissions'] = TRUE;
    $result = civicrm_api4($savedSearch['api_entity'], 'get', $apiParams);
    $ids = $this->normalizeIds(array_column($result->getArrayCopy(), 'id'));
    if (!$ids) {
      throw new CRM_Core_Exception(E::ts('The selected saved search returned no Contacts or Contributions.'));
    }
    return [
      'id' => (int) $savedSearch['id'],
      'name' => (string) $savedSearch['name'],
      'label' => (string) ($savedSearch['label'] ?: $savedSearch['name']),
      'api_entity' => (string) $savedSearch['api_entity'],
      'result_count' => count($ids),
      'ids' => $ids,
    ];
  }

  /**
   * Build explicit-contribution tasks. In bulk mode, selected contributions
   * are grouped by contact so that Donrec makes at most one bulk receipt per
   * task while never selecting contributions outside the original list.
   */
  private function buildContributionWorkItems(array $contributionIds, bool $bulk): array {
    $contributions = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('id', 'contact_id', 'receive_date')
      ->addWhere('id', 'IN', $contributionIds)
      ->execute()
      ->indexBy('id');
    if (count($contributions) !== count($contributionIds)) {
      throw new CRM_Core_Exception(E::ts('At least one selected contribution no longer exists.'));
    }

    $groups = [];
    foreach ($contributionIds as $contributionId) {
      $contribution = $contributions[$contributionId];
      $date = substr((string) ($contribution['receive_date'] ?? ''), 0, 10);
      if (!$contribution['contact_id'] || !$date || !$this->isDate($date)) {
        throw new CRM_Core_Exception(E::ts('Contribution %1 has no valid contact or contribution date.', [1 => $contributionId]));
      }
      $key = $bulk ? (int) $contribution['contact_id'] : $contributionId;
      $groups[$key] ??= [
        'source' => 'contribution',
        'contact_id' => (int) $contribution['contact_id'],
        'contribution_ids' => [],
        'date_from' => $date,
        'date_to' => $date,
      ];
      $groups[$key]['contribution_ids'][] = $contributionId;
      $groups[$key]['date_from'] = min($groups[$key]['date_from'], $date);
      $groups[$key]['date_to'] = max($groups[$key]['date_to'], $date);
    }
    return array_values($groups);
  }

  /**
   * Build one immutable date-window task per contact.
   */
  private function buildContactWorkItems(array $contactIds, string $dateFrom, string $dateTo): array {
    if (!$this->isDate($dateFrom) || !$this->isDate($dateTo)) {
      throw new CRM_Core_Exception(E::ts('dateFrom and dateTo must use the YYYY-MM-DD format when using contact IDs.'));
    }
    if ($dateFrom > $dateTo) {
      throw new CRM_Core_Exception(E::ts('The start date must not be later than the end date.'));
    }
    return array_map(static fn(int $contactId): array => [
      'source' => 'contact',
      'contact_id' => $contactId,
      'date_from' => $dateFrom,
      'date_to' => $dateTo,
    ], $contactIds);
  }

  private function getWorkItemTitle(array $workItem): string {
    if ($workItem['source'] === 'contact') {
      return E::ts('Issue receipt(s) for contact %1', [1 => $workItem['contact_id']]);
    }
    if (count($workItem['contribution_ids']) === 1) {
      return E::ts('Issue receipt for contribution %1', [1 => $workItem['contribution_ids'][0]]);
    }
    return E::ts('Issue grouped receipt for contact %1', [1 => $workItem['contact_id']]);
  }

  private function getWorkItemKey(array $workItem): string {
    return $workItem['source'] === 'contact'
      ? 'contact:' . $workItem['contact_id']
      : 'contribution:' . implode(',', $workItem['contribution_ids']);
  }

  private function isDate(string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return (bool) $parsed && $parsed->format('Y-m-d') === $date;
  }

  private function normalizeExporters($exporters): array {
    if (is_string($exporters)) {
      $exporters = explode(',', $exporters);
    }
    $exporters = array_values(array_unique(array_filter(array_map('trim', (array) $exporters))));
    return $exporters ?: ['PDF'];
  }

  private function resolveCreatorContactId(int $requestedId): int {
    if ($requestedId > 0) {
      return $requestedId;
    }
    $sessionId = (int) CRM_Core_Session::getLoggedInContactID();
    if ($sessionId > 0) {
      return $sessionId;
    }
    $domainContactId = (int) CRM_Core_DAO::singleValueQuery(
      'SELECT contact_id FROM civicrm_domain WHERE id = %1',
      [1 => [(int) CRM_Core_Config::domainID(), 'Integer']]
    );
    if (!$domainContactId) {
      throw new CRM_Core_Exception(E::ts('No creator contact could be resolved for the Donrec run.'));
    }
    return $domainContactId;
  }

}
