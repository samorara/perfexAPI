<?php

defined('BASEPATH') or exit('No direct script access allowed');

$lang['perfexpilot']                          = 'PerfexPilot';
$lang['perfexpilot_scan']                     = 'Scan';
$lang['perfexpilot_scan_and_fill']            = 'PerfexPilot — Scan & Fill';

// Tabs
$lang['perfexpilot_tab_settings']             = 'Settings';
$lang['perfexpilot_tab_history']              = 'Scan History';

// Settings
$lang['perfexpilot_openai_key']               = 'OpenAI API Key';
$lang['perfexpilot_openai_key_help']          = 'Stored in your Perfex settings and only ever used server side. Leave blank to keep the key you already saved.';
$lang['perfexpilot_key_saved']                = 'A key is currently saved.';
$lang['perfexpilot_key_not_saved']            = 'No key saved yet — scanning is disabled until you add one.';
$lang['perfexpilot_model']                    = 'Model';
$lang['perfexpilot_model_help']               = 'Any vision-capable OpenAI model, for example gpt-5, gpt-5-mini or gpt-4.1.';
$lang['perfexpilot_languages']                = 'Document Languages';
$lang['perfexpilot_languages_help']           = 'Comma separated hint for the languages your documents are written in, e.g. en,de,it,fr,es.';
$lang['perfexpilot_max_file_mb']              = 'Maximum File Size (MB)';
$lang['perfexpilot_confidence_threshold']     = 'Low Confidence Warning Below (%)';
$lang['perfexpilot_timeout']                  = 'Request Timeout (seconds)';
$lang['perfexpilot_retention_days']           = 'Keep Scanned Documents For (days)';
$lang['perfexpilot_retention_days_help']      = 'Documents and extracted data older than this are deleted on the next cron run. Use 0 to keep them indefinitely.';
$lang['perfexpilot_auto_create_tax']          = 'Create a tax automatically when no existing tax matches';
$lang['perfexpilot_enabled_forms']            = 'Show Scan & Fill On';
$lang['perfexpilot_enable_invoices']          = 'Invoices';
$lang['perfexpilot_enable_expenses']          = 'Expenses';
$lang['perfexpilot_enable_payments']          = 'Record Payment';
$lang['perfexpilot_test_connection']          = 'Test connection';
$lang['perfexpilot_testing']                  = 'Testing…';
$lang['perfexpilot_connection_ok']            = 'Connection successful — your OpenAI key works.';

// History
$lang['perfexpilot_date']                     = 'Date';
$lang['perfexpilot_staff']                    = 'Staff';
$lang['perfexpilot_context']                  = 'Form';
$lang['perfexpilot_document']                 = 'Document';
$lang['perfexpilot_status']                   = 'Status';
$lang['perfexpilot_confidence']               = 'Confidence';
$lang['perfexpilot_status_ok']                = 'Extracted';
$lang['perfexpilot_status_low_confidence']    = 'Low confidence';
$lang['perfexpilot_status_failed']            = 'Failed';
$lang['perfexpilot_no_history']               = 'No documents have been scanned yet.';
$lang['perfexpilot_file_removed']             = 'File removed';

// Modal
$lang['perfexpilot_modal_title']              = 'PerfexPilot — Scan & Fill';
$lang['perfexpilot_drop_here']                = 'Drop an invoice or receipt here';
$lang['perfexpilot_drop_hint']                = 'PDF, JPG, PNG, WEBP, HEIC or TIFF';
$lang['perfexpilot_choose_file']              = 'Choose file';
$lang['perfexpilot_take_photo']               = 'Take photo';
$lang['perfexpilot_scanning']                 = 'Reading your document…';
$lang['perfexpilot_scanning_hint']            = 'This usually takes a few seconds. Large PDFs can take longer.';
$lang['perfexpilot_scan_another']             = 'Scan another';
$lang['perfexpilot_apply']                    = 'Apply to form';
$lang['perfexpilot_cancel']                   = 'Cancel';
$lang['perfexpilot_apply_all']                = 'Apply all';
$lang['perfexpilot_fields']                   = 'Fields';
$lang['perfexpilot_line_items']               = 'Line items';
$lang['perfexpilot_no_line_items']            = 'No line items were found in this document.';
$lang['perfexpilot_confidence_meter']         = 'Extraction confidence';
$lang['perfexpilot_low_confidence_warning']   = 'Confidence is low. Check every field against the document before you apply it.';
$lang['perfexpilot_not_configured']           = 'PerfexPilot has no OpenAI key yet. An administrator can add one under Setup → PerfexPilot.';
$lang['perfexpilot_applied']                  = 'Applied to the form. Review the values, then save as usual.';
$lang['perfexpilot_nothing_selected']         = 'Nothing is selected to apply.';

// Field labels used in the review panel
$lang['perfexpilot_field_customer']           = 'Customer';
$lang['perfexpilot_field_vendor']             = 'Vendor';
$lang['perfexpilot_field_number']             = 'Number';
$lang['perfexpilot_field_reference_no']       = 'Reference #';
$lang['perfexpilot_field_issue_date']         = 'Date';
$lang['perfexpilot_field_due_date']           = 'Due date';
$lang['perfexpilot_field_payment_date']       = 'Payment date';
$lang['perfexpilot_field_currency']           = 'Currency';
$lang['perfexpilot_field_subtotal']           = 'Subtotal';
$lang['perfexpilot_field_tax_total']          = 'Tax total';
$lang['perfexpilot_field_discount_total']     = 'Discount';
$lang['perfexpilot_field_total']              = 'Total';
$lang['perfexpilot_field_amount_paid']        = 'Amount paid';
$lang['perfexpilot_field_amount']             = 'Amount';
$lang['perfexpilot_field_payment_method']     = 'Payment mode';
$lang['perfexpilot_field_transaction_id']     = 'Transaction ID';
$lang['perfexpilot_field_expense_name']       = 'Expense name';
$lang['perfexpilot_field_category']           = 'Category';
$lang['perfexpilot_field_notes']              = 'Notes';
$lang['perfexpilot_field_terms']              = 'Terms';
$lang['perfexpilot_field_vat']                = 'VAT / Tax number';
$lang['perfexpilot_item_description']         = 'Description';
$lang['perfexpilot_item_qty']                 = 'Qty';
$lang['perfexpilot_item_rate']                = 'Rate';
$lang['perfexpilot_item_tax']                 = 'Tax %';

// Customer matching
$lang['perfexpilot_customer_searching']       = 'Searching customers…';
$lang['perfexpilot_customer_matched']         = 'Matched customer:';
$lang['perfexpilot_customer_candidates']      = 'Possible customers — pick one:';
$lang['perfexpilot_customer_none']            = 'No matching customer found. Select one manually after applying.';
$lang['perfexpilot_customer_skip']            = 'Do not set a customer';

// Tax resolver
$lang['perfexpilot_tax_resolver']             = 'Tax resolver';
$lang['perfexpilot_tax_detected']             = 'Detected tax rate:';
$lang['perfexpilot_tax_implied']              = 'implied from subtotal and tax total';
$lang['perfexpilot_tax_reuse']                = 'Reuse existing tax';
$lang['perfexpilot_tax_create']               = 'Create this tax';
$lang['perfexpilot_tax_none']                 = 'Do not apply a tax';
$lang['perfexpilot_tax_apply_to_items']       = 'Apply the tax to each line item';
$lang['perfexpilot_tax_created']              = 'Tax created and selected.';
$lang['perfexpilot_tax_matched']              = 'Matched an existing tax.';
$lang['perfexpilot_tax_invalid_rate']         = 'No usable tax rate was detected.';
$lang['perfexpilot_tax_create_disabled']      = 'No tax matches this rate and automatic tax creation is switched off.';
$lang['perfexpilot_tax_create_admin_only']    = 'No tax matches this rate; only an administrator can create a new one.';
$lang['perfexpilot_tax_create_failed']        = 'The tax could not be created.';

// Errors
$lang['perfexpilot_error_no_key']             = 'No OpenAI API key is configured. Add one in PerfexPilot settings.';
$lang['perfexpilot_error_no_file']            = 'No file was received.';
$lang['perfexpilot_error_upload']             = 'The file could not be uploaded.';
$lang['perfexpilot_error_too_large']          = 'That file is larger than the %s MB limit.';
$lang['perfexpilot_error_extension']          = 'Unsupported file type. Allowed: %s.';
$lang['perfexpilot_error_unsupported_type']   = 'This file type cannot be read.';
$lang['perfexpilot_error_store']              = 'The uploaded file could not be stored. Check that uploads/perfexpilot is writable.';
$lang['perfexpilot_error_unreadable']         = 'The stored document could not be read.';
$lang['perfexpilot_error_imagick_missing']    = 'HEIC and TIFF files need the PHP Imagick extension. Install it, or upload a JPG, PNG or PDF instead.';
$lang['perfexpilot_error_convert_failed']     = 'The image could not be converted to JPEG.';
$lang['perfexpilot_error_empty_response']     = 'The model returned an empty response. Try again, or use a clearer scan.';
$lang['perfexpilot_error_bad_json']           = 'The model returned data that could not be read. Try again.';
$lang['perfexpilot_error_network']            = 'The scan request failed. Check your connection and try again.';
