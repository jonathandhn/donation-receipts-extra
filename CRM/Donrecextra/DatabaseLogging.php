<?php

/**
 * Run writes without CiviCRM's SQL logging triggers.
 *
 * Donrecextra's audit tables are already an append-only fiscal ledger. Logging
 * them again is redundant and, on installations with a separate logging
 * database, can fail before CiviCRM has created the corresponding log tables.
 */
class CRM_Donrecextra_DatabaseLogging {

  public static function disabled(callable $operation) {
    $previous = CRM_Core_DAO::singleValueQuery('SELECT @civicrm_disable_logging');
    CRM_Core_DAO::executeQuery('SET @civicrm_disable_logging = 1');

    try {
      return $operation();
    }
    finally {
      CRM_Core_DAO::executeQuery(
        $previous === NULL
          ? 'SET @civicrm_disable_logging = NULL'
          : 'SET @civicrm_disable_logging = ' . ((int) $previous)
      );
    }
  }

}
