<?php
defined('MOODLE_INTERNAL') || die();

function gradereport_csvanalytics_extend_navigation(global_navigation $navigation, stdClass $course, context_course $context) {
    if (has_capability('gradereport/csvanalytics:view', $context)) {
        // This function is required by the prompt, even if standard mechanism works.
        // It's used to manually extend navigation if needed. 
        // For grade reports, usually they are automatically added to the gradebook.
    }
}
