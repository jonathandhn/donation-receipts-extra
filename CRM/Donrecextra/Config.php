<?php

use CRM_Donrecextra_ExtensionUtil as E;

class CRM_Donrecextra_Config {

  private static $possible_values_address = [
    "state_province_name",
    "state_province_abbreviation",
    "country",
    "state_province_id",
    "supplemental_address_1",
    "supplemental_address_2",
    "city",
    "postal_code",
    "id",
  ];

  private static $_singleton = NULL;

  private $params = [];

  public static function &singleton() {
    if (self::$_singleton === NULL) {
      // first, attempt to get configuration object from cache
      $cache = CRM_Utils_Cache::singleton();
      self::$_singleton = $cache->get('CRM_Donrecextra_Config');
      // if not in cache, fire off config construction
      if (!self::$_singleton) {
        self::$_singleton = new CRM_Donrecextra_Config();
        self::$_singleton->_initialize();
        $cache->set('CRM_Donrecextra_Config', self::$_singleton);
      }
      else {
        self::$_singleton->_initialize();
      }
    }
    return self::$_singleton;
  }

  private function _initialize() {
    $this->params = Civi::settings()->get('donrecextra');
    $this->params["customFieldsContact"] = $this->getCustomFields();
  }

  public function getParams($param = '') {
    if (!empty($param)) {
      return isset($this->params[$param]) ? $this->params[$param] : NULL;
    }
    else {
      return $this->params;
    }
  }

  public function setParams($params = []) {
    Civi::settings()->set('donrecextra', $params);
  }

  public static function getConstants() {
    $oClass = new ReflectionClass(__CLASS__);
    return $oClass->getConstants();
  }

  public static function getCustomFields($extends = NULL) {
    $custom_fields = [];
    if (!$extends) {
      $extends = ["Contact", "Individual", "Organization", "Household"];
    }
    else {
      if (!is_array($extends)) {
        $extends = [$extends];
      }
    }

    // List of Custom Fields
    $results = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'return' => ["id", "label", "name", "custom_group_id.name", "custom_group_id.table_name", "custom_group_id.title"],
      'custom_group_id.extends' => ['IN' => $extends],
      'options' => ['sort' => "custom_group_id.title, label", 'limit' => 0],
    ]);
    foreach ($results["values"] as $value) {
      $custom_fields['custom_' . $value['id']] = $value['custom_group_id.title'] . ' :: ' . $value['label'];
    }

    return $custom_fields;
  }

  public static function fieldOptions($entities = []) {
    if (empty($entities)) {
      $entities = [
        'contact',
        'contribution',
        'address',
        'membership',
      ];
    }

    // Hack to add options not retrieved by get fields
    $options = [
      'group' => 'group',
      'tag' => 'tag',
      'on_hold' => 'on_hold',
      'is_bulkmail' => 'is_bulkmail',
      'payment_instrument' => 'payment_instrument',
      'membership_status' => 'membership_status',
      'membership_type' => 'membership_type',
      'member_campaign_id' => 'member_campaign_id',
      'member_is_test' => 'member_is_test',
      'member_is_pay_later' => 'member_is_pay_later',
      'is_override' => 'is_override',
    ];

    CRM_Contact_BAO_Query_Hook::singleton()->alterSearchBuilderOptions($entities, $options);
    foreach ($entities as $entity) {
      $fields = civicrm_api3($entity, 'getfields');
      foreach ($fields['values'] as $field => $info) {
        if (isset($info['entity'])) {
          $options[$field] = ts($info['entity']) . '::' . $info['title'];
        }
        elseif (isset($info['groupTitle'])) {
          $options[$field] = ts($info['groupTitle']) . '::' . $info['title'];
        }
        else {
          $options[$field] = $info['title'];
        }
      }
    }
    asort($options);
    return $options;
  }

  public static function importAPIFiles($path) {
    $dir = E::path($path);
    Civi::log()->debug('## Auto Upgrade ## Searching upgrade files in ' . $dir);
    _Donrecextra_civix_civicrm_config();
    $mgdFiles = _Donrecextra_civix_find_files($dir, '*.ins.php');
    foreach ($mgdFiles as $file) {
      Civi::log()->debug('## Auto Upgrade ## Executing ' . $file);
      $es = include $file;
      foreach ($es as $e) {
        $result = civicrm_api3($e['entity'], 'create', $e['params']);
        $result_name = "UPGRADER_" . strtoupper($e['name']);
        if ($e['entity'] == "ActivityType") {
          $result_value = $result["values"][$result["id"]]["value"];
        }
        else {
          $result_value = $result["id"];
        }
        define($result_name, $result_value);
      }
    }
  }

  /**
   * Extended from CRM_Donrec_Logic_ReceiptTokens::lookupAddressTokens
   *
   * @param int $contact_id
   * @param int $location_type
   * @return void
   */
  public static function lookupAddressExtend($contact_id, $location_type) {
    if (empty($contact_id)) {
      return NULL;
    }

    $custom_fields = self::getCustomFields("Address");

    if (is_array($custom_fields)) {
      foreach ($custom_fields as $key => $value) {
        $address[$key] = "";
      }
    }
    foreach (self::$possible_values_address as $key => $value) {
      $address[$value] = "";
    }

    // compile query
    $query_params['contact_id'] = $contact_id;
    $query_params['sequential'] = 1;
    if (empty($location_type) || $location_type == 0) {
      $query_params['is_primary'] = 1;
    }
    else {
      $query_params['location_type_id'] = $location_type;
    }
    // execute the query
    try {
      $address_all = civicrm_api3('Address', 'get', $query_params);

      if (!isset($address_all["values"][0])) {
        return;
      }
      $temp_address = $address_all["values"][0];

      if (!empty($temp_address['country_id'])) {
        $country = CRM_Core_PseudoConstant::country($temp_address['country_id']);
        $temp_address['country'] = $country;
      }
      if (!empty($temp_address['state_province_id'])) {
        $state_province = civicrm_api3('StateProvince', 'getsingle', ['id' => $temp_address['state_province_id']]);
        $temp_address['state_province_name'] = $state_province["name"];
        $temp_address['state_province_abbreviation'] = $state_province["abbreviation"];
      }
      foreach ($temp_address as $key => $value) {
        $address[$key] = $temp_address[$key];
      }

      self::replace_option_values($address);

      return $address;
    }
    catch (Exception $e) {
      // address does not exist
      return NULL;
    }
  }

  /**
   * Method to replace option values
   *
   * @param array $entity_with_params
   * @return void
   */
  public static function replace_option_values(&$entity_with_params) {
    $reg_id_custom_field = '/custom\_([0-9]*)$/m';
    foreach ($entity_with_params as $key => &$value_token) {
      if (strpos($key, 'custom_') !== FALSE) {
        $custom_field_id = substr($key, 7);
        preg_match_all($reg_id_custom_field, $key, $matches, PREG_SET_ORDER, 0);
        if (isset($matches[0][1])) {
          $custom_field_id = $matches[0][1];
          $field = CRM_Core_BAO_CustomField::getFieldObject($custom_field_id);
          if ($field->html_type == 'Select Date') {
            $value_token = CRM_Utils_Date::format($value_token);
          }
          else {
            $value_token = CRM_Core_BAO_CustomField::displayValue($value_token, $field);
          }
        }
      }
    }
  }
}
