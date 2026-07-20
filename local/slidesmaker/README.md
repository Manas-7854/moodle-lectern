# Slides Maker (local_slidesmaker)

A Moodle local plugin that turns a textbook PDF into a slide deck. Teachers
upload a PDF, the plugin asks an external AI backend to split it into
chapters, the teacher picks which chapters and a topic to focus on, and the
backend generates a downloadable presentation.

## Features

- Upload a textbook PDF and have it split into chapters via the AI backend.
- Select which chapters to include and specify a topic/focus for the slides.
- Download the generated presentation as a PDF.

## Installation

1. Copy this plugin to `local/slidesmaker` in your Moodle codebase.
2. Visit *Site administration &gt; Notifications* to complete the install.
3. Go to *Site administration &gt; Plugins &gt; Local plugins &gt; Slides
   Maker* and set the **AI backend URL** to point at your running AI backend
   service (default `http://127.0.0.1:8000`).
4. Grant the `local/slidesmaker:generate` capability to the roles that
   should be able to generate slides (teachers/managers by default).
5. Open a course and navigate to `/local/slidesmaker/index.php?id=<courseid>`
   (or the course administration link, if added) to use the tool.

## Dependencies

- Moodle 4.0 (2022041900) or later.
- A separately-hosted AI backend service exposing `/split_pdf_chapters` and
  `/generate_presentation` endpoints, configured via the plugin's admin
  setting. This backend is not included with the plugin.
