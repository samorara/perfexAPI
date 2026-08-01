<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Perfexpilot_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('perfexpilot/perfexpilot_extractor');
    }

    /* ------------------------------------------------------------------
     * Scan records
     * ---------------------------------------------------------------- */

    public function add_scan($data)
    {
        $this->db->insert(db_prefix() . 'perfexpilot_scans', [
            'staff_id'       => isset($data['staff_id']) ? $data['staff_id'] : get_staff_user_id(),
            'context'        => isset($data['context']) ? $data['context'] : 'invoice',
            'original_name'  => $data['original_name'],
            'stored_name'    => isset($data['stored_name']) ? $data['stored_name'] : null,
            'mime'           => $data['mime'],
            'filesize'       => isset($data['filesize']) ? (int) $data['filesize'] : 0,
            'model'          => isset($data['model']) ? $data['model'] : null,
            'status'         => isset($data['status']) ? $data['status'] : 'ok',
            'confidence'     => isset($data['confidence']) ? (int) $data['confidence'] : 0,
            'extracted_json' => isset($data['extracted_json']) ? $data['extracted_json'] : null,
            'error'          => isset($data['error']) ? $data['error'] : null,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    public function get_scan($id)
    {
        return $this->db->where('id', (int) $id)
            ->get(db_prefix() . 'perfexpilot_scans')->row_array();
    }

    public function get_history($limit = 100)
    {
        return $this->db->order_by('id', 'desc')->limit((int) $limit)
            ->get(db_prefix() . 'perfexpilot_scans')->result_array();
    }

    public function delete_scan($id)
    {
        $scan = $this->get_scan($id);

        if (!$scan) {
            return false;
        }

        $this->delete_stored_file($scan['stored_name']);
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'perfexpilot_scans');

        return true;
    }

    /**
     * Drop scans past the retention window. A retention of 0 keeps everything.
     */
    public function purge_expired()
    {
        $days = (int) get_option('perfexpilot_retention_days');

        if ($days <= 0) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

        $expired = $this->db->where('created_at <', $cutoff)
            ->get(db_prefix() . 'perfexpilot_scans')->result_array();

        foreach ($expired as $scan) {
            $this->delete_stored_file($scan['stored_name']);
        }

        $this->db->where('created_at <', $cutoff)->delete(db_prefix() . 'perfexpilot_scans');

        return count($expired);
    }

    public function delete_stored_file($stored_name)
    {
        if (empty($stored_name)) {
            return;
        }

        // Defend against traversal in a value that ends up in a filesystem path.
        $stored_name = basename($stored_name);
        $path        = perfexpilot_upload_path() . $stored_name;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /* ------------------------------------------------------------------
     * Lookups used by the browser to map extracted values onto Perfex records
     * ---------------------------------------------------------------- */

    public function lookup_data()
    {
        $taxes = $this->db->order_by('name', 'asc')
            ->get(db_prefix() . 'taxes')->result_array();

        $payment_modes = $this->db->where('active', 1)->order_by('name', 'asc')
            ->get(db_prefix() . 'payment_modes')->result_array();

        $categories = $this->db->order_by('name', 'asc')
            ->get(db_prefix() . 'expenses_categories')->result_array();

        $currencies = $this->db->get(db_prefix() . 'currencies')->result_array();

        return [
            'taxes' => array_map(function ($tax) {
                return [
                    'id'      => (int) $tax['id'],
                    'name'    => $tax['name'],
                    'taxrate' => (float) $tax['taxrate'],
                    // The exact value of the item tax <option> in the invoice form.
                    'value'   => $tax['name'] . '|' . $tax['taxrate'],
                ];
            }, $taxes),
            'payment_modes' => array_map(function ($mode) {
                return ['id' => (int) $mode['id'], 'name' => $mode['name']];
            }, $payment_modes),
            'expense_categories' => array_map(function ($category) {
                return ['id' => (int) $category['id'], 'name' => $category['name']];
            }, $categories),
            'currencies' => array_map(function ($currency) {
                return [
                    'id'        => (int) $currency['id'],
                    'name'      => $currency['name'],
                    'symbol'    => $currency['symbol'],
                    'isdefault' => (int) $currency['isdefault'],
                ];
            }, $currencies),
        ];
    }

    /**
     * Find a tax matching the given rate, or create one when the admin allows it.
     *
     * @param float  $rate percentage, e.g. 19 for 19%
     * @param string $name preferred name when a new tax has to be created
     *
     * @return array ['success' => bool, 'tax' => array|null, 'created' => bool, 'error' => string|null]
     */
    public function resolve_tax($rate, $name = '')
    {
        $rate = (float) $rate;

        if ($rate <= 0) {
            return ['success' => false, 'tax' => null, 'created' => false, 'error' => _l('perfexpilot_tax_invalid_rate')];
        }

        foreach ($this->db->get(db_prefix() . 'taxes')->result_array() as $tax) {
            if (abs((float) $tax['taxrate'] - $rate) < 0.01) {
                return [
                    'success' => true,
                    'created' => false,
                    'error'   => null,
                    'tax'     => [
                        'id'      => (int) $tax['id'],
                        'name'    => $tax['name'],
                        'taxrate' => (float) $tax['taxrate'],
                        'value'   => $tax['name'] . '|' . $tax['taxrate'],
                    ],
                ];
            }
        }

        if (get_option('perfexpilot_auto_create_tax') != '1') {
            return ['success' => false, 'tax' => null, 'created' => false, 'error' => _l('perfexpilot_tax_create_disabled')];
        }

        if (!is_admin()) {
            return ['success' => false, 'tax' => null, 'created' => false, 'error' => _l('perfexpilot_tax_create_admin_only')];
        }

        $name = trim($name);

        if ($name === '') {
            $name = 'TAX ' . rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%';
        }

        $this->db->insert(db_prefix() . 'taxes', [
            'name'    => $name,
            'taxrate' => $rate,
        ]);

        $id = $this->db->insert_id();

        if (!$id) {
            return ['success' => false, 'tax' => null, 'created' => false, 'error' => _l('perfexpilot_tax_create_failed')];
        }

        log_activity('PerfexPilot created tax [' . $name . ' ' . $rate . '%]');

        return [
            'success' => true,
            'created' => true,
            'error'   => null,
            'tax'     => [
                'id'      => (int) $id,
                'name'    => $name,
                'taxrate' => $rate,
                'value'   => $name . '|' . $rate,
            ],
        ];
    }
}
