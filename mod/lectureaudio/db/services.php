<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_lectureaudio_upload_recording' => [
        'classname'   => 'mod_lectureaudio\external',
        'methodname'  => 'upload_recording',
        'description' => 'Uploads a lecture recording',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
