<?php

namespace Civi\Api4;

use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Searchable audit ledger for donation receipts.
 *
 * @searchable primary
 * @labelField receipt_number
 * @searchFields receipt_number,contact_id
 * @icon fa-balance-scale
 */
class DonationReceiptAudit extends Generic\SqlView {

  use DonrecExtraSqlViewTrait;

  /**
   * Avoid creating the view during the early extension-installation phase.
   *
   * scan-classes may discover this service before Entity Schema has created
   * the audit tables. A later cache rebuild creates the view once all source
   * tables are available.
   */
  public static function _on_civi_api4_entityTypes(\Civi\Core\Event\GenericHookEvent $event): void {
    if (\CRM_Core_Config::isInitializing()) {
      return;
    }
    foreach ([
      'civicrm_donrecextra_receipt_audit',
      'civicrm_donrecextra_receipt_event',
      'civicrm_donrecextra_receipt_item_audit',
    ] as $table) {
      if (!\CRM_Core_DAO::checkTableExists($table)) {
        return;
      }
    }
    try {
      static::rebuildSqlView();
    }
    catch (\Throwable $e) {
      // Ignore view creation errors during early container initialization
    }
  }

  public static function permissions(): array {
    return ['default' => ['administer CiviCRM']];
  }

  protected static function getEntityTitle(bool $plural = FALSE): string {
    return $plural ? E::ts('Donation Receipt Audit Entries') : E::ts('Donation Receipt Audit Entry');
  }

  protected static function viewSelect(): array {
    return [
      self::field('audit.id', 'id', 'Integer', E::ts('Audit receipt ID')),
      self::field('audit.donrec_receipt_id', 'donrec_receipt_id', 'Integer', E::ts('Donrec receipt ID')),
      self::field('audit.receipt_number', 'receipt_number', 'String', E::ts('Receipt number')),
      self::originalField('audit.contact_id', 'contact_id', 'Contact.id'),
      self::field('audit.beneficiary_type', 'beneficiary_type', 'String', E::ts('Beneficiary type')),
      self::field('audit.receipt_type', 'receipt_type', 'String', E::ts('Receipt type')),
      self::field('audit.current_status', 'current_status', 'String', E::ts('Current status')),
      self::field('audit.issued_at', 'issued_at', 'Timestamp', E::ts('Issued at')),
      self::field('withdrawn.occurred_at', 'withdrawn_at', 'Timestamp', E::ts('Withdrawn at')),
      self::field('withdrawn.time_precision', 'withdrawn_time_precision', 'String', E::ts('Withdrawal time precision')),
      self::field('audit.total_amount', 'total_amount', 'Money', E::ts('Total amount')),
      self::field('audit.non_deductible_amount', 'non_deductible_amount', 'Money', E::ts('Non-deductible amount')),
      self::field('audit.currency', 'currency', 'String', E::ts('Currency')),
      self::field('items.contribution_count', 'contribution_count', 'Integer', E::ts('Contribution count')),
      self::field('audit.pdf_sha256', 'pdf_sha256', 'String', E::ts('PDF SHA-256')),
    ];
  }

  protected static function viewFrom(): string {
    return "FROM civicrm_donrecextra_receipt_audit audit
      LEFT JOIN civicrm_donrecextra_receipt_event withdrawn
        ON withdrawn.receipt_audit_id = audit.id AND withdrawn.event_type = 'WITHDRAWN'
      LEFT JOIN (
        SELECT receipt_audit_id, COUNT(DISTINCT contribution_id) contribution_count
          FROM civicrm_donrecextra_receipt_item_audit
         GROUP BY receipt_audit_id
      ) items ON items.receipt_audit_id = audit.id";
  }

  private static function field(string $select, string $name, string $dataType, string $title): array {
    return [
      'select' => $select,
      'name' => $name,
      'data_type' => $dataType,
      'title' => $title,
      'readonly' => TRUE,
    ];
  }

  private static function originalField(string $select, string $name, string $originalField): array {
    return [
      'select' => $select,
      'name' => $name,
      'original_field' => $originalField,
      'readonly' => TRUE,
    ];
  }

}
