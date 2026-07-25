<?php

use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Headless bridge around Donrec's snapshot and generation engine.
 *
 * Donrec owns all receipt selection, rendering and persistence. This class
 * only gives non-GUI callers one stable entry point and normalized results.
 */
class CRM_Donrecextra_ReceiptGenerator {

  /**
   * Generate or preview donation receipts.
   *
   * @param array $params
   * @return array
   * @throws \CRM_Core_Exception
   */
  public function run(array $params): array {
    $this->assertDonrecAvailable();

    $contactIds = $this->normalizeIds($params['contact_ids'] ?? []);
    $contributionIds = $this->normalizeIds($params['contribution_ids'] ?? []);
    if (!$contactIds && !$contributionIds) {
      throw new CRM_Core_Exception(E::ts('At least one contact ID or contribution ID is required.'));
    }
    if ($contactIds && $contributionIds) {
      throw new CRM_Core_Exception(E::ts('Use contact IDs or contribution IDs, not both.'));
    }

    $profile = $this->resolveProfile((int) ($params['profile_id'] ?? 0));
    $currency = strtoupper(trim((string) ($params['currency'] ?? 'EUR')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
      throw new CRM_Core_Exception(E::ts('Currency must be a three-letter ISO code.'));
    }

    $dateFrom = $this->normalizeDate((string) ($params['date_from'] ?? ''), 'date_from');
    $dateTo = $this->normalizeDate((string) ($params['date_to'] ?? ''), 'date_to');
    if ($dateFrom > $dateTo) {
      throw new CRM_Core_Exception(E::ts('The start date must not be later than the end date.'));
    }

    $exporters = $this->normalizeExporters($params['exporters'] ?? ['PDF']);
    $this->validateExporterRequirements($exporters, $profile);
    $dryRun = !empty($params['dry_run']);
    if (!$dryRun && !$profile->saveOriginalPDF()) {
      throw new CRM_Core_Exception(E::ts('The selected Donrec profile must store the original PDF.'));
    }

    $session = CRM_Core_Session::singleton();
    $previousUserId = $session->get('userID');
    $creatorContactId = $this->resolveCreatorContactId((int) ($params['creator_contact_id'] ?? 0), $previousUserId);
    $snapshot = NULL;

    try {
      // Donrec uses the session contact both as snapshot owner and issued_by.
      $session->set('userID', $creatorContactId);
      $selectorParams = [
        'profile' => $profile->getId(),
        'donrec_contribution_currency' => $currency,
        'donrec_contribution_horizon_relative' => '0',
        'donrec_contribution_horizon_from' => $dateFrom,
        'donrec_contribution_horizon_to' => $dateTo,
      ];
      if ($contactIds) {
        $selectorParams['contact_ids'] = $contactIds;
      }
      else {
        $selectorParams['contribution_ids'] = $contributionIds;
      }

      $selection = CRM_Donrec_Logic_Selector::createSnapshot($selectorParams);
      $snapshot = $selection['snapshot'] ?? NULL;
      if (!$snapshot) {
        return [
          'status' => 'no_eligible_contributions',
          'dry_run' => $dryRun,
          'profile_id' => $profile->getId(),
          'contribution_count' => 0,
          'receipt_ids' => [],
        ];
      }

      if (!empty($selection['intersection_error'])) {
        $intersection = $selection['intersection_error'];
        $snapshot->delete();
        $snapshot = NULL;
        throw new CRM_Core_Exception(E::ts('The selected contributions overlap an open Donrec run: %1', [
          1 => json_encode($intersection),
        ]));
      }

      $snapshotId = (int) $snapshot->getId();
      $selectedContributionIds = $this->getSnapshotContributionIds($snapshotId);
      $statistics = CRM_Donrec_Logic_Snapshot::getStatistic($snapshotId);

      // Validate the explicitly selected data before either a preview or a
      // real issue. This single enforcement point covers API, CiviRules and
      // the one-contribution form.
      (new CRM_Donrecextra_ReceiptDataValidator())->assertValid($profile, $selectedContributionIds);

      if ($dryRun) {
        $snapshot->delete();
        $snapshot = NULL;
        return [
          'status' => 'preview',
          'dry_run' => TRUE,
          'profile_id' => $profile->getId(),
          'contact_count' => (int) $statistics['contact_count'],
          'contribution_count' => (int) $statistics['contribution_count'],
          'total_amount' => (float) $statistics['total_amount'],
          'currency' => $statistics['currency'],
          'contribution_ids' => $selectedContributionIds,
          'receipt_ids' => [],
        ];
      }

      $engineParams = [
        'test' => 0,
        'bulk' => !empty($params['bulk']) ? 1 : 0,
        'exporters' => implode(',', $exporters),
      ];
      $engine = new CRM_Donrec_Logic_Engine();
      $error = $engine->init($snapshotId, $engineParams, TRUE);
      if ($error) {
        throw new CRM_Core_Exception((string) $error);
      }

      $lastStats = [];
      for ($step = 0; $step < 1000; $step++) {
        $lastStats = $engine->nextStep();
        if ((float) ($lastStats['progress'] ?? 0) >= 100) {
          break;
        }
      }
      if ((float) ($lastStats['progress'] ?? 0) < 100) {
        throw new CRM_Core_Exception(E::ts('Donrec did not finish within the safety step limit.'));
      }

      $receiptIds = $this->getReceiptIdsForContributions($selectedContributionIds);
      (new CRM_Donrecextra_AuditLedger())->reconcileReceiptIds($receiptIds, NULL, 'DonationReceipt.generate');

      return [
        'status' => 'generated',
        'dry_run' => FALSE,
        'snapshot_id' => $snapshotId,
        'profile_id' => $profile->getId(),
        'contact_count' => (int) $statistics['contact_count'],
        'contribution_count' => (int) $statistics['contribution_count'],
        'total_amount' => (float) $statistics['total_amount'],
        'currency' => $statistics['currency'],
        'contribution_ids' => $selectedContributionIds,
        'receipt_ids' => $receiptIds,
      ];
    }
    catch (Throwable $e) {
      Civi::log()->error('Donrecextra headless receipt generation failed: {message}', [
        'message' => $e->getMessage(),
      ]);
      throw $e;
    }
    finally {
      $session->set('userID', $previousUserId);
    }
  }

  private function assertDonrecAvailable(): void {
    foreach ([
      'CRM_Donrec_Logic_Selector',
      'CRM_Donrec_Logic_Snapshot',
      'CRM_Donrec_Logic_Engine',
    ] as $class) {
      if (!class_exists($class)) {
        throw new CRM_Core_Exception(E::ts('Donrec is unavailable or disabled.'));
      }
    }
  }

  private function resolveProfile(int $profileId): CRM_Donrec_Logic_Profile {
    $profile = $profileId
      ? CRM_Donrec_Logic_Profile::getProfile($profileId)
      : CRM_Donrec_Logic_Profile::getDefaultProfile();
    if (!$profile->getId() || !$profile->isActive()) {
      throw new CRM_Core_Exception(E::ts('The selected Donrec profile does not exist or is inactive.'));
    }
    return $profile;
  }

  private function normalizeDate(string $date, string $field): string {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
      throw new CRM_Core_Exception(E::ts('%1 must use the YYYY-MM-DD format.', [1 => $field]));
    }
    return $date;
  }

  private function normalizeIds($ids): array {
    if (!is_array($ids)) {
      $ids = [$ids];
    }
    return array_values(array_unique(array_filter(array_map('intval', $ids))));
  }

  private function normalizeExporters($exporters): array {
    if (is_string($exporters)) {
      $exporters = explode(',', $exporters);
    }
    $available = CRM_Donrec_Logic_Exporter::listExporters();
    $exporters = array_values(array_unique(array_filter(array_map('trim', (array) $exporters))));
    if (!$exporters) {
      $exporters = ['PDF'];
    }
    foreach ($exporters as $exporter) {
      if (!in_array($exporter, $available, TRUE)) {
        throw new CRM_Core_Exception(E::ts('Unknown Donrec exporter: %1', [1 => $exporter]));
      }
    }
    return $exporters;
  }

  private function validateExporterRequirements(array $exporters, CRM_Donrec_Logic_Profile $profile): void {
    foreach ($exporters as $exporter) {
      $class = CRM_Donrec_Logic_Exporter::getClassForExporter($exporter);
      if (!class_exists($class)) {
        throw new CRM_Core_Exception(E::ts('Donrec exporter class is unavailable: %1', [1 => $exporter]));
      }
      $instance = new $class();
      $requirements = $instance->checkRequirements($profile);
      if (!empty($requirements['is_error'])) {
        throw new CRM_Core_Exception((string) ($requirements['message'] ?? E::ts('Donrec exporter requirements are not met.')));
      }
    }
  }

  private function resolveCreatorContactId(int $requestedId, $sessionUserId): int {
    if ($requestedId > 0) {
      return $requestedId;
    }
    if (is_numeric($sessionUserId) && (int) $sessionUserId > 0) {
      return (int) $sessionUserId;
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

  private function getSnapshotContributionIds(int $snapshotId): array {
    $ids = [];
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT DISTINCT contribution_id FROM donrec_snapshot WHERE snapshot_id = %1 ORDER BY contribution_id',
      [1 => [$snapshotId, 'Integer']]
    );
    while ($dao->fetch()) {
      $ids[] = (int) $dao->contribution_id;
    }
    return $ids;
  }

  private function getReceiptIdsForContributions(array $contributionIds): array {
    if (!$contributionIds) {
      return [];
    }
    CRM_Donrec_Logic_ReceiptItem::getCustomFields();
    $fields = CRM_Donrec_Logic_ReceiptItem::$_custom_fields;
    $table = CRM_Donrec_DataStructure::getTableName('zwb_donation_receipt_item');
    $ids = implode(',', array_map('intval', $contributionIds));
    $issuedIn = $fields['issued_in'];
    $status = $fields['status'];
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT DISTINCT `$issuedIn` receipt_id FROM `$table` WHERE entity_id IN ($ids) AND `$status` = 'ORIGINAL' ORDER BY `$issuedIn`"
    );
    $receiptIds = [];
    while ($dao->fetch()) {
      $receiptIds[] = (int) $dao->receipt_id;
    }
    return $receiptIds;
  }

}
