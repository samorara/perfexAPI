<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php if (!empty($new_token)) { ?>
                    <div class="alert alert-success">
                        <h4><?php echo _l('perfex_api_new_token_heading'); ?></h4>
                        <p><?php echo _l('perfex_api_new_token_info'); ?></p>
                        <pre style="user-select:all;word-break:break-all;"><?php echo html_escape($new_token); ?></pre>
                    </div>
                <?php } ?>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('perfex_api_keys'); ?></h4>
                        <hr class="hr-panel-heading" />

                        <?php if (has_permission('perfex_api', '', 'create') || is_admin()) { ?>
                        <?php echo form_open(admin_url('perfex_api/create'), ['class' => 'mbot25']); ?>
                            <div class="row">
                                <div class="col-md-3">
                                    <?php echo render_input('name', 'perfex_api_key_name'); ?>
                                </div>
                                <div class="col-md-3">
                                    <?php echo render_date_input('expires_at', 'perfex_api_expires_at'); ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="control-label"><?php echo _l('perfex_api_permissions_label'); ?></label>
                                    <div>
                                        <label class="checkbox-inline"><input type="checkbox" name="can_create" value="1" checked> <?php echo _l('perfex_api_can_create'); ?></label>
                                        <label class="checkbox-inline"><input type="checkbox" name="can_update" value="1" checked> <?php echo _l('perfex_api_can_update'); ?></label>
                                        <label class="checkbox-inline"><input type="checkbox" name="can_delete" value="1"> <?php echo _l('perfex_api_can_delete'); ?></label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="control-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-info btn-block"><?php echo _l('perfex_api_generate_key'); ?></button>
                                </div>
                            </div>
                        <?php echo form_close(); ?>
                        <?php } ?>

                        <div class="table-responsive">
                            <table class="table dt-table">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('perfex_api_key_name'); ?></th>
                                        <th><?php echo _l('perfex_api_token_prefix'); ?></th>
                                        <th><?php echo _l('perfex_api_permissions_label'); ?></th>
                                        <th><?php echo _l('perfex_api_status'); ?></th>
                                        <th><?php echo _l('perfex_api_expires_at'); ?></th>
                                        <th><?php echo _l('perfex_api_last_used'); ?></th>
                                        <th><?php echo _l('options'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($keys as $key) { ?>
                                    <tr>
                                        <td><?php echo html_escape($key['name']); ?></td>
                                        <td><code><?php echo html_escape($key['token_prefix']); ?>&hellip;</code></td>
                                        <td>
                                            <?php
                                            $perms = ['read'];
                                            if ($key['can_create']) { $perms[] = 'create'; }
                                            if ($key['can_update']) { $perms[] = 'update'; }
                                            if ($key['can_delete']) { $perms[] = 'delete'; }
                                            echo html_escape(implode(', ', $perms));
                                            ?>
                                        </td>
                                        <td>
                                            <span class="label label-<?php echo $key['active'] ? 'success' : 'default'; ?>">
                                                <?php echo $key['active'] ? _l('perfex_api_active') : _l('perfex_api_inactive'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $key['expires_at'] ? _dt($key['expires_at']) : '-'; ?></td>
                                        <td><?php echo $key['last_used_at'] ? _dt($key['last_used_at']) : _l('perfex_api_never'); ?></td>
                                        <td>
                                            <?php if (has_permission('perfex_api', '', 'create') || is_admin()) { ?>
                                                <a href="<?php echo admin_url('perfex_api/toggle/' . $key['id']); ?>" class="btn btn-default btn-icon">
                                                    <i class="fa fa-power-off"></i>
                                                </a>
                                            <?php } ?>
                                            <?php if (has_permission('perfex_api', '', 'delete') || is_admin()) { ?>
                                                <a href="<?php echo admin_url('perfex_api/delete/' . $key['id']); ?>" class="btn btn-danger btn-icon _delete">
                                                    <i class="fa fa-remove"></i>
                                                </a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <hr />
                        <h4><?php echo _l('perfex_api_usage'); ?></h4>
                        <p><?php echo _l('perfex_api_base_url'); ?>: <code><?php echo site_url('perfex_api/api/v1'); ?></code></p>
                        <pre>curl -H "Authorization: Bearer &lt;token&gt;" <?php echo site_url('perfex_api/api/v1/customers'); ?></pre>
                        <p><?php echo _l('perfex_api_docs_hint'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
