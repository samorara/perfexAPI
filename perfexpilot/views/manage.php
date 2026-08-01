<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings'; ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('perfexpilot'); ?></h4>
                        <hr class="hr-panel-heading" />

                        <ul class="nav nav-tabs" role="tablist">
                            <?php if (is_admin()) { ?>
                            <li class="<?php echo $active_tab === 'settings' ? 'active' : ''; ?>"><a href="#tab_settings" data-toggle="tab"><?php echo _l('perfexpilot_tab_settings'); ?></a></li>
                            <?php } ?>
                            <li class="<?php echo $active_tab === 'history' || !is_admin() ? 'active' : ''; ?>"><a href="#tab_history" data-toggle="tab"><?php echo _l('perfexpilot_tab_history'); ?></a></li>
                        </ul>

                        <div class="tab-content mtop15">

                            <?php if (is_admin()) { ?>
                            <div class="tab-pane <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" id="tab_settings">
                                <?php echo form_open(admin_url('perfexpilot/save_settings')); ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <?php echo render_input('perfexpilot_openai_key', 'perfexpilot_openai_key', '', 'password', ['placeholder' => 'sk-…', 'autocomplete' => 'new-password']); ?>
                                        <p class="text-muted"><?php echo _l('perfexpilot_openai_key_help'); ?></p>
                                        <p>
                                            <?php if (get_option('perfexpilot_openai_key') !== '') { ?>
                                            <span class="label label-success"><?php echo _l('perfexpilot_key_saved'); ?></span>
                                            <button type="button" class="btn btn-default btn-xs mleft5" id="perfexpilot-test-connection"><?php echo _l('perfexpilot_test_connection'); ?></button>
                                            <span id="perfexpilot-test-result" class="mleft5"></span>
                                            <?php } else { ?>
                                            <span class="label label-warning"><?php echo _l('perfexpilot_key_not_saved'); ?></span>
                                            <?php } ?>
                                        </p>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_input('perfexpilot_model', 'perfexpilot_model', get_option('perfexpilot_model')); ?>
                                        <p class="text-muted"><?php echo _l('perfexpilot_model_help'); ?></p>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_input('perfexpilot_languages', 'perfexpilot_languages', get_option('perfexpilot_languages'), 'text', ['placeholder' => 'en,de,it,fr']); ?>
                                        <p class="text-muted"><?php echo _l('perfexpilot_languages_help'); ?></p>
                                    </div>
                                </div>

                                <hr />

                                <div class="row">
                                    <div class="col-md-3">
                                        <?php echo render_input('perfexpilot_max_file_mb', 'perfexpilot_max_file_mb', get_option('perfexpilot_max_file_mb'), 'number'); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_input('perfexpilot_confidence_threshold', 'perfexpilot_confidence_threshold', get_option('perfexpilot_confidence_threshold'), 'number'); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_input('perfexpilot_timeout', 'perfexpilot_timeout', get_option('perfexpilot_timeout'), 'number'); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_input('perfexpilot_retention_days', 'perfexpilot_retention_days', get_option('perfexpilot_retention_days'), 'number'); ?>
                                        <p class="text-muted"><?php echo _l('perfexpilot_retention_days_help'); ?></p>
                                    </div>
                                </div>

                                <hr />

                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="control-label"><?php echo _l('perfexpilot_enabled_forms'); ?></label>
                                        <?php foreach ([
                                            'perfexpilot_enable_invoices' => 'perfexpilot_enable_invoices',
                                            'perfexpilot_enable_expenses' => 'perfexpilot_enable_expenses',
                                            'perfexpilot_enable_payments' => 'perfexpilot_enable_payments',
                                        ] as $option => $label) { ?>
                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox" name="<?php echo $option; ?>" id="<?php echo $option; ?>" value="1" <?php echo get_option($option) == '1' ? 'checked' : ''; ?>>
                                            <label for="<?php echo $option; ?>"><?php echo _l($label); ?></label>
                                        </div>
                                        <?php } ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="control-label"><?php echo _l('perfexpilot_tax_resolver'); ?></label>
                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox" name="perfexpilot_auto_create_tax" id="perfexpilot_auto_create_tax" value="1" <?php echo get_option('perfexpilot_auto_create_tax') == '1' ? 'checked' : ''; ?>>
                                            <label for="perfexpilot_auto_create_tax"><?php echo _l('perfexpilot_auto_create_tax'); ?></label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-info mtop15"><?php echo _l('submit'); ?></button>
                                <?php echo form_close(); ?>
                            </div>
                            <?php } ?>

                            <div class="tab-pane <?php echo $active_tab === 'history' || !is_admin() ? 'active' : ''; ?>" id="tab_history">
                                <?php if (empty($history)) { ?>
                                <p class="text-muted"><?php echo _l('perfexpilot_no_history'); ?></p>
                                <?php } else { ?>
                                <div class="table-responsive">
                                    <table class="table dt-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo _l('perfexpilot_date'); ?></th>
                                                <th><?php echo _l('perfexpilot_staff'); ?></th>
                                                <th><?php echo _l('perfexpilot_context'); ?></th>
                                                <th><?php echo _l('perfexpilot_document'); ?></th>
                                                <th><?php echo _l('perfexpilot_model'); ?></th>
                                                <th><?php echo _l('perfexpilot_status'); ?></th>
                                                <th><?php echo _l('perfexpilot_confidence'); ?></th>
                                                <th><?php echo _l('options'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($history as $scan) { ?>
                                            <tr>
                                                <td><?php echo _dt($scan['created_at']); ?></td>
                                                <td><?php echo html_escape(get_staff_full_name($scan['staff_id'])); ?></td>
                                                <td><?php echo html_escape($scan['context']); ?></td>
                                                <td>
                                                    <?php if (!empty($scan['stored_name']) && is_file(perfexpilot_upload_path() . basename($scan['stored_name']))) { ?>
                                                    <a href="<?php echo admin_url('perfexpilot/file/' . $scan['id']); ?>" target="_blank"><?php echo html_escape($scan['original_name']); ?></a>
                                                    <?php } else { ?>
                                                    <span class="text-muted"><?php echo html_escape($scan['original_name']); ?> — <?php echo _l('perfexpilot_file_removed'); ?></span>
                                                    <?php } ?>
                                                </td>
                                                <td><?php echo html_escape((string) $scan['model']); ?></td>
                                                <td>
                                                    <?php
                                                    $labels = ['ok' => 'success', 'low_confidence' => 'warning', 'failed' => 'danger'];
                                                    $label  = isset($labels[$scan['status']]) ? $labels[$scan['status']] : 'default';
                                                    ?>
                                                    <span class="label label-<?php echo $label; ?>" data-toggle="tooltip" title="<?php echo html_escape((string) $scan['error']); ?>">
                                                        <?php echo _l('perfexpilot_status_' . $scan['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo (int) $scan['confidence']; ?>%</td>
                                                <td>
                                                    <?php if (has_permission('perfexpilot', '', 'delete') || is_admin()) { ?>
                                                    <a href="<?php echo admin_url('perfexpilot/delete_scan/' . $scan['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php } ?>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
$(function () {
    $('#perfexpilot-test-connection').on('click', function () {
        var button = $(this),
            result = $('#perfexpilot-test-result'),
            data   = {};

        data[app.csrfData.token_name] = app.csrfData.hash;

        button.prop('disabled', true);
        result.removeClass('text-success text-danger').text('<?php echo _l('perfexpilot_testing'); ?>');

        $.post('<?php echo admin_url('perfexpilot/test_connection'); ?>', data, function (response) {
            result.addClass(response.success ? 'text-success' : 'text-danger').text(response.message);
        }, 'json').fail(function () {
            result.addClass('text-danger').text('<?php echo _l('perfexpilot_error_network'); ?>');
        }).always(function () {
            button.prop('disabled', false);
        });
    });
});
</script>
</body>
</html>
