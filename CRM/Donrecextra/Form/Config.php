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
    $values_submit = [];
    foreach ($this->configuration_tokens as $value) {
      $values_submit[$value] = $this->_submitValues[$value] ?? 0;
    }
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
