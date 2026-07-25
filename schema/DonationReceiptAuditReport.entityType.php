<?php

use CRM_Donrecextra_ExtensionUtil as E;

return [
  'name' => 'DonationReceiptAuditReport',
  'table' => 'civicrm_donrecextra_audit_report',
  'class' => 'CRM_Donrecextra_DAO_DonationReceiptAuditReport',
  'getInfo' => fn() => [
    'title' => E::ts('Donation Receipt Audit Report'),
    'title_plural' => E::ts('Donation Receipt Audit Reports'),
    'description' => E::ts('Frozen audit report snapshots for donation receipts.'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'IX_donrecextra_report_period' => ['fields' => ['period_from' => TRUE, 'period_to' => TRUE]],
    'IX_donrecextra_report_asof' => ['fields' => ['as_of' => TRUE]],
  ],
  'getFields' => fn() => [
    'id' => ['title' => E::ts('ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'required' => TRUE, 'primary_key' => TRUE, 'auto_increment' => TRUE],
    'period_from' => ['title' => E::ts('Period From'), 'sql_type' => 'date', 'input_type' => 'Select Date', 'required' => TRUE],
    'period_to' => ['title' => E::ts('Period To'), 'sql_type' => 'date', 'input_type' => 'Select Date', 'required' => TRUE],
    'as_of' => ['title' => E::ts('As Of'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'required' => TRUE],
    'granularity' => ['title' => E::ts('Granularity'), 'sql_type' => 'varchar(16)', 'input_type' => 'Text', 'required' => TRUE],
    'metrics_json' => ['title' => E::ts('Metrics JSON'), 'sql_type' => 'longtext', 'input_type' => 'TextArea', 'required' => TRUE],
    'selection_hash' => ['title' => E::ts('Selection Hash'), 'sql_type' => 'char(64)', 'input_type' => 'Text', 'required' => TRUE],
    'created_at' => ['title' => E::ts('Created At'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'required' => TRUE],
    'created_by' => ['title' => E::ts('Created By'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'default' => NULL],
  ],
];
