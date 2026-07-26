<?php

namespace Civi\Api4;

use CRM_Donrec_DataStructure;
use CRM_Donrec_Logic_ReceiptItem;
use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Donation receipts issued by Donrec.
 *
 * This read-only entity exposes Donrec's multi-value custom data as one row
 * per receipt. It can be used as a primary entity in SearchKit and Afform.
 *
 * @searchable primary
 * @labelField receipt_id
 * @searchFields receipt_id,display_name
 * @icon fa-file-invoice
 */
class DonationReceipt extends Generic\SqlView {

  /**
   * Limit access to users who may view contributions.
   */
  public static function permissions(): array {
    return [
      'default' => ['access CiviContribute'],
      'generate' => ['create and withdraw receipts'],
      'queue' => ['create and withdraw receipts'],
      'queueStatus' => ['create and withdraw receipts'],
    ];
  }

  protected static function getEntityTitle(bool $plural = FALSE): string {
    return $plural ? E::ts('Donation Receipts') : E::ts('Donation Receipt');
  }

  /**
   * {@inheritDoc}
   */
  protected static function viewSelect(): array {
    $receiptFields = CRM_Donrec_DataStructure::getCustomFields('zwb_donation_receipt');

    return [
      self::field('receipt.id', 'id', 'Integer', E::ts('Receipt record ID')),
      self::originalField('receipt.entity_id', 'contact_id', 'Contact.id'),
      self::field(self::column('receipt', $receiptFields, 'receipt_id'), 'receipt_id', 'String', E::ts('Receipt number')),
      self::field(self::column('receipt', $receiptFields, 'status'), 'status', 'String', E::ts('Status')),
      self::field(self::optionalColumn('receipt', $receiptFields, 'type'), 'type', 'String', E::ts('Type')),
      self::field(self::optionalColumn('receipt', $receiptFields, 'issued_on'), 'issued_on', 'Timestamp', E::ts('Issued on')),
      self::originalField(self::optionalColumn('receipt', $receiptFields, 'issued_by'), 'issued_by', 'Contact.id'),
      self::originalField(self::optionalColumn('receipt', $receiptFields, 'original_file'), 'original_file_id', 'File.id'),
      self::originalField('receipt_file.uri', 'file_uri', 'File.uri'),
      self::originalField('receipt_file.mime_type', 'file_mime_type', 'File.mime_type'),
      self::field(self::optionalColumn('receipt', $receiptFields, 'date_from'), 'date_from', 'Timestamp', E::ts('Period start')),
      self::field(self::optionalColumn('receipt', $receiptFields, 'date_to'), 'date_to', 'Timestamp', E::ts('Period end')),
      self::field(self::optionalColumn('receipt', $receiptFields, 'profile_id'), 'profile_id', 'Integer', E::ts('Donrec profile ID')),
      self::field(self::optionalColumn('receipt', $receiptFields, 'exporters'), 'exporters', 'String', E::ts('Exporters')),
      self::field(self::optionalColumn('receipt', $receiptFields, 'display_name'), 'display_name', 'String', E::ts('Recipient name')),
      self::field(self::optionalColumn('receipt', $receiptFields, 'street_address'), 'street_address', 'String', E::ts('Recipient street address')),
      self::field(self::optionalColumn('receipt', $receiptFields, 'postal_code'), 'postal_code', 'String', E::ts('Recipient postal code')),
      self::field(self::optionalColumn('receipt', $receiptFields, 'city'), 'city', 'String', E::ts('Recipient city')),
      self::field(self::optionalColumn('receipt', $receiptFields, 'country'), 'country', 'String', E::ts('Recipient country')),
      self::field('items.item_count', 'item_count', 'Integer', E::ts('Receipt item count')),
      self::field('items.contribution_count', 'contribution_count', 'Integer', E::ts('Contribution count')),
      self::field('items.total_amount', 'total_amount', 'Money', E::ts('Total amount')),
      self::field('items.non_deductible_amount', 'non_deductible_amount', 'Money', E::ts('Non-deductible amount')),
      self::field('items.total_amount - COALESCE(items.non_deductible_amount, 0)', 'deductible_amount', 'Money', E::ts('Deductible amount')),
      self::field('items.currency', 'currency', 'String', E::ts('Currency')),
      self::field('items.first_receive_date', 'first_receive_date', 'Timestamp', E::ts('First contribution date')),
      self::field('items.last_receive_date', 'last_receive_date', 'Timestamp', E::ts('Last contribution date')),
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

    $issuedIn = self::identifier($itemFields['issued_in']);
    $totalAmount = self::identifier($itemFields['total_amount']);
    $nonDeductible = self::identifier($itemFields['non_deductible_amount']);
    $currency = self::identifier($itemFields['currency']);
    $receiveDate = self::identifier($itemFields['receive_date']);

    return sprintf(
      'FROM `%s` receipt
       LEFT JOIN civicrm_file receipt_file
         ON receipt_file.id = %s
       LEFT JOIN (
         SELECT `%s` receipt_record_id,
                COUNT(*) item_count,
                COUNT(DISTINCT entity_id) contribution_count,
                SUM(`%s`) total_amount,
                SUM(COALESCE(`%s`, 0)) non_deductible_amount,
                MIN(`%s`) currency,
                MIN(`%s`) first_receive_date,
                MAX(`%s`) last_receive_date
           FROM `%s`
          GROUP BY `%s`
       ) items ON items.receipt_record_id = receipt.id',
      self::identifier($receiptTable),
      self::optionalColumn('receipt', $receiptFields, 'original_file', '0'),
      $issuedIn,
      $totalAmount,
      $nonDeductible,
      $currency,
      $receiveDate,
      $receiveDate,
      self::identifier($itemTable),
      $issuedIn
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

  private static function optionalColumn(string $alias, array $fields, string $name, string $fallback = 'NULL'): string {
    if (empty($fields[$name])) {
      return $fallback;
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
