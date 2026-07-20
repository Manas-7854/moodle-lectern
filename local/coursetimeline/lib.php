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

defined('MOODLE_INTERNAL') || die();

/**
 * Add a link to the Course Administration menu.
 *
 * @package    local_coursetimeline
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 */
function local_coursetimeline_extend_navigation_course($navigation, $course, $context) {
    // Show this link if the user has permission to view the timeline
    if (has_capability('local/coursetimeline:view', $context)) {
        // Using 'id' to match the index.php parameter
        $url = new moodle_url('/local/coursetimeline/index.php', ['id' => $course->id]);

        // Add the link to the 'Course administration' section (or secondary menu in Boost theme)
        $navigation->add(
            get_string('pluginname', 'local_coursetimeline'), // Label (AI Course Timeline Generator)
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'coursetimeline',
            new pix_icon('i/settings', '') // Standard settings icon
        );
    }
}
