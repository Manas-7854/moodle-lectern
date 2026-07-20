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

namespace local_coursetimeline;

/**
 * Unit tests for the save_timeline / get_timeline round-trip.
 *
 * @package    local_coursetimeline
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursetimeline\external
 */
final class external_test extends \advanced_testcase {
    public function test_get_timeline_with_no_saved_data(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = external::get_timeline($course->id);

        $this->assertFalse($result['has_data']);
        $this->assertSame('', $result['timeline_json']);
        $this->assertSame('', $result['resources_json']);
    }

    public function test_save_and_get_timeline_roundtrip(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $timeline = json_encode(['course_title' => 'Test Course', 'timeline' => [
            ['week' => 1, 'title' => 'Introduction'],
        ]]);
        $resources = json_encode([['title' => 'Resource 1', 'url' => 'https://example.org']]);

        $saveresult = external::save_timeline($course->id, $timeline, $resources);
        $this->assertSame('success', $saveresult['status']);

        $fetched = external::get_timeline($course->id);
        $this->assertTrue($fetched['has_data']);
        $this->assertSame($timeline, $fetched['timeline_json']);
        $this->assertSame($resources, $fetched['resources_json']);
    }

    public function test_save_timeline_updates_existing_record_instead_of_duplicating(): void {
        $this->resetAfterTest(true);
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        external::save_timeline($course->id, json_encode(['v' => 1]), json_encode([]));
        external::save_timeline($course->id, json_encode(['v' => 2]), json_encode([]));

        $this->assertSame(1, $DB->count_records('local_coursetimeline', ['courseid' => $course->id]));

        $fetched = external::get_timeline($course->id);
        $this->assertSame(json_encode(['v' => 2]), $fetched['timeline_json']);
    }

    public function test_save_timeline_is_scoped_per_course(): void {
        $this->resetAfterTest(true);

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course1, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $course2->id, 'editingteacher');
        $this->setUser($teacher);

        external::save_timeline($course1->id, json_encode(['course' => 1]), json_encode([]));

        $fetchedcourse2 = external::get_timeline($course2->id);
        $this->assertFalse($fetchedcourse2['has_data']);
    }
}
