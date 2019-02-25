<?php

require_once 'sage50export.civix.php';

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function sage50export_civicrm_config(&$config) {
  _sage50export_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function sage50export_civicrm_xmlMenu(&$files) {
  _sage50export_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function sage50export_civicrm_install() {
  _sage50export_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function sage50export_civicrm_uninstall() {
  _sage50export_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function sage50export_civicrm_enable() {
  _sage50export_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function sage50export_civicrm_disable() {
  _sage50export_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function sage50export_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _sage50export_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function sage50export_civicrm_managed(&$entities) {
  _sage50export_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function sage50export_civicrm_caseTypes(&$caseTypes) {
  _sage50export_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function sage50export_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _sage50export_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

function sage50export_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Financial_Form_Export') {
    $optionTypes = array(
      'IIF' => ts('Export to IIF'),
      'CSV' => ts('Export to CSV'),
      'Sage50' => ts('Export to Sage 50'),
    );
    $form->addRadio('export_format', NULL, $optionTypes, NULL, '<br/>', TRUE);
    $exportOption = CRM_Utils_Array::value('export_format', $_GET, $form->getVar('export-format'));
    if ($exportOption) {
      $form->setVar('export-format', $exportOption);
      CRM_Core_Resources::singleton()->addScript(
        "CRM.$(function($) {
          $('input[name=\"export_format\"]').filter('[value={$exportOption}]').prop('checked', true);
        });"
      );
    }
  }
}