<?php

use CRM_Donrecextra_ExtensionUtil as E;

return [
  'name' => 'DonationReceiptEvent',
  'table' => 'civicrm_donrecextra_receipt_event',
  'class' => 'CRM_Donrecextra_DAO_DonationReceiptEvent',
  'getInfo' => fn() => [
    'title' => E::ts('Donation Receipt Event'),
    'title_plural' => E::ts('Donation Receipt Events'),
    'description' => E::ts('Lifecycle events recorded for donation receipt audits.'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'UI_donrecextra_receipt_event' => ['fields' => ['receipt_audit_id' => TRUE, 'event_type' => TRUE], 'unique' => TRUE],
    'IX_donrecextra_event_occurred' => ['fields' => ['occurred_at' => TRUE]],
    'IX_donrecextra_event_type' => ['fields' => ['event_type' => TRUE]],
  ],
  'getFields' => fn() => [
    'id' => ['title' => E::ts('ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'required' => TRUE, 'primary_key' => TRUE, 'auto_increment' => TRUE],
    'receipt_audit_id' => ['title' => E::ts('Receipt Audit ID'), 'sql_type' => 'int unsigned', 'input_type' => 'EntityRef', 'required' => TRUE, 'entity_reference' => ['entity' => 'DonrecextraReceiptAudit', 'key' => 'id', 'on_delete' => 'RESTRICT']],
    'event_type' => ['title' => E::ts('Event Type'), 'sql_type' => 'varchar(24)', 'input_type' => 'Text', 'required' => TRUE],
    'occurred_at' => ['title' => E::ts('Occurred At'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'required' => TRUE],
    'recorded_at' => ['title' => E::ts('Recorded At'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'required' => TRUE],
    'time_precision' => ['title' => E::ts('Time Precision'), 'sql_type' => 'varchar(16)', 'input_type' => 'Text', 'required' => TRUE, 'default' => 'exact'],
    'actor_contact_id' => ['title' => E::ts('Actor Contact ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'default' => NULL],
    'reason' => ['title' => E::ts('Reason'), 'sql_type' => 'text', 'input_type' => 'TextArea', 'default' => NULL],
    'source' => ['title' => E::ts('Source'), 'sql_type' => 'varchar(64)', 'input_type' => 'Text', 'required' => TRUE],
    'metadata' => ['title' => E::ts('Metadata'), 'sql_type' => 'longtext', 'input_type' => 'TextArea', 'default' => NULL],
    'event_hash' => ['title' => E::ts('Event Hash'), 'sql_type' => 'char(64)', 'input_type' => 'Text', 'required' => TRUE],
  ],
];
