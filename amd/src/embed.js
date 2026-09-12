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

        const render = el.dataset.render || '';

        const [request] = Ajax.call([{
            methodname: 'report_sql_get_embed',
            args: {
                reportid: parseInt(el.dataset.reportid, 10),
                mode: el.dataset.mode || 'auto',
                pagecourseid: parseInt(el.dataset.pagecourseid || '0', 10),
                render: render,
            },
        }]);

        try {
            const {html} = await request;
            if (html) {
                el.innerHTML = html;
                if (render === 'rb') {
                    // Report Builder interactive table: its own {{#js}} init is dropped when the
                    // markup is built inside the web service, so wire the report + dynamic-table
                    // behaviour here. Both use document-level event delegation, so a single init
                    // covers this and any later-injected embed.
                    activateReportBuilder();
                } else {
                    hideColumns(el, el.dataset.hidecols);
                }
            } else {
                // Unknown report or no access: leave nothing behind.
                el.remove();
            }
        } catch (e) {
            el.remove();
        }
    });
};

/** @var {boolean} Whether the Report Builder behaviour has already been initialised this page. */
let rbActivated = false;

/**
 * Load and initialise core's Report Builder + dynamic-table behaviour for injected RB embeds.
 *
 * Both modules attach document-level delegated listeners, so this runs at most once per page and
 * then covers every rb embed (including any hydrated after it). Loaded lazily so pages that use
 * only the default static embed never pull the Report Builder JS.
 */
const activateReportBuilder = () => {
    if (rbActivated) {
        return;
    }
    rbActivated = true;
    Promise.all([
        import('core_reportbuilder/report'),
        import('core_table/dynamic'),
    ]).then(([report, dynamic]) => {
        report.init();
        dynamic.init();
        return;
    }).catch(() => {
        // If the behaviour cannot load, the table still shows — just without sort/paging.
        rbActivated = false;
    });
};

/**
 * Remove columns from every rendered table by header label.
 *
 * Cosmetic only: the rows already arrived from the access-gated web service, so dropping a column
 * client-side never exposes anything. Matching is case-insensitive on trimmed header text. Labels
 * with no matching header are ignored; charts (no table) are left untouched.
 *
 * @param {HTMLElement} el The hydrated placeholder.
 * @param {string} [spec] Comma-separated header labels from data-hidecols.
 */
const hideColumns = (el, spec) => {
    if (!spec) {
        return;
    }
    const wanted = spec.split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);
    if (!wanted.length) {
        return;
    }

    el.querySelectorAll('table').forEach((table) => {
        const headers = table.querySelectorAll('thead th, thead td');
        // Collect the column indices whose header label is in the hide list.
        const drop = [];
        headers.forEach((th, i) => {
            if (wanted.includes(th.textContent.trim().toLowerCase())) {
                drop.push(i);
            }
        });
        if (!drop.length) {
            return;
        }
        // Remove from the bottom up so earlier indices stay valid as cells are deleted.
        drop.sort((a, b) => b - a);
        table.querySelectorAll('tr').forEach((row) => {
            const cells = row.children;
            drop.forEach((i) => {
                if (cells[i]) {
                    cells[i].remove();
                }
            });
        });
    });
};
