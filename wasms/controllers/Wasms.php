<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wasms extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('wasms/wasms_model');
    }

    public function index()
    {
        if (!has_permission('wasms', '', 'view') && !is_admin()) {
            access_denied('wasms');
        }

        $data['title']        = _l('wasms');
        $data['auto_replies'] = $this->wasms_model->get_auto_replies();
        $data['log']          = $this->wasms_model->get_log(100);

        $this->load->view('wasms/manage', $data);
    }

    public function send()
    {
        if (!has_permission('wasms', '', 'create') && !is_admin()) {
            access_denied('wasms');
        }

        if ($this->input->post()) {
            $channel = $this->input->post('channel') === 'sms' ? 'sms' : 'whatsapp';
            $phone   = trim($this->input->post('phone'));
            $message = trim($this->input->post('message'));

            if ($phone === '' || $message === '') {
                set_alert('warning', _l('wasms_phone_message_required'));
            } else {
                $result = $this->wasms_model->send($channel, $phone, $message, 'out', get_staff_user_id());
                if ($result['success']) {
                    set_alert('success', _l('wasms_message_sent'));
                } else {
                    set_alert('danger', _l('wasms_message_failed') . ' ' . html_escape($result['response']));
                }
            }
        }

        redirect(admin_url('wasms'));
    }

    public function save_settings()
    {
        if (!is_admin()) {
            access_denied('wasms');
        }

        if ($this->input->post()) {
            $options = [
                'wasms_whatsapp_mode'        => $this->input->post('wasms_whatsapp_mode') === 'cloud' ? 'cloud' : 'personal',
                'wasms_personal_url'         => trim($this->input->post('wasms_personal_url', false)),
                'wasms_personal_method'      => $this->input->post('wasms_personal_method') === 'POST' ? 'POST' : 'GET',
                'wasms_personal_body_format' => $this->input->post('wasms_personal_body_format') === 'json' ? 'json' : 'form',
                'wasms_cloud_token'          => trim($this->input->post('wasms_cloud_token', false)),
                'wasms_cloud_phone_number_id' => trim($this->input->post('wasms_cloud_phone_number_id')),
                'wasms_cloud_api_version'    => trim($this->input->post('wasms_cloud_api_version')) ?: 'v20.0',
                'wasms_sms_url'              => trim($this->input->post('wasms_sms_url', false)),
                'wasms_sms_method'           => $this->input->post('wasms_sms_method') === 'POST' ? 'POST' : 'GET',
                'wasms_sms_body_format'      => $this->input->post('wasms_sms_body_format') === 'json' ? 'json' : 'form',
                'wasms_auto_reply_enabled'   => $this->input->post('wasms_auto_reply_enabled') ? '1' : '0',
                'wasms_default_reply'        => trim($this->input->post('wasms_default_reply', false)),
            ];

            foreach ($options as $name => $value) {
                update_option($name, $value);
            }

            set_alert('success', _l('settings_updated'));
        }

        redirect(admin_url('wasms') . '?tab=settings');
    }

    public function add_auto_reply()
    {
        if (!has_permission('wasms', '', 'create') && !is_admin()) {
            access_denied('wasms');
        }

        if ($this->input->post()) {
            $keyword = trim($this->input->post('keyword'));
            $reply   = trim($this->input->post('reply', false));

            if ($reply === '' || ($keyword === '' && $this->input->post('match_type') !== 'any')) {
                set_alert('warning', _l('wasms_auto_reply_fields_required'));
            } else {
                $this->wasms_model->add_auto_reply([
                    'keyword'    => $keyword,
                    'match_type' => $this->input->post('match_type'),
                    'channel'    => $this->input->post('channel'),
                    'reply'      => $reply,
                ]);
                set_alert('success', _l('wasms_auto_reply_added'));
            }
        }

        redirect(admin_url('wasms') . '?tab=replies');
    }

    public function toggle_auto_reply($id)
    {
        if (!has_permission('wasms', '', 'create') && !is_admin()) {
            access_denied('wasms');
        }

        $this->wasms_model->toggle_auto_reply((int) $id);
        redirect(admin_url('wasms') . '?tab=replies');
    }

    public function delete_auto_reply($id)
    {
        if (!has_permission('wasms', '', 'delete') && !is_admin()) {
            access_denied('wasms');
        }

        $this->wasms_model->delete_auto_reply((int) $id);
        set_alert('success', _l('wasms_auto_reply_deleted'));
        redirect(admin_url('wasms') . '?tab=replies');
    }
}
