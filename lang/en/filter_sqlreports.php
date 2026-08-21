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

/**
 * Strings for filter_sqlreports.
 *
 * @package   filter_sqlreports
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['filtername'] = 'SQL report embed';
$string['loading'] = 'Loading report…';
$string['noaccess'] = 'You do not have permission to view this report.';
$string['notfound'] = 'Report not found.';
$string['privacy:metadata'] = 'The SQL report embed filter does not store any personal data. It renders reports owned by report_sql, which are subject to that plugin\'s access controls.';
$string['syntax'] = 'Insert <code>[[sqlreport:ID]]</code> into any text to embed a published SQL report, where ID is the report id from its /reportbuilder/view.php?id= URL. Add <code>chart</code> or <code>table</code> to force a display mode, e.g. <code>[[sqlreport:5 chart]]</code>.';
