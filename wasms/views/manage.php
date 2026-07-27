<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'send'; ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('wasms'); ?></h4>
                        <hr class="hr-panel-heading" />

                        <ul class="nav nav-tabs" role="tablist">
                            <li class="<?php echo $active_tab === 'send' ? 'active' : ''; ?>"><a href="#tab_send" data-toggle="tab"><?php echo _l('wasms_tab_send'); ?></a></li>
                            <li class="<?php echo $active_tab === 'replies' ? 'active' : ''; ?>"><a href="#tab_replies" data-toggle="tab"><?php echo _l('wasms_tab_replies'); ?></a></li>
                            <li class="<?php echo $active_tab === 'log' ? 'active' : ''; ?>"><a href="#tab_log" data-toggle="tab"><?php echo _l('wasms_tab_log'); ?></a></li>
                            <?php if (is_admin()) { ?>
                            <li class="<?php echo $active_tab === 'settings' ? 'active' : ''; ?>"><a href="#tab_settings" data-toggle="tab"><?php echo _l('wasms_tab_settings'); ?></a></li>
                            <?php } ?>
                        </ul>

                        <div class="tab-content mtop15">

                            <!-- Send -->
                            <div class="tab-pane <?php echo $active_tab === 'send' ? 'active' : ''; ?>" id="tab_send">
                                <?php echo form_open(admin_url('wasms/send')); ?>
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="control-label"><?php echo _l('wasms_channel'); ?></label>
                                        <select name="channel" class="selectpicker" data-width="100%">
                                            <option value="whatsapp">WhatsApp</option>
                                            <option value="sms">SMS</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_input('phone', 'wasms_phone', '', 'text', ['placeholder' => '+15551234567']); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php echo render_textarea('message', 'wasms_message'); ?>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-info"><?php echo _l('wasms_send'); ?></button>
                                <?php echo form_close(); ?>
                            </div>

                            <!-- Auto replies -->
                            <div class="tab-pane <?php echo $active_tab === 'replies' ? 'active' : ''; ?>" id="tab_replies">
                                <p class="text-muted"><?php echo _l('wasms_auto_reply_help'); ?></p>
                                <?php echo form_open(admin_url('wasms/add_auto_reply'), ['class' => 'mbot25']); ?>
                                <div class="row">
                                    <div class="col-md-2">
                                        <?php echo render_input('keyword', 'wasms_keyword'); ?>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="control-label"><?php echo _l('wasms_match_type'); ?></label>
                                        <select name="match_type" class="form-control">
                                            <option value="contains"><?php echo _l('wasms_match_contains'); ?></option>
                                            <option value="exact"><?php echo _l('wasms_match_exact'); ?></option>
                                            <option value="starts_with"><?php echo _l('wasms_match_starts_with'); ?></option>
                                            <option value="any"><?php echo _l('wasms_match_any'); ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="control-label"><?php echo _l('wasms_channel'); ?></label>
                                        <select name="channel" class="form-control">
                                            <option value="both"><?php echo _l('wasms_channel_both'); ?></option>
                                            <option value="whatsapp">WhatsApp</option>
                                            <option value="sms">SMS</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_textarea('reply', 'wasms_reply', '', ['rows' => 2]); ?>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="control-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-info btn-block"><?php echo _l('wasms_add_rule'); ?></button>
                                    </div>
                                </div>
                                <?php echo form_close(); ?>

                                <div class="table-responsive">
                                    <table class="table dt-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo _l('wasms_keyword'); ?></th>
                                                <th><?php echo _l('wasms_match_type'); ?></th>
                                                <th><?php echo _l('wasms_channel'); ?></th>
                                                <th><?php echo _l('wasms_reply'); ?></th>
                                                <th><?php echo _l('wasms_status'); ?></th>
                                                <th><?php echo _l('options'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($auto_replies as $rule) { ?>
                                            <tr>
                                                <td><?php echo html_escape($rule['keyword']); ?></td>
                                                <td><?php echo html_escape($rule['match_type']); ?></td>
                                                <td><?php echo html_escape($rule['channel']); ?></td>
                                                <td><?php echo html_escape($rule['reply']); ?></td>
                                                <td>
                                                    <span class="label label-<?php echo $rule['active'] ? 'success' : 'default'; ?>">
                                                        <?php echo $rule['active'] ? _l('wasms_active') : _l('wasms_inactive'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="<?php echo admin_url('wasms/toggle_auto_reply/' . $rule['id']); ?>" class="btn btn-default btn-icon"><i class="fa fa-power-off"></i></a>
                                                    <a href="<?php echo admin_url('wasms/delete_auto_reply/' . $rule['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Log -->
                            <div class="tab-pane <?php echo $active_tab === 'log' ? 'active' : ''; ?>" id="tab_log">
                                <div class="table-responsive">
                                    <table class="table dt-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo _l('wasms_date'); ?></th>
                                                <th><?php echo _l('wasms_channel'); ?></th>
                                                <th><?php echo _l('wasms_direction'); ?></th>
                                                <th><?php echo _l('wasms_phone'); ?></th>
                                                <th><?php echo _l('wasms_message'); ?></th>
                                                <th><?php echo _l('wasms_status'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($log as $row) { ?>
                                            <tr>
                                                <td><?php echo _dt($row['created_at']); ?></td>
                                                <td><?php echo html_escape($row['channel']); ?></td>
                                                <td>
                                                    <span class="label label-<?php echo $row['direction'] === 'in' ? 'info' : 'default'; ?>">
                                                        <?php echo $row['direction'] === 'in' ? _l('wasms_incoming') : _l('wasms_outgoing'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo html_escape($row['phone']); ?></td>
                                                <td><?php echo html_escape($row['message']); ?></td>
                                                <td>
                                                    <span class="label label-<?php echo $row['status'] === 'failed' ? 'danger' : 'success'; ?>" data-toggle="tooltip" title="<?php echo html_escape((string) $row['gateway_response']); ?>">
                                                        <?php echo html_escape($row['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Settings -->
                            <?php if (is_admin()) { ?>
                            <div class="tab-pane <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" id="tab_settings">
                                <?php echo form_open(admin_url('wasms/save_settings')); ?>

                                <h4><?php echo _l('wasms_whatsapp_settings'); ?></h4>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="control-label"><?php echo _l('wasms_whatsapp_mode'); ?></label>
                                        <select name="wasms_whatsapp_mode" class="form-control">
                                            <option value="personal" <?php echo get_option('wasms_whatsapp_mode') === 'personal' ? 'selected' : ''; ?>><?php echo _l('wasms_mode_personal'); ?></option>
                                            <option value="cloud" <?php echo get_option('wasms_whatsapp_mode') === 'cloud' ? 'selected' : ''; ?>><?php echo _l('wasms_mode_cloud'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mtop15">
                                    <div class="col-md-6">
                                        <?php echo render_input('wasms_personal_url', 'wasms_personal_url', get_option('wasms_personal_url'), 'text', ['placeholder' => 'http://192.168.1.50:8080/send?to={phone}&text={message}']); ?>
                                        <p class="text-muted"><?php echo _l('wasms_template_help'); ?></p>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="control-label"><?php echo _l('wasms_http_method'); ?></label>
                                        <select name="wasms_personal_method" class="form-control">
                                            <option value="GET" <?php echo get_option('wasms_personal_method') === 'GET' ? 'selected' : ''; ?>>GET</option>
                                            <option value="POST" <?php echo get_option('wasms_personal_method') === 'POST' ? 'selected' : ''; ?>>POST</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="control-label"><?php echo _l('wasms_body_format'); ?></label>
                                        <select name="wasms_personal_body_format" class="form-control">
                                            <option value="form" <?php echo get_option('wasms_personal_body_format') === 'form' ? 'selected' : ''; ?>>Form</option>
                                            <option value="json" <?php echo get_option('wasms_personal_body_format') === 'json' ? 'selected' : ''; ?>>JSON</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mtop15">
                                    <div class="col-md-4">
                                        <?php echo render_input('wasms_cloud_token', 'wasms_cloud_token', get_option('wasms_cloud_token'), 'password'); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_input('wasms_cloud_phone_number_id', 'wasms_cloud_phone_number_id', get_option('wasms_cloud_phone_number_id')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_input('wasms_cloud_api_version', 'wasms_cloud_api_version', get_option('wasms_cloud_api_version')); ?>
                                    </div>
                                </div>

                                <hr />
                                <h4><?php echo _l('wasms_sms_settings'); ?></h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <?php echo render_input('wasms_sms_url', 'wasms_sms_url', get_option('wasms_sms_url'), 'text', ['placeholder' => 'https://sms-gateway.example.com/api?apikey=KEY&to={phone}&msg={message}']); ?>
                                        <p class="text-muted"><?php echo _l('wasms_template_help'); ?></p>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="control-label"><?php echo _l('wasms_http_method'); ?></label>
                                        <select name="wasms_sms_method" class="form-control">
                                            <option value="GET" <?php echo get_option('wasms_sms_method') === 'GET' ? 'selected' : ''; ?>>GET</option>
                                            <option value="POST" <?php echo get_option('wasms_sms_method') === 'POST' ? 'selected' : ''; ?>>POST</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="control-label"><?php echo _l('wasms_body_format'); ?></label>
                                        <select name="wasms_sms_body_format" class="form-control">
                                            <option value="form" <?php echo get_option('wasms_sms_body_format') === 'form' ? 'selected' : ''; ?>>Form</option>
                                            <option value="json" <?php echo get_option('wasms_sms_body_format') === 'json' ? 'selected' : ''; ?>>JSON</option>
                                        </select>
                                    </div>
                                </div>

                                <hr />
                                <h4><?php echo _l('wasms_auto_reply_settings'); ?></h4>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox" name="wasms_auto_reply_enabled" id="wasms_auto_reply_enabled" value="1" <?php echo get_option('wasms_auto_reply_enabled') == '1' ? 'checked' : ''; ?>>
                                            <label for="wasms_auto_reply_enabled"><?php echo _l('wasms_auto_reply_enabled'); ?></label>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <?php echo render_textarea('wasms_default_reply', 'wasms_default_reply', get_option('wasms_default_reply'), ['rows' => 2]); ?>
                                        <p class="text-muted"><?php echo _l('wasms_default_reply_help'); ?></p>
                                    </div>
                                </div>

                                <hr />
                                <h4><?php echo _l('wasms_webhooks'); ?></h4>
                                <p><?php echo _l('wasms_webhook_whatsapp_label'); ?>: <code><?php echo site_url('wasms/webhook/whatsapp'); ?></code><br/>
                                <?php echo _l('wasms_webhook_verify_token'); ?>: <code><?php echo html_escape(get_option('wasms_cloud_verify_token')); ?></code></p>
                                <p><?php echo _l('wasms_webhook_generic_label'); ?>:</p>
                                <pre><?php echo site_url('wasms/webhook/incoming/sms'); ?>?secret=<?php echo html_escape(get_option('wasms_webhook_secret')); ?>&amp;phone={phone}&amp;message={message}
<?php echo site_url('wasms/webhook/incoming/whatsapp'); ?>?secret=<?php echo html_escape(get_option('wasms_webhook_secret')); ?>&amp;phone={phone}&amp;message={message}</pre>
                                <p class="text-muted"><?php echo _l('wasms_webhook_help'); ?></p>

                                <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
                                <?php echo form_close(); ?>
                            </div>
                            <?php } ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
