# filter_sqlreports — user guide

The **SQL reports** filter turns a short marker in any filtered text into a live
`report_sql` report — a table or a chart — rendered inline where the marker sits.
Use it to drop a report into a page, label, book, course summary, forum post, or any
content that Moodle passes through filters.

## Requirements

- The **SQL reports** filter must be enabled (Site administration → Plugins → Filters →
  Manage filters), and enabled for the context you are editing in.
- The `report_sql` plugin must be installed (this filter depends on it).
- The report you reference must already exist and be **published** in Report Builder.

## Syntax

Place a marker in your text:

```
[[sqlreport:ID]]
[[sqlreport:ID table]]
[[sqlreport:ID chart]]
[[sqlreport:ID auto]]
```

- **`ID`** is the **Report Builder report id** — the number in the report's view URL,
  `/reportbuilder/view.php?id=ID`. It is *not* the internal query id.
- The optional second word is the **display mode**:
  - `auto` *(default)* — render the chart if the report has one configured, otherwise a table.
  - `table` — always render a table.
  - `chart` — render the report's configured chart (falls back to a table if the report has
    no chart set up).

Examples:

```
Here are your current enrolments: [[sqlreport:42]]
Completion by week: [[sqlreport:42 chart]]
[[sqlreport:42 table]]
```

You can place more than one marker in the same content; each renders independently.

## What each viewer sees

The marker itself carries **no data** — the report is fetched fresh for each viewer when the
page loads. That means:

- **Access is re-checked per viewer.** A viewer only sees the report if they pass the
  report's own Report Builder access (context + audience). If they have no access, the
  marker renders **nothing** — no error, no "you can't see this" message.
- **Rows are scoped per viewer.** If the report filters rows per user (or per teacher's
  courses), each viewer sees only their own rows from the same marker.
- **Course pages scope to that course.** On a course page, a report that has a page-course
  column is automatically limited to that course's rows. On the Dashboard or site front
  page there is no course scope, so the viewer sees everything their other filters allow.

Because access is resolved per viewer, it is safe to put a marker in shared content — the
author's own access never leaks into what other people see.

## Column display

- **Redundant key columns are hidden automatically.** When a report uses a link that is keyed
  on another column (for example a *name* column linked using a raw `id`/`userid` column),
  that raw key column is duplicated inside the link, so it is **omitted from the table** to
  keep the output clean. The link itself still works — the value is still read from the row.
- **A clickable id is kept.** If an id column is *itself* a link (not merely a key for another
  column), it stays visible.
- **Hide columns per marker.** Add `hide=` followed by a comma-separated list of column header
  labels to drop those columns from the table, e.g. `[[sqlreport:5 table hide=Email,ID number]]`.
  Labels are matched case-insensitively and whitespace is trimmed. This is cosmetic only — the
  rows still come from the access-gated web service, so hiding a column never exposes anything and
  can only remove columns the viewer was already allowed to see. It has no effect on charts.
- Dates from `%%TIMESTAMP%%` columns, text-case transforms from `%%CASE%%`, and `%%LINK%%`
  links all render exactly as they do in the full Report Builder report.
- Links open in a new tab.

## Limitations

- **JavaScript is required.** The filter emits a placeholder that is filled in by a small
  script after the page loads. With JavaScript disabled the placeholder shows
  "Loading report…" and never resolves. (This design is deliberate: filtered text is cached
  per context, not per user, so the per-viewer rows must be fetched live rather than baked
  into cacheable text.)
- **Row cap.** An inline embed shows at most **200 rows** — it is a summary, not the full
  report. Link to the full report in Report Builder when a viewer needs everything.
- **No column sorting or paging.** The inline table is static. Use the full Report Builder
  report for interactive sorting, filtering, and paging.
- **Charts are static images.** Charts render as a server-side SVG image (no interactive
  charting library). The filter has no in-page "expand" control — that is a block-only
  feature.
- **Unknown or unpublished report id → nothing.** A wrong id, an unpublished report, or a
  report the viewer cannot access all render the same empty result, on purpose (no way to
  probe which reports exist).
- **The marker must be in filtered text.** If a field is not passed through Moodle's filters,
  the marker appears as literal `[[sqlreport:ID]]` text. Most rich-text content is filtered;
  plain-text-only fields are not.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Marker shows as literal `[[sqlreport:5]]` text | Filter not enabled for this context, or the field is not filtered. |
| Stuck on "Loading report…" | JavaScript disabled or blocked. |
| Nothing appears where the marker was | Wrong/unpublished report id, or the viewer has no access to the report. |
| Fewer rows than expected | 200-row embed cap, per-viewer row scoping, or course-page scoping. |
| A column you expected is missing | It is a link key column and was auto-hidden (its value is inside the link). |
