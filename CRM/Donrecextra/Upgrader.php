<?php
use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Donrecextra_Upgrader extends CRM_Extension_Upgrader_Base {

  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_setting WHERE name LIKE "%donrecextra_%"');
    return TRUE;
  }

}
