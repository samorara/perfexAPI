<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Turns an uploaded invoice/receipt into structured fields using the OpenAI
 * Responses API with a strict JSON schema, so the reply always has the same
 * shape and the browser never has to guess.
 *
 * Images are sent as `input_image` data URIs, PDFs as `input_file`. Files above
 * PerfexPilot's inline threshold are uploaded to the Files API first and passed
 * by id, which is what keeps large multi-page PDFs working.
 *
 * HEIC and TIFF are not accepted by the vision endpoint; when the Imagick
 * extension is available they are transcoded to JPEG first, otherwise the scan
 * fails with an actionable message.
 */
class Perfexpilot_extractor
{
    /** Above this size the document goes through the Files API instead of a data URI. */
    const INLINE_LIMIT_BYTES = 8388608; // 8 MB

    const API_BASE = 'https://api.openai.com/v1';

    /** Image types the vision endpoint accepts as-is. */
    protected $native_image_types = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    /** Image types we can only send after transcoding to JPEG. */
    protected $convertible_image_types = ['image/heic', 'image/heif', 'image/tiff'];

    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * @param string $path    absolute path of the stored document
     * @param string $mime    detected mime type
     * @param string $context invoice|expense|payment
     *
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null, 'model' => string]
     */
    public function extract($path, $mime, $context)
    {
        $model = get_option('perfexpilot_model') ?: 'gpt-5';
        $key   = get_option('perfexpilot_openai_key');

        if ($key === '') {
            return $this->fail(_l('perfexpilot_error_no_key'), $model);
        }

        if (!is_readable($path)) {
            return $this->fail(_l('perfexpilot_error_unreadable'), $model);
        }

        $prepared = $this->prepare_document($path, $mime);

        if (!$prepared['success']) {
            return $this->fail($prepared['error'], $model);
        }

        $content = $this->build_document_content($prepared['path'], $prepared['mime'], $key);

        // A temporary transcode is no longer needed once it has been read/uploaded.
        if ($prepared['path'] !== $path) {
            @unlink($prepared['path']);
        }

        if (!$content['success']) {
            return $this->fail($content['error'], $model);
        }

        $payload = [
            'model' => $model,
            'input' => [
                [
                    'role'    => 'system',
                    'content' => [['type' => 'input_text', 'text' => $this->system_prompt()]],
                ],
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $this->user_prompt($context)],
                        $content['part'],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type'   => 'json_schema',
                    'name'   => 'perfexpilot_document',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];

        $payload = hooks()->apply_filters('perfexpilot_extraction_payload', $payload, [
            'context' => $context,
            'mime'    => $prepared['mime'],
        ]);

        $response = $this->http_request('POST', self::API_BASE . '/responses', [
            'headers' => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
            ],
            'body' => json_encode($payload),
        ]);

        if (!$response['success']) {
            return $this->fail($this->api_error_message($response['response']), $model);
        }

        $decoded = json_decode($response['response'], true);
        $text    = $this->extract_output_text($decoded);

        if ($text === null) {
            return $this->fail(_l('perfexpilot_error_empty_response'), $model);
        }

        $data = json_decode($text, true);

        if (!is_array($data)) {
            return $this->fail(_l('perfexpilot_error_bad_json'), $model);
        }

        $data = $this->normalize($data);
        $data = hooks()->apply_filters('perfexpilot_extraction_result', $data, [
            'context' => $context,
            'model'   => $model,
        ]);

        return ['success' => true, 'data' => $data, 'error' => null, 'model' => $model];
    }

    /**
     * Verify the stored key works, without sending a document.
     *
     * @return array ['success' => bool, 'response' => string]
     */
    public function test_connection()
    {
        $key = get_option('perfexpilot_openai_key');

        if ($key === '') {
            return ['success' => false, 'response' => _l('perfexpilot_error_no_key')];
        }

        $response = $this->http_request('GET', self::API_BASE . '/models', [
            'headers' => ['Authorization: Bearer ' . $key],
        ]);

        if (!$response['success']) {
            return ['success' => false, 'response' => $this->api_error_message($response['response'])];
        }

        return ['success' => true, 'response' => _l('perfexpilot_connection_ok')];
    }

    /* ------------------------------------------------------------------
     * Document preparation
     * ---------------------------------------------------------------- */

    /**
     * Convert unsupported image types to JPEG so the vision endpoint accepts them.
     *
     * @return array ['success' => bool, 'path' => string, 'mime' => string, 'error' => string|null]
     */
    protected function prepare_document($path, $mime)
    {
        $mime = strtolower($mime);

        if ($mime === 'application/pdf' || in_array($mime, $this->native_image_types, true)) {
            return ['success' => true, 'path' => $path, 'mime' => $mime, 'error' => null];
        }

        if (!in_array($mime, $this->convertible_image_types, true)) {
            return ['success' => false, 'path' => $path, 'mime' => $mime, 'error' => _l('perfexpilot_error_unsupported_type') . ' (' . $mime . ')'];
        }

        if (!class_exists('Imagick')) {
            return ['success' => false, 'path' => $path, 'mime' => $mime, 'error' => _l('perfexpilot_error_imagick_missing')];
        }

        try {
            $image = new Imagick();
            $image->readImage($path);
            $image->setIteratorIndex(0);
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(90);

            $converted = $path . '.jpg';
            $image->writeImage($converted);
            $image->clear();
            $image->destroy();
        } catch (Exception $e) {
            return ['success' => false, 'path' => $path, 'mime' => $mime, 'error' => _l('perfexpilot_error_convert_failed') . ' ' . $e->getMessage()];
        }

        return ['success' => true, 'path' => $converted, 'mime' => 'image/jpeg', 'error' => null];
    }

    /**
     * Build the image/file content part, uploading large documents to the Files API.
     *
     * @return array ['success' => bool, 'part' => array|null, 'error' => string|null]
     */
    protected function build_document_content($path, $mime, $key)
    {
        $size     = (int) filesize($path);
        $is_pdf   = $mime === 'application/pdf';
        $filename = basename($path);

        if ($size > self::INLINE_LIMIT_BYTES) {
            $upload = $this->upload_file($path, $mime, $key);

            if (!$upload['success']) {
                return ['success' => false, 'part' => null, 'error' => $upload['error']];
            }

            return [
                'success' => true,
                'error'   => null,
                'part'    => $is_pdf
                    ? ['type' => 'input_file', 'file_id' => $upload['file_id']]
                    : ['type' => 'input_image', 'file_id' => $upload['file_id'], 'detail' => 'high'],
            ];
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return ['success' => false, 'part' => null, 'error' => _l('perfexpilot_error_unreadable')];
        }

        $data_uri = 'data:' . $mime . ';base64,' . base64_encode($contents);

        return [
            'success' => true,
            'error'   => null,
            'part'    => $is_pdf
                ? ['type' => 'input_file', 'filename' => $filename, 'file_data' => $data_uri]
                : ['type' => 'input_image', 'image_url' => $data_uri, 'detail' => 'high'],
        ];
    }

    /**
     * @return array ['success' => bool, 'file_id' => string|null, 'error' => string|null]
     */
    protected function upload_file($path, $mime, $key)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::API_BASE . '/files',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout(),
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
            CURLOPT_POSTFIELDS     => [
                'purpose' => 'user_data',
                'file'    => new CURLFile($path, $mime, basename($path)),
            ],
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error     = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'file_id' => null, 'error' => 'cURL error: ' . $error];
        }

        $decoded = json_decode($response, true);

        if ($http_code < 200 || $http_code >= 300 || empty($decoded['id'])) {
            return ['success' => false, 'file_id' => null, 'error' => $this->api_error_message($response)];
        }

        return ['success' => true, 'file_id' => $decoded['id'], 'error' => null];
    }

    /* ------------------------------------------------------------------
     * Prompting and schema
     * ---------------------------------------------------------------- */

    protected function system_prompt()
    {
        $languages = trim((string) get_option('perfexpilot_languages')) ?: 'en';

        $prompt = 'You read scanned invoices, bills and receipts and return their contents as structured data. '
            . 'The document may be a photo taken with a phone, a scan, or a PDF, and may be rotated, skewed or partially cut off. '
            . 'Expected document languages: ' . $languages . '. '
            . 'Rules: '
            . 'Return every monetary value as a plain number with a dot decimal separator and no currency symbol or thousands separator. '
            . 'Convert every date to strict YYYY-MM-DD. When a date is ambiguous, use the document language and currency to decide between day-first and month-first. '
            . 'Use the ISO 4217 code for the currency. '
            . 'The vendor is whoever issued the document; the customer is whoever it is addressed to. '
            . 'For line items, rate is the unit price excluding tax and amount is the line total; if only one of them is printed, compute the other from the quantity when it is unambiguous. '
            . 'Set a field to null when it is not present in the document. Never invent a value and never copy an example. '
            . 'Score confidence from 0 to 100 per field, reflecting how legible and unambiguous the source text was; overall is your confidence in the extraction as a whole.';

        return hooks()->apply_filters('perfexpilot_extraction_prompt', $prompt, ['type' => 'system']);
    }

    protected function user_prompt($context)
    {
        $prompts = [
            'invoice' => 'Extract this document so it can be entered as an invoice: customer, invoice number, issue and due dates, currency, every line item, subtotal, tax total, discount and grand total.',
            'expense' => 'Extract this document so it can be entered as a single expense: the vendor who issued it, a short expense name, the category it most likely belongs to, the date, the amount excluding tax, the tax rates applied, the payment method and any reference number. Line items are optional context here.',
            'payment' => 'Extract this document so it can be entered as a payment against an invoice: the amount actually paid, the payment date, the payment method, the transaction or reference id, and the invoice number it settles.',
        ];

        $prompt = isset($prompts[$context]) ? $prompts[$context] : $prompts['invoice'];

        return hooks()->apply_filters('perfexpilot_extraction_prompt', $prompt, ['type' => 'user', 'context' => $context]);
    }

    /**
     * Strict JSON schema: every property is required and nullable, which is what
     * `strict: true` demands, and `confidence.fields` is a list rather than a map
     * because strict mode forbids free-form object keys.
     */
    protected function schema()
    {
        $string = ['type' => ['string', 'null']];
        $number = ['type' => ['number', 'null']];

        $object = function ($properties) {
            return [
                'type'                 => ['object', 'null'],
                'properties'           => $properties,
                'required'             => array_keys($properties),
                'additionalProperties' => false,
            ];
        };

        $properties = [
            'document_type' => ['type' => ['string', 'null'], 'description' => 'invoice, receipt, bill, credit note or other'],
            'currency'      => $string,
            'vendor'        => $object([
                'name' => $string, 'vat_number' => $string, 'email' => $string,
                'phone' => $string, 'address' => $string,
            ]),
            'customer' => $object([
                'name' => $string, 'vat_number' => $string,
            ]),
            'number'         => $string,
            'reference_no'   => $string,
            'issue_date'     => $string,
            'due_date'       => $string,
            'payment_date'   => $string,
            'subtotal'       => $number,
            'tax_total'      => $number,
            'discount_total' => $number,
            'total'          => $number,
            'amount_paid'    => $number,
            'payment_method' => $string,
            'transaction_id' => $string,
            'notes'          => $string,
            'terms'          => $string,
            'line_items'     => [
                'type'  => ['array', 'null'],
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'description'      => $string,
                        'long_description' => $string,
                        'qty'              => $number,
                        'unit'             => $string,
                        'rate'             => $number,
                        'amount'           => $number,
                        'tax_rate'         => $number,
                    ],
                    'required'             => ['description', 'long_description', 'qty', 'unit', 'rate', 'amount', 'tax_rate'],
                    'additionalProperties' => false,
                ],
            ],
            'expense_category_hint' => $string,
            'confidence'            => [
                'type'       => 'object',
                'properties' => [
                    'overall' => ['type' => 'number'],
                    'fields'  => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'field' => ['type' => 'string'],
                                'score' => ['type' => 'number'],
                            ],
                            'required'             => ['field', 'score'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required'             => ['overall', 'fields'],
                'additionalProperties' => false,
            ],
        ];

        return [
            'type'                 => 'object',
            'properties'           => $properties,
            'required'             => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /* ------------------------------------------------------------------
     * Response handling
     * ---------------------------------------------------------------- */

    /**
     * Pull the assistant text out of a Responses API payload.
     */
    protected function extract_output_text($decoded)
    {
        if (!is_array($decoded)) {
            return null;
        }

        if (isset($decoded['output_text']) && is_string($decoded['output_text']) && $decoded['output_text'] !== '') {
            return $decoded['output_text'];
        }

        if (empty($decoded['output']) || !is_array($decoded['output'])) {
            return null;
        }

        foreach ($decoded['output'] as $item) {
            if (empty($item['content']) || !is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $part) {
                if (isset($part['text']) && is_string($part['text']) && trim($part['text']) !== '') {
                    return $part['text'];
                }
            }
        }

        return null;
    }

    /**
     * Guarantee the shape the browser expects, whatever the model returned, and
     * re-validate dates and numbers server side.
     */
    protected function normalize($data)
    {
        $out = [];

        foreach (['document_type', 'currency', 'number', 'reference_no', 'payment_method', 'transaction_id', 'notes', 'terms', 'expense_category_hint'] as $field) {
            $out[$field] = $this->clean_string(isset($data[$field]) ? $data[$field] : null);
        }

        foreach (['issue_date', 'due_date', 'payment_date'] as $field) {
            $out[$field] = $this->clean_date(isset($data[$field]) ? $data[$field] : null);
        }

        foreach (['subtotal', 'tax_total', 'discount_total', 'total', 'amount_paid'] as $field) {
            $out[$field] = $this->clean_number(isset($data[$field]) ? $data[$field] : null);
        }

        $out['vendor'] = [
            'name'       => $this->clean_string($this->dig($data, 'vendor', 'name')),
            'vat_number' => $this->clean_string($this->dig($data, 'vendor', 'vat_number')),
            'email'      => $this->clean_string($this->dig($data, 'vendor', 'email')),
            'phone'      => $this->clean_string($this->dig($data, 'vendor', 'phone')),
            'address'    => $this->clean_string($this->dig($data, 'vendor', 'address')),
        ];

        $out['customer'] = [
            'name'       => $this->clean_string($this->dig($data, 'customer', 'name')),
            'vat_number' => $this->clean_string($this->dig($data, 'customer', 'vat_number')),
        ];

        $out['line_items'] = [];

        if (!empty($data['line_items']) && is_array($data['line_items'])) {
            foreach ($data['line_items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $qty    = $this->clean_number(isset($item['qty']) ? $item['qty'] : null);
                $rate   = $this->clean_number(isset($item['rate']) ? $item['rate'] : null);
                $amount = $this->clean_number(isset($item['amount']) ? $item['amount'] : null);

                // Fill in whichever of qty/rate/amount the document left implicit.
                if ($qty === null && $rate !== null && $amount !== null && $rate != 0) {
                    $qty = round($amount / $rate, 2);
                }
                if ($rate === null && $qty !== null && $amount !== null && $qty != 0) {
                    $rate = round($amount / $qty, 2);
                }
                if ($amount === null && $qty !== null && $rate !== null) {
                    $amount = round($qty * $rate, 2);
                }

                $description = $this->clean_string(isset($item['description']) ? $item['description'] : null);

                if ($description === null && $rate === null && $amount === null) {
                    continue;
                }

                $out['line_items'][] = [
                    'description'      => $description,
                    'long_description' => $this->clean_string(isset($item['long_description']) ? $item['long_description'] : null),
                    'qty'              => $qty === null ? 1 : $qty,
                    'unit'             => $this->clean_string(isset($item['unit']) ? $item['unit'] : null),
                    'rate'             => $rate,
                    'amount'           => $amount,
                    'tax_rate'         => $this->clean_number(isset($item['tax_rate']) ? $item['tax_rate'] : null),
                ];
            }
        }

        // Derive the tax rate the document implies, so the resolver has something
        // to work with even when no per-line rate was printed.
        $out['implied_tax_rate'] = null;

        if ($out['subtotal'] !== null && $out['tax_total'] !== null && $out['subtotal'] > 0 && $out['tax_total'] > 0) {
            $out['implied_tax_rate'] = round(($out['tax_total'] / $out['subtotal']) * 100, 2);
        }

        $overall = $this->clean_number($this->dig($data, 'confidence', 'overall'));
        $fields  = [];

        if (!empty($data['confidence']['fields']) && is_array($data['confidence']['fields'])) {
            foreach ($data['confidence']['fields'] as $entry) {
                if (isset($entry['field'], $entry['score'])) {
                    $fields[(string) $entry['field']] = max(0, min(100, (int) round((float) $entry['score'])));
                }
            }
        }

        $out['confidence'] = [
            'overall' => $overall === null ? 0 : max(0, min(100, (int) round($overall))),
            'fields'  => $fields,
        ];

        return $out;
    }

    protected function dig($data, $parent, $child)
    {
        return isset($data[$parent][$child]) ? $data[$parent][$child] : null;
    }

    protected function clean_string($value)
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'null' || strtolower($value) === 'n/a') {
            return null;
        }

        return $value;
    }

    protected function clean_number($value)
    {
        if ($value === null || is_array($value) || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        // Strip currency symbols, spaces and thousands separators the model may have kept.
        $value = str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', (string) $value));

        // With several separators left, the last one is the decimal point.
        if (substr_count($value, '.') > 1) {
            $parts = explode('.', $value);
            $last  = array_pop($parts);
            $value = implode('', $parts) . '.' . $last;
        }

        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Accept only real YYYY-MM-DD dates; anything else is dropped rather than guessed at.
     */
    protected function clean_date($value)
    {
        $value = $this->clean_string($value);

        if ($value === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }

    /**
     * Turn an OpenAI error body into something a user can act on.
     */
    protected function api_error_message($body)
    {
        $decoded = json_decode((string) $body, true);

        if (isset($decoded['error']['message'])) {
            return $decoded['error']['message'];
        }

        return substr((string) $body, 0, 500);
    }

    protected function timeout()
    {
        $timeout = (int) get_option('perfexpilot_timeout');

        return $timeout > 0 ? $timeout : 120;
    }

    protected function fail($error, $model)
    {
        return ['success' => false, 'data' => null, 'error' => $error, 'model' => $model];
    }

    /**
     * @return array ['success' => bool, 'response' => string]
     */
    protected function http_request($method, $url, $options = [])
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout(),
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if (!empty($options['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
        }
        if (isset($options['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
        }

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error     = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'response' => 'cURL error: ' . $error];
        }

        return [
            'success'  => $http_code >= 200 && $http_code < 300,
            'response' => (string) $response,
        ];
    }
}
