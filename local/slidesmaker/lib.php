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
 * Add a link to the Course Administration menu => "Slides Maker"
 *
 * @package    local_slidesmaker
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 */
function local_slidesmaker_extend_navigation_course($navigation, $course, $context) {
    // Only show if user has the capability in this course context
    if (has_capability('local/slidesmaker:generate', $context)) {
        $url = new moodle_url('/local/slidesmaker/index.php', ['id' => $course->id]);

        $navigation->add(
            get_string('pluginname', 'local_slidesmaker'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'slidesmaker',
            new pix_icon('i/presentation', '')
        );
    }
}
