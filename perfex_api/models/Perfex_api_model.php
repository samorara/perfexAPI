<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Perfex_api_model extends App_Model
{
    private $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'perfex_api_keys';
    }

    public function get_keys()
    {
        return $this->db->order_by('id', 'desc')->get($this->table)->result_array();
    }

    public function get_key($id)
    {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    /**
     * Create a new API key. Returns the plain token (shown once) or false.
     */
    public function create_key($data)
    {
        $token = 'pfx_' . bin2hex(random_bytes(24));

        $this->db->insert($this->table, [
            'name'         => $data['name'],
            'token_hash'   => hash('sha256', $token),
            'token_prefix' => substr($token, 0, 12),
            'can_create'   => !empty($data['can_create']) ? 1 : 0,
            'can_update'   => !empty($data['can_update']) ? 1 : 0,
            'can_delete'   => !empty($data['can_delete']) ? 1 : 0,
            'active'       => 1,
            'expires_at'   => !empty($data['expires_at']) ? to_sql_date($data['expires_at'], true) : null,
            'created_by'   => get_staff_user_id(),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id() ? $token : false;
    }

    public function toggle_key($id)
    {
        $key = $this->get_key($id);
        if (!$key) {
            return false;
        }
        $this->db->where('id', $id)->update($this->table, ['active' => $key['active'] ? 0 : 1]);

        return true;
    }

    public function delete_key($id)
    {
        $this->db->where('id', $id)->delete($this->table);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Validate a plain token. Returns the key row or false.
     */
    public function validate_token($token)
    {
        if (empty($token)) {
            return false;
        }

        $key = $this->db->where('token_hash', hash('sha256', $token))
            ->where('active', 1)
            ->get($this->table)
            ->row_array();

        if (!$key) {
            return false;
        }

        if (!empty($key['expires_at']) && strtotime($key['expires_at']) < time()) {
            return false;
        }

        $this->db->where('id', $key['id'])->update($this->table, ['last_used_at' => date('Y-m-d H:i:s')]);

        return $key;
    }
}
