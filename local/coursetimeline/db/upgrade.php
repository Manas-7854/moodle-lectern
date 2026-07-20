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
 * Upgrade steps for local_coursetimeline.
 *
 * @package    local_coursetimeline
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_local_coursetimeline_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024012502) {
        // Define table local_coursetimeline to be created.
        $table = new xmldb_table('local_coursetimeline');

        // Adding fields to table local_coursetimeline.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeline_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('resources_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_coursetimeline.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid_idx', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        // Conditionally launch create table for local_coursetimeline.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Local coursetimeline savepoint reached.
        upgrade_plugin_savepoint(true, 2024012502, 'local', 'coursetimeline');
    }

    return true;
}
