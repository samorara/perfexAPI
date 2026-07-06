<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Public REST endpoint controller.
 *
 * Base URL: <crm-url>/perfex_api/api/v1/<resource>[/<id>]
 *
 * Authentication (one of):
 *   Authorization: Bearer <token>
 *   X-API-KEY: <token>
 */
class Api extends App_Controller
{
    /** @var array|null Authenticated API key row */
    private $api_key = null;

    /**
     * Resource map: URL segment => table / primary key / searchable columns.
     * Write operations are dispatched per-resource in handle_create/update/delete
     * so the correct Perfex model methods (with their side effects) run.
     */
    private $resources = [
        'customers'    => ['table' => 'clients', 'pk' => 'userid', 'search' => ['company', 'vat', 'phonenumber', 'city', 'website']],
        'contacts'     => ['table' => 'contacts', 'pk' => 'id', 'search' => ['firstname', 'lastname', 'email', 'phonenumber']],
        'leads'        => ['table' => 'leads', 'pk' => 'id', 'search' => ['name', 'email', 'company', 'phonenumber', 'city']],
        'invoices'     => ['table' => 'invoices', 'pk' => 'id', 'search' => ['number', 'clientnote']],
        'estimates'    => ['table' => 'estimates', 'pk' => 'id', 'search' => ['number']],
        'proposals'    => ['table' => 'proposals', 'pk' => 'id', 'search' => ['subject', 'proposal_to', 'email']],
        'payments'     => ['table' => 'invoicepaymentrecords', 'pk' => 'id', 'search' => ['transactionid', 'note']],
        'credit_notes' => ['table' => 'creditnotes', 'pk' => 'id', 'search' => ['number']],
        'projects'     => ['table' => 'projects', 'pk' => 'id', 'search' => ['name']],
        'tasks'        => ['table' => 'tasks', 'pk' => 'id', 'search' => ['name']],
        'tickets'      => ['table' => 'tickets', 'pk' => 'ticketid', 'search' => ['subject']],
        'staff'        => ['table' => 'staff', 'pk' => 'staffid', 'search' => ['firstname', 'lastname', 'email']],
        'expenses'     => ['table' => 'expenses', 'pk' => 'id', 'search' => ['expense_name', 'note']],
        'contracts'    => ['table' => 'contracts', 'pk' => 'id', 'search' => ['subject']],
        'items'        => ['table' => 'items', 'pk' => 'id', 'search' => ['description', 'long_description']],
        // Read-only lookups
        'currencies'    => ['table' => 'currencies', 'pk' => 'id', 'search' => ['name', 'symbol'], 'readonly' => true],
        'taxes'         => ['table' => 'taxes', 'pk' => 'id', 'search' => ['name'], 'readonly' => true],
        'payment_modes' => ['table' => 'payment_modes', 'pk' => 'id', 'search' => ['name'], 'readonly' => true],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('perfex_api/perfex_api_model');
    }

    /**
     * Route /perfex_api/api/v1/<resource>/<id> to the resource handlers.
     */
    public function _remap($method, $params = [])
    {
        // Allow both /perfex_api/api/v1/customers and /perfex_api/api/customers
        if ($method === 'v1') {
            $method = array_shift($params);
        }

        header('Content-Type: application/json');

        if ($method === null || $method === 'index') {
            $this->respond(['status' => true, 'module' => 'perfex_api', 'version' => PERFEX_API_VERSION, 'resources' => array_keys($this->resources)]);
        }

        $this->authenticate();

        if (!isset($this->resources[$method])) {
            $this->respond_error(404, 'Unknown resource "' . $method . '". Available: ' . implode(', ', array_keys($this->resources)));
        }

        $resource = $method;
        $id       = isset($params[0]) ? (int) $params[0] : null;
        $verb     = $this->request_method();

        switch ($verb) {
            case 'GET':
                $id === null ? $this->handle_list($resource) : $this->handle_get($resource, $id);
                break;
            case 'POST':
                $this->require_write('can_create');
                $this->handle_create($resource, $this->request_body());
                break;
            case 'PUT':
            case 'PATCH':
                $this->require_write('can_update');
                if ($id === null) {
                    $this->respond_error(400, 'Resource id is required for updates.');
                }
                $this->handle_update($resource, $id, $this->request_body());
                break;
            case 'DELETE':
                $this->require_write('can_delete');
                if ($id === null) {
                    $this->respond_error(400, 'Resource id is required for deletes.');
                }
                $this->handle_delete($resource, $id);
                break;
            default:
                $this->respond_error(405, 'Method ' . $verb . ' not allowed.');
        }
    }

    /* ---------------------------------------------------------------------
     * Read handlers
     * ------------------------------------------------------------------- */

    private function handle_list($resource)
    {
        $cfg   = $this->resources[$resource];
        $table = db_prefix() . $cfg['table'];

        $limit  = min(max((int) ($this->input->get('limit') ?: 50), 1), 200);
        $offset = max((int) $this->input->get('offset'), 0);

        $this->db->from($table);

        // Simple column filters: any query param matching a real column
        foreach ($this->input->get() as $key => $value) {
            if (in_array($key, ['limit', 'offset', 'search', 'sort_by', 'sort_order'], true)) {
                continue;
            }
            if ($this->db->field_exists($key, $table)) {
                $this->db->where($key, $value);
            }
        }

        $search = $this->input->get('search');
        if ($search !== null && $search !== '' && !empty($cfg['search'])) {
            $this->db->group_start();
            foreach ($cfg['search'] as $i => $column) {
                $i === 0 ? $this->db->like($column, $search) : $this->db->or_like($column, $search);
            }
            $this->db->group_end();
        }

        $count_db = clone $this->db;
        $total    = $count_db->count_all_results();

        $sort_by = $this->input->get('sort_by');
        if ($sort_by && $this->db->field_exists($sort_by, $table)) {
            $order = strtolower($this->input->get('sort_order')) === 'desc' ? 'DESC' : 'ASC';
            $this->db->order_by($sort_by, $order);
        } else {
            $this->db->order_by($cfg['pk'], 'DESC');
        }

        $rows = $this->db->limit($limit, $offset)->get()->result_array();

        $this->respond([
            'status' => true,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
            'data'   => array_map([$this, 'sanitize_row'], $rows),
        ]);
    }

    private function handle_get($resource, $id)
    {
        $cfg = $this->resources[$resource];
        $row = $this->db->where($cfg['pk'], $id)->get(db_prefix() . $cfg['table'])->row_array();

        if (!$row) {
            $this->respond_error(404, ucfirst($resource) . ' with id ' . $id . ' not found.');
        }

        $row = $this->sanitize_row($row);

        // Attach useful sub-resources
        if ($resource === 'customers') {
            $row['contacts'] = $this->db->where('userid', $id)->get(db_prefix() . 'contacts')->result_array();
        } elseif (in_array($resource, ['invoices', 'estimates', 'proposals', 'credit_notes'], true)) {
            $rel_type    = $resource === 'credit_notes' ? 'credit_note' : rtrim($resource, 's');
            $row['items'] = $this->db->where('rel_id', $id)->where('rel_type', $rel_type)
                ->order_by('item_order', 'ASC')->get(db_prefix() . 'itemable')->result_array();
        } elseif ($resource === 'tickets') {
            $row['replies'] = $this->db->where('ticketid', $id)->order_by('date', 'ASC')
                ->get(db_prefix() . 'ticket_replies')->result_array();
        }

        $this->respond(['status' => true, 'data' => $row]);
    }

    /* ---------------------------------------------------------------------
     * Write handlers — dispatch to Perfex models so side effects
     * (numbering, totals, activity log, hooks) run as in the UI.
     * ------------------------------------------------------------------- */

    private function handle_create($resource, $data)
    {
        if (empty($data)) {
            $this->respond_error(400, 'Request body is empty or not valid JSON/form data.');
        }

        $this->guard_readonly($resource);
        $insert_id = false;

        switch ($resource) {
            case 'customers':
                $this->load->model('clients_model');
                $insert_id = $this->clients_model->add($data, true);
                break;
            case 'contacts':
                $this->load->model('clients_model');
                $customer_id = (int) $this->extract($data, 'userid', 'customer_id');
                if (!$customer_id) {
                    $this->respond_error(400, 'Field "userid" (customer id) is required to create a contact.');
                }
                $insert_id = $this->clients_model->add_contact($data, $customer_id, true);
                break;
            case 'leads':
                $this->load->model('leads_model');
                $data['status'] = isset($data['status']) ? $data['status'] : $this->default_lead_status();
                $data['source'] = isset($data['source']) ? $data['source'] : $this->default_lead_source();
                $insert_id      = $this->leads_model->add($data);
                break;
            case 'invoices':
                $this->load->model('invoices_model');
                $insert_id = $this->invoices_model->add($this->prepare_sale_data($data));
                break;
            case 'estimates':
                $this->load->model('estimates_model');
                $insert_id = $this->estimates_model->add($this->prepare_sale_data($data));
                break;
            case 'proposals':
                $this->load->model('proposals_model');
                $insert_id = $this->proposals_model->add($this->prepare_sale_data($data));
                break;
            case 'credit_notes':
                $this->load->model('credit_notes_model');
                $insert_id = $this->credit_notes_model->add($this->prepare_sale_data($data));
                break;
            case 'payments':
                $this->load->model('payments_model');
                if (empty($data['invoiceid'])) {
                    $this->respond_error(400, 'Field "invoiceid" is required to record a payment.');
                }
                $insert_id = $this->payments_model->process_payment($data, '');
                break;
            case 'projects':
                $this->load->model('projects_model');
                $insert_id = $this->projects_model->add($data);
                break;
            case 'tasks':
                $this->load->model('tasks_model');
                $insert_id = $this->tasks_model->add($data);
                break;
            case 'tickets':
                $this->load->model('tickets_model');
                $insert_id = $this->tickets_model->add($data);
                break;
            case 'staff':
                $this->load->model('staff_model');
                $insert_id = $this->staff_model->add($data);
                break;
            case 'expenses':
                $this->load->model('expenses_model');
                $insert_id = $this->expenses_model->add($data);
                break;
            case 'contracts':
                $this->load->model('contracts_model');
                $insert_id = $this->contracts_model->add($data);
                break;
            case 'items':
                $this->load->model('invoice_items_model');
                $insert_id = $this->invoice_items_model->add($data);
                break;
        }

        if (!$insert_id) {
            $this->respond_error(422, 'Could not create ' . rtrim($resource, 's') . '. Check that all required fields are present and valid.');
        }

        $this->handle_get($resource, (int) $insert_id);
    }

    private function handle_update($resource, $id, $data)
    {
        if (empty($data)) {
            $this->respond_error(400, 'Request body is empty or not valid JSON/form data.');
        }

        $this->guard_readonly($resource);
        $this->ensure_exists($resource, $id);
        $success = false;

        switch ($resource) {
            case 'customers':
                $this->load->model('clients_model');
                $success = $this->clients_model->update($data, $id, true);
                break;
            case 'contacts':
                $this->load->model('clients_model');
                $success = $this->clients_model->update_contact($data, $id, true);
                if (is_array($success)) { // model may return ['set_password_email_sent' => ...]
                    $success = true;
                }
                break;
            case 'leads':
                $this->load->model('leads_model');
                $success = $this->leads_model->update($data, $id);
                break;
            case 'invoices':
                $this->load->model('invoices_model');
                $success = $this->invoices_model->update($this->prepare_sale_data($data, $resource, $id), $id);
                break;
            case 'estimates':
                $this->load->model('estimates_model');
                $success = $this->estimates_model->update($this->prepare_sale_data($data, $resource, $id), $id);
                break;
            case 'proposals':
                $this->load->model('proposals_model');
                $success = $this->proposals_model->update($this->prepare_sale_data($data, $resource, $id), $id);
                break;
            case 'credit_notes':
                $this->load->model('credit_notes_model');
                $success = $this->credit_notes_model->update($this->prepare_sale_data($data, $resource, $id), $id);
                break;
            case 'payments':
                $this->load->model('payments_model');
                $success = $this->payments_model->update($data, $id);
                break;
            case 'projects':
                $this->load->model('projects_model');
                $success = $this->projects_model->update($data, $id);
                break;
            case 'tasks':
                $this->load->model('tasks_model');
                $success = $this->tasks_model->update($data, $id);
                break;
            case 'tickets':
                $this->load->model('tickets_model');
                $success = $this->tickets_model->update_single_ticket_settings(array_merge($data, ['ticketid' => $id]));
                break;
            case 'staff':
                $this->load->model('staff_model');
                $success = $this->staff_model->update($data, $id);
                break;
            case 'expenses':
                $this->load->model('expenses_model');
                $success = $this->expenses_model->update($data, $id);
                break;
            case 'contracts':
                $this->load->model('contracts_model');
                $success = $this->contracts_model->update($data, $id);
                break;
            case 'items':
                $this->load->model('invoice_items_model');
                $data['itemid'] = $id;
                $success        = $this->invoice_items_model->edit($data);
                break;
        }

        if (!$success) {
            $this->respond_error(422, 'Nothing was updated. Check field names and values.');
        }

        $this->handle_get($resource, $id);
    }

    private function handle_delete($resource, $id)
    {
        $this->guard_readonly($resource);
        $this->ensure_exists($resource, $id);
        $success = false;

        switch ($resource) {
            case 'customers':
                $this->load->model('clients_model');
                $success = $this->clients_model->delete($id);
                break;
            case 'contacts':
                $this->load->model('clients_model');
                $success = $this->clients_model->delete_contact($id);
                break;
            case 'leads':
                $this->load->model('leads_model');
                $success = $this->leads_model->delete($id);
                break;
            case 'invoices':
                $this->load->model('invoices_model');
                $success = $this->invoices_model->delete($id, true);
                break;
            case 'estimates':
                $this->load->model('estimates_model');
                $success = $this->estimates_model->delete($id, true);
                break;
            case 'proposals':
                $this->load->model('proposals_model');
                $success = $this->proposals_model->delete($id);
                break;
            case 'credit_notes':
                $this->load->model('credit_notes_model');
                $success = $this->credit_notes_model->delete($id, true);
                break;
            case 'payments':
                $this->load->model('payments_model');
                $payment = $this->db->where('id', $id)->get(db_prefix() . 'invoicepaymentrecords')->row_array();
                $success = $payment ? $this->payments_model->delete($id, $payment['invoiceid']) : false;
                break;
            case 'projects':
                $this->load->model('projects_model');
                $success = $this->projects_model->delete($id);
                break;
            case 'tasks':
                $this->load->model('tasks_model');
                $success = $this->tasks_model->delete_task($id);
                break;
            case 'tickets':
                $this->load->model('tickets_model');
                $success = $this->tickets_model->delete($id);
                break;
            case 'staff':
                $this->load->model('staff_model');
                $success = $this->staff_model->delete($id, get_staff_user_id() ?: 1);
                break;
            case 'expenses':
                $this->load->model('expenses_model');
                $success = $this->expenses_model->delete($id);
                break;
            case 'contracts':
                $this->load->model('contracts_model');
                $success = $this->contracts_model->delete($id);
                break;
            case 'items':
                $this->load->model('invoice_items_model');
                $success = $this->invoice_items_model->delete($id);
                break;
        }

        if (!$success) {
            $this->respond_error(422, 'Delete failed. The record may be referenced by other data.');
        }

        $this->respond(['status' => true, 'message' => ucfirst(rtrim($resource, 's')) . ' ' . $id . ' deleted.']);
    }

    /* ---------------------------------------------------------------------
     * Auth & request plumbing
     * ------------------------------------------------------------------- */

    private function authenticate()
    {
        $token = null;

        $auth_header = $this->input->get_request_header('Authorization', true);
        if ($auth_header && stripos($auth_header, 'Bearer ') === 0) {
            $token = trim(substr($auth_header, 7));
        }

        if (!$token) {
            $token = $this->input->get_request_header('X-API-KEY', true);
        }

        if (!$token) {
            $this->respond_error(401, 'Missing API token. Send it as "Authorization: Bearer <token>" or "X-API-KEY: <token>".');
        }

        $key = $this->perfex_api_model->validate_token($token);
        if (!$key) {
            $this->respond_error(401, 'Invalid, inactive or expired API token.');
        }

        $this->api_key = $key;
    }

    private function require_write($capability)
    {
        if (empty($this->api_key[$capability])) {
            $this->respond_error(403, 'This API key does not have the "' . str_replace('can_', '', $capability) . '" permission.');
        }
    }

    private function guard_readonly($resource)
    {
        if (!empty($this->resources[$resource]['readonly'])) {
            $this->respond_error(405, 'Resource "' . $resource . '" is read-only.');
        }
    }

    private function ensure_exists($resource, $id)
    {
        $cfg    = $this->resources[$resource];
        $exists = $this->db->where($cfg['pk'], $id)->count_all_results(db_prefix() . $cfg['table']) > 0;
        if (!$exists) {
            $this->respond_error(404, ucfirst($resource) . ' with id ' . $id . ' not found.');
        }
    }

    private function request_method()
    {
        $method = strtoupper($this->input->server('REQUEST_METHOD'));
        // Allow overriding for hosts that block PUT/DELETE
        $override = $this->input->get_request_header('X-HTTP-Method-Override', true);
        if ($method === 'POST' && $override) {
            $method = strtoupper($override);
        }

        return $method;
    }

    /**
     * Accept JSON bodies and standard form-encoded bodies alike.
     */
    private function request_body()
    {
        $raw = trim(file_get_contents('php://input'));

        if ($raw !== '') {
            $content_type = (string) $this->input->get_request_header('Content-Type', true);
            if (stripos($content_type, 'application/json') !== false || in_array($raw[0], ['{', '['], true)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->respond_error(400, 'Invalid JSON body: ' . json_last_error_msg());
                }

                return is_array($decoded) ? $decoded : [];
            }

            parse_str($raw, $parsed);

            return $parsed;
        }

        return $this->input->post() ?: [];
    }

    /**
     * Perfex sales models expect newitems as arrays with rate/qty/description.
     * Normalize an API "items" field into what the models expect.
     */
    private function prepare_sale_data($data, $resource = null, $id = null)
    {
        if (isset($data['items']) && !isset($data['newitems'])) {
            $data['newitems'] = $data['items'];
            unset($data['items']);
        }

        if (isset($data['newitems']) && is_array($data['newitems'])) {
            $order = 1;
            foreach ($data['newitems'] as $i => $item) {
                $data['newitems'][$i]['order']           = isset($item['order']) ? $item['order'] : $order++;
                $data['newitems'][$i]['description']     = isset($item['description']) ? $item['description'] : '';
                $data['newitems'][$i]['long_description'] = isset($item['long_description']) ? $item['long_description'] : '';
                $data['newitems'][$i]['qty']             = isset($item['qty']) ? $item['qty'] : 1;
                $data['newitems'][$i]['rate']            = isset($item['rate']) ? $item['rate'] : 0;
                $data['newitems'][$i]['unit']            = isset($item['unit']) ? $item['unit'] : '';
                if (!isset($data['newitems'][$i]['taxname'])) {
                    $data['newitems'][$i]['taxname'] = [];
                }
            }
        }

        // On update, keep existing items untouched unless explicitly provided
        if ($id !== null && !isset($data['removed_items'])) {
            $data['removed_items'] = [];
        }
        if ($id !== null && !isset($data['items'])) {
            $data['items'] = [];
        }

        return $data;
    }

    private function default_lead_status()
    {
        $row = $this->db->order_by('statusorder', 'ASC')->limit(1)->get(db_prefix() . 'leads_status')->row_array();

        return $row ? $row['id'] : 1;
    }

    private function default_lead_source()
    {
        $row = $this->db->limit(1)->get(db_prefix() . 'leads_sources')->row_array();

        return $row ? $row['id'] : 1;
    }

    private function extract($data, ...$keys)
    {
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                return $data[$key];
            }
        }

        return null;
    }

    /**
     * Strip sensitive columns from responses.
     */
    private function sanitize_row($row)
    {
        foreach (['password', 'new_pass_key', 'new_pass_key_requested', 'two_factor_auth_code'] as $column) {
            unset($row[$column]);
        }

        return $row;
    }

    private function respond($payload, $code = 200)
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            ->_display();
        exit;
    }

    private function respond_error($code, $message)
    {
        $this->respond(['status' => false, 'error' => $message], $code);
    }
}
