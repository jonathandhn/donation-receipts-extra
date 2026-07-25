<?php

use CRM_Donrecextra_ExtensionUtil as E;

return [
  'name' => 'DonrecextraReceiptAudit',
  'table' => 'civicrm_donrecextra_receipt_audit',
  'class' => 'CRM_Donrecextra_DAO_DonrecextraReceiptAudit',
  'getInfo' => fn() => [
    'title' => E::ts('Donation Receipt Audit'),
    'title_plural' => E::ts('Donation Receipt Audits'),
    'description' => E::ts('Immutable audit projection of Donrec tax receipts.'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'UI_donrecextra_donrec_receipt' => ['fields' => ['donrec_receipt_id' => TRUE], 'unique' => TRUE],
    'IX_donrecextra_receipt_number' => ['fields' => ['receipt_number' => TRUE]],
    'IX_donrecextra_receipt_contact' => ['fields' => ['contact_id' => TRUE]],
    'IX_donrecextra_receipt_issued' => ['fields' => ['issued_at' => TRUE]],
    'IX_donrecextra_receipt_status' => ['fields' => ['current_status' => TRUE]],
    'IX_donrecextra_receipt_beneficiary' => ['fields' => ['beneficiary_type' => TRUE]],
  ],
  'getFields' => fn() => [
    'id' => ['title' => E::ts('ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'required' => TRUE, 'primary_key' => TRUE, 'auto_increment' => TRUE],
    'donrec_receipt_id' => ['title' => E::ts('Donrec Receipt ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'required' => TRUE],
    'receipt_number' => ['title' => E::ts('Receipt Number'), 'sql_type' => 'varchar(128)', 'input_type' => 'Text', 'required' => TRUE],
    'contact_id' => ['title' => E::ts('Contact ID'), 'sql_type' => 'int unsigned', 'input_type' => 'EntityRef', 'required' => TRUE],
    'beneficiary_type' => ['title' => E::ts('Beneficiary Type'), 'sql_type' => 'varchar(32)', 'input_type' => 'Text', 'required' => TRUE, 'default' => 'Unknown'],
    'receipt_type' => ['title' => E::ts('Receipt Type'), 'sql_type' => 'varchar(16)', 'input_type' => 'Text', 'required' => TRUE],
    'current_status' => ['title' => E::ts('Current Status'), 'sql_type' => 'varchar(24)', 'input_type' => 'Text', 'required' => TRUE],
    'issued_at' => ['title' => E::ts('Issued At'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'required' => TRUE],
    'period_from' => ['title' => E::ts('Period From'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'default' => NULL],
    'period_to' => ['title' => E::ts('Period To'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'default' => NULL],
    'total_amount' => ['title' => E::ts('Total Amount'), 'sql_type' => 'decimal(20,2)', 'input_type' => 'Number', 'required' => TRUE, 'default' => '0.00'],
    'non_deductible_amount' => ['title' => E::ts('Non-deductible Amount'), 'sql_type' => 'decimal(20,2)', 'input_type' => 'Number', 'required' => TRUE, 'default' => '0.00'],
    'currency' => ['title' => E::ts('Currency'), 'sql_type' => 'char(3)', 'input_type' => 'Text', 'required' => TRUE, 'default' => 'EUR'],
    'original_file_id' => ['title' => E::ts('Original File ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'default' => NULL],
    'pdf_sha256' => ['title' => E::ts('PDF SHA-256'), 'sql_type' => 'char(64)', 'input_type' => 'Text', 'default' => NULL],
    'first_seen_at' => ['title' => E::ts('First Seen At'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'required' => TRUE],
    'updated_at' => ['title' => E::ts('Updated At'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'required' => TRUE],
  ],
];
