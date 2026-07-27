# WA SMS — WhatsApp & SMS Communication Module for Perfex CRM

Installable Perfex CRM module for two-way WhatsApp and SMS communication:

- **WhatsApp via your existing personal phone** — works with any HTTP gateway app
  running against your own number (configure a URL template, GET or POST).
- **WhatsApp Business Cloud API** — the official Meta Graph API (token + phone
  number ID).
- **SMS via any HTTP gateway** — same URL-template mechanism, GET or POST.
- **Automatic replies** — incoming messages hit the module's webhooks and are
  answered automatically based on your keyword rules and settings.
- **Message log** — every incoming and outgoing message is recorded with its
  gateway response.

## Installation

1. Perfex admin → **Setup → Modules** → upload `wasms.zip`.
2. Click **Activate** — this creates the `tblwasms_messages` and
   `tblwasms_auto_replies` tables and default settings.
3. Open the new **WA SMS** sidebar item.

## Sending (GET or POST)

Configure gateways under **WA SMS → Settings**. Personal-phone WhatsApp and SMS
both use a URL template with `{phone}` and `{message}` placeholders:

```
GET  http://192.168.1.50:8080/send?to={phone}&text={message}
POST https://sms-gateway.example.com/api/send        (phone/message in form or JSON body)
```

- **GET** — placeholders are URL-encoded into the query string.
- **POST** — placeholders in the URL are still replaced, and `phone` /
  `message` are additionally sent in the request body as form fields or JSON
  (your choice).

For the official WhatsApp Business Cloud API, switch **WhatsApp Mode** to
*Cloud API* and paste your permanent access token and phone number ID from the
Meta developer console.

Messages can be sent from the **Send Message** tab, and every send is logged.

## Receiving & automatic replies

Incoming messages arrive through two webhook styles (URLs are displayed on the
Settings tab, including your generated secret):

**WhatsApp Business Cloud API** — set this as the webhook in the Meta console
and use the displayed verify token:

```
https://your-crm.example.com/wasms/webhook/whatsapp
```

**Personal-phone gateway / SMS forwarder apps** — point the app at (GET or POST,
query string, form data or JSON body all work):

```
https://your-crm.example.com/wasms/webhook/incoming/sms?secret=SECRET&phone={phone}&message={message}
https://your-crm.example.com/wasms/webhook/incoming/whatsapp?secret=SECRET&phone={phone}&message={message}
```

On every incoming message the module:

1. Logs it in the Message Log.
2. Checks your **Auto Replies** rules top to bottom (keyword + match type:
   contains / exact / starts with / any message, per channel or both).
3. Sends the first matching reply back through the same channel — or the
   **Default Reply** if no rule matches (leave it empty to send nothing).

The webhook's JSON response also contains the matched reply text, so gateway
apps that can answer directly from an HTTP response may deliver it themselves.

## Notes

- Staff permissions: view / create (send + manage rules) / delete, plus
  admin-only settings.
- The personal-phone approach depends on a third-party gateway app and is not
  endorsed by WhatsApp — use the Cloud API mode for production-critical flows.
- Always use HTTPS for gateway URLs and keep the webhook secret private.
