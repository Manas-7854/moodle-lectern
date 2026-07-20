<?php
namespace local_coursetimeline;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_description;

class external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function save_timeline_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'The course ID'),
                'timeline_json' => new external_value(PARAM_RAW, 'The timeline JSON string'),
                'resources_json' => new external_value(PARAM_RAW, 'The resources JSON string')
            )
        );
    }

    /**
     * Saves the timeline and resources for a course.
     *
     * @param int $courseid
     * @param string $timeline_json
     * @param string $resources_json
     * @return array
     */
    public static function save_timeline($courseid, $timeline_json, $resources_json) {
        global $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::save_timeline_parameters(), array(
            'courseid' => $courseid,
            'timeline_json' => $timeline_json,
            'resources_json' => $resources_json
        ));

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

        return array(
            'status' => 'success',
            'message' => 'Timeline saved successfully.'
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function save_timeline_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_TEXT, 'Operation status'),
                'message' => new external_value(PARAM_TEXT, 'Return message')
            )
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_timeline_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'The course ID')
            )
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
        $params = self::validate_parameters(self::get_timeline_parameters(), array(
            'courseid' => $courseid
        ));

        // Security checks.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/coursetimeline:view', $context);

        $existing = $DB->get_record('local_coursetimeline', ['courseid' => $params['courseid']]);

        if ($existing) {
            return array(
                'timeline_json' => $existing->timeline_json,
                'resources_json' => $existing->resources_json,
                'has_data' => true
            );
        } else {
            return array(
                'timeline_json' => '',
                'resources_json' => '',
                'has_data' => false
            );
        }
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_timeline_returns() {
        return new external_single_structure(
            array(
                'timeline_json' => new external_value(PARAM_RAW, 'The timeline JSON string'),
                'resources_json' => new external_value(PARAM_RAW, 'The resources JSON string'),
                'has_data' => new external_value(PARAM_BOOL, 'Whether data was found')
            )
        );
    }
}
