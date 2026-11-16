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

/**
 * Tests for the [[sqlreport:ID]] embed filter.
 *
 * The filter must never render report rows (they are per-viewer and the filtered-text cache is not
 * user-keyed); it only rewrites markers into an inert placeholder. These tests pin that contract:
 * markers become placeholders carrying the right data-attributes, and non-marker text is returned
 * untouched by the fast bail.
 *
 * @package   filter_sqlreports
 * @covers    \filter_sqlreports\text_filter
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filter_test extends \advanced_testcase {
    /**
     * Build a filter instance bound to the system context.
     *
     * @return text_filter
     */
    protected function make_filter(): text_filter {
        return new text_filter(\context_system::instance(), []);
    }

    /**
     * Text with no marker is returned byte-for-byte (fast bail, no placeholder).
     */
    public function test_no_marker_passes_through(): void {
        $this->resetAfterTest();
        $filter = $this->make_filter();
        $text = '<p>Nothing to see: sqlreport without brackets.</p>';
        $this->assertSame($text, $filter->filter($text));
    }

    /**
     * A bare marker becomes a placeholder div carrying the report id and default auto mode. No rows,
     * no report content — only the inert placeholder is emitted.
     */
    public function test_marker_becomes_placeholder(): void {
        $this->resetAfterTest();
        $filter = $this->make_filter();
        $out = $filter->filter('before [[sqlreport:42]] after');

        $this->assertStringContainsString('sqlreport-embed', $out);
        $this->assertStringContainsString('data-reportid="42"', $out);
        $this->assertStringContainsString('data-mode="auto"', $out);
        // The marker itself is gone.
        $this->assertStringNotContainsString('[[sqlreport:42]]', $out);
        // Surrounding text is preserved.
        $this->assertStringContainsString('before', $out);
        $this->assertStringContainsString('after', $out);
    }

    /**
     * An explicit display mode is carried onto the placeholder.
     */
    public function test_marker_with_mode(): void {
        $this->resetAfterTest();
        $filter = $this->make_filter();

        $chart = $filter->filter('[[sqlreport:7 chart]]');
        $this->assertStringContainsString('data-reportid="7"', $chart);
        $this->assertStringContainsString('data-mode="chart"', $chart);

        $table = $filter->filter('[[sqlreport:7 table]]');
        $this->assertStringContainsString('data-mode="table"', $table);
    }

    /**
     * A hide= clause becomes a normalised data-hidecols attribute; labels are trimmed and the mode
     * is still carried. No hide= clause means no attribute.
     */
    public function test_marker_with_hide(): void {
        $this->resetAfterTest();
        $filter = $this->make_filter();

        $out = $filter->filter('[[sqlreport:5 table hide=Email, ID number ,Idnumber]]');
        $this->assertStringContainsString('data-mode="table"', $out);
        // Labels trimmed, order preserved, escaped by html_writer (& -> &amp; etc. not needed here).
        $this->assertStringContainsString('data-hidecols="Email,ID number,Idnumber"', $out);

        // Without the clause there is no attribute at all.
        $plain = $filter->filter('[[sqlreport:5 table]]');
        $this->assertStringNotContainsString('data-hidecols', $plain);
    }

    /**
     * hide= works without an explicit mode (defaults to auto).
     */
    public function test_marker_hide_without_mode(): void {
        $this->resetAfterTest();
        $filter = $this->make_filter();

        $out = $filter->filter('[[sqlreport:5 hide=Email]]');
        $this->assertStringContainsString('data-mode="auto"', $out);
        $this->assertStringContainsString('data-hidecols="Email"', $out);
    }

    /**
     * Several markers in one text each become their own placeholder.
     */
    public function test_multiple_markers(): void {
        $this->resetAfterTest();
        $filter = $this->make_filter();
        $out = $filter->filter('[[sqlreport:1]] and [[sqlreport:2 chart]]');

        $this->assertSame(2, substr_count($out, 'sqlreport-embed'));
        $this->assertStringContainsString('data-reportid="1"', $out);
        $this->assertStringContainsString('data-reportid="2"', $out);
    }

    /**
     * A malformed marker (non-numeric id) is not rewritten — it is left as literal text.
     */
    public function test_malformed_marker_ignored(): void {
        $this->resetAfterTest();
        $filter = $this->make_filter();
        $text = '[[sqlreport:abc]] [[sqlreport:]]';
        $out = $filter->filter($text);

        $this->assertStringNotContainsString('sqlreport-embed', $out);
        $this->assertStringContainsString('[[sqlreport:abc]]', $out);
    }

    /**
     * Non-string input is returned unchanged (defensive fast bail).
     */
    public function test_non_string_input(): void {
        $this->resetAfterTest();
        $filter = $this->make_filter();
        $this->assertSame('', $filter->filter(''));
    }

    /**
     * Off a real course (default $PAGE course is the site) the placeholder carries pagecourseid 0,
     * so the downstream page-course filter is skipped and the viewer's other scoping still applies.
     */
    public function test_pagecourseid_zero_off_course(): void {
        $this->resetAfterTest();
        $filter = $this->make_filter();
        $out = $filter->filter('[[sqlreport:42]]');
        $this->assertStringContainsString('data-pagecourseid="0"', $out);
    }

    /**
     * On a course page the placeholder carries that course id, so a report with a pagecoursecolumn
     * is scoped to the course when the JS hydrator forwards it to the web service.
     */
    public function test_pagecourseid_from_course_page(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_course($course);

        $filter = $this->make_filter();
        $out = $filter->filter('[[sqlreport:42]]');
        $this->assertStringContainsString('data-pagecourseid="' . $course->id . '"', $out);
    }
}
