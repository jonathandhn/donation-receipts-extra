<?php

require_once 'donrecextra.civix.php';
use CRM_Donrecextra_ExtensionUtil as E;
use CRM_Donrecextra_Config as C;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function donrecextra_civicrm_config(&$config) {
  _donrecextra_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function donrecextra_civicrm_install() {
  _donrecextra_civix_civicrm_install();
  // Do not call APIs here. The module lifecycle invokes this hook before the
  // upgrader has created our SQL tables. Registering the CiviRules action can
  // rebuild the API4 entity cache, which would try to create the audit SQL
  // view before its source tables exist. The enable hook runs immediately
  // after the upgrader during a fresh installation and performs registration.
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function donrecextra_civicrm_enable() {
  _donrecextra_civix_civicrm_enable();
  donrecextra_register_civirules_actions();
  if (class_exists('CRM_Donrecextra_AuditLedger')) {
    (new CRM_Donrecextra_AuditLedger())->ensureTablesExist();
  }
}

/**
 * Register Donrec Extra actions when CiviRules is available.
 */
function donrecextra_register_civirules_actions() {
  if (!class_exists('CRM_Civirules_Utils_Upgrader')) {
    return;
  }

  $jsonFile = __DIR__ . DIRECTORY_SEPARATOR . 'civirules_actions.json';
  if (is_readable($jsonFile)) {
    try {
      CRM_Civirules_Utils_Upgrader::insertActionsFromJson($jsonFile);
    }
    catch (Throwable $e) {
      // CiviRules is an optional integration. A missing, disabled or partially
      // upgraded CiviRules installation must never prevent Donrecextra itself
      // from being installed or enabled.
      Civi::log()->warning('Donrecextra skipped optional CiviRules action registration: {message}', [
        'message' => $e->getMessage(),
      ]);
    }
  }
}

/**
 * Capture Donrec withdrawal lifecycle events around the API call.
 */
function donrecextra_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  if (
    strtolower((string) ($apiRequest['entity'] ?? '')) === 'donationreceipt'
    && strtolower((string) ($apiRequest['action'] ?? '')) === 'withdraw'
  ) {
    $wrappers[] = new CRM_Donrecextra_ApiWrapper_DonationReceiptWithdraw();
  }
}

/**
 * Add a one-contribution receipt action to the native contribution selector.
 *
 * This is deliberately a link on each classic CiviCRM contribution row rather
 * than a bulk SearchKit action. The linked form re-reads the contribution and
 * issues a receipt with an explicit contribution ID, so a Donrec daily date
 * boundary can never absorb another payment.
 *
 * @param string $objectName
 * @param array $headers
 * @param array $values
 * @param array|object|null $selector
 */
function donrecextra_civicrm_searchColumns(
  string $objectName,
  array &$headers,
  array &$values,
  array|object|null &$selector
): void {
  if ($objectName !== 'contribution' || !CRM_Core_Permission::check('create and withdraw receipts')) {
    return;
  }

  foreach ($values as $rowNumber => $row) {
    $contributionId = (int) ($row['contribution_id'] ?? $row['id'] ?? 0);
    $contactId = (int) ($row['contact_id'] ?? 0);
    if (!$contributionId || !$contactId || empty($row['action'])) {
      continue;
    }

    // Donrec's exact receipt-item check is the authoritative duplicate guard.
    // The form performs the same check again before issuing.
    if (CRM_Donrec_Logic_ReceiptItem::hasValidReceiptItem($contributionId, TRUE) !== FALSE) {
      continue;
    }

    $title = E::ts('Issue a receipt for this contribution');
    $url = CRM_Utils_System::url('civicrm/donrecextra/receipt/issue', [
      'reset' => 1,
      'id' => $contributionId,
      'cid' => $contactId,
    ], htmlize: FALSE);
    $link = sprintf(
      '<a href="%s" class="crm-hover-button" title="%s">%s</a>',
      htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars(E::ts('Issue receipt'), ENT_QUOTES, 'UTF-8')
    );

    if (str_contains($row['action'], '</ul></span>')) {
      $values[$rowNumber]['action'] = str_replace(
        '</ul></span>',
        '<li>' . $link . '</li></ul></span>',
        $row['action']
      );
    }
    else {
      $values[$rowNumber]['action'] = str_replace('</span>', $link . '</span>', $row['action']);
    }
  }
}

/**
 * Add the same one-contribution receipt action to the native contribution
 * detail form. The form button only redirects: CRM_Donrecextra_Form_IssueReceipt
 * re-loads the contribution and owns all validation and issuing logic.
 *
 * @param string $formName
 * @param CRM_Core_Form $form
 */
function donrecextra_civicrm_buildForm(string $formName, CRM_Core_Form &$form): void {
  if (
    !is_a($form, 'CRM_Contribute_Form_ContributionView')
    || !CRM_Core_Permission::check('create and withdraw receipts')
  ) {
    return;
  }

  $contributionId = (int) $form->get('id');
  if (!$contributionId || CRM_Donrec_Logic_ReceiptItem::hasValidReceiptItem($contributionId, TRUE) !== FALSE) {
    return;
  }

  // ContributionView normally has only the Done button. Preserve it when
  // adding the issue button, as addButtons() replaces the button definition.
  $form->addButtons([
    [
      'type' => 'cancel',
      'name' => E::ts('Done'),
      'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
      'isDefault' => TRUE,
    ],
    [
      'type' => 'submit',
      'subName' => 'issue_donrecextra_receipt',
      'name' => E::ts('Issue receipt'),
      'isDefault' => FALSE,
      'icon' => 'fa-file-text-o',
    ],
  ]);
}

/**
 * Redirect the native contribution detail button to the shared issue form.
 *
 * @param string $formName
 * @param CRM_Core_Form $form
 */
function donrecextra_civicrm_postProcess(string $formName, CRM_Core_Form &$form): void {
  if (!is_a($form, 'CRM_Contribute_Form_ContributionView')) {
    return;
  }

  if ($form->controller->getButtonName() !== '_qf_ContributionView_submit_issue_donrecextra_receipt') {
    return;
  }

  $contributionId = (int) $form->get('id');
  $contactId = (int) $form->get('cid');
  if (!$contributionId || !$contactId) {
    return;
  }

  // Keep the contribution screen as the return location for Cancel and for
  // any validation error on the issuing form.
  CRM_Core_Session::singleton()->pushUserContext(CRM_Utils_System::url(
    'civicrm/contact/view/contribution',
    [
      'reset' => 1,
      'id' => $contributionId,
      'cid' => $contactId,
      'action' => 'view',
      'context' => 'dashboard',
      'selectedChild' => 'contribute',
    ]
  ));
  CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/donrecextra/receipt/issue', [
    'reset' => 1,
    'id' => $contributionId,
    'cid' => $contactId,
  ]));
}

/**
 * Define hook_civicrm_donationReceiptTokenValues
 *
 * @param array $values
 * @return void
 */
function donrecextra_civicrm_donationReceiptTokenValues(&$values) {
  // Donrec accesses every configured receipt token directly. Older releases
  // do not use isset(), which produces PHP 8 warnings for optional fields.
  // Supplying NULL defaults preserves Donrec's fallback behaviour while
  // keeping headless and cron runs quiet.
  $receiptFields = CRM_Donrecextra_DonrecMetadata::getCustomFields('zwb_donation_receipt');
  foreach (array_keys($receiptFields) as $tokenName) {
    if (str_starts_with($tokenName, 'shipping_')) {
      $addressToken = substr($tokenName, strlen('shipping_'));
      $values['addressee'][$addressToken] = $values['addressee'][$addressToken] ?? NULL;
    }
    else {
      if (!array_key_exists($tokenName, $values)) {
        $values[$tokenName] = NULL;
      }
      if (!array_key_exists($tokenName, $values['contributor'])) {
        $values['contributor'][$tokenName] = NULL;
      }
    }
  }

  $values_config = (array) Civi::settings()->get(E::SHORT_NAME);
  $extraContactTokens = !empty($values_config['donrecextra_extra_tokens_contact']);
  $organizationReceipts = !empty($values_config['donrecextra_enable_organization_receipts']);

  if ($extraContactTokens || $organizationReceipts) {
    $config_donrec_extra = C::singleton()->getParams();
    $fields_to_return = ["legal_name", "first_name", "last_name", "organization_name", "state_province", "languages", "phone", "email", "prefix_id"];
    if ($extraContactTokens) {
      foreach (($config_donrec_extra["customFieldsContact"] ?? []) as $key_field => $value) {
        $fields_to_return[] = $key_field;
      }
    }

    $organizationTokenFields = [];
    if ($organizationReceipts) {
      $organizationTokenFields = CRM_Donrecextra_OrganizationReceipt::ensureCustomFields();
      $fields_to_return = array_merge($fields_to_return, array_keys($organizationTokenFields));
    }
    $fields_to_return = array_values(array_unique($fields_to_return));

    if (!empty($values["contributor"]["id"])) {
      $contact_extra = civicrm_api3('Contact', 'getSingle', ['id' => $values["contributor"]["id"], "return" => $fields_to_return]);
      $values["contributor"] = array_merge($contact_extra, $values["contributor"]);
      C::replace_option_values($values["contributor"]);
      foreach ($organizationTokenFields as $customField => $tokenName) {
        $values["contributor"][$tokenName] = $values["contributor"][$customField] ?? '';
      }
    }
  }

  if (!empty($values_config['donrecextra_extra_tokens_address'])) {
    $values["address"] = C::lookupAddressExtend($values["contributor"]["id"], $values_config['donrecextra_location_type'] ?? 0);
  }

  if (!empty($values_config['donrecextra_extra_tokens_contribution'])) {
    foreach ($values["lines"] as $key => $value) {
      if (!empty($value["contribution_id"])) {
        $contribution = civicrm_api3('Contribution', 'getSingle', ['id' => $value["contribution_id"]]);
        C::replace_option_values($contribution);
        $values["lines"][$key] = array_merge($contribution, $values["lines"][$key]);
      }
    }
  }
}

function donrecextra_civicrm_navigationMenu(&$menu) {
  _donrecextra_civix_insert_navigation_menu($menu, 'Administer/CiviContribute/donrec', [
    'label' => E::ts('DonRec Extra Configuration'),
    'url' => 'civicrm/admin/donrecextra',
    'name' => 'donrecextra_admin_config',
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => 0,
    'weight' => 3,
  ]);
  _donrecextra_civix_insert_navigation_menu($menu, 'Contributions', [
    'label' => E::ts('Donation receipt audit'),
    'url' => 'civicrm/admin/donrecextra/audit?reset=1',
    'name' => 'donrecextra_receipt_audit',
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => 0,
    'weight' => 1002,
  ]);
  _donrecextra_civix_insert_navigation_menu($menu, 'Contributions', [
    'label' => E::ts('Donation receipt campaigns'),
    'url' => 'civicrm/admin/donrecextra/queue?reset=1',
    'name' => 'donrecextra_receipt_campaigns',
    'permission' => 'create and withdraw receipts',
    'operator' => NULL,
    'separator' => 0,
    'weight' => 1003,
  ]);
}
