<?php

namespace Civi\Api4;

use CRM_Donrec_DataStructure;
use CRM_Donrec_Logic_ReceiptItem;
use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Donation receipt lines issued by Donrec.
 *
 * This read-only entity exposes one row per contribution included in a Donrec
 * receipt. It is intended for SearchKit joins from Contribution.id, so a
 * contribution can display or download the receipt that contains it.
 *
 * @searchable primary
 * @labelField receipt_id
 * @searchFields receipt_id,contribution_id,display_name
 * @icon fa-file-invoice-dollar
 */
class DonationReceiptItem extends Generic\SqlView {

  /**
   * Limit access to users who may view contributions.
   */
  public static function permissions(): array {
    return ['default' => ['access CiviContribute']];
  }

  protected static function getEntityTitle(bool $plural = FALSE): string {
    return $plural ? E::ts('Donation Receipt Items') : E::ts('Donation Receipt Item');
  }

  /**
   * {@inheritDoc}
   */
  protected static function viewSelect(): array {
    $receiptFields = CRM_Donrec_DataStructure::getCustomFields('zwb_donation_receipt');
    CRM_Donrec_Logic_ReceiptItem::getCustomFields();
    $itemFields = CRM_Donrec_Logic_ReceiptItem::$_custom_fields;

    return [
      self::field('item.id', 'id', 'Integer', E::ts('Receipt item ID')),
      self::originalField('item.entity_id', 'contribution_id', 'Contribution.id'),
      self::field(self::column('item', $itemFields, 'issued_in'), 'receipt_record_id', 'Integer', E::ts('Receipt record ID')),
      self::originalField('receipt.entity_id', 'contact_id', 'Contact.id'),
      self::field(self::column('receipt', $receiptFields, 'receipt_id'), 'receipt_id', 'String', E::ts('Receipt number')),
      self::field(self::column('receipt', $receiptFields, 'status'), 'status', 'String', E::ts('Status')),
      self::field(self::column('receipt', $receiptFields, 'type'), 'type', 'String', E::ts('Type')),
      self::field(self::column('receipt', $receiptFields, 'issued_on'), 'issued_on', 'Timestamp', E::ts('Issued on')),
      self::originalField(self::column('receipt', $receiptFields, 'issued_by'), 'issued_by', 'Contact.id'),
      self::originalField(self::column('receipt', $receiptFields, 'original_file'), 'original_file_id', 'File.id'),
      self::originalField('receipt_file.uri', 'file_uri', 'File.uri'),
      self::originalField('receipt_file.mime_type', 'file_mime_type', 'File.mime_type'),
      self::field(self::column('receipt', $receiptFields, 'date_from'), 'date_from', 'Timestamp', E::ts('Period start')),
      self::field(self::column('receipt', $receiptFields, 'date_to'), 'date_to', 'Timestamp', E::ts('Period end')),
      self::field(self::column('receipt', $receiptFields, 'profile_id'), 'profile_id', 'Integer', E::ts('Donrec profile ID')),
      self::field(self::column('receipt', $receiptFields, 'exporters'), 'exporters', 'String', E::ts('Exporters')),
      self::field(self::column('receipt', $receiptFields, 'display_name'), 'display_name', 'String', E::ts('Recipient name')),
      self::field(self::column('receipt', $receiptFields, 'street_address'), 'street_address', 'String', E::ts('Recipient street address')),
      self::field(self::column('receipt', $receiptFields, 'postal_code'), 'postal_code', 'String', E::ts('Recipient postal code')),
      self::field(self::column('receipt', $receiptFields, 'city'), 'city', 'String', E::ts('Recipient city')),
      self::field(self::column('receipt', $receiptFields, 'country'), 'country', 'String', E::ts('Recipient country')),
      self::field(self::column('item', $itemFields, 'receive_date'), 'receive_date', 'Timestamp', E::ts('Contribution date')),
      self::field(self::column('item', $itemFields, 'total_amount'), 'total_amount', 'Money', E::ts('Contribution amount')),
      self::field(self::column('item', $itemFields, 'non_deductible_amount'), 'non_deductible_amount', 'Money', E::ts('Non-deductible amount')),
      self::field(self::column('item', $itemFields, 'total_amount') . ' - COALESCE(' . self::column('item', $itemFields, 'non_deductible_amount') . ', 0)', 'deductible_amount', 'Money', E::ts('Deductible amount')),
      self::field(self::column('item', $itemFields, 'currency'), 'currency', 'String', E::ts('Currency')),
    ];
  }

  /**
   * {@inheritDoc}
   */
  protected static function viewFrom(): string {
    $receiptTable = CRM_Donrec_DataStructure::getTableName('zwb_donation_receipt');
    $receiptFields = CRM_Donrec_DataStructure::getCustomFields('zwb_donation_receipt');
    $itemTable = CRM_Donrec_DataStructure::getTableName('zwb_donation_receipt_item');
    CRM_Donrec_Logic_ReceiptItem::getCustomFields();
    $itemFields = CRM_Donrec_Logic_ReceiptItem::$_custom_fields;

    return sprintf(
      'FROM `%s` item
       INNER JOIN `%s` receipt
         ON receipt.id = %s
       LEFT JOIN civicrm_file receipt_file
         ON receipt_file.id = %s',
      self::identifier($itemTable),
      self::identifier($receiptTable),
      self::column('item', $itemFields, 'issued_in'),
      self::column('receipt', $receiptFields, 'original_file')
    );
  }

  private static function field(string $select, string $name, string $dataType, string $title): array {
    return [
      'select' => $select,
      'name' => $name,
      'data_type' => $dataType,
      'title' => $title,
    ];
  }

  private static function originalField(string $select, string $name, string $originalField): array {
    return [
      'select' => $select,
      'name' => $name,
      'original_field' => $originalField,
    ];
  }

  private static function column(string $alias, array $fields, string $name): string {
    if (empty($fields[$name])) {
      throw new \CRM_Core_Exception("Missing Donrec field: $name");
    }
    return $alias . '.`' . self::identifier($fields[$name]) . '`';
  }

  private static function identifier(string $identifier): string {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
      throw new \CRM_Core_Exception('Invalid SQL identifier in Donrec metadata');
    }
    return $identifier;
  }

}
