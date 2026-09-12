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

## Why the report is loaded per request (the one hard constraint)

Moodle's `filter_manager` **caches filtered HTML keyed on text + context, not user.** These reports
are per-viewer scoped (`useridcolumn` / `coursecolumn`) and audience-gated. If the filter
server-rendered rows, the first viewer's rows would be cached and served to everyone → data leak.

So the filter emits **only an inert placeholder**
(`<div class="sqlreport-embed" data-reportid=… data-mode=…>`). The cached HTML is that placeholder,
which is safe to share. The real content is fetched **live, per request, per user** by
`amd/src/embed.js` calling the `report_sql_get_embed` web service, which re-runs:

- `query::current_user_can_view_report()` — the same access gate as `/reportbuilder/view.php`;
- `query::fetch_rows_for_viewer()` — the per-viewer / teacher-course / page-course row scoping.

Embedding a report you cannot see shows **you** nothing. A wrong report id and a report you have no
access to look exactly the same — both show the same empty result. So a visitor can never tell
"this report id does not exist" apart from "this report id exists but I am not allowed to see it",
which stops other users from snooping which hidden reports exist.

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

## Get the marker from the block (if installed)

If the companion `block_sqlreports` block is installed, you do not have to look up the Report
Builder report id by hand. When you **edit** a page that has a SQL report block for the report you
want, the block's footer shows a small **copy-embed-marker** button (`[[sqlreport:ID]]`) wired to
core's click-to-copy. One click copies the finished marker — correct id and all — and you paste it
straight into any filter-enabled text area. It is an editing-only helper (hidden from ordinary
viewers, since it is raw markup), and it only appears for a block bound to a *published* report —
both because the marker needs a bound Report Builder report id, and because only published reports
can be embedded anyway.

## License

GNU GPL v3 or later — see [COPYING](https://www.gnu.org/licenses/gpl-3.0.html).
