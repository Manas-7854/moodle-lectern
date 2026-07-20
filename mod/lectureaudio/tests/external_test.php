<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_lectureaudio;

/**
 * Unit tests for the upload_recording external function.
 *
 * @package    mod_lectureaudio
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_lectureaudio\external
 */
final class external_test extends \advanced_testcase {
    public function test_upload_recording_parameters_contextid_is_required(): void {
        $params = external::upload_recording_parameters();
        $keys = $params->keys;

        $this->assertArrayHasKey('contextid', $keys);
        $this->assertSame(VALUE_REQUIRED, $keys['contextid']->required);
    }

    public function test_upload_recording_parameters_optional_fields_default_empty(): void {
        $params = external::upload_recording_parameters();
        $keys = $params->keys;

        $this->assertSame('', $keys['filecontent']->default);
        $this->assertSame('', $keys['transcript']->default);
        $this->assertSame('', $keys['summary']->default);
    }

    public function test_upload_recording_missing_contextid_throws_invalid_parameter_exception(): void {
        $this->expectException(\invalid_parameter_exception::class);
        \external_api::validate_parameters(external::upload_recording_parameters(), []);
    }

    public function test_upload_recording_requires_manageactivities_capability(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $lectureaudio = $this->getDataGenerator()->create_module('lectureaudio', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('lectureaudio', $lectureaudio->id, $course->id);
        $context = \context_module::instance($cm->id);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        external::upload_recording($context->id, '', 'some transcript text', '');
    }

    public function test_upload_recording_stores_transcript_and_summary_but_never_audio(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $lectureaudio = $this->getDataGenerator()->create_module('lectureaudio', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('lectureaudio', $lectureaudio->id, $course->id);
        $context = \context_module::instance($cm->id);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        // The real recorder.js always uploads with filecontent left empty; no raw audio is ever persisted.
        $result = external::upload_recording($context->id, '', 'hello transcript', '# Summary');
        $this->assertTrue($result['status']);

        $fs = get_file_storage();
        $files = array_values($fs->get_area_files($context->id, 'mod_lectureaudio', 'content', 0, 'timecreated DESC', false));
        $extensions = array_map(fn($file) => pathinfo($file->get_filename(), PATHINFO_EXTENSION), $files);

        $this->assertContains('txt', $extensions);
        $this->assertContains('md', $extensions);
        $this->assertNotContains('wav', $extensions);
    }
}
