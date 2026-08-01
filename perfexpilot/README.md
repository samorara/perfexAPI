# PerfexPilot — Scan invoices & receipts, auto-fill Perfex

An installable Perfex CRM module that reads a PDF or a photo of an invoice or receipt and
writes the fields straight into the Perfex form you already have open — Invoices, Expenses
and the AJAX **Record Payment** drawer — without a page reload.

Works with Perfex CRM 2.3+ and 3.x (CodeIgniter based).

## Installation

1. In your Perfex admin area go to **Setup → Modules**.
2. Upload `perfexpilot.zip` (or copy the `perfexpilot` folder into `<perfex-root>/modules/`).
3. Click **Activate**. Activation creates the `tblperfexpilot_scans` table and the
   private `uploads/perfexpilot/` folder.
4. Open **PerfexPilot** in the admin sidebar, paste your OpenAI API key, save, then click
   **Test connection**.

The key is stored in Perfex options and only ever used server side — it is never sent to
the browser.

## Using it

1. Open **Invoices → Create**, **Expenses → Create**, or open an invoice and click
   **Record Payment**.
2. Click **PerfexPilot — Scan & Fill**.
3. Drag & drop a file, pick one, or tap **Take photo** on a phone.
4. Review the extracted values: untick anything you don't want, edit values inline, pick a
   customer from the match list, choose how tax should be handled, and deselect line items
   you don't need.
5. Click **Apply to form**. The values land in the form; nothing is saved until *you* press
   Save.

### What gets filled

| Form | Fields |
|---|---|
| Invoice | Customer, number, date, due date, currency, reference #, notes, terms, and line items (description, qty, rate, tax) |
| Expense | Vendor → Customer, expense name, category, date, amount, currency, payment mode, reference #, note, Tax 1 and Tax 2 |
| Record Payment | Amount, date, payment mode, transaction ID, note |

Document totals (subtotal, tax total, discount, total) are shown for cross-checking on
invoices but are never written in, because Perfex derives invoice totals from the line
items themselves.

## Features

- **Images and PDFs** — JPG, PNG, WEBP, GIF, HEIC, TIFF and PDF. Files above 8 MB are
  streamed through the OpenAI Files API, so large multi-page PDFs work.
- **Smart customer match** — searches Perfex's own customer relation endpoint, scores the
  candidates, selects a clear winner outright and offers a shortlist otherwise.
- **Tax resolver** — works out the implied rate from subtotal and tax total (or from the
  line items), reuses a matching Perfex tax, or creates one, then applies it per line or
  leaves it off.
- **Confidence meter** — an overall score plus per-field scores, with a warning banner
  below your configured threshold.
- **Mobile ready** — camera capture, single-column fields and large tap targets.
- **Multi-language OCR** — hint the expected document languages, e.g. `en,de,it,fr,es`.
- **Audit trail** — every scan is recorded with the original document, viewable from the
  Scan History tab and auto-purged after your retention window.

## Settings

| Setting | Default | Notes |
|---|---|---|
| OpenAI API Key | *(empty)* | Leave blank when saving to keep the existing key |
| Model | `gpt-5` | Any vision-capable OpenAI model |
| Document Languages | `en` | Comma separated hint, e.g. `en,de,it` |
| Maximum File Size | 20 MB | Also bounded by your PHP `upload_max_filesize` |
| Low Confidence Warning Below | 70% | Shows the review warning banner |
| Request Timeout | 120s | Raise it for large PDFs |
| Keep Scanned Documents For | 90 days | `0` keeps them forever; purged on the Perfex cron |
| Create a tax automatically | on | Only administrators can create taxes |
| Show Scan & Fill On | all three | Invoices / Expenses / Record Payment |

## Permissions

The module registers standard Perfex staff capabilities under **PerfexPilot**:

- **View** — see the admin page, Scan History and stored documents
- **Create** — use Scan & Fill (the button is hidden without it)
- **Delete** — remove scan records and their documents

Settings are administrator-only.

## Requirements

- PHP 7.2+ with cURL, and an OpenAI API key with access to the configured model.
- **HEIC and TIFF only:** the PHP Imagick extension, used to transcode them to JPEG since
  the vision endpoint does not accept those formats. Without Imagick those two formats are
  rejected with a clear message; every other format works as normal.
- Outbound HTTPS to `api.openai.com`.
- The Perfex cron, if you want the retention purge to run.

## Storage and privacy

Scanned documents are written to `uploads/perfexpilot/`, which ships with an `.htaccess`
deny rule; they are only ever served through `admin/perfexpilot/file/{id}`, which checks
the viewer's permission. Documents and extracted JSON are removed by the retention purge,
by deleting the row in Scan History, or when the module is uninstalled.

## Hooks

For customisation without touching the module:

| Hook | Type | Purpose |
|---|---|---|
| `perfexpilot_supported_contexts` | filter | Map admin URL segments to scan contexts |
| `perfexpilot_extraction_prompt` | filter | Adjust the system or per-context prompt |
| `perfexpilot_extraction_payload` | filter | Change the OpenAI request before it is sent |
| `perfexpilot_extraction_result` | filter | Post-process the extracted fields |
| `perfexpilot_after_scan` | action | React to a completed scan |

## Troubleshooting

- **The button doesn't appear** — check the form is enabled in settings, and that your
  staff role has the PerfexPilot *Create* capability.
- **"No OpenAI API key is configured"** — add the key in settings and test the connection.
- **A field wasn't filled** — the module skips fields the theme doesn't expose rather than
  breaking the form; fill that one by hand and the rest still applies.
- **HEIC upload rejected** — install `php-imagick`, or take the photo as JPEG.
- **Large PDF times out** — raise the Request Timeout setting.
