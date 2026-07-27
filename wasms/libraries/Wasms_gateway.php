<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Sends WhatsApp and SMS messages through the configured gateways.
 *
 * WhatsApp supports two modes:
 *  - personal: your existing phone running an HTTP gateway app; the request is
 *    built from a URL template with {phone} and {message} placeholders and sent
 *    via GET or POST.
 *  - cloud: the official WhatsApp Business Cloud API (Meta Graph API).
 *
 * SMS uses the same URL-template mechanism (any HTTP SMS gateway, GET or POST).
 */
class Wasms_gateway
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * @return array ['success' => bool, 'response' => string]
     */
    public function send($channel, $phone, $message)
    {
        $phone = $this->normalize_phone($phone);

        if ($channel === 'whatsapp') {
            $result = get_option('wasms_whatsapp_mode') === 'cloud'
                ? $this->send_whatsapp_cloud($phone, $message)
                : $this->send_via_template(
                    get_option('wasms_personal_url'),
                    get_option('wasms_personal_method'),
                    get_option('wasms_personal_body_format'),
                    $phone,
                    $message
                );
        } elseif ($channel === 'sms') {
            $result = $this->send_via_template(
                get_option('wasms_sms_url'),
                get_option('wasms_sms_method'),
                get_option('wasms_sms_body_format'),
                $phone,
                $message
            );
        } else {
            $result = ['success' => false, 'response' => 'Unknown channel: ' . $channel];
        }

        return $result;
    }

    /**
     * Generic template gateway: {phone} and {message} placeholders in the URL.
     * GET  -> placeholders are URL-encoded into the query string.
     * POST -> placeholders left in the URL are replaced too, and phone/message
     *         are additionally sent in the body (form or JSON).
     */
    protected function send_via_template($url_template, $method, $body_format, $phone, $message)
    {
        if (empty($url_template)) {
            return ['success' => false, 'response' => 'Gateway URL is not configured. Set it in WA SMS settings.'];
        }

        $method = strtoupper($method) === 'POST' ? 'POST' : 'GET';

        $url = str_replace(
            ['{phone}', '{message}'],
            [rawurlencode($phone), rawurlencode($message)],
            $url_template
        );

        $options = [];
        if ($method === 'POST') {
            if ($body_format === 'json') {
                $options['headers'] = ['Content-Type: application/json'];
                $options['body']    = json_encode(['phone' => $phone, 'message' => $message]);
            } else {
                $options['headers'] = ['Content-Type: application/x-www-form-urlencoded'];
                $options['body']    = http_build_query(['phone' => $phone, 'message' => $message]);
            }
        }

        return $this->http_request($method, $url, $options);
    }

    protected function send_whatsapp_cloud($phone, $message)
    {
        $token           = get_option('wasms_cloud_token');
        $phone_number_id = get_option('wasms_cloud_phone_number_id');
        $api_version     = get_option('wasms_cloud_api_version') ?: 'v20.0';

        if (empty($token) || empty($phone_number_id)) {
            return ['success' => false, 'response' => 'WhatsApp Cloud API token or phone number id is not configured.'];
        }

        $url = 'https://graph.facebook.com/' . $api_version . '/' . $phone_number_id . '/messages';

        return $this->http_request('POST', $url, [
            'headers' => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            'body' => json_encode([
                'messaging_product' => 'whatsapp',
                'to'                => $phone,
                'type'              => 'text',
                'text'              => ['body' => $message],
            ]),
        ]);
    }

    protected function http_request($method, $url, $options = [])
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
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
            'response' => 'HTTP ' . $http_code . ': ' . substr((string) $response, 0, 1000),
        ];
    }

    protected function normalize_phone($phone)
    {
        // Keep digits and a leading +
        $phone = trim($phone);
        $plus  = strpos($phone, '+') === 0 ? '+' : '';

        return $plus . preg_replace('/[^0-9]/', '', $phone);
    }
}
