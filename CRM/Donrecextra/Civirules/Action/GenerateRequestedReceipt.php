<?php

if (!class_exists('CRM_Civirules_Action')) {
  return;
}

use Civi\Api4\DonationReceipt;
use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Final CiviRules action which generates a requested donation receipt.
 *
 * Delays, remediation and business conditions remain regular CiviRules
 * concerns. This action only resolves the request contact and immutable
 * request boundary, re-evaluates the matching contributions, and calls the
 * headless Donrec API with explicit contribution IDs.
 */
class CRM_Donrecextra_Civirules_Action_GenerateRequestedReceipt extends CRM_Civirules_Action {

  /**
   * Keep the business delay entirely under CiviRules control.
   *
   * A Contribution "is added" trigger runs inside the contribution creation
   * transaction. Donrec itself registers a post-commit cleanup which removes
   * receipt custom values copied during contribution creation. Generating the
   * receipt before that cleanup therefore creates a PDF and then loses its
   * receipt item. Queueing the action one second later is only a transaction
   * boundary; it is not a statutory waiting period. Any future date already
   * calculated by the administrator's CiviRules delay remains untouched.
   */
  public function delayTo(DateTime $date, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $now = new DateTime();
    if ($date > $now) {
      return FALSE;
    }

    if ($this->getTriggerContributionId($triggerData) && CRM_Core_Transaction::isActive()) {
      return $now->modify('+1 second');
    }

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactId = (int) $triggerData->getContactId();
    if (!$contactId) {
      throw new CRM_Core_Exception(E::ts('The CiviRules trigger did not provide a contact ID.'));
    }

    $triggerContributionId = $this->getTriggerContributionId($triggerData);
    if ($triggerContributionId) {
      // Automatic donor flow: the delayed rule re-evaluates only the payment
      // which originally triggered it. Later payments belong to their own rule
      // execution and can never be absorbed into this receipt.
      $contribution = $this->loadEligibleContribution($triggerContributionId, $contactId);
      $contributionIds = $contribution ? [$triggerContributionId] : [];
      $requestDate = $contribution
        ? new DateTimeImmutable($contribution['receive_date'])
        : $this->resolveContributionTriggerDate($triggerData);
      $dateFrom = $requestDate->format('Y-m-d');
      $dateTo = $dateFrom;
    }
    else {
      // On-demand member flow: include the still-eligible payments made from
      // January 1 up to the exact creation time of the request Activity.
      $requestDate = $this->resolveRequestDate($triggerData);
      $dateFrom = $requestDate->format('Y-01-01');
      $dateTo = $requestDate->format('Y-m-d');
      $contributionIds = $this->findContributionIds($contactId, $dateFrom, $requestDate);
    }

    if (!$contributionIds) {
      $this->logAction(E::ts(
        'No completed EUR contribution remained eligible between %1 and the trigger boundary %2.',
        [1 => $dateFrom, 2 => $requestDate->format('Y-m-d H:i:s')]
      ), $triggerData);
      return;
    }

    try {
      $actionParams = $this->getActionParameters();
      $exporter = ($actionParams['delivery_mode'] ?? 'PDF') === 'EmailPDF' ? 'EmailPDF' : 'PDF';
      $contactType = $this->getContactType($contactId);
      $profileId = $contactType === 'Organization'
        ? (int) ($actionParams['organization_profile_id'] ?? 0)
        : (int) ($actionParams['individual_profile_id'] ?? 0);
      if (!$profileId) {
        throw new CRM_Core_Exception(E::ts(
          'No Donrec profile is configured for contact type %1.',
          [1 => $contactType]
        ));
      }

      $generation = DonationReceipt::generate(FALSE)
        ->setContributionIds($contributionIds)
        ->setDateFrom($dateFrom)
        ->setDateTo($dateTo)
        ->setCurrency('EUR')
        ->setBulk(TRUE)
        ->setExporters([$exporter])
        ->setDryRun(FALSE);
      $result = $generation
        ->setProfileId($profileId)
        ->execute()
        ->first();

      $this->logAction(E::ts(
        'Donrec generation finished with status %1 for %2 contribution(s), using profile %3 for %4.',
        [
          1 => $result['status'] ?? 'unknown',
          2 => count($contributionIds),
          3 => $profileId,
          4 => $contactType,
        ]
      ), $triggerData);
    }
    catch (Throwable $e) {
      $this->logAction(E::ts('Donrec generation failed: %1', [1 => $e->getMessage()]), $triggerData, \Psr\Log\LogLevel::ERROR);
      throw $e;
    }
  }

  /**
   * @param int $ruleActionId
   * @return string
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl(
      'civicrm/civirule/form/action/donrecextra/generate-receipt',
      $ruleActionId
    );
  }

  /**
   * Contribution triggers support automatic one-payment receipts. Activity
   * triggers support grouped receipts requested by a member.
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity('Contribution') || $trigger->doesProvideEntity('Activity');
  }

  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    $delivery = ($params['delivery_mode'] ?? 'PDF') === 'EmailPDF'
      ? E::ts('send PDF by email')
      : E::ts('create downloadable PDF');
    return E::ts(
      'Generate a receipt and %1 (individual profile: %2; organization profile: %3).',
      [
        1 => $delivery,
        2 => (int) ($params['individual_profile_id'] ?? 0),
        3 => (int) ($params['organization_profile_id'] ?? 0),
      ]
    );
  }

  public function getHelpText(string $context): string {
    return E::ts(
      'Contribution triggers generate one receipt for that payment. Activity triggers must be restricted to Target contacts and generate a year-to-request grouped receipt. Configure waiting periods and validation separately.'
    );
  }

  /**
   * Return the contribution which initiated a Contribution-based rule.
   */
  private function getTriggerContributionId(CRM_Civirules_TriggerData_TriggerData $triggerData): int {
    $contribution = $triggerData->getEntityData('Contribution');
    return (int) ($contribution['id'] ?? $contribution['contribution_id'] ?? 0);
  }

  /**
   * Re-read the triggering payment after the CiviRules delay.
   *
   * @return array<string,mixed>
   */
  private function loadEligibleContribution(int $contributionId, int $contactId): array {
    $completedStatusId = $this->getCompletedStatusId();
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT id, contact_id, receive_date
         FROM civicrm_contribution
        WHERE id = %1
          AND contact_id = %2
          AND contribution_status_id = %3
          AND currency = %4
          AND is_test = 0',
      [
        1 => [$contributionId, 'Integer'],
        2 => [$contactId, 'Integer'],
        3 => [$completedStatusId, 'Integer'],
        4 => ['EUR', 'String'],
      ]
    );
    return $dao->fetch() ? $dao->toArray() : [];
  }

  /**
   * Keep a deterministic date for logging when the delayed contribution is no
   * longer eligible (refund, chargeback or cancellation).
   */
  private function resolveContributionTriggerDate(CRM_Civirules_TriggerData_TriggerData $triggerData): DateTimeImmutable {
    $contribution = $triggerData->getEntityData('Contribution');
    if (!empty($contribution['receive_date'])) {
      return new DateTimeImmutable($contribution['receive_date']);
    }
    if (!empty($triggerData->delayedSubmitDateTime)) {
      $date = DateTimeImmutable::createFromFormat('YmdHis', $triggerData->delayedSubmitDateTime);
      if ($date instanceof DateTimeImmutable) {
        return $date;
      }
    }
    throw new CRM_Core_Exception(E::ts('Unable to determine the triggering contribution date.'));
  }

  /**
   * Resolve the immutable time at which the portal request was created.
   */
  private function resolveRequestDate(CRM_Civirules_TriggerData_TriggerData $triggerData): DateTimeImmutable {
    $activity = $triggerData->getEntityData('Activity');
    $activityId = (int) ($activity['id'] ?? 0);

    if ($activityId) {
      $dao = CRM_Core_DAO::executeQuery(
        'SELECT created_date FROM civicrm_activity WHERE id = %1',
        [1 => [$activityId, 'Integer']]
      );
      if ($dao->fetch() && !empty($dao->created_date)) {
        return new DateTimeImmutable($dao->created_date);
      }
    }

    if (!empty($activity['created_date'])) {
      return new DateTimeImmutable($activity['created_date']);
    }

    if (!empty($triggerData->delayedSubmitDateTime)) {
      $date = DateTimeImmutable::createFromFormat('YmdHis', $triggerData->delayedSubmitDateTime);
      if ($date instanceof DateTimeImmutable) {
        return $date;
      }
    }

    throw new CRM_Core_Exception(E::ts('Unable to determine the original receipt request date.'));
  }

  /**
   * Re-evaluate contributions at execution time while excluding every payment
   * made after the portal request, including payments made later the same day.
   *
   * @return int[]
   */
  private function findContributionIds(int $contactId, string $dateFrom, DateTimeImmutable $requestDate): array {
    $completedStatusId = $this->getCompletedStatusId();
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT id
         FROM civicrm_contribution
        WHERE contact_id = %1
          AND receive_date >= %2
          AND receive_date <= %3
          AND contribution_status_id = %4
          AND currency = %5
          AND is_test = 0
        ORDER BY receive_date, id',
      [
        1 => [$contactId, 'Integer'],
        2 => [$dateFrom . ' 00:00:00', 'String'],
        3 => [$requestDate->format('Y-m-d H:i:s'), 'String'],
        4 => [$completedStatusId, 'Integer'],
        5 => ['EUR', 'String'],
      ]
    );

    $ids = [];
    while ($dao->fetch()) {
      $ids[] = (int) $dao->id;
    }
    return $ids;
  }

  private function getCompletedStatusId(): int {
    $completedStatusId = (int) CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution',
      'contribution_status_id',
      'Completed'
    );
    if (!$completedStatusId) {
      throw new CRM_Core_Exception(E::ts('The Completed contribution status could not be resolved.'));
    }

    return $completedStatusId;
  }

  /**
   * Resolve the donor type at execution time, after any CiviRules delay.
   */
  private function getContactType(int $contactId): string {
    $contactType = (string) CRM_Core_DAO::singleValueQuery(
      'SELECT contact_type FROM civicrm_contact WHERE id = %1 AND is_deleted = 0',
      [1 => [$contactId, 'Integer']]
    );
    if (!in_array($contactType, ['Individual', 'Organization'], TRUE)) {
      throw new CRM_Core_Exception(E::ts(
        'Contact %1 is neither an Individual nor an Organization.',
        [1 => $contactId]
      ));
    }
    return $contactType;
  }

}
