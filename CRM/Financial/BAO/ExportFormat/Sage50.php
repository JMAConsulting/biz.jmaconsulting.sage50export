<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2018                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2018
 */

/**
 * @link http://wiki.civicrm.org/confluence/display/CRM/CiviAccounts+Specifications+-++Batches#CiviAccountsSpecifications-Batches-%C2%A0Overviewofimplementation
 */
class CRM_Financial_BAO_ExportFormat_Sage50 extends CRM_Financial_BAO_ExportFormat {

  /**
   * Class constructor.
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * @param array $exportParams
   */
  public function export($exportParams) {
    parent::export($exportParams);
  }

  /**
   * @param int $batchId
   *
   * @return Object
   */
  public function generateExportQuery($batchID) {
    civicrm_api3('Batch', 'create', [
      'id' => $batchID,
      'data' => 'Not Synchronized',
    ]);

    return $batchID;
  }

  /**
   * Generate CSV array for export.
   *
   * @param array $export
   */
  public function makeExport($export) {
    $batchEntries = [];
    foreach ($export as $batchID) {
      $this->_batchIds = $batchID;
      CRM_Core_DAO::executeQuery("INSERT IGNORE INTO civicrm_sage50_batches(`batch_id`) VALUES ($batchID)");
      $batchEntries[$batchID] = CRM_Sage50export_Util::fetchEntries($batchID);
    }

    // Save the file in the public directory.
    $fileName = self::putFile($batchEntries);

    $this->output($fileName);
  }

  /**
   * Exports sbatches in $this->_batchIds, and saves to file.
   *
   * @param string $fileName - use this file name (if applicable)
   */
  public function output($fileName = NULL) {
    // Default behaviour, override if needed:
    self::createActivityExport($this->_batchIds, $fileName);
  }

  /**
   * @param $batchIds
   * @param string $fileName
   *
   * @throws CRM_Core_Exception
   */
  public static function createActivityExport($batchIds, $fileName) {
    $loggedInContactId =  CRM_Core_Session::getLoggedInContactID();
    $values = array();
    $params = array('id' => $batchIds);
    CRM_Batch_BAO_Batch::retrieve($params, $values);
    $createdBy = CRM_Contact_BAO_Contact::displayName($values['created_id']);
    $modifiedBy = CRM_Contact_BAO_Contact::displayName($values['modified_id']);

    $values['payment_instrument_id'] = '';
    if (isset($values['payment_instrument_id'])) {
      $paymentInstrument = array_flip(CRM_Contribute_PseudoConstant::paymentInstrument('label'));
      $values['payment_instrument_id'] = array_search($values['payment_instrument_id'], $paymentInstrument);
    }
    $details = '<p>' . ts('Record:') . ' ' . $values['title'] . '</p><p>' . ts('Description:') . '</p><p>' . ts('Created By:') . " $createdBy" . '</p><p>' . ts('Created Date:') . ' ' . $values['created_date'] . '</p><p>' . ts('Last Modified By:') . ' ' . $modifiedBy . '</p><p>' . ts('Payment Method:') . ' ' . $values['payment_instrument_id'] . '</p>';
    if (!empty($values['total'])) {
      $details .= ts('Total') . '[' . CRM_Utils_Money::format($values['total']) . '],';
    }
    if (!empty($values['item_count'])) {
      $details .= ' ' . ts('Count') . '[' . $values['item_count'] . '],';
    }

    // create activity.
    $details .= ' ' . ts('Batch') . '[' . $values['title'] . ']';
    $activityParams = array(
      'activity_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Export Accounting Batch'),
      'subject' => 'Payments exported to Sage 50',
      'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Scheduled'),
      'activity_date_time' => date('YmdHis'),
      'source_contact_id' => $loggedInContactId,
      'source_record_id' => $values['id'],
      'target_contact_id' => $loggedInContactId,
      'details' => $details,
      'attachFile_1' => array(
        'uri' => $fileName,
        'type' => 'text/plain',
        'location' => $fileName,
        'upload_date' => date('YmdHis'),
      ),
    );

    CRM_Activity_BAO_Activity::create($activityParams);
  }

  /**
   * @param $export
   *
   * @return string
   */
  public function putFile($export) {
    $fileName = CRM_Core_Config::singleton()->uploadDir . 'Financial_Transactions_Sage50Entries_' . date('YmdHis') . '.' . $this->getFileExtension();
    $out = fopen($fileName, 'w');
    $content = [];
    foreach ($export as $batchId => $item) {
      foreach ($item as $trxn => $entries) {
        if ($trxn == "batch_description") {
          $content[] = $entries['date'] . ',"' . $entries['doc_number'] . '","' . $entries['header'] . '"';
        }
        foreach ($entries as $header => $entry) {
          if ($header == 'from_header') {
            $content[] = $entry['code'] . ',' . $entry['amount'] . ',"' . $entry['comment'] . '","' . $entry['project_allocation'] . '"';
          }
          if ($header == 'from_entry') {
            $content[] = '"' . $entry['fund'] . '",' . $entry['amount'];
          }
          if ($header == 'to_header') {
            $content[] = $entry['code'] . ',' . $entry['amount'] . ',"' . $entry['comment'] . '","' . $entry['project_allocation'] . '"';
          }
          if ($header == 'to_entry') {
            $content[] = '"' . $entry['fund'] . '",' . $entry['amount'];
          }
        }
      }
    }
    $content = implode("\n", $content);
    file_put_contents($fileName, $content, FILE_APPEND);
    fclose($out);

    return $fileName;
  }


  /**
   * @return void
   */
  public function getFileExtension() {
    return 'txt';
  }

}
