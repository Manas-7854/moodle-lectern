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
 * Library functions for mod_lectureaudio.
 *
 * @package    mod_lectureaudio
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Supports the module.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function lectureaudio_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Adds a new instance of lectureaudio
 *
 * @param stdClass $lectureaudio
 * @param mod_lectureaudio_mod_form $mform
 * @return int intance id
 */
function lectureaudio_add_instance($lectureaudio, $mform = null) {
    global $DB;

    $lectureaudio->timecreated = time();
    $lectureaudio->timemodified = time();

    $lectureaudio->id = $DB->insert_record('lectureaudio', $lectureaudio);

    return $lectureaudio->id;
}

/**
 * Updates an instance of the lectureaudio
 *
 * @param stdClass $lectureaudio
 * @param mod_lectureaudio_mod_form $mform
 * @return bool
 */
function lectureaudio_update_instance($lectureaudio, $mform = null) {
    global $DB;

    $lectureaudio->timemodified = time();
    $lectureaudio->id = $lectureaudio->instance;

    return $DB->update_record('lectureaudio', $lectureaudio);
}

/**
 * Deletes an instance of the lectureaudio
 *
 * @param int $id
 * @return bool
 */
function lectureaudio_delete_instance($id) {
    global $DB;

    if (!$lectureaudio = $DB->get_record('lectureaudio', ['id' => $id])) {
        return false;
    }

    // Delete any files associated with this module.
    $fs = get_file_storage();
    $fs->delete_area_files(context_module::instance($lectureaudio->coursemodule)->id);

    $DB->delete_records('lectureaudio', ['id' => $lectureaudio->id]);

    return true;
}

/**
 * Serves module files.
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function lectureaudio_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if ($filearea !== 'content') {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_lectureaudio/$filearea/$relativepath";

    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
