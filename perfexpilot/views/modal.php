<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal fade perfexpilot-modal" id="perfexpilot_modal" tabindex="-1" role="dialog" aria-labelledby="perfexpilot_modal_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="perfexpilot_modal_title"><i class="fa fa-magic"></i> <?php echo _l('perfexpilot_modal_title'); ?></h4>
            </div>

            <div class="modal-body">

                <?php if (!$configured) { ?>
                <div class="alert alert-warning"><?php echo _l('perfexpilot_not_configured'); ?></div>
                <?php } ?>

                <!-- Shown at any step, so review-time problems are not hidden. -->
                <div class="alert alert-danger hide" id="perfexpilot_error"></div>

                <!-- Step 1: capture -->
                <div class="perfexpilot-step" data-step="capture">
                    <div class="perfexpilot-dropzone" id="perfexpilot_dropzone">
                        <i class="fa fa-cloud-upload perfexpilot-dropzone-icon"></i>
                        <p class="perfexpilot-dropzone-title"><?php echo _l('perfexpilot_drop_here'); ?></p>
                        <p class="text-muted"><?php echo _l('perfexpilot_drop_hint'); ?></p>
                        <div class="perfexpilot-capture-buttons">
                            <button type="button" class="btn btn-info" id="perfexpilot_choose"><i class="fa fa-folder-open"></i> <?php echo _l('perfexpilot_choose_file'); ?></button>
                            <button type="button" class="btn btn-default" id="perfexpilot_camera"><i class="fa fa-camera"></i> <?php echo _l('perfexpilot_take_photo'); ?></button>
                        </div>
                    </div>
                    <input type="file" id="perfexpilot_file" class="hide" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.tif,.tiff,application/pdf,image/*">
                    <input type="file" id="perfexpilot_capture" class="hide" accept="image/*" capture="environment">
                </div>

                <!-- Step 2: working -->
                <div class="perfexpilot-step hide" data-step="loading">
                    <div class="perfexpilot-loading">
                        <i class="fa fa-circle-o-notch fa-spin fa-2x"></i>
                        <p class="perfexpilot-loading-title"><?php echo _l('perfexpilot_scanning'); ?></p>
                        <p class="text-muted"><?php echo _l('perfexpilot_scanning_hint'); ?></p>
                        <p class="text-muted" id="perfexpilot_filename"></p>
                    </div>
                </div>

                <!-- Step 3: review -->
                <div class="perfexpilot-step hide" data-step="review">
                    <div class="perfexpilot-confidence">
                        <div class="perfexpilot-confidence-head">
                            <span><?php echo _l('perfexpilot_confidence_meter'); ?></span>
                            <strong id="perfexpilot_confidence_value">0%</strong>
                        </div>
                        <div class="perfexpilot-meter"><div class="perfexpilot-meter-bar" id="perfexpilot_confidence_bar"></div></div>
                    </div>
                    <div class="alert alert-warning hide" id="perfexpilot_low_confidence"><?php echo _l('perfexpilot_low_confidence_warning'); ?></div>

                    <div class="perfexpilot-section-head">
                        <h5><?php echo _l('perfexpilot_fields'); ?></h5>
                        <div class="checkbox checkbox-primary no-margin">
                            <input type="checkbox" id="perfexpilot_toggle_fields" checked>
                            <label for="perfexpilot_toggle_fields"><?php echo _l('perfexpilot_apply_all'); ?></label>
                        </div>
                    </div>
                    <div id="perfexpilot_fields"></div>

                    <div id="perfexpilot_customer_block" class="perfexpilot-customer hide"></div>

                    <div id="perfexpilot_tax_block" class="perfexpilot-tax hide">
                        <div class="perfexpilot-section-head">
                            <h5><?php echo _l('perfexpilot_tax_resolver'); ?></h5>
                        </div>
                        <div id="perfexpilot_tax_body"></div>
                    </div>

                    <div id="perfexpilot_items_block" class="hide">
                        <div class="perfexpilot-section-head">
                            <h5><?php echo _l('perfexpilot_line_items'); ?></h5>
                            <div class="checkbox checkbox-primary no-margin">
                                <input type="checkbox" id="perfexpilot_toggle_items" checked>
                                <label for="perfexpilot_toggle_items"><?php echo _l('perfexpilot_apply_all'); ?></label>
                            </div>
                        </div>
                        <div class="table-responsive perfexpilot-items">
                            <table class="table table-condensed">
                                <thead>
                                    <tr>
                                        <th class="perfexpilot-col-check"></th>
                                        <th><?php echo _l('perfexpilot_item_description'); ?></th>
                                        <th class="perfexpilot-col-num"><?php echo _l('perfexpilot_item_qty'); ?></th>
                                        <th class="perfexpilot-col-num"><?php echo _l('perfexpilot_item_rate'); ?></th>
                                        <th class="perfexpilot-col-num"><?php echo _l('perfexpilot_item_tax'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="perfexpilot_items"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default hide" id="perfexpilot_rescan"><?php echo _l('perfexpilot_scan_another'); ?></button>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('perfexpilot_cancel'); ?></button>
                <button type="button" class="btn btn-info hide" id="perfexpilot_apply"><?php echo _l('perfexpilot_apply'); ?></button>
            </div>
        </div>
    </div>
</div>
<script>
window.perfexpilotConfig = <?php echo json_encode([
    'context'    => $context,
    'dateformat' => $dateformat,
    'threshold'  => $threshold,
    'maxFileMb'  => $max_file_mb,
    'configured' => (bool) $configured,
    'urls'       => [
        'scan'       => admin_url('perfexpilot/scan'),
        'lookup'     => admin_url('perfexpilot/lookup'),
        'resolveTax' => admin_url('perfexpilot/resolve_tax'),
        'customers'  => admin_url('misc/get_relation_data'),
    ],
    'lang' => [
        'button'             => _l('perfexpilot_scan_and_fill'),
        'applied'            => _l('perfexpilot_applied'),
        'nothingSelected'    => _l('perfexpilot_nothing_selected'),
        'networkError'       => _l('perfexpilot_error_network'),
        'tooLarge'           => sprintf(_l('perfexpilot_error_too_large'), $max_file_mb),
        'noLineItems'        => _l('perfexpilot_no_line_items'),
        'customerSearching'  => _l('perfexpilot_customer_searching'),
        'customerMatched'    => _l('perfexpilot_customer_matched'),
        'customerCandidates' => _l('perfexpilot_customer_candidates'),
        'customerNone'       => _l('perfexpilot_customer_none'),
        'customerSkip'       => _l('perfexpilot_customer_skip'),
        'taxDetected'        => _l('perfexpilot_tax_detected'),
        'taxImplied'         => _l('perfexpilot_tax_implied'),
        'taxReuse'           => _l('perfexpilot_tax_reuse'),
        'taxCreate'          => _l('perfexpilot_tax_create'),
        'taxNone'            => _l('perfexpilot_tax_none'),
        'taxApplyToItems'    => _l('perfexpilot_tax_apply_to_items'),
        'taxCreated'         => _l('perfexpilot_tax_created'),
        'taxMatched'         => _l('perfexpilot_tax_matched'),
        'fields'             => [
            'customer'       => _l('perfexpilot_field_customer'),
            'vendor'         => _l('perfexpilot_field_vendor'),
            'number'         => _l('perfexpilot_field_number'),
            'reference_no'   => _l('perfexpilot_field_reference_no'),
            'issue_date'     => _l('perfexpilot_field_issue_date'),
            'due_date'       => _l('perfexpilot_field_due_date'),
            'payment_date'   => _l('perfexpilot_field_payment_date'),
            'currency'       => _l('perfexpilot_field_currency'),
            'subtotal'       => _l('perfexpilot_field_subtotal'),
            'tax_total'      => _l('perfexpilot_field_tax_total'),
            'discount_total' => _l('perfexpilot_field_discount_total'),
            'total'          => _l('perfexpilot_field_total'),
            'amount_paid'    => _l('perfexpilot_field_amount_paid'),
            'amount'         => _l('perfexpilot_field_amount'),
            'payment_method' => _l('perfexpilot_field_payment_method'),
            'transaction_id' => _l('perfexpilot_field_transaction_id'),
            'expense_name'   => _l('perfexpilot_field_expense_name'),
            'category'       => _l('perfexpilot_field_category'),
            'notes'          => _l('perfexpilot_field_notes'),
            'terms'          => _l('perfexpilot_field_terms'),
            'vat'            => _l('perfexpilot_field_vat'),
        ],
    ],
]); ?>;
</script>
