<?php

use CRM_Donrecextra_ExtensionUtil as E;

return [
  'name' => 'DonationReceiptItemAudit',
  'table' => 'civicrm_donrecextra_receipt_item_audit',
  'class' => 'CRM_Donrecextra_DAO_DonationReceiptItemAudit',
  'getInfo' => fn() => [
    'title' => E::ts('Donation Receipt Item Audit'),
    'title_plural' => E::ts('Donation Receipt Item Audits'),
    'description' => E::ts('Contribution allocations recorded on donation receipt audits.'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'UI_donrecextra_receipt_item' => ['fields' => ['receipt_audit_id' => TRUE, 'donrec_receipt_item_id' => TRUE], 'unique' => TRUE],
    'IX_donrecextra_item_contribution' => ['fields' => ['contribution_id' => TRUE]],
  ],
  'getFields' => fn() => [
    'id' => ['title' => E::ts('ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'required' => TRUE, 'primary_key' => TRUE, 'auto_increment' => TRUE],
    'receipt_audit_id' => ['title' => E::ts('Receipt Audit ID'), 'sql_type' => 'int unsigned', 'input_type' => 'EntityRef', 'required' => TRUE, 'entity_reference' => ['entity' => 'DonrecextraReceiptAudit', 'key' => 'id', 'on_delete' => 'RESTRICT']],
    'donrec_receipt_item_id' => ['title' => E::ts('Donrec Receipt Item ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'required' => TRUE],
    'contribution_id' => ['title' => E::ts('Contribution ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'required' => TRUE],
    'receive_date' => ['title' => E::ts('Receive Date'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'default' => NULL],
    'total_amount' => ['title' => E::ts('Total Amount'), 'sql_type' => 'decimal(20,2)', 'input_type' => 'Number', 'required' => TRUE, 'default' => '0.00'],
    'non_deductible_amount' => ['title' => E::ts('Non-deductible Amount'), 'sql_type' => 'decimal(20,2)', 'input_type' => 'Number', 'required' => TRUE, 'default' => '0.00'],
    'currency' => ['title' => E::ts('Currency'), 'sql_type' => 'char(3)', 'input_type' => 'Text', 'required' => TRUE, 'default' => 'EUR'],
    'financial_type_id' => ['title' => E::ts('Financial Type ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'default' => NULL],
    'contribution_hash' => ['title' => E::ts('Contribution Hash'), 'sql_type' => 'varchar(255)', 'input_type' => 'Text', 'default' => NULL],
  ],
];
