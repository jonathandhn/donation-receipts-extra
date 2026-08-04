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

  public function upgrade_4201() {
    $this->ctx->log->info('Registering the Donrec receipt generation CiviRules action');
    if (class_exists('CRM_Civirules_Utils_Upgrader')) {
      try {
        CRM_Civirules_Utils_Upgrader::insertActionsFromJson(
          dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'civirules_actions.json'
        );
      }
      catch (Throwable $e) {
        $this->ctx->log->warning(
          'Skipping optional CiviRules action registration: ' . $e->getMessage()
        );
      }
    }
    return TRUE;
  }

  public function upgrade_4202() {
    $this->ctx->log->info('Creating the donation receipt audit ledger');
    $this->executeSqlFile('sql/auto_install.sql');
    return TRUE;
  }

  public function upgrade_4203() {
    $this->ctx->log->info('Preserving multiple Donrec receipt lines for one contribution');
    $auditItemTable = 'civicrm_donrecextra_receipt_item_audit';
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists($auditItemTable, 'donrec_receipt_item_id')) {
      CRM_Core_DAO::executeQuery(
        "ALTER TABLE `$auditItemTable`
         ADD COLUMN `donrec_receipt_item_id` int unsigned DEFAULT NULL AFTER `receipt_audit_id`"
      );
    }
    if (CRM_Core_BAO_SchemaHandler::checkIfIndexExists($auditItemTable, 'UI_donrecextra_receipt_contribution')) {
      CRM_Core_DAO::executeQuery(
        "ALTER TABLE `$auditItemTable` DROP INDEX `UI_donrecextra_receipt_contribution`"
      );
    }
    if (!CRM_Core_BAO_SchemaHandler::checkIfIndexExists($auditItemTable, 'UI_donrecextra_receipt_item')) {
      CRM_Core_DAO::executeQuery(
        "ALTER TABLE `$auditItemTable`
         ADD UNIQUE KEY `UI_donrecextra_receipt_item` (`receipt_audit_id`, `donrec_receipt_item_id`)"
      );
    }
    $itemFields = CRM_Donrec_Logic_ReceiptItem::getCustomFields() ?? [];
    $itemTable = CRM_Donrec_DataStructure::getTableName('zwb_donation_receipt_item');
    $issuedIn = $itemFields['issued_in'] ?? NULL;
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_donrecextra_receipt_item_audit audit_item
       JOIN civicrm_donrecextra_receipt_audit audit_receipt
         ON audit_receipt.id = audit_item.receipt_audit_id
       JOIN (
         SELECT `$issuedIn` receipt_id, entity_id contribution_id, MIN(id) receipt_item_id
           FROM `$itemTable`
          GROUP BY `$issuedIn`, entity_id
       ) donrec_item
         ON donrec_item.receipt_id = audit_receipt.donrec_receipt_id
        AND donrec_item.contribution_id = audit_item.contribution_id
        SET audit_item.donrec_receipt_item_id = donrec_item.receipt_item_id"
    );
    (new CRM_Donrecextra_AuditLedger())->reconcileAll();
    return TRUE;
  }

}
