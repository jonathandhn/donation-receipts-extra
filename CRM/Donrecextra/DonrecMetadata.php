<?php

/**
 * Retrieves Donrec custom-data metadata without depending on its PHP internals.
 *
 * Donrec Extra supports several Donrec releases. In particular, it must not
 * access a protected cache or rely on the visibility of Donrec helper methods.
 */
class CRM_Donrecextra_DonrecMetadata {

  /**
   * Return the physical table name for a managed Donrec custom group.
   */
  public static function getTableName(string $groupName): ?string {
    $group = self::getGroup($groupName);
    return is_string($group['table_name'] ?? NULL) ? $group['table_name'] : NULL;
  }

  /**
   * Return a map of Donrec field names to physical SQL column names.
   *
   * @return array<string, string>
   */
  public static function getCustomFields(string $groupName): array {
    $group = self::getGroup($groupName);
    if (empty($group['id'])) {
      return [];
    }

    try {
      $result = civicrm_api3('CustomField', 'get', [
        'custom_group_id' => $group['id'],
        'option.limit' => 999,
      ]);
    }
    catch (\Throwable $e) {
      return [];
    }

    $fields = [];
    foreach ($result['values'] ?? [] as $field) {
      if (is_string($field['name'] ?? NULL) && is_string($field['column_name'] ?? NULL)) {
        $fields[$field['name']] = $field['column_name'];
      }
    }
    return $fields;
  }

  /**
   * Look up a custom group through Civi's stable public API.
   *
   * @return array<string, mixed>
   */
  private static function getGroup(string $groupName): array {
    try {
      $group = civicrm_api3('CustomGroup', 'getsingle', ['name' => $groupName]);
      return empty($group['is_error']) ? $group : [];
    }
    catch (\Throwable $e) {
      return [];
    }
  }

}
