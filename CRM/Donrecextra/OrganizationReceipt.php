<?php

use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Provision and expose legal information used on organization receipts.
 *
 * Machine names are deliberately used instead of numeric custom-field IDs so
 * that the feature can be deployed safely on different CiviCRM instances.
 */
class CRM_Donrecextra_OrganizationReceipt {

  private const CUSTOM_GROUP_NAME = 'INFORMATIONS_L_GALES';

  /**
   * Custom field definitions indexed by their stable machine name.
   */
  private const CUSTOM_FIELDS = [
    'SIREN' => [
      'label' => 'SIREN',
      'data_type' => 'Int',
      'html_type' => 'Text',
      'weight' => 8,
      'token' => 'organization_siren',
    ],
    'Num_ro_de_TVA' => [
      'label' => 'Numéro de TVA',
      'data_type' => 'String',
      'html_type' => 'Text',
      'weight' => 11,
      'token' => 'organization_vat_number',
    ],
    'Forme_juridique' => [
      'label' => 'Forme juridique',
      'data_type' => 'String',
      'html_type' => 'Text',
      'weight' => 12,
      'token' => 'organization_legal_form',
    ],
    'SIRET_DU_SIEGE' => [
      'label' => 'Siret du siège',
      'data_type' => 'String',
      'html_type' => 'Text',
      'weight' => 14,
      'token' => 'organization_head_office_siret',
    ],
  ];

  /**
   * Ensure the organization custom group and its fields exist.
   *
   * Existing fields are preserved. The returned map uses API field names
   * (custom_N) as keys and portable receipt token names as values.
   *
   * @return array<string,string>
   * @throws \CRM_Core_Exception
   */
  public static function ensureCustomFields(): array {
    $group = civicrm_api3('CustomGroup', 'get', [
      'sequential' => 1,
      'name' => self::CUSTOM_GROUP_NAME,
      'options' => ['limit' => 1],
    ]);

    if (!empty($group['values'][0])) {
      $groupId = (int) $group['values'][0]['id'];
    }
    else {
      $createdGroup = civicrm_api3('CustomGroup', 'create', [
        'name' => self::CUSTOM_GROUP_NAME,
        'title' => E::ts('LEGAL INFORMATION'),
        'extends' => 'Organization',
        'style' => 'Inline',
        'is_multiple' => 0,
        'is_active' => 1,
        'is_public' => 1,
        'collapse_display' => 0,
        'collapse_adv_display' => 1,
      ]);
      $groupId = (int) $createdGroup['id'];
    }

    $tokenFields = [];
    foreach (self::CUSTOM_FIELDS as $name => $definition) {
      $field = civicrm_api3('CustomField', 'get', [
        'sequential' => 1,
        'custom_group_id' => $groupId,
        'name' => $name,
        'options' => ['limit' => 1],
      ]);

      if (!empty($field['values'][0])) {
        $fieldId = (int) $field['values'][0]['id'];
      }
      else {
        $createdField = civicrm_api3('CustomField', 'create', [
          'custom_group_id' => $groupId,
          'name' => $name,
          'label' => E::ts($definition['label']),
          'data_type' => $definition['data_type'],
          'html_type' => $definition['html_type'],
          'weight' => $definition['weight'],
          'is_required' => 0,
          'is_searchable' => 1,
          'is_active' => 1,
        ]);
        $fieldId = (int) $createdField['id'];
      }

      $tokenFields['custom_' . $fieldId] = $definition['token'];
    }

    return $tokenFields;
  }

}
