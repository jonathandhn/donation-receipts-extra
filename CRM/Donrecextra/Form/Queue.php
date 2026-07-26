<?php

use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Campaign screen for durable receipt queues.
 */
class CRM_Donrecextra_Form_Queue extends CRM_Core_Form {

  private array $profiles = [];
  private array $exporters = [];
  private array $jobs = [];
  private array $savedSearches = [];

  public function preProcess(): void {
    parent::preProcess();
    CRM_Utils_System::setTitle(E::ts('Donation receipt campaigns'));
    $this->profiles = CRM_Donrec_Logic_Profile::getAllActiveNames('is_default', 'DESC');
    if (!$this->profiles) {
      throw new CRM_Core_Exception(E::ts('No active Donrec profile is available.'));
    }
    foreach (CRM_Donrec_Logic_Exporter::listExporters() as $exporter) {
      $class = CRM_Donrec_Logic_Exporter::getClassForExporter($exporter);
      if (class_exists($class)) {
        $this->exporters[$exporter] = $class::name();
      }
    }
    if (!$this->exporters) {
      throw new CRM_Core_Exception(E::ts('No Donrec export mode is available.'));
    }
    $this->jobs = (new CRM_Donrecextra_ReceiptQueue())->listJobs();
    foreach (\Civi\Api4\SavedSearch::get(FALSE)
      ->addSelect('id', 'name', 'label', 'api_entity')
      ->addWhere('api_entity', 'IN', ['Contact', 'Contribution'])
      ->addOrderBy('label', 'ASC')
      ->execute() as $search) {
      $this->savedSearches[(int) $search['id']] = $search;
    }
  }

  public function buildQuickForm(): void {
    $this->add('select', 'selection_mode', E::ts('Selection'), [
      'contributions' => E::ts('Explicit contributions'),
      'contacts' => E::ts('Contacts in a period'),
      'saved_search' => E::ts('Saved SearchKit search'),
    ], TRUE, ['class' => 'crm-select2']);
    $this->add('textarea', 'contribution_ids', E::ts('Contribution IDs'), ['rows' => 3, 'cols' => 60]);
    $this->add('textarea', 'contact_ids', E::ts('Contact IDs'), ['rows' => 3, 'cols' => 60]);
    $this->add('select', 'saved_search_id', E::ts('Saved search'), $this->getSavedSearchOptions(), FALSE, ['class' => 'crm-select2']);
    $this->add('datepicker', 'date_from', E::ts('Period from'), [], FALSE, ['time' => FALSE]);
    $this->add('datepicker', 'date_to', E::ts('Period to'), [], FALSE, ['time' => FALSE]);
    $this->add('select', 'profile_id', E::ts('Donrec profile'), $this->profiles, TRUE, ['class' => 'crm-select2']);
    $this->add('select', 'exporters', E::ts('Export modes'), $this->exporters, TRUE, [
      'class' => 'crm-select2',
      'multiple' => 'multiple',
    ]);
    $this->add('checkbox', 'bulk', E::ts('Group contributions into one receipt per contact'));
    $this->add('checkbox', 'dry_run', E::ts('Preview only — do not issue receipts'));
    $this->add('text', 'label', E::ts('Campaign label'), ['size' => 60, 'maxlength' => 255]);
    $this->add('select', 'queue_job_id', E::ts('Existing campaign'), $this->getJobOptions(), FALSE, ['class' => 'crm-select2']);
    $this->add('select', 'operation', E::ts('Action'), [
      'create' => E::ts('Create campaign'),
      'run' => E::ts('Run or resume selected campaign (10 tasks)'),
    ], TRUE, ['class' => 'crm-select2']);
    $this->addFormRule([$this, 'validateForm']);
    // Keep a single standard submit action. HTML_QuickForm_Controller parses
    // button suffixes as controller actions, so custom action names such as
    // "run_queue" are not safe on this one-page form.
    $this->addButtons([
      ['type' => 'submit', 'name' => E::ts('Apply'), 'isDefault' => TRUE],
    ]);

    $this->assign('jobs', $this->jobs);
    parent::buildQuickForm();
  }

  public function setDefaultValues(): array {
    $today = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
    return [
      'selection_mode' => 'contacts',
      'date_from' => $today->format('Y-01-01'),
      'date_to' => $today->format('Y-m-d'),
      'profile_id' => (int) CRM_Donrec_Logic_Profile::getDefaultProfile()->getId(),
      'exporters' => ['PDF'],
      'bulk' => 1,
      'dry_run' => 1,
      'operation' => 'create',
    ];
  }

  public function postProcess(): void {
    $values = $this->exportValues();
    $queue = new CRM_Donrecextra_ReceiptQueue();

    if (($values['operation'] ?? 'create') === 'run') {
      $jobId = (int) ($values['queue_job_id'] ?? 0);
      if (!$jobId) {
        throw new CRM_Core_Exception(E::ts('Choose an existing campaign to run.'));
      }
      $status = $queue->run($jobId);
      CRM_Core_Session::setStatus(
        E::ts('Campaign processed. %1 task(s) remain ready.', [1 => $status['queue_ready']]),
        E::ts('Donation receipt campaign'),
        'success'
      );
    }
    else {
      $result = $queue->create([
        'contribution_ids' => ($values['selection_mode'] ?? '') === 'contributions' ? $this->parseIds($values['contribution_ids'] ?? '') : [],
        'contact_ids' => ($values['selection_mode'] ?? '') === 'contacts' ? $this->parseIds($values['contact_ids'] ?? '') : [],
        'saved_search_id' => ($values['selection_mode'] ?? '') === 'saved_search' ? (int) ($values['saved_search_id'] ?? 0) : NULL,
        'date_from' => $values['date_from'] ?? '',
        'date_to' => $values['date_to'] ?? '',
        'profile_id' => (int) $values['profile_id'],
        'exporters' => (array) ($values['exporters'] ?? []),
        'bulk' => !empty($values['bulk']),
        'dry_run' => !empty($values['dry_run']),
        'label' => $values['label'] ?? '',
      ]);
      CRM_Core_Session::setStatus(
        E::ts('Campaign #%1 was created with %2 task(s).', [
          1 => $result['user_job_id'],
          2 => $result['work_item_count'],
        ]),
        E::ts('Donation receipt campaign'),
        'success'
      );
    }
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/admin/donrecextra/queue', 'reset=1'));
  }

  public function validateForm(array $values): array {
    $errors = [];
    if (($values['operation'] ?? 'create') === 'run') {
      return $errors;
    }
    $mode = $values['selection_mode'] ?? '';
    if ($mode === 'contacts') {
      if (!$this->parseIds($values['contact_ids'] ?? '')) {
        $errors['contact_ids'] = E::ts('Enter at least one contact ID.');
      }
      if (empty($values['date_from']) || empty($values['date_to'])) {
        $errors['date_from'] = E::ts('A period is required for contact selection.');
      }
    }
    elseif ($mode === 'contributions') {
      if (!$this->parseIds($values['contribution_ids'] ?? '')) {
        $errors['contribution_ids'] = E::ts('Enter at least one contribution ID.');
      }
    }
    elseif ($mode === 'saved_search') {
      $search = $this->savedSearches[(int) ($values['saved_search_id'] ?? 0)] ?? NULL;
      if (!$search) {
        $errors['saved_search_id'] = E::ts('Choose a saved SearchKit search.');
      }
      elseif ($search['api_entity'] === 'Contact' && (empty($values['date_from']) || empty($values['date_to']))) {
        $errors['date_from'] = E::ts('A period is required when the saved search returns contacts.');
      }
    }
    else {
      $errors['selection_mode'] = E::ts('Choose a selection mode.');
    }
    return $errors;
  }

  private function getJobOptions(): array {
    $options = ['' => E::ts('- Select a campaign -')];
    foreach ($this->jobs as $job) {
      $options[$job['user_job_id']] = sprintf(
        '#%d — %s (%s, %d %s)',
        $job['user_job_id'],
        $job['label'],
        $job['queue_status'],
        $job['queue_ready'],
        E::ts('ready')
      );
    }
    return $options;
  }

  private function getSavedSearchOptions(): array {
    $options = ['' => E::ts('- Select a saved search -')];
    foreach ($this->savedSearches as $search) {
      $options[$search['id']] = sprintf('%s — %s', $search['label'] ?: $search['name'], $search['api_entity']);
    }
    return $options;
  }

  private function parseIds(string $value): array {
    $parts = preg_split('/[\s,;]+/', trim($value)) ?: [];
    return array_values(array_unique(array_filter(array_map('intval', $parts))));
  }

}
