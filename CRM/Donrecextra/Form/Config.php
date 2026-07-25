<?php

use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 *
 */
class CRM_Donrecextra_Form_Config extends CRM_Core_Form {

  private $configuration_tokens = [
    "donrecextra_location_type",
    "donrecextra_extra_tokens_contact",
    "donrecextra_extra_tokens_address",
    "donrecextra_extra_tokens_contribution",
    "donrecextra_enable_organization_receipts",
  ];

  /** @var array<int, string> */
  private array $profileRequirementFields = [];

  public function buildQuickForm() {
    // add form elements
    $this->add(
      'select',
      'donrecextra_location_type',
      E::ts('Location Type Address'),
      $this->getOptionsLocationTypes(),
      TRUE
    );

    $this->addElement('checkbox', 'donrecextra_extra_tokens_contact', E::ts('Enable custom tokens Contact'));
    $this->addElement('checkbox', 'donrecextra_extra_tokens_address', E::ts('Enable custom tokens Address'));
    $this->addElement('checkbox', 'donrecextra_extra_tokens_contribution', E::ts('Enable custom tokens Contribution'));
    $this->addElement(
      'checkbox',
      'donrecextra_enable_organization_receipts',
      E::ts('Enable organization receipt fields')
    );
    $this->assign('organizationReceiptsDescription', E::ts(
      'Creates the legal-information fields for organizations and exposes stable receipt tokens. Disabled by default.'
    ));

    foreach (CRM_Donrec_Logic_Profile::getAllActiveNames('is_default', 'DESC') as $profileId => $profileName) {
      $fieldName = 'donrecextra_required_tokens_profile_' . $profileId;
      $this->profileRequirementFields[(int) $profileId] = $fieldName;
      $this->add('select', $fieldName, E::ts('Required receipt data — %1', [1 => $profileName]),
        CRM_Donrecextra_ReceiptDataValidator::getTokenOptions(), FALSE, [
          'multiple' => 'multiple',
          'class' => 'crm-select2 huge',
        ]);
    }
    $requiredReceiptDataDescription = E::ts(
      'Choose the receipt tokens which must contain a value before a receipt using this Donrec profile can be issued. The check applies to the one-contribution form, CiviRules and API generation.'
    );
    $this->assign('requiredReceiptDataDescription', $requiredReceiptDataDescription);
    $this->assign('profileRequirementDescriptions', array_fill_keys(
      array_values($this->profileRequirementFields),
      $requiredReceiptDataDescription
    ));

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ],
    ]);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $settings = (array) Civi::settings()->get(E::SHORT_NAME);
    $values_submit = $settings;
    foreach ($this->configuration_tokens as $value) {
      $values_submit[$value] = $this->_submitValues[$value] ?? 0;
    }
    $requirements = (array) ($settings['donrecextra_required_tokens_by_profile'] ?? []);
    foreach ($this->profileRequirementFields as $profileId => $fieldName) {
      $requirements[$profileId] = array_values(array_filter((array) ($this->_submitValues[$fieldName] ?? [])));
    }
    $values_submit['donrecextra_required_tokens_by_profile'] = $requirements;
    if (!empty($values_submit['donrecextra_enable_organization_receipts'])) {
      CRM_Donrecextra_OrganizationReceipt::ensureCustomFields();
    }
    Civi::settings()->set(E::SHORT_NAME, $values_submit);
    parent::postProcess();
  }

  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();
    $values_config = (array) Civi::settings()->get(E::SHORT_NAME);
    foreach ($this->configuration_tokens as $value) {
      $defaults[$value] = $values_config[$value] ?? 0;
    }
    foreach ($this->profileRequirementFields as $profileId => $fieldName) {
      $defaults[$fieldName] = $values_config['donrecextra_required_tokens_by_profile'][$profileId] ?? [];
    }
    return $defaults;
  }

  public function getOptionsLocationTypes() {
    $query = "SELECT `id`, `name` FROM `civicrm_location_type`";
    $result = CRM_Core_DAO::executeQuery($query);
    $options = [0 => E::ts('primary address')];
    while ($result->fetch()) {
      $options[$result->id] = E::ts($result->name);
    }
    return $options;
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    $elementNames = [];
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
