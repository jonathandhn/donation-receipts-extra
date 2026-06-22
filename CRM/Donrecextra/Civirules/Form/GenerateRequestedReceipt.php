<?php

if (!class_exists('CRM_CivirulesActions_Form_Form')) {
  return;
}

use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Per-rule delivery configuration for the Donrec generation action.
 */
class CRM_Donrecextra_Civirules_Form_GenerateRequestedReceipt extends CRM_CivirulesActions_Form_Form {

  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $profiles = CRM_Donrec_Logic_Profile::getAllActiveNames('is_default', 'DESC');
    if (!$profiles) {
      throw new CRM_Core_Exception(E::ts('No active Donrec profile is available.'));
    }
    $this->add('select', 'delivery_mode', E::ts('Receipt delivery'), [
      'PDF' => E::ts('Create downloadable PDF only'),
      'EmailPDF' => E::ts('Send PDF using the Donrec email exporter'),
    ], TRUE);
    $this->add('select', 'individual_profile_id', E::ts('Individual Donrec profile'), $profiles, TRUE);
    $this->add('select', 'organization_profile_id', E::ts('Organization Donrec profile'), $profiles, TRUE);
    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
    ]);
  }

  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();
    $defaults['rule_action_id'] = $this->ruleActionId;
    $params = $this->ruleAction->unserializeParams();
    $defaults['delivery_mode'] = ($params['delivery_mode'] ?? 'PDF') === 'EmailPDF' ? 'EmailPDF' : 'PDF';
    $defaultProfileId = (int) CRM_Donrec_Logic_Profile::getDefaultProfile()->getId();
    $defaults['individual_profile_id'] = (int) ($params['individual_profile_id'] ?? $defaultProfileId);
    $defaults['organization_profile_id'] = (int) ($params['organization_profile_id'] ?? $defaultProfileId);
    return $defaults;
  }

  public function postProcess() {
    $deliveryMode = ($this->_submitValues['delivery_mode'] ?? 'PDF') === 'EmailPDF' ? 'EmailPDF' : 'PDF';
    $this->ruleAction->action_params = serialize([
      'delivery_mode' => $deliveryMode,
      'individual_profile_id' => (int) $this->_submitValues['individual_profile_id'],
      'organization_profile_id' => (int) $this->_submitValues['organization_profile_id'],
    ]);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
