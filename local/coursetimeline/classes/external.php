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

namespace local_coursetimeline;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_description;

/**
 * External functions for saving and retrieving a course's AI-generated timeline.
 *
 * @package    local_coursetimeline
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function save_timeline_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'The course ID'),
                'timeline_json' => new external_value(PARAM_RAW, 'The timeline JSON string'),
                'resources_json' => new external_value(PARAM_RAW, 'The resources JSON string'),
            ]
        );
    }

    /**
     * Saves the timeline and resources for a course.
     *
     * @param int $courseid
     * @param string $timelinejson
     * @param string $resourcesjson
     * @return array
     */
    public static function save_timeline($courseid, $timelinejson, $resourcesjson) {
        global $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::save_timeline_parameters(), [
            'courseid' => $courseid,
            'timeline_json' => $timelinejson,
            'resources_json' => $resourcesjson,
        ]);

        // Security checks.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/coursetimeline:view', $context); // Assuming view capability covers this for now, or use a new one.

        $record = new \stdClass();
        $record->courseid = $params['courseid'];
        $record->timeline_json = $params['timeline_json'];
        $record->resources_json = $params['resources_json'];
        $record->timecreated = time();
        $record->timemodified = time();

        // Check if a record already exists for this course to update it, or insert new?
        // For simplicity, let's append/insert for now or update if we only want one per course.
        // User asked to "save in the database", let's assume one per course for now or just log it.
        // Let's check if one exists.
        $existing = $DB->get_record('local_coursetimeline', ['courseid' => $params['courseid']]);

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_coursetimeline', $record);
        } else {
            $DB->insert_record('local_coursetimeline', $record);
        }

        return [
            'status' => 'success',
            'message' => 'Timeline saved successfully.',
        ];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function save_timeline_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_TEXT, 'Operation status'),
                'message' => new external_value(PARAM_TEXT, 'Return message'),
            ]
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_timeline_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'The course ID'),
            ]
        );
    }

    /**
     * Retrieves the timeline and resources for a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_timeline($courseid) {
        global $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::get_timeline_parameters(), [
            'courseid' => $courseid,
        ]);

        // Security checks.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/coursetimeline:view', $context);

        $existing = $DB->get_record('local_coursetimeline', ['courseid' => $params['courseid']]);

        if ($existing) {
            return [
                'timeline_json' => $existing->timeline_json,
                'resources_json' => $existing->resources_json,
                'has_data' => true,
            ];
        } else {
            return [
                'timeline_json' => '',
                'resources_json' => '',
                'has_data' => false,
            ];
        }
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_timeline_returns() {
        return new external_single_structure(
            [
                'timeline_json' => new external_value(PARAM_RAW, 'The timeline JSON string'),
                'resources_json' => new external_value(PARAM_RAW, 'The resources JSON string'),
                'has_data' => new external_value(PARAM_BOOL, 'Whether data was found'),
            ]
        );
    }
}
