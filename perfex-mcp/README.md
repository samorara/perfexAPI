# perfex-mcp — Perfex CRM MCP Connector

An [MCP](https://modelcontextprotocol.io) server that connects Claude (Claude Code,
Claude Desktop, or any MCP client) to a Perfex CRM instance through the
[Perfex API module](../perfex_api/) in this repository.

## Prerequisites

1. **Node.js 18+** on the machine running the MCP client.
2. The **Perfex API module** installed and activated on your Perfex CRM
   (upload `dist/perfex_api.zip` via **Setup → Modules**).
3. An **API key** generated in the Perfex admin sidebar under **Perfex API**.

## Configuration

The server is configured entirely via environment variables:

| Variable | Meaning |
|---|---|
| `PERFEX_URL` | Base URL of the CRM, e.g. `https://crm.example.com` |
| `PERFEX_API_KEY` | Token generated in the Perfex admin panel (starts with `pfx_`) |

## Setup

Install dependencies once:

```bash
cd perfex-mcp && npm install
```

### Claude Code

```bash
claude mcp add perfex \
  --env PERFEX_URL=https://crm.example.com \
  --env PERFEX_API_KEY=pfx_xxxxxxxx \
  -- node /path/to/perfexAPI/perfex-mcp/index.js
```

Or add to your project's `.mcp.json`:

```json
{
  "mcpServers": {
    "perfex": {
      "command": "node",
      "args": ["/path/to/perfexAPI/perfex-mcp/index.js"],
      "env": {
        "PERFEX_URL": "https://crm.example.com",
        "PERFEX_API_KEY": "pfx_xxxxxxxx"
      }
    }
  }
}
```

### Claude Desktop

Add the same block to `claude_desktop_config.json`
(**Settings → Developer → Edit Config**), then restart Claude Desktop.

## Tools

| Tool | Description |
|---|---|
| `perfex_list` | List records of any resource with `search`, exact-match `filters`, `limit`/`offset` pagination and sorting |
| `perfex_get` | Fetch one record by id (customers include contacts; invoices/estimates include line items; tickets include replies) |
| `perfex_create` | Create a record (field names match Perfex columns/forms) |
| `perfex_update` | Update fields of an existing record |
| `perfex_delete` | Permanently delete a record |

Supported resources: `customers`, `contacts`, `leads`, `invoices`, `estimates`,
`proposals`, `payments`, `credit_notes`, `projects`, `tasks`, `tickets`, `staff`,
`expenses`, `contracts`, `items`, plus read-only `currencies`, `taxes`,
`payment_modes`.

Example prompts once connected:

- *"List my 10 most recent leads"*
- *"Create an invoice for customer 7 with 10 hours of consulting at $120"*
- *"Find customers matching 'acme' and show their contacts"*
- *"Mark task 42 as high priority"*

## Security

- The connector is only as privileged as its API key — generate a key with the
  minimum permissions the assistant needs (e.g. read-only: uncheck
  create/update/delete when generating it).
- Deletes are irreversible; the tool description instructs the model to confirm
  with you first, but a key without the delete permission is the hard guarantee.
- Always use HTTPS for `PERFEX_URL`.

## License

MIT
