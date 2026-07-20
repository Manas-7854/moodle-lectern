<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_coursetimeline_save_timeline' => array(
        'classname'   => 'local_coursetimeline\external',
        'methodname'  => 'save_timeline',
        'description' => 'Saves the course timeline.',
        'type'        => 'write',
        'ajax'        => true,
    ),
    'local_coursetimeline_get_timeline' => array(
        'classname'   => 'local_coursetimeline\external',
        'methodname'  => 'get_timeline',
        'description' => 'Retrieves the course timeline.',
        'type'        => 'read',
        'ajax'        => true,
    ),
);
