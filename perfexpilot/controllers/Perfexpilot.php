<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin controller: settings, scan history and the AJAX endpoints the
 * Scan & Fill modal talks to.
 */
class Perfexpilot extends AdminController
{
    /** Mime types accepted for upload, keyed by the extensions that may carry them. */
    private $allowed_types = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
        'tif'  => 'image/tiff',
        'tiff' => 'image/tiff',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('perfexpilot/perfexpilot_model');
    }

    /* ------------------------------------------------------------------
     * Admin pages
     * ---------------------------------------------------------------- */

    public function index()
    {
        if (!has_permission('perfexpilot', '', 'view') && !is_admin()) {
            access_denied('perfexpilot');
        }

        $data['title']   = _l('perfexpilot');
        $data['history'] = $this->perfexpilot_model->get_history(100);

        $this->load->view('perfexpilot/manage', $data);
    }

    public function save_settings()
    {
        if (!is_admin()) {
            access_denied('perfexpilot');
        }

        if ($this->input->post()) {
            $key = trim($this->input->post('perfexpilot_openai_key', false));

            $options = [
                'perfexpilot_model'                => trim($this->input->post('perfexpilot_model')) ?: 'gpt-5',
                'perfexpilot_languages'            => trim($this->input->post('perfexpilot_languages')) ?: 'en',
                'perfexpilot_max_file_mb'          => max(1, (int) $this->input->post('perfexpilot_max_file_mb')),
                'perfexpilot_confidence_threshold' => max(0, min(100, (int) $this->input->post('perfexpilot_confidence_threshold'))),
                'perfexpilot_timeout'              => max(10, (int) $this->input->post('perfexpilot_timeout')),
                'perfexpilot_retention_days'       => max(0, (int) $this->input->post('perfexpilot_retention_days')),
                'perfexpilot_auto_create_tax'      => $this->input->post('perfexpilot_auto_create_tax') ? '1' : '0',
                'perfexpilot_enable_invoices'      => $this->input->post('perfexpilot_enable_invoices') ? '1' : '0',
                'perfexpilot_enable_expenses'      => $this->input->post('perfexpilot_enable_expenses') ? '1' : '0',
                'perfexpilot_enable_payments'      => $this->input->post('perfexpilot_enable_payments') ? '1' : '0',
            ];

            // An empty key field means "leave the stored key alone", so a saved key
            // is never wiped by someone saving the other settings.
            if ($key !== '') {
                $options['perfexpilot_openai_key'] = $key;
            }

            foreach ($options as $name => $value) {
                update_option($name, $value);
            }

            set_alert('success', _l('settings_updated'));
        }

        redirect(admin_url('perfexpilot'));
    }

    public function test_connection()
    {
        if (!is_admin()) {
            access_denied('perfexpilot');
        }

        $this->load->library('perfexpilot/perfexpilot_extractor');
        $result = $this->perfexpilot_extractor->test_connection();

        $this->json_response([
            'success' => $result['success'],
            'message' => $result['response'],
        ]);
    }

    public function delete_scan($id)
    {
        if (!has_permission('perfexpilot', '', 'delete') && !is_admin()) {
            access_denied('perfexpilot');
        }

        if ($this->perfexpilot_model->delete_scan((int) $id)) {
            set_alert('success', _l('deleted', _l('perfexpilot_scan')));
        }

        redirect(admin_url('perfexpilot'));
    }

    /**
     * Stream a retained document back to permitted staff. Files live outside the
     * web root's reach, so this is the only way to read them.
     */
    public function file($id)
    {
        if (!has_permission('perfexpilot', '', 'view') && !is_admin()) {
            access_denied('perfexpilot');
        }

        $scan = $this->perfexpilot_model->get_scan((int) $id);

        if (!$scan || empty($scan['stored_name'])) {
            show_404();
        }

        $path = perfexpilot_upload_path() . basename($scan['stored_name']);

        if (!is_file($path)) {
            show_404();
        }

        header('Content-Type: ' . $scan['mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $scan['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /* ------------------------------------------------------------------
     * AJAX endpoints used by the Scan & Fill modal
     * ---------------------------------------------------------------- */

    /**
     * Taxes, payment modes, expense categories and currencies, so the browser can
     * map extracted text onto real Perfex records without extra round trips.
     */
    public function lookup()
    {
        if (!has_permission('perfexpilot', '', 'create') && !is_admin()) {
            $this->json_response(['success' => false, 'message' => _l('access_denied')], 403);
        }

        $this->json_response([
            'success' => true,
            'data'    => $this->perfexpilot_model->lookup_data(),
        ]);
    }

    /**
     * Reuse an existing tax for the given rate, or create one.
     */
    public function resolve_tax()
    {
        if (!has_permission('perfexpilot', '', 'create') && !is_admin()) {
            $this->json_response(['success' => false, 'message' => _l('access_denied')], 403);
        }

        $result = $this->perfexpilot_model->resolve_tax(
            (float) $this->input->post('rate'),
            (string) $this->input->post('name')
        );

        $this->json_response([
            'success' => $result['success'],
            'created' => $result['created'],
            'tax'     => $result['tax'],
            'message' => $result['error'],
        ]);
    }

    /**
     * Accept an uploaded document, extract its fields and return them as JSON.
     */
    public function scan()
    {
        if (!has_permission('perfexpilot', '', 'create') && !is_admin()) {
            $this->json_response(['success' => false, 'message' => _l('access_denied')], 403);
        }

        $context = $this->input->post('context');

        if (!in_array($context, ['invoice', 'expense', 'payment'], true)) {
            $context = 'invoice';
        }

        if (empty($_FILES['file']['name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            $this->json_response(['success' => false, 'message' => _l('perfexpilot_error_no_file')], 400);
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->json_response(['success' => false, 'message' => _l('perfexpilot_error_upload') . ' (' . $file['error'] . ')'], 400);
        }

        $max_bytes = ((int) get_option('perfexpilot_max_file_mb') ?: 20) * 1024 * 1024;

        if ($file['size'] > $max_bytes) {
            $this->json_response([
                'success' => false,
                'message' => sprintf(_l('perfexpilot_error_too_large'), get_option('perfexpilot_max_file_mb')),
            ], 400);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!isset($this->allowed_types[$extension])) {
            $this->json_response([
                'success' => false,
                'message' => sprintf(_l('perfexpilot_error_extension'), implode(', ', array_keys($this->allowed_types))),
            ], 400);
        }

        // Trust the file's own bytes over the extension and the browser's claim.
        $mime = $this->detect_mime($file['tmp_name'], $extension);

        if (!in_array($mime, array_values($this->allowed_types), true)) {
            $this->json_response([
                'success' => false,
                'message' => _l('perfexpilot_error_unsupported_type') . ' (' . $mime . ')',
            ], 400);
        }

        $upload_path = perfexpilot_upload_path();

        if (!is_dir($upload_path)) {
            @mkdir($upload_path, 0755, true);
        }

        $stored_name = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $stored_path = $upload_path . $stored_name;

        if (!move_uploaded_file($file['tmp_name'], $stored_path)) {
            $this->json_response(['success' => false, 'message' => _l('perfexpilot_error_store')], 500);
        }

        $this->load->library('perfexpilot/perfexpilot_extractor');
        $result = $this->perfexpilot_extractor->extract($stored_path, $mime, $context);

        $threshold  = (int) get_option('perfexpilot_confidence_threshold');
        $confidence = $result['success'] ? (int) $result['data']['confidence']['overall'] : 0;

        if (!$result['success']) {
            $status = 'failed';
        } elseif ($confidence < $threshold) {
            $status = 'low_confidence';
        } else {
            $status = 'ok';
        }

        $scan_id = $this->perfexpilot_model->add_scan([
            'context'        => $context,
            'original_name'  => $file['name'],
            'stored_name'    => $stored_name,
            'mime'           => $mime,
            'filesize'       => (int) $file['size'],
            'model'          => $result['model'],
            'status'         => $status,
            'confidence'     => $confidence,
            'extracted_json' => $result['success'] ? json_encode($result['data']) : null,
            'error'          => $result['success'] ? null : $result['error'],
        ]);

        hooks()->do_action('perfexpilot_after_scan', [
            'scan_id' => $scan_id,
            'context' => $context,
            'status'  => $status,
            'data'    => $result['data'],
        ]);

        if (!$result['success']) {
            $this->json_response(['success' => false, 'scan_id' => $scan_id, 'message' => $result['error']], 200);
        }

        $this->json_response([
            'success'    => true,
            'scan_id'    => $scan_id,
            'status'     => $status,
            'confidence' => $confidence,
            'threshold'  => $threshold,
            'data'       => $result['data'],
        ]);
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------- */

    private function detect_mime($path, $extension)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo) {
                $detected = finfo_file($finfo, $path);
                finfo_close($finfo);

                if ($detected) {
                    $detected = strtolower($detected);

                    // HEIC/HEIF are frequently reported as a generic ISO container.
                    if (in_array($detected, ['application/octet-stream', 'video/quicktime'], true)
                        && in_array($extension, ['heic', 'heif'], true)) {
                        return $this->allowed_types[$extension];
                    }

                    return $detected;
                }
            }
        }

        return $this->allowed_types[$extension];
    }

    private function json_response($payload, $status = 200)
    {
        $this->output->set_status_header($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        die;
    }
}
