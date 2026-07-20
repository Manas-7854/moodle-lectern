# AI Course Timeline Generator (local_coursetimeline)

A Moodle local plugin that helps teachers plan a course: it searches for
learning resources on a topic and uses an external AI backend to turn a
selection of those resources into a week-by-week course timeline, which is
then saved against the course.

## Features

- Search for open resources (textbooks, websites, etc.) for a given topic.
- Select which resources to include and generate an AI-produced weekly
  timeline (topics, learning objectives, activities, assessment per week).
- Save and reload the generated timeline and resource selection per course.
- Adds a link to the course administration / secondary navigation menu for
  users with the `local/coursetimeline:view` capability.

## Installation

1. Copy this plugin to `local/coursetimeline` in your Moodle codebase.
2. Visit *Site administration &gt; Notifications* to complete the install.
3. Go to *Site administration &gt; Plugins &gt; Local plugins &gt; AI Course
   Timeline Generator* and set the **AI backend URL** to point at your
   running AI backend service (default `http://localhost:8000`).
4. Open a course and select "AI Course Timeline Generator" from the course
   administration menu.

## Dependencies

- Moodle 4.1 (2022112800) or later.
- A separately-hosted AI backend service exposing `/fetch_resources` and
  `/generate_timeline` endpoints, configured via the plugin's admin setting.
  This backend is not included with the plugin.
