<?php

use Civi\Api4\DonationReceiptItem;
use Civi\Api4\Contribution;
use Civi\Api4\DonationReceipt;

class CRM_Donrecextra_Page_DownloadReceipt extends CRM_Core_Page {

  public function run() {
    $contributionId = CRM_Utils_Request::retrieve('id', 'Positive', $this, TRUE);
    $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this, FALSE);

    $contribution = Contribution::get(FALSE)
      ->addSelect('id', 'contact_id', 'receive_date')
      ->addWhere('id', '=', $contributionId)
      ->setLimit(1)
      ->execute()
      ->first();

    if (!$contribution) {
      throw new CRM_Core_Exception(ts('Invalid contribution receipt request.'), 403);
    }

    $contributionContactId = (int) $contribution['contact_id'];
    if (!$contactId) {
      $contactId = $contributionContactId;
    }
    else {
      $contactId = (int) $contactId;
    }

    if ($contributionContactId !== $contactId) {
      throw new CRM_Core_Exception(ts('Invalid contribution receipt request.'), 403);
    }

    if (!CRM_Contact_BAO_Contact_Permission::allow($contactId, CRM_Core_Permission::VIEW)) {
      throw new CRM_Core_Exception(ts('You do not have permission to access this donation receipt.'), 403);
    }

    $receipt = DonationReceiptItem::get(FALSE)
      ->addSelect('original_file_id', 'contact_id', 'file_uri', 'file_mime_type', 'issued_on')
      ->addWhere('contribution_id', '=', $contributionId)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('status', 'IN', ['ORIGINAL', 'original'])
      ->addWhere('original_file_id', 'IS NOT NULL')
      ->addWhere('file_uri', 'IS NOT NULL')
      ->addWhere('file_mime_type', 'IS NOT NULL')
      ->addOrderBy('issued_on', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();

    if (!$receipt) {
      if (!empty($contribution['receive_date'])) {
        $receipt = DonationReceipt::get(FALSE)
          ->addSelect('original_file_id', 'contact_id', 'file_uri', 'file_mime_type', 'issued_on')
          ->addWhere('contact_id', '=', $contactId)
          ->addWhere('status', 'IN', ['ORIGINAL', 'original'])
          ->addWhere('original_file_id', 'IS NOT NULL')
          ->addWhere('file_uri', 'IS NOT NULL')
          ->addWhere('file_mime_type', 'IS NOT NULL')
          ->addWhere('date_from', '<=', $contribution['receive_date'])
          ->addWhere('date_to', '>=', $contribution['receive_date'])
          ->addOrderBy('issued_on', 'DESC')
          ->setLimit(1)
          ->execute()
          ->first();
      }
    }

    if (!$receipt) {
      CRM_Core_Session::setStatus(
        ts('No donation receipt file is available for this contribution.'),
        ts('Donation receipt unavailable'),
        'warning'
      );
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contribute/transact', [
        'reset' => 1,
        'id' => $contributionId,
        'cid' => $contactId,
      ]));
    }

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/file', [
      'reset' => 1,
      'id' => (int) $receipt['original_file_id'],
      'eid' => (int) $receipt['contact_id'],
      'filename' => $receipt['file_uri'],
      'mime-type' => $receipt['file_mime_type'],
    ]));
  }

}
