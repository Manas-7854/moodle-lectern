<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add a link to the Course Administration menu.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 */
function local_coursetimeline_extend_navigation_course($navigation, $course, $context) {
    // Show this link if the user has permission to view the timeline
    if (has_capability('local/coursetimeline:view', $context)) {
        
        // Using 'id' to match the index.php parameter
        $url = new moodle_url('/local/coursetimeline/index.php', array('id' => $course->id));
        
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
