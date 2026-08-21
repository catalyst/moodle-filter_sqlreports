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

/**
 * Hydrates [[sqlreport:ID]] placeholders emitted by filter_sqlreports.
 *
 * The filter emits an inert `.sqlreport-embed` div (the filtered-text cache is per-context, not
 * per-user, so rows must never be cached there). This module fetches each report's rows live, per
 * request, via the report_sql_get_embed web service — which re-runs the access gate and per-viewer
 * row scoping — and drops the resulting HTML into the placeholder. No access (or unknown report)
 * returns empty HTML, and the placeholder is removed so nothing is shown.
 *
 * @module     filter_sqlreports/embed
 * @copyright  2026 Marcus Green
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Find every un-hydrated placeholder and request its report.
 */
export const init = () => {
    document.querySelectorAll('.sqlreport-embed[data-reportid]').forEach(async(el) => {
        // Dedup: filter() may load this module more than once per page.
        if (el.dataset.sqlreportDone) {
            return;
        }
        el.dataset.sqlreportDone = '1';

        const [request] = Ajax.call([{
            methodname: 'report_sql_get_embed',
            args: {
                reportid: parseInt(el.dataset.reportid, 10),
                mode: el.dataset.mode || 'auto',
                pagecourseid: parseInt(el.dataset.pagecourseid || '0', 10),
            },
        }]);

        try {
            const {html} = await request;
            if (html) {
                el.innerHTML = html;
            } else {
                // Unknown report or no access: leave nothing behind.
                el.remove();
            }
        } catch (e) {
            el.remove();
        }
    });
};
