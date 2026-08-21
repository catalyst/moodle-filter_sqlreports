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
        if ($PAGE instanceof \moodle_page) {
            $PAGE->requires->js_call_amd('filter_sqlreports/embed', 'init');
        }

        return preg_replace_callback(
            '/\[\[sqlreport:(\d+)(?:\s+(chart|table|auto))?\]\]/',
            static function (array $m): string {
                $reportid = (int) $m[1];
                $mode     = $m[2] ?? 'auto';
                return html_writer::div(
                    get_string('loading', 'filter_sqlreports'),
                    'sqlreport-embed',
                    [
                        'data-reportid' => $reportid,
                        'data-mode'     => $mode,
                    ]
                );
            },
            $text
        );
    }
}
