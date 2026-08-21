# filter_sqlreports

Embed a published [`report_sql`](../../report/sql) report inline in any Moodle text — a label, page,
book chapter, forum post, course summary — with a Configurable-Reports-style marker:

```
[[sqlreport:5]]           → the report (table, or chart if one is configured)
[[sqlreport:5 chart]]     → force the chart
[[sqlreport:5 table]]     → force the table
```

`5` is the **Report Builder report id** — the `id` in the report's
`/reportbuilder/view.php?id=5` URL, the same id `report_sql` binds each published query to.

This complements the other view paths: the direct RB view URL and the plugin's own `chart.php`.

## Why hydration is per-request (the one hard constraint)

Moodle's `filter_manager` **caches filtered HTML keyed on text + context, not user.** These reports
are per-viewer scoped (`useridcolumn` / `coursecolumn`) and audience-gated. If the filter
server-rendered rows, the first viewer's rows would be cached and served to everyone → data leak.

So the filter emits **only an inert placeholder**
(`<div class="sqlreport-embed" data-reportid=… data-mode=…>`). The cached HTML is that placeholder,
which is safe to share. The real content is fetched **live, per request, per user** by
`amd/src/embed.js` calling the `report_sql_get_embed` web service, which re-runs:

- `query::current_user_can_view_report()` — the same access gate as `/reportbuilder/view.php`;
- `query::fetch_rows_for_viewer()` — the per-viewer / teacher-course / page-course row scoping.

Embedding a report you cannot see shows **you** nothing. Unknown report id and no-access both return
empty HTML (no existence oracle).

## Architecture

```
[[sqlreport:ID]]  in any text
      │ classes/text_filter.php  (strpos bail → regex → placeholder div)
      ▼
<div class="sqlreport-embed" data-reportid=ID data-mode=auto>…loading…</div>
      │ amd/src/embed.js  (core/ajax, dedup via data-sqlreportDone)
      ▼
report_sql_get_embed(reportid, mode, pagecourseid)   ← in report_sql
      │ current_user_can_view_report()   ← access gate, per user
      │ fetch_rows_for_viewer()          ← row scoping, per user
      │ embed_renderer::render()         ← shared table / chart-SVG renderer
      ▼ {html}  →  innerHTML into placeholder (empty → div removed)
```

The web service and renderer (`report_sql\external\get_embed`, `report_sql\output\embed_renderer`)
live in `report_sql`; this filter is only the marker → placeholder front end.

## Install

Depends on `report_sql`. Drop this directory at `filter/sqlreports`, then
**Site admin → Notifications** to install, and enable the filter at
**Site admin → Plugins → Filters → Manage filters**.

## Build

The AMD source is `amd/src/embed.js`; the committed build is `amd/build/embed.min.js`. Rebuild from
the Moodle root with:

```
grunt amd --root=filter/sqlreports
```

## Security

- **No access bypass** — the embedder's edit rights are not the viewer's read rights; every hydrate
  re-checks access per user.
- **Cache-safe by construction** — only the inert placeholder is text-cached; data never is.
- **No new XSS surface** — the renderer reuses `format_text` for table cells and an `<img>`-wrapped
  SVG for charts; filter output is a bare `data-` div.
- External (non-Moodle iframe) embedding is out of scope.

## License

GNU GPL v3 or later — see [COPYING](https://www.gnu.org/licenses/gpl-3.0.html).
