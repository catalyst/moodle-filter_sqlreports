<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace filter_sqlreports;

use core\output\html_writer;

/**
 * Replaces `[[sqlreport:ID]]` markers with a live report_sql report (table or chart).
 *
 * The filtered-text cache is keyed on text + context, NOT user, but these reports are per-viewer
 * scoped and audience-gated. So the filter never server-renders rows: it emits only an inert
 * placeholder (safe to cache and share), and the report is hydrated per request, per user, by the
 * `report_sql_get_embed` web service — which re-runs the access gate and row scoping. See README.md
 * for the full rationale.
 *
 * Syntax: `[[sqlreport:5]]`, `[[sqlreport:5 chart]]`, `[[sqlreport:5 table]]`. The id is the
 * Report Builder report id (as in the /reportbuilder/view.php?id= URL); access is re-checked
 * downstream regardless of who authored the text.
 *
 * An optional `rb` flag (`[[sqlreport:5 rb]]`, `[[sqlreport:5 table rb]]`) renders a table embed
 * through Report Builder's own interactive output — sortable, self-paging — instead of the default
 * flat static table. It has no effect on a chart embed.
 *
 * An optional `hide=` clause drops columns by header label from the rendered table, e.g.
 * `[[sqlreport:5 table hide=Email,ID number]]`. The clause comes last and takes a comma-separated
 * list of header labels (case-insensitive, whitespace trimmed). It is a purely cosmetic client-side
 * strip applied after the web service returns HTML; it never changes the query or the access gate,
 * so it can only remove columns the viewer was already allowed to see. It has no effect on charts.
 *
 * @package   filter_sqlreports
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {
    /**
     * Replace every marker with a placeholder div. Kept brutally cheap: it runs over all filtered
     * text, so a fast substring bail precedes the regex.
     *
     * @param string $text The text to filter.
     * @param array $options Filter options (unused).
     * @return string
     */
    #[\Override]
    public function filter($text, array $options = []) {
        if (!is_string($text) || strpos($text, '[[sqlreport:') === false) {
            // Fast bail: no marker, no regex.
            return $text;
        }

        // Load the hydrator once per page. Requesting it here (rather than lazily in JS) keeps the
        // placeholder markup free of inline script and works wherever the filter runs.
        global $PAGE;
        $pagecourseid = 0;
        if ($PAGE instanceof \moodle_page) {
            $PAGE->requires->js_call_amd('filter_sqlreports/embed', 'init');

            // Host page course id, mirroring block_sqlreports: on a course page it scopes rows to
            // that course (for a report with a pagecoursecolumn). SITEID is the Dashboard/site front
            // page — not a real course scope — so pass 0 there. The value only narrows the WS query
            // (WHERE ... AND); it can never widen it, so emitting it to the client is safe.
            if (!empty($PAGE->course->id)) {
                $pagecourseid = (int) $PAGE->course->id;
                if ($pagecourseid === (int) SITEID) {
                    $pagecourseid = 0;
                }
            }
        }

        return preg_replace_callback(
            '/\[\[sqlreport:(\d+)(?:\s+(chart|table|auto))?(?:\s+(rb))?(?:\s+hide=([^\]]+))?\]\]/',
            static function (array $m) use ($pagecourseid): string {
                $reportid = (int) $m[1];
                $mode     = !empty($m[2]) ? $m[2] : 'auto';
                // Optional "rb" flag: render the table through Report Builder's interactive output.
                $render   = !empty($m[3]) ? 'rb' : '';

                // Normalise the hide= list: split on comma, trim each label, drop empties, and
                // re-join. The value is header labels (which may contain spaces), so only commas
                // delimit. Emitted as a data-attribute for the JS hydrator to strip columns by
                // label; html_writer escapes it. Empty/absent clause yields no attribute.
                $hidecols = '';
                if (!empty($m[4])) {
                    $labels = array_filter(array_map('trim', explode(',', $m[4])), 'strlen');
                    $hidecols = implode(',', $labels);
                }

                $attrs = [
                    'data-reportid'     => $reportid,
                    'data-mode'         => $mode,
                    'data-pagecourseid' => $pagecourseid,
                ];
                if ($render !== '') {
                    $attrs['data-render'] = $render;
                }
                if ($hidecols !== '') {
                    $attrs['data-hidecols'] = $hidecols;
                }

                return html_writer::div(
                    get_string('loading', 'filter_sqlreports'),
                    'sqlreport-embed',
                    $attrs
                );
            },
            $text
        );
    }
}
