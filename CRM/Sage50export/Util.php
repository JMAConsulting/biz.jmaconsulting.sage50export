<?php

/**
 * Class to send Moodle API request
 */
class CRM_Sage50export_Util {


  /**
   * IF the given array of batch IDs consist of any transactions related to grant payment
   */
  public static function batchesByEntityTable($batchIDs, $entityTable) {
      $sql = "SELECT COUNT(eb.batch_id)
      FROM civicrm_entity_batch eb
      INNER JOIN civicrm_financial_trxn tx ON tx.id = eb.entity_id AND eb.entity_table = 'civicrm_financial_trxn'
      INNER JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id = tx.id AND eft.entity_table = '{$entityTable}'
      INNER JOIN civicrm_batch b ON b.id = eb.batch_id
      WHERE eb.batch_id IN (" . implode(',', $batchIDs) . ")
      GROUP BY eb.batch_id";
      $dao = CRM_Core_DAO::executeQuery($sql);
      return $dao->N;
  }

  public static function fetchEntries($batchID) {
    $sql = "SELECT
      ft.id as financial_trxn_id,
      ft.trxn_date,
      fa_to.accounting_code AS to_account_code,
      fa_to.name AS to_account_name,
      fa_to.account_type_code AS to_account_type_code,
      fa_to.id AS to_account_id,
      ft.total_amount AS debit_total_amount,
      ft.trxn_id AS trxn_id,
      cov.label AS payment_instrument,
      ft.check_number,
      c.id AS contribution_id,
      c.total_amount AS contribution_amount,
      c.contact_id AS contact_id,
      cc.display_name,
      eb.batch_id AS batch_id,
      ft.currency AS currency,
      CASE
        WHEN efti.entity_id IS NOT NULL
        THEN efti.amount
        ELSE eftc.amount
      END AS amount,
      fa_from.account_type_code AS credit_account_type_code,
      fa_from.accounting_code AS credit_account,
      fa_from.name AS credit_account_name,
      fa_from.id AS credit_account_id,
      fac.account_type_code AS from_credit_account_type_code,
      fac.accounting_code AS from_credit_account,
      fac.name AS from_credit_account_name,
      fac.id AS from_credit_account_id,
      fi.description AS item_description,
      fi.id AS financial_item_id,
      b.title as batch_title,
      ce_from.chapter_code as chapter_from,
      ce_to.chapter_code as chapter_to,
      covf_from.label as fund_from,
      covf_to.label as fund_to,
      eftc.entity_id AS entity_id
      FROM civicrm_entity_batch eb
      LEFT JOIN civicrm_financial_trxn ft ON (eb.entity_id = ft.id AND eb.entity_table = 'civicrm_financial_trxn')
      LEFT JOIN civicrm_batch b ON eb.batch_id = b.id
      LEFT JOIN civicrm_financial_account fa_to ON fa_to.id = ft.to_financial_account_id
      LEFT JOIN civicrm_financial_account fa_from ON fa_from.id = ft.from_financial_account_id
      LEFT JOIN civicrm_option_group cog ON cog.name = 'payment_instrument'
      LEFT JOIN civicrm_option_value cov ON (cov.value = ft.payment_instrument_id AND cov.option_group_id = cog.id)
      LEFT JOIN civicrm_entity_financial_trxn eftc ON (eftc.financial_trxn_id  = ft.id AND eftc.entity_table = 'civicrm_contribution')
      LEFT JOIN civicrm_contribution c ON c.id = eftc.entity_id
      LEFT JOIN civicrm_contact cc ON cc.id = c.contact_id
      LEFT JOIN civicrm_entity_financial_trxn efti ON (efti.financial_trxn_id  = ft.id AND efti.entity_table = 'civicrm_financial_item')
      LEFT JOIN civicrm_financial_item fi ON fi.id = efti.entity_id
      LEFT JOIN civicrm_chapter_entity ce_from ON ce_from.entity_id = fi.id AND ce_from.entity_table = 'civicrm_financial_item'
      LEFT JOIN civicrm_chapter_entity ce_to ON ce_to.entity_id = ft.id AND ce_to.entity_table = 'civicrm_financial_trxn'
      LEFT JOIN civicrm_option_group cogf ON cogf.name = 'fund_codes'
      LEFT JOIN civicrm_option_value covf_from ON (covf_from.value = ce_from.fund_code AND covf_from.option_group_id = cogf.id)
      LEFT JOIN civicrm_option_value covf_to ON (covf_to.value = ce_to.fund_code AND covf_to.option_group_id = cogf.id)
      LEFT JOIN civicrm_financial_account fac ON fac.id = fi.financial_account_id
      LEFT JOIN civicrm_financial_account fa ON fa.id = fi.financial_account_id
      WHERE eb.batch_id = ( %1 ) AND ft.id IS NOT NULL ORDER BY ft.trxn_date ASC";

    $params = array(1 => array($batchID, 'Integer'));
    $dao = CRM_Core_DAO::executeQuery($sql, $params);

    return self::formatSage50BatchParams($dao, $batchID);
  }

  public static function formatSage50BatchParams($dao, $batchID) {
    $sage50Batch = [];
    $totalAmount = 0;
    while ($dao->fetch()) {
      $batch = $dao->batch_title;
      $doc = $dao->batch_id;
      $date = date('m-d-y', strtotime($dao->trxn_date));
      $sage50Batch[$dao->financial_trxn_id] = [
        "from_header" => [
          "code" => $dao->from_credit_account . "-" . $dao->chapter_from,
          "amount" => $dao->debit_total_amount,
          "comment" => $dao->item_description,
          "project_allocation" => 1,
        ],
        "from_entry" => [
          "fund" => $dao->fund_from,
          "amount" => $dao->debit_total_amount,
        ],
        "to_header" => [
          "code" => $dao->to_account_code . "-" . $dao->chapter_to,
          "amount" => -$dao->debit_total_amount,
          "comment" => $dao->item_description,
          "project_allocation" => 1,
        ],
        "to_entry" => [
          "fund" => $dao->fund_to,
          "amount" => -$dao->debit_total_amount,
        ],
      ];
    }
    $header = [
      "batch_description" => [
        "date" => $date,
        "doc_number" => $doc,
        "header" => $batch,
      ],
    ];
    $sage50Batch = $header + $sage50Batch;
    return $sage50Batch;
  }

  public static function processSyncIntacctResponse($batchID, $response) {
    $activity = civicrm_api3('Activity', 'getsingle', [
      'source_record_id' => $batchID,
      'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Scheduled'),
      'activity_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Export Accounting Batch'),
    ]);

    $fileName = CRM_Core_Config::singleton()->uploadDir . 'Financial_Transactions_Response_' . date('YmdHis') . '.txt';
    $content = sprintf('Batch ID - %d: %s', $batchID, var_export($response, TRUE));
    file_put_contents($fileName, $content, FILE_APPEND);

    $activityParams = array(
      'id' => $activity['id'],
      'attachFile_2' => array(
        'uri' => $fileName,
        'type' => 'text/plain',
        'location' => $fileName,
        'upload_date' => date('YmdHis'),
      ),
    );
      $activityParams['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Completed');
      civicrm_api3('Batch', 'create', [
        'id' => $batchID,
        'data' => 'Synchronization completed at ' . date('Y-m-d H:i:s'),
      ]);
      CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sage50_batches WHERE batch_id = " . $batchID);
    CRM_Activity_BAO_Activity::create($activityParams);
  }

}
