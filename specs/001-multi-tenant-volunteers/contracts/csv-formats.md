# CSV File Contract

**Feature**: [spec.md](../spec.md) (FR-046 to FR-051) | **Research**: R10, R11

## Import file (upload)

| Property | Rule |
|---|---|
| Encoding | UTF-8. A leading byte-order mark is ignored. |
| Delimiter / quoting | Comma, with standard double-quote escaping (RFC 4180). |
| Size | At most 1 MB, and at most **1,000 data rows** (the header doesn't count). |
| Header row | Required. Must contain `name` and `email`, matched without regard to case and surrounding spaces. Other columns are ignored. Column order doesn't matter. |
| Blank lines | Ignored, and not counted as rows. |
| Role | Every imported person is invited as **Volunteer** (FR-046). |

### Whole-file rejection (nothing is invited)

| Condition | Message shown |
|---|---|
| Not a readable CSV, or not text | "The file could not be read. Upload a CSV file." |
| `name` or `email` column missing | "The file must have a header row with name and email columns." |
| More than 1,000 data rows | "The file has {n} rows. The limit is 1,000." |
| No data rows | "The file has no rows to import." |

### Row skip reasons (rest of file still processed)

Row numbers count the header as row 1, so the first data row is row 2, matching what
spreadsheet apps show.

| Condition | Reason text |
|---|---|
| `name` empty after trimming | "Missing name" |
| `email` not a valid address | "Invalid email" |
| Email repeats an earlier row (ignoring case) | "Duplicate of row {n}" |
| Email has a pending, active or inactive membership, or an open invitation, in this organization | "Already in roster" |

An email that belongs only to *another* organization is **not** a skip reason. That person is
invited normally, and the report doesn't reveal the other membership (FR-049).

### Report (shown after import)

The report shows two counts, "{x} invited" and "{y} skipped", then a table of skipped rows
with columns `Row` and `Reason`, plus the row's email when present. The report is shown once and
not stored.

### Sample file (`orgs.imports.sample`)

```csv
name,email
Ada Lovelace,ada@example.org
Grace Hopper,grace@example.org
```

## Export file (download)

| Property | Rule |
|---|---|
| Filename | `{organization-slug}-roster-{YYYY-MM-DD}.csv` |
| Encoding | UTF-8 with a byte-order mark, so Excel detects UTF-8. |
| Rows | Exactly the roster entries that match the current `q`, `role` and `status` filters, from the current organization only, in roster order. |
| Header | `Name,Email,Phone,Role,Status,Date joined` |
| Role values | `Administrator`, `Volunteer` |
| Status values | `Invited`, `Pending approval`, `Active`, `Inactive`, `Left` |
| Date joined | `YYYY-MM-DD`, or empty when there's no join date (invited or pending entries). |
| Formula safety | Any cell beginning with `=`, `+`, `-`, `@`, a tab or a carriage return is prefixed with `'` (R11). |
