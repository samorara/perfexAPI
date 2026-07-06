#!/usr/bin/env node
/**
 * perfex-mcp — MCP server (connector) for Perfex CRM.
 *
 * Talks to the "Perfex API" module (perfex_api) installed on a Perfex CRM
 * instance and exposes its REST resources as MCP tools.
 *
 * Configuration (environment variables):
 *   PERFEX_URL      Base URL of the Perfex CRM install, e.g. https://crm.example.com
 *   PERFEX_API_KEY  API token generated in Perfex admin (Perfex API sidebar item)
 */

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';

const PERFEX_URL = (process.env.PERFEX_URL || '').replace(/\/+$/, '');
const PERFEX_API_KEY = process.env.PERFEX_API_KEY || '';

if (!PERFEX_URL || !PERFEX_API_KEY) {
  console.error(
    'perfex-mcp: set PERFEX_URL (e.g. https://crm.example.com) and PERFEX_API_KEY environment variables.'
  );
  process.exit(1);
}

const WRITABLE_RESOURCES = [
  'customers',
  'contacts',
  'leads',
  'invoices',
  'estimates',
  'proposals',
  'payments',
  'credit_notes',
  'projects',
  'tasks',
  'tickets',
  'staff',
  'expenses',
  'contracts',
  'items',
];
const READONLY_RESOURCES = ['currencies', 'taxes', 'payment_modes'];
const ALL_RESOURCES = [...WRITABLE_RESOURCES, ...READONLY_RESOURCES];

const RESOURCE_NOTES = `Resource notes:
- contacts: creating one requires "userid" (the customer id) in data.
- payments: creating one requires "invoiceid" in data.
- invoices/estimates/proposals/credit_notes: pass line items as data.items = [{description, qty, rate, ...}].
- currencies, taxes, payment_modes are read-only.`;

async function perfexRequest(method, path, { query, body } = {}) {
  const url = new URL(`${PERFEX_URL}/perfex_api/api/v1/${path}`);
  for (const [key, value] of Object.entries(query || {})) {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  }

  const response = await fetch(url, {
    method,
    headers: {
      Authorization: `Bearer ${PERFEX_API_KEY}`,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  const text = await response.text();
  let payload;
  try {
    payload = JSON.parse(text);
  } catch {
    throw new Error(
      `Perfex returned a non-JSON response (HTTP ${response.status}). ` +
        'Check that the Perfex API module is installed and activated, and that PERFEX_URL is correct. ' +
        `Body starts with: ${text.slice(0, 200)}`
    );
  }

  if (!response.ok || payload.status === false) {
    throw new Error(payload.error || `Perfex API error (HTTP ${response.status})`);
  }

  return payload;
}

function toolResult(payload) {
  return { content: [{ type: 'text', text: JSON.stringify(payload, null, 2) }] };
}

function toolError(error) {
  return { isError: true, content: [{ type: 'text', text: `Error: ${error.message}` }] };
}

const server = new McpServer({ name: 'perfex-mcp', version: '1.0.0' });

server.registerTool(
  'perfex_list',
  {
    title: 'List Perfex CRM records',
    description:
      `List records of a Perfex CRM resource with pagination, free-text search, exact-match column filters and sorting. Returns {total, limit, offset, data}. ${RESOURCE_NOTES}`,
    inputSchema: {
      resource: z.enum(ALL_RESOURCES).describe('Which CRM resource to list'),
      search: z.string().optional().describe('Free-text search over the resource main text columns'),
      filters: z
        .record(z.union([z.string(), z.number()]))
        .optional()
        .describe('Exact-match column filters, e.g. {"status": 1, "clientid": 7}'),
      limit: z.number().int().min(1).max(200).optional().describe('Page size (default 50, max 200)'),
      offset: z.number().int().min(0).optional().describe('Row offset for pagination'),
      sort_by: z.string().optional().describe('Column to sort by'),
      sort_order: z.enum(['asc', 'desc']).optional(),
    },
  },
  async ({ resource, search, filters, limit, offset, sort_by, sort_order }) => {
    try {
      const payload = await perfexRequest('GET', resource, {
        query: { ...(filters || {}), search, limit, offset, sort_by, sort_order },
      });
      return toolResult(payload);
    } catch (error) {
      return toolError(error);
    }
  }
);

server.registerTool(
  'perfex_get',
  {
    title: 'Get a Perfex CRM record',
    description:
      'Fetch a single record by id. Customers include their contacts; invoices/estimates/proposals/credit_notes include line items; tickets include replies.',
    inputSchema: {
      resource: z.enum(ALL_RESOURCES).describe('Which CRM resource'),
      id: z.number().int().positive().describe('Record id'),
    },
  },
  async ({ resource, id }) => {
    try {
      return toolResult(await perfexRequest('GET', `${resource}/${id}`));
    } catch (error) {
      return toolError(error);
    }
  }
);

server.registerTool(
  'perfex_create',
  {
    title: 'Create a Perfex CRM record',
    description:
      `Create a record. Field names match the Perfex database columns / admin forms (e.g. customers: company, phonenumber; leads: name, email; tasks: name, startdate). Returns the created record. ${RESOURCE_NOTES}`,
    inputSchema: {
      resource: z.enum(WRITABLE_RESOURCES).describe('Which CRM resource to create'),
      data: z.record(z.any()).describe('Field values for the new record'),
    },
  },
  async ({ resource, data }) => {
    try {
      return toolResult(await perfexRequest('POST', resource, { body: data }));
    } catch (error) {
      return toolError(error);
    }
  }
);

server.registerTool(
  'perfex_update',
  {
    title: 'Update a Perfex CRM record',
    description:
      'Update an existing record by id. Only include the fields to change. Returns the updated record.',
    inputSchema: {
      resource: z.enum(WRITABLE_RESOURCES).describe('Which CRM resource to update'),
      id: z.number().int().positive().describe('Record id'),
      data: z.record(z.any()).describe('Field values to change'),
    },
  },
  async ({ resource, id, data }) => {
    try {
      return toolResult(await perfexRequest('PUT', `${resource}/${id}`, { body: data }));
    } catch (error) {
      return toolError(error);
    }
  }
);

server.registerTool(
  'perfex_delete',
  {
    title: 'Delete a Perfex CRM record',
    description:
      'Permanently delete a record by id. This cannot be undone — confirm with the user before deleting.',
    inputSchema: {
      resource: z.enum(WRITABLE_RESOURCES).describe('Which CRM resource to delete from'),
      id: z.number().int().positive().describe('Record id'),
    },
  },
  async ({ resource, id }) => {
    try {
      return toolResult(await perfexRequest('DELETE', `${resource}/${id}`));
    } catch (error) {
      return toolError(error);
    }
  }
);

const transport = new StdioServerTransport();
await server.connect(transport);
console.error(`perfex-mcp connected to ${PERFEX_URL}`);
