<?php
defined('MOODLE_INTERNAL') || die();

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

    if ($oldversion < 2024012507) {
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
        upgrade_plugin_savepoint(true, 2024012507, 'local', 'coursetimeline');
    }

    return true;
}
