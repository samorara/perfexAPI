<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Perfex_api extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('perfex_api/perfex_api_model');
    }

    public function index()
    {
        if (!has_permission('perfex_api', '', 'view') && !is_admin()) {
            access_denied('perfex_api');
        }

        $data['title'] = _l('perfex_api');
        $data['keys']  = $this->perfex_api_model->get_keys();

        // Plain token from a key created on the previous request, shown once.
        $data['new_token'] = $this->session->flashdata('perfex_api_new_token');

        $this->load->view('perfex_api/manage', $data);
    }

    public function create()
    {
        if (!has_permission('perfex_api', '', 'create') && !is_admin()) {
            access_denied('perfex_api');
        }

        if ($this->input->post()) {
            $name = trim($this->input->post('name'));

            if ($name === '') {
                set_alert('warning', _l('perfex_api_key_name_required'));
                redirect(admin_url('perfex_api'));
            }

            $token = $this->perfex_api_model->create_key([
                'name'       => $name,
                'can_create' => $this->input->post('can_create'),
                'can_update' => $this->input->post('can_update'),
                'can_delete' => $this->input->post('can_delete'),
                'expires_at' => $this->input->post('expires_at'),
            ]);

            if ($token) {
                $this->session->set_flashdata('perfex_api_new_token', $token);
                set_alert('success', _l('perfex_api_key_created'));
            } else {
                set_alert('danger', _l('perfex_api_key_create_failed'));
            }
        }

        redirect(admin_url('perfex_api'));
    }

    public function toggle($id)
    {
        if (!has_permission('perfex_api', '', 'create') && !is_admin()) {
            access_denied('perfex_api');
        }

        $this->perfex_api_model->toggle_key((int) $id);
        set_alert('success', _l('perfex_api_key_updated'));
        redirect(admin_url('perfex_api'));
    }

    public function delete($id)
    {
        if (!has_permission('perfex_api', '', 'delete') && !is_admin()) {
            access_denied('perfex_api');
        }

        if ($this->perfex_api_model->delete_key((int) $id)) {
            set_alert('success', _l('perfex_api_key_deleted'));
        }

        redirect(admin_url('perfex_api'));
    }
}
