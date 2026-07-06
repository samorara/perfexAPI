# Perfex API — REST API Module for Perfex CRM

An installable Perfex CRM module that exposes a token-authenticated JSON REST API over the
core CRM entities, plus an admin panel for generating and managing API keys.

Works with Perfex CRM 2.3+ and 3.x (CodeIgniter based).

## Installation

1. In your Perfex admin area go to **Setup → Modules**.
2. Upload `perfex_api.zip` (or copy the `perfex_api` folder into `<perfex-root>/modules/`).
3. Click **Activate** on the *Perfex API* module. Activation creates the
   `tblperfex_api_keys` table automatically.
4. A **Perfex API** item appears in the admin sidebar — open it and click
   **Generate Key**. Copy the token immediately; it is stored hashed and cannot
   be shown again.

## Authentication

Send the token on every request using either header:

```
Authorization: Bearer pfx_xxxxxxxxxxxxxxxx
X-API-KEY: pfx_xxxxxxxxxxxxxxxx
```

Keys can be restricted per operation (create / update / delete — read is always allowed),
deactivated, given an expiry date, or deleted from the admin panel.

## Base URL

```
https://your-crm.example.com/perfex_api/api/v1
```

(If your install does not use `index.php` removal, include it: `/index.php/perfex_api/api/v1`.)

## Resources

| Resource | Endpoint | Write support |
|---|---|---|
| Customers | `/customers` | create, update, delete |
| Contacts | `/contacts` | create (requires `userid`), update, delete |
| Leads | `/leads` | create, update, delete |
| Invoices | `/invoices` | create, update, delete |
| Estimates | `/estimates` | create, update, delete |
| Proposals | `/proposals` | create, update, delete |
| Payments | `/payments` | create (requires `invoiceid`), update, delete |
| Credit notes | `/credit_notes` | create, update, delete |
| Projects | `/projects` | create, update, delete |
| Tasks | `/tasks` | create, update, delete |
| Tickets | `/tickets` | create, update, delete |
| Staff | `/staff` | create, update, delete |
| Expenses | `/expenses` | create, update, delete |
| Contracts | `/contracts` | create, update, delete |
| Items (products/services) | `/items` | create, update, delete |
| Currencies | `/currencies` | read-only |
| Taxes | `/taxes` | read-only |
| Payment modes | `/payment_modes` | read-only |

Writes are dispatched through Perfex's own models, so numbering, totals,
activity logs and module hooks fire exactly as they do from the UI.

## Endpoints

| Method | URL | Action |
|---|---|---|
| GET | `/customers` | List (paginated) |
| GET | `/customers/12` | Single record (with related sub-resources) |
| POST | `/customers` | Create |
| PUT / PATCH | `/customers/12` | Update |
| DELETE | `/customers/12` | Delete |

Hosts that block PUT/DELETE can send POST with an `X-HTTP-Method-Override: PUT` header.

### Listing, filtering, pagination

| Query param | Meaning |
|---|---|
| `limit` | Page size, default 50, max 200 |
| `offset` | Row offset |
| `search` | Free-text search over the resource's main text columns |
| `sort_by` / `sort_order` | Any real column, `asc`/`desc` |
| `<column>=<value>` | Exact-match filter on any real column, e.g. `/invoices?status=1&clientid=7` |

List responses include `total`, `limit` and `offset` for pagination.

### Examples

```bash
BASE=https://your-crm.example.com/perfex_api/api/v1
TOKEN=pfx_xxxxxxxxxxxx

# List customers matching "acme"
curl -H "Authorization: Bearer $TOKEN" "$BASE/customers?search=acme"

# Create a lead
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Jane Doe","email":"jane@example.com","company":"Acme"}' \
  "$BASE/leads"

# Create an invoice with line items
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{
        "clientid": 7,
        "currency": 1,
        "date": "2026-07-06",
        "duedate": "2026-08-05",
        "items": [
          {"description": "Consulting", "qty": 10, "rate": 120},
          {"description": "Hosting", "qty": 1, "rate": 25}
        ]
      }' \
  "$BASE/invoices"

# Update a task
curl -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Renamed task","priority":3}' "$BASE/tasks/42"

# Delete a contact
curl -X DELETE -H "Authorization: Bearer $TOKEN" "$BASE/contacts/9"
```

### Response format

```json
{ "status": true, "total": 128, "limit": 50, "offset": 0, "data": [ ... ] }
```

Errors return the appropriate HTTP status (400/401/403/404/405/422) with:

```json
{ "status": false, "error": "Human readable message" }
```

Sensitive columns (password hashes, reset keys, 2FA codes) are stripped from all responses.

## Security notes

- Tokens are 48-hex-char random strings stored as SHA-256 hashes — a database
  leak does not expose usable tokens.
- Always serve the API over HTTPS.
- Grant each integration its own key with the minimum permissions it needs.

## Building the ZIP

From the repository root:

```bash
./build.sh   # produces dist/perfex_api.zip
```

## License

MIT
