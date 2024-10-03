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
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function donrecextra_civicrm_enable() {
  _donrecextra_civix_civicrm_enable();
}

/**
 * Define hook_civicrm_donationReceiptTokenValues
 *
 * @param array $values
 * @return void
 */
function donrecextra_civicrm_donationReceiptTokenValues(&$values) {
  $values_config = Civi::settings()->get(E::SHORT_NAME);
  if ($values_config['donrecextra_extra_tokens_contact'] == 1) {
    $config_donrec_extra = C::singleton()->getParams();
    $fields_to_return = ["legal_name", "first_name", "last_name", "state_province", "languages", "phone", "email", "prefix_id"];
    foreach ($config_donrec_extra["customFieldsContact"] as $key_field => $value) {
      $fields_to_return[] = $key_field;
    }

    if (!empty($values["contributor"]["id"])) {
      $contact_extra = civicrm_api3('Contact', 'getSingle', ['id' => $values["contributor"]["id"], "return" => $fields_to_return]);
      $values["contributor"] = array_merge($contact_extra, $values["contributor"]);
      C::replace_option_values($values["contributor"]);
    }
  }

  if ($values_config['donrecextra_extra_tokens_address'] == 1) {
    $values["address"] = C::lookupAddressExtend($values["contributor"]["id"], $values_config['donrecextra_location_type']);
  }

  if ($values_config['donrecextra_extra_tokens_contribution'] == 1) {
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
  _donrecextra_civix_insert_navigation_menu($menu, 'Administer', [
    'label' => E::ts('DonRec Extra Configuration'),
    'url' => 'civicrm/admin/donrecextra',
    'name' => 'donrecextra_admin_config',
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => 0,
  ]);
}
