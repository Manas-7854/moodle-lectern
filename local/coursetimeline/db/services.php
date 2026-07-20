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
 * Web service definitions for local_coursetimeline.
 *
 * @package    local_coursetimeline
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coursetimeline_save_timeline' => [
        'classname'   => 'local_coursetimeline\external',
        'methodname'  => 'save_timeline',
        'description' => 'Saves the course timeline.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_coursetimeline_get_timeline' => [
        'classname'   => 'local_coursetimeline\external',
        'methodname'  => 'get_timeline',
        'description' => 'Retrieves the course timeline.',
        'type'        => 'read',
        'ajax'        => true,
    ],
];
