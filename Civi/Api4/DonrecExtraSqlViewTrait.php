<?php

namespace Civi\Api4;

use Civi\Core\Event\GenericHookEvent;

/**
 * Rebuild SQL views atomically when the API4 entity cache is refreshed.
 */
trait DonrecExtraSqlViewTrait {

  /**
   * Rebuild the view without a drop/create race between concurrent requests.
   *
   * @internal
   */
  public static function _on_civi_api4_entityTypes(GenericHookEvent $event): void {
    $entityName = static::getEntityName();
    if (!isset($event->entityTypes[$entityName])) {
      $event->entityTypes[$entityName] = static::getInfo();
    }

    try {
      static::rebuildSqlView();
    }
    catch (\Throwable $e) {
      if (class_exists('Civi') && method_exists('Civi', 'log')) {
        \Civi::log()->debug('DonrecExtraSqlViewTrait: rebuildSqlView deferred: ' . $e->getMessage());
      }
    }
  }

  /**
   * Create or replace the SQL view for this API entity.
   */
  protected static function rebuildSqlView(): void {
    $viewName = static::viewName();
    if (preg_match('/^[a-zA-Z0-9_]+$/', $viewName) !== 1) {
      throw new \CRM_Core_Exception('Invalid Donrec Extra SQL view name');
    }

    $selects = [];
    foreach (static::viewSelect() as $field) {
      $fieldName = $field['name'] ?? NULL;
      if (!is_string($fieldName) || preg_match('/^[a-zA-Z0-9_]+$/', $fieldName) !== 1) {
        throw new \CRM_Core_Exception('Invalid Donrec Extra SQL view field name');
      }
      $selects[] = "{$field['select']} AS `{$fieldName}`";
    }

    $select = implode(', ', $selects);
    \CRM_Core_DAO::executeQuery("CREATE OR REPLACE VIEW `$viewName` AS SELECT $select " . static::viewFrom());
  }

}
