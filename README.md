# perfexAPI

Installable **Perfex API** module for Perfex CRM (2.3+ / 3.x): a token-authenticated
JSON REST API over customers, contacts, leads, invoices, estimates, proposals,
payments, credit notes, projects, tasks, tickets, staff, expenses, contracts and
items, plus an admin panel for managing API keys.

Also includes **perfex-mcp**, an MCP connector that lets Claude (Claude Code /
Claude Desktop / any MCP client) read and write CRM data through that API, and
**WA SMS**, a WhatsApp & SMS communication module (personal-phone gateway or
official Business Cloud API, generic GET/POST SMS gateways, incoming webhooks
and keyword-based automatic replies).

- Perfex API module: [`perfex_api/`](perfex_api/) — zip: [`dist/perfex_api.zip`](dist/perfex_api.zip), docs: [`perfex_api/README.md`](perfex_api/README.md)
- WA SMS module: [`wasms/`](wasms/) — zip: [`dist/wasms.zip`](dist/wasms.zip), docs: [`wasms/README.md`](wasms/README.md)
- MCP connector: [`perfex-mcp/`](perfex-mcp/) — zip: [`dist/perfex-mcp.zip`](dist/perfex-mcp.zip), setup guide: [`perfex-mcp/README.md`](perfex-mcp/README.md)
- Install module zips via **Setup → Modules** in Perfex; rebuild them with `./build.sh`
