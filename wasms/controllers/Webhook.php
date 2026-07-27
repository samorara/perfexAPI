<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Public webhook endpoints for incoming messages.
 *
 * WhatsApp Business Cloud API (Meta):
 *   GET/POST <crm-url>/wasms/webhook/whatsapp
 *   - GET handles Meta's verification handshake (hub.challenge).
 *   - POST receives message notifications and triggers auto replies.
 *
 * Generic gateways (personal-phone WhatsApp gateway apps, SMS forwarder apps):
 *   GET/POST <crm-url>/wasms/webhook/incoming/sms?secret=...&phone=...&message=...
 *   GET/POST <crm-url>/wasms/webhook/incoming/whatsapp?secret=...&phone=...&message=...
 *   - Accepts phone/message (aliases: from/number, message/text/body) via GET
 *     query string, POST form data, or a POST JSON body.
 *   - The JSON response includes the matched auto-reply text, so gateway apps
 *     that can answer from an HTTP response may deliver the reply themselves.
 */
class Webhook extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('wasms/wasms_model');
    }

    public function whatsapp()
    {
        // Meta webhook verification handshake
        if ($this->input->server('REQUEST_METHOD') === 'GET') {
            $mode      = $this->input->get('hub_mode');
            $token     = $this->input->get('hub_verify_token');
            $challenge = $this->input->get('hub_challenge');

            if ($mode === 'subscribe' && $token === get_option('wasms_cloud_verify_token')) {
                echo $challenge;
                exit;
            }

            $this->json(['status' => false, 'error' => 'Verification failed'], 403);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $handled = 0;

        if (!empty($payload['entry'])) {
            foreach ($payload['entry'] as $entry) {
                foreach (isset($entry['changes']) ? $entry['changes'] : [] as $change) {
                    $value = isset($change['value']) ? $change['value'] : [];
                    foreach (isset($value['messages']) ? $value['messages'] : [] as $msg) {
                        $phone = isset($msg['from']) ? $msg['from'] : '';
                        $text  = isset($msg['text']['body']) ? $msg['text']['body'] : '';
                        if ($phone !== '') {
                            $this->wasms_model->process_incoming('whatsapp', $phone, $text);
                            $handled++;
                        }
                    }
                }
            }
        }

        $this->json(['status' => true, 'handled' => $handled]);
    }

    /**
     * Generic incoming endpoint for personal-phone gateway / SMS forwarder apps.
     * Accepts GET or POST.
     */
    public function incoming($channel = 'sms')
    {
        $channel = $channel === 'whatsapp' ? 'whatsapp' : 'sms';
        $params  = $this->collect_params();

        $secret = get_option('wasms_webhook_secret');
        if ($secret !== '' && (!isset($params['secret']) || !hash_equals($secret, (string) $params['secret']))) {
            $this->json(['status' => false, 'error' => 'Invalid or missing secret.'], 401);
        }

        $phone   = $this->first_of($params, ['phone', 'from', 'number', 'sender']);
        $message = $this->first_of($params, ['message', 'text', 'body', 'content']);

        if ($phone === null || $message === null) {
            $this->json(['status' => false, 'error' => 'Parameters "phone" and "message" are required (GET or POST).'], 400);
        }

        $reply = $this->wasms_model->process_incoming($channel, $phone, $message);

        $this->json([
            'status'     => true,
            'channel'    => $channel,
            'reply_sent' => $reply !== null,
            'reply'      => $reply,
        ]);
    }

    private function collect_params()
    {
        $params = array_merge($this->input->get() ?: [], $this->input->post() ?: []);

        $raw = trim(file_get_contents('php://input'));
        if ($raw !== '' && in_array(substr($raw, 0, 1), ['{', '['], true)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $params = array_merge($params, $json);
            }
        }

        return $params;
    }

    private function first_of($params, $keys)
    {
        foreach ($keys as $key) {
            if (isset($params[$key]) && trim((string) $params[$key]) !== '') {
                return trim((string) $params[$key]);
            }
        }

        return null;
    }

    private function json($payload, $code = 200)
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload))
            ->_display();
        exit;
    }
}
