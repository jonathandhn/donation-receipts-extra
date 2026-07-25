<?php

use Civi\Api4\Contribution;
use Civi\Api4\DonationReceipt;
use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Confirmation form for issuing a receipt for one explicit contribution.
 */
class CRM_Donrecextra_Form_IssueReceipt extends CRM_Core_Form {

  /** @var array<string, mixed> */
  private array $contribution = [];

  /** @var array<int, string> */
  private array $profiles = [];

  /** @var array<string, string> */
  private array $exporters = [];

  public function preProcess(): void {
    parent::preProcess();
    CRM_Utils_System::setTitle(E::ts('Issue donation receipt'));

    $contributionId = CRM_Utils_Request::retrieve('id', 'Positive', $this, TRUE);
    $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
    $this->contribution = $this->loadContribution($contributionId, $contactId);

    if (CRM_Donrec_Logic_ReceiptItem::hasValidReceiptItem($contributionId, TRUE) !== FALSE) {
      CRM_Core_Session::setStatus(
        E::ts('An original donation receipt already exists for this contribution.'),
        E::ts('Receipt already issued'),
        'warning'
      );
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/donrecextra/receipt/download', [
        'reset' => 1,
        'id' => $contributionId,
        'cid' => $contactId,
      ]));
    }

    $this->profiles = CRM_Donrec_Logic_Profile::getAllActiveNames('is_default', 'DESC');
    if (!$this->profiles) {
      throw new CRM_Core_Exception(E::ts('No active Donrec profile is available.'));
    }
    $this->exporters = $this->getExporterOptions();
  }

  public function buildQuickForm(): void {
    $this->add('hidden', 'id');
    $this->add('hidden', 'cid');
    $this->add('select', 'profile_id', E::ts('Donrec profile'), $this->profiles, TRUE, [
      'class' => 'crm-select2',
    ]);
    $this->add('select', 'exporter', E::ts('Export mode'), $this->exporters, TRUE, [
      'class' => 'crm-select2',
    ]);
    $this->addButtons([
      ['type' => 'submit', 'name' => E::ts('Issue receipt'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
    ]);

    $this->assign('contribution', $this->contribution);
    $this->assign('periodDate', (new DateTimeImmutable($this->contribution['receive_date']))->format('Y-m-d'));
    parent::buildQuickForm();
  }

  public function setDefaultValues(): array {
    return [
      'id' => (int) $this->contribution['id'],
      'cid' => (int) $this->contribution['contact_id'],
      'profile_id' => (int) CRM_Donrec_Logic_Profile::getDefaultProfile()->getId(),
      'exporter' => 'PDF',
    ];
  }

  public function postProcess(): void {
    $values = $this->exportValues();
    $contribution = $this->loadContribution((int) $values['id'], (int) $values['cid']);
    $profileId = (int) $values['profile_id'];
    if (!isset($this->profiles[$profileId])) {
      throw new CRM_Core_Exception(E::ts('The selected Donrec profile is unavailable.'));
    }
    $exporter = (string) ($values['exporter'] ?? '');
    if (!isset($this->exporters[$exporter])) {
      throw new CRM_Core_Exception(E::ts('The selected export mode is unavailable.'));
    }
    if (CRM_Donrec_Logic_ReceiptItem::hasValidReceiptItem((int) $contribution['id'], TRUE) !== FALSE) {
      throw new CRM_Core_Exception(E::ts('An original donation receipt already exists for this contribution.'));
    }

    $day = (new DateTimeImmutable($contribution['receive_date']))->format('Y-m-d');
    $result = DonationReceipt::generate(FALSE)
      ->setContributionIds([(int) $contribution['id']])
      ->setDateFrom($day)
      ->setDateTo($day)
      ->setCurrency((string) $contribution['currency'])
      ->setProfileId($profileId)
      ->setBulk(FALSE)
      ->setExporters([$exporter])
      ->setDryRun(FALSE)
      ->execute()
      ->first();

    if (($result['status'] ?? NULL) !== 'generated' || empty($result['receipt_ids'])) {
      throw new CRM_Core_Exception(E::ts('No receipt could be issued because this contribution is no longer eligible.'));
    }

    CRM_Core_Session::setStatus(
      E::ts('A donation receipt was issued for contribution #%1.', [1 => $contribution['id']]),
      E::ts('Donation receipt issued'),
      'success'
    );
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contact/view/contribution', [
      'reset' => 1,
      'id' => (int) $contribution['id'],
      'cid' => (int) $contribution['contact_id'],
      'action' => 'view',
      'context' => 'dashboard',
      'selectedChild' => 'contribute',
    ]));
  }

  /**
   * Re-read and validate the record, rather than trusting URL or form values.
   *
   * @return array<string, mixed>
   */
  private function loadContribution(int $contributionId, int $contactId): array {
    $contribution = Contribution::get(FALSE)
      ->addSelect('id', 'contact_id', 'total_amount', 'currency', 'receive_date', 'contribution_status_id', 'is_test')
      ->addWhere('id', '=', $contributionId)
      ->addWhere('contact_id', '=', $contactId)
      ->setLimit(1)
      ->execute()
      ->first();
    if (!$contribution) {
      throw new CRM_Core_Exception(E::ts('Invalid contribution receipt request.'), 403);
    }
    return $contribution;
  }

  /**
   * Use Donrec's installed exporters rather than maintaining a duplicate list.
   * Requirements specific to the selected profile are checked again by the
   * shared generator immediately before the receipt is issued.
   *
   * @return array<string, string>
   */
  private function getExporterOptions(): array {
    $options = [];
    foreach (CRM_Donrec_Logic_Exporter::listExporters() as $exporter) {
      $class = CRM_Donrec_Logic_Exporter::getClassForExporter($exporter);
      if (class_exists($class)) {
        $options[$exporter] = $class::name();
      }
    }
    if (!$options) {
      throw new CRM_Core_Exception(E::ts('No Donrec export mode is available.'));
    }
    return $options;
  }

}
