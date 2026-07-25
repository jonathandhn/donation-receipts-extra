<?php

use CRM_Donrecextra_ExtensionUtil as E;

/**
 * Validates the administrator-selected receipt tokens before Donrec renders.
 *
 * Donrec stores template HTML on its profile, so requirements are deliberately
 * configured per profile rather than by attempting to parse Smarty markup.
 */
class CRM_Donrecextra_ReceiptDataValidator {

  /**
   * @return array<string, string>
   */
  public static function getTokenOptions(): array {
    $options = [
      'contributor.first_name' => E::ts('$contributor.first_name — First name'),
      'contributor.last_name' => E::ts('$contributor.last_name — Last name'),
      'contributor.display_name' => E::ts('$contributor.display_name — Display name'),
      'contributor.organization_name' => E::ts('$contributor.organization_name — Organization name'),
      'contributor.legal_name' => E::ts('$contributor.legal_name — Legal name'),
      'contributor.email' => E::ts('$contributor.email — Email'),
      'contributor.street_address' => E::ts('$contributor.street_address — Street address'),
      'contributor.supplemental_address_1' => E::ts('$contributor.supplemental_address_1 — Address supplement'),
      'contributor.postal_code' => E::ts('$contributor.postal_code — Postal code'),
      'contributor.city' => E::ts('$contributor.city — City'),
      'contributor.country' => E::ts('$contributor.country — Country'),
    ];

    foreach (self::getCustomContactTokenOptions() as $field => $label) {
      $options['contributor.' . $field] = '$contributor.' . $field . ' — ' . $label;
    }
    asort($options);
    return $options;
  }

  /**
   * Receipt and receipt-item custom groups are technically attached to a
   * contact in some Donrec versions. They are snapshot storage, not donor
   * data, and must never be offered as required contributor tokens.
   *
   * @return array<string, string>
   */
  private static function getCustomContactTokenOptions(): array {
    $result = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'return' => ['id', 'label', 'custom_group_id.name', 'custom_group_id.title'],
      'custom_group_id.extends' => ['IN' => ['Contact', 'Individual', 'Organization', 'Household']],
      'options' => ['sort' => 'custom_group_id.title, label', 'limit' => 0],
    ]);
    $options = [];
    foreach ($result['values'] as $field) {
      $groupName = (string) ($field['custom_group_id.name'] ?? '');
      if (str_starts_with($groupName, 'zwb_donation_receipt')) {
        continue;
      }
      $options['custom_' . (int) $field['id']] = sprintf('%s :: %s',
        (string) ($field['custom_group_id.title'] ?? E::ts('Custom field')),
        (string) $field['label']
      );
    }
    return $options;
  }

  /**
   * @param CRM_Donrec_Logic_Profile $profile
   * @param int[] $contributionIds
   */
  public function assertValid(CRM_Donrec_Logic_Profile $profile, array $contributionIds): void {
    $requirements = $this->getRequirements($profile->getId());
    if (!$requirements || !$contributionIds) {
      return;
    }
    $this->assertTokensAreExposed($requirements);

    $contactIds = $this->getContactIds($contributionIds);
    $missing = [];
    foreach ($contactIds as $contactId) {
      $contact = $this->loadContact($contactId, $requirements);
      $address = $this->loadPrimaryAddress($contactId);
      $missingTokens = [];
      foreach ($requirements as $token) {
        if ($this->isMissing($token, $contact, $address)) {
          $missingTokens[] = self::getTokenOptions()[$token] ?? $token;
        }
      }
      if ($missingTokens) {
        $missing[] = E::ts('Contact %1: %2', [
          1 => $contactId,
          2 => implode(', ', $missingTokens),
        ]);
      }
    }

    if ($missing) {
      throw new CRM_Core_Exception(E::ts(
        'Receipt profile “%1” requires data which is missing. %2',
        [1 => $profile->getName(), 2 => implode('; ', $missing)]
      ));
    }
  }

  /**
   * @return string[]
   */
  private function getRequirements(int $profileId): array {
    $settings = (array) Civi::settings()->get(E::SHORT_NAME);
    $requirements = $settings['donrecextra_required_tokens_by_profile'][$profileId] ?? [];
    return array_values(array_intersect((array) $requirements, array_keys(self::getTokenOptions())));
  }

  /**
   * Donrec core does not expose names, email or arbitrary Contact custom
   * fields to its receipt Smarty context. Requiring such a token while the
   * Donrec Extra token feature is disabled would otherwise produce an empty
   * value in the rendered PDF despite valid contact data.
   *
   * @param string[] $requirements
   */
  private function assertTokensAreExposed(array $requirements): void {
    $coreTokens = [
      'contributor.display_name',
      'contributor.street_address',
      'contributor.supplemental_address_1',
      'contributor.postal_code',
      'contributor.city',
      'contributor.country',
    ];
    $needsExtraTokens = (bool) array_diff($requirements, $coreTokens);
    $settings = (array) Civi::settings()->get(E::SHORT_NAME);
    if ($needsExtraTokens
      && empty($settings['donrecextra_extra_tokens_contact'])
      && empty($settings['donrecextra_enable_organization_receipts'])) {
      throw new CRM_Core_Exception(E::ts(
        'The selected required tokens need “Enable custom tokens Contact” to be enabled in Donation Receipts Extra settings.'
      ));
    }
  }

  /**
   * @param int[] $contributionIds
   * @return int[]
   */
  private function getContactIds(array $contributionIds): array {
    $ids = implode(',', array_map('intval', $contributionIds));
    $dao = CRM_Core_DAO::executeQuery("SELECT DISTINCT contact_id FROM civicrm_contribution WHERE id IN ($ids)");
    $contactIds = [];
    while ($dao->fetch()) {
      $contactIds[] = (int) $dao->contact_id;
    }
    return $contactIds;
  }

  /**
   * @param string[] $requirements
   * @return array<string, mixed>
   */
  private function loadContact(int $contactId, array $requirements): array {
    $fields = ['id', 'display_name', 'first_name', 'last_name', 'organization_name', 'legal_name', 'email'];
    foreach ($requirements as $token) {
      if (preg_match('/^contributor\.(custom_\d+)$/', $token, $matches)) {
        $fields[] = $matches[1];
      }
    }
    return civicrm_api3('Contact', 'getsingle', [
      'id' => $contactId,
      'return' => array_values(array_unique($fields)),
    ]);
  }

  /**
   * @return array<string, mixed>
   */
  private function loadPrimaryAddress(int $contactId): array {
    $result = civicrm_api3('Address', 'get', [
      'contact_id' => $contactId,
      'is_primary' => 1,
      'sequential' => 1,
      'options' => ['limit' => 1],
    ]);
    return $result['values'][0] ?? [];
  }

  /**
   * @param array<string, mixed> $contact
   * @param array<string, mixed> $address
   */
  private function isMissing(string $token, array $contact, array $address): bool {
    $field = substr($token, strlen('contributor.'));
    $value = match ($field) {
      'street_address', 'supplemental_address_1', 'postal_code', 'city' => $address[$field] ?? NULL,
      'country' => $address['country_id'] ?? NULL,
      'organization_name' => $contact['organization_name'] ?? $contact['display_name'] ?? NULL,
      default => $contact[$field] ?? NULL,
    };
    return $value === NULL || (is_string($value) && trim($value) === '');
  }

}
