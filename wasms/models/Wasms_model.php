<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wasms_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('wasms/wasms_gateway');
    }

    /**
     * Send a message via the gateway and log it.
     *
     * @return array ['success' => bool, 'response' => string]
     */
    public function send($channel, $phone, $message, $direction = 'out', $staff_id = 0)
    {
        $result = $this->wasms_gateway->send($channel, $phone, $message);

        $this->log_message([
            'channel'          => $channel,
            'direction'        => $direction,
            'phone'            => $phone,
            'message'          => $message,
            'status'           => $result['success'] ? 'sent' : 'failed',
            'gateway_response' => $result['response'],
            'staff_id'         => $staff_id,
        ]);

        return $result;
    }

    public function log_message($data)
    {
        $this->db->insert(db_prefix() . 'wasms_messages', [
            'channel'          => $data['channel'],
            'direction'        => isset($data['direction']) ? $data['direction'] : 'out',
            'phone'            => $data['phone'],
            'message'          => $data['message'],
            'status'           => isset($data['status']) ? $data['status'] : 'received',
            'gateway_response' => isset($data['gateway_response']) ? $data['gateway_response'] : null,
            'staff_id'         => isset($data['staff_id']) ? $data['staff_id'] : 0,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    public function get_log($limit = 100)
    {
        return $this->db->order_by('id', 'desc')->limit($limit)
            ->get(db_prefix() . 'wasms_messages')->result_array();
    }

    /* ------------------------------------------------------------------
     * Auto replies
     * ---------------------------------------------------------------- */

    public function get_auto_replies($only_active = false)
    {
        if ($only_active) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('id', 'asc')->get(db_prefix() . 'wasms_auto_replies')->result_array();
    }

    public function add_auto_reply($data)
    {
        $this->db->insert(db_prefix() . 'wasms_auto_replies', [
            'keyword'    => $data['keyword'],
            'match_type' => in_array($data['match_type'], ['contains', 'exact', 'starts_with', 'any'], true) ? $data['match_type'] : 'contains',
            'channel'    => in_array($data['channel'], ['both', 'sms', 'whatsapp'], true) ? $data['channel'] : 'both',
            'reply'      => $data['reply'],
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    public function toggle_auto_reply($id)
    {
        $row = $this->db->where('id', $id)->get(db_prefix() . 'wasms_auto_replies')->row_array();
        if ($row) {
            $this->db->where('id', $id)->update(db_prefix() . 'wasms_auto_replies', ['active' => $row['active'] ? 0 : 1]);
        }
    }

    public function delete_auto_reply($id)
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'wasms_auto_replies');
    }

    /**
     * Find the auto reply text for an incoming message, or null.
     */
    public function match_auto_reply($channel, $message)
    {
        if (get_option('wasms_auto_reply_enabled') != '1') {
            return null;
        }

        $message_lc = mb_strtolower(trim($message));

        foreach ($this->get_auto_replies(true) as $rule) {
            if ($rule['channel'] !== 'both' && $rule['channel'] !== $channel) {
                continue;
            }

            $keyword = mb_strtolower(trim($rule['keyword']));
            $matched = false;

            switch ($rule['match_type']) {
                case 'any':
                    $matched = true;
                    break;
                case 'exact':
                    $matched = $message_lc === $keyword;
                    break;
                case 'starts_with':
                    $matched = $keyword !== '' && strpos($message_lc, $keyword) === 0;
                    break;
                case 'contains':
                default:
                    $matched = $keyword !== '' && strpos($message_lc, $keyword) !== false;
            }

            if ($matched) {
                return $rule['reply'];
            }
        }

        $default = get_option('wasms_default_reply');

        return $default !== '' ? $default : null;
    }

    /**
     * Handle an incoming message: log it, match rules, send the reply.
     *
     * @return string|null the reply text that was sent (or null)
     */
    public function process_incoming($channel, $phone, $message)
    {
        $this->log_message([
            'channel'   => $channel,
            'direction' => 'in',
            'phone'     => $phone,
            'message'   => $message,
            'status'    => 'received',
        ]);

        $reply = $this->match_auto_reply($channel, $message);

        if ($reply !== null && trim($phone) !== '') {
            $this->send($channel, $phone, $reply, 'out');
        }

        return $reply;
    }
}
