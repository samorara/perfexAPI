<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Zuri — AI agent for automatic replies.
 *
 * Calls the Claude Messages API with a persona system prompt and the recent
 * conversation history for the sender's phone number, and returns a reply
 * suitable for SMS/WhatsApp. Returns null on any failure so the caller can
 * fall back to keyword rules / the default reply.
 */
class Wasms_ai
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function is_enabled()
    {
        return get_option('wasms_ai_enabled') == '1' && get_option('wasms_ai_api_key') !== '';
    }

    /**
     * @return string|null the generated reply
     */
    public function generate_reply($channel, $phone, $message)
    {
        if (!$this->is_enabled()) {
            return null;
        }

        $messages   = $this->conversation_history($phone);
        $messages[] = ['role' => 'user', 'content' => $message];

        $payload = [
            'model'      => get_option('wasms_ai_model') ?: 'claude-opus-5',
            'max_tokens' => (int) (get_option('wasms_ai_max_tokens') ?: 500),
            'system'     => $this->system_prompt($channel),
            'messages'   => $messages,
        ];

        $response = $this->api_request($payload);

        if ($response === null) {
            return null;
        }

        if (isset($response['stop_reason']) && $response['stop_reason'] === 'refusal') {
            log_activity('WA SMS AI (Zuri): request declined by model safety filters');

            return null;
        }

        $text = '';
        foreach (isset($response['content']) ? $response['content'] : [] as $block) {
            if (isset($block['type']) && $block['type'] === 'text') {
                $text .= $block['text'];
            }
        }

        $text = trim($text);

        return $text !== '' ? $text : null;
    }

    protected function system_prompt($channel)
    {
        $name    = get_option('wasms_ai_agent_name') ?: 'Zuri';
        $company = get_option('companyname') ?: '';

        $prompt = 'You are ' . $name . ', a friendly customer assistant'
            . ($company !== '' ? ' for ' . $company : '')
            . ', replying to customers over ' . ($channel === 'whatsapp' ? 'WhatsApp' : 'SMS') . ".\n\n"
            . "Rules:\n"
            . "- Keep replies short and conversational, suitable for a text message (2-4 sentences).\n"
            . "- Plain text only: no markdown, headers, or bullet lists.\n"
            . "- If you don't know something or a request needs a human, say a team member will follow up.\n"
            . "- Never invent prices, order details, or commitments.\n"
            . '- Reply in the same language the customer writes in.';

        $persona = trim((string) get_option('wasms_ai_persona'));
        if ($persona !== '') {
            $prompt .= "\n\nBusiness information and instructions:\n" . $persona;
        }

        return $prompt;
    }

    /**
     * Recent exchange with this phone number, oldest first, mapped to API roles.
     */
    protected function conversation_history($phone)
    {
        $limit = (int) (get_option('wasms_ai_history_limit') ?: 10);

        $rows = $this->CI->db->where('phone', $phone)
            ->order_by('id', 'desc')
            ->limit($limit)
            ->get(db_prefix() . 'wasms_messages')
            ->result_array();

        $rows     = array_reverse($rows);
        $messages = [];

        foreach ($rows as $row) {
            if (trim($row['message']) === '') {
                continue;
            }
            $messages[] = [
                'role'    => $row['direction'] === 'in' ? 'user' : 'assistant',
                'content' => $row['message'],
            ];
        }

        // The API requires the first message to be a user turn
        while (!empty($messages) && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        return $messages;
    }

    protected function api_request($payload)
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . get_option('wasms_ai_api_key'),
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $body      = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error     = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            log_activity('WA SMS AI (Zuri): cURL error - ' . $error);

            return null;
        }

        $decoded = json_decode($body, true);

        if ($http_code < 200 || $http_code >= 300 || !is_array($decoded)) {
            $api_error = isset($decoded['error']['message']) ? $decoded['error']['message'] : substr((string) $body, 0, 300);
            log_activity('WA SMS AI (Zuri): API error HTTP ' . $http_code . ' - ' . $api_error);

            return null;
        }

        return $decoded;
    }
}
