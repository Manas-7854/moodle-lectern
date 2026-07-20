# Lecture Audio (mod_lectureaudio)

A Moodle activity module that lets a teacher record a lecture directly in
the browser, get a live AI transcript as they speak, and generate an AI
summary of the lecture that is saved with the activity.

## Features

- In-browser recording with a live waveform visualizer and timer.
- Audio is chunked and streamed to an AI backend for live transcription; the
  transcript is editable before saving.
- One-click AI-generated summary (rendered from Markdown) of the transcript.
- Transcript and summary are saved as activity files and shown to students
  who view the activity.

## Installation

1. Copy this plugin to `mod/lectureaudio` in your Moodle codebase.
2. Visit *Site administration &gt; Notifications* to complete the install.
3. Go to *Site administration &gt; Plugins &gt; Activity modules &gt;
   Lecture Audio* and set the **AI backend URL** to point at your running
   AI backend service (default `http://localhost:8000`).
4. Add a "Lecture Audio" activity to any course; teachers/managers
   (users with `moodle/course:manageactivities`) can record, students can
   view the resulting transcript/summary.

## Dependencies

- Moodle 4.5 (2024100700) or later.
- A separately-hosted AI backend service exposing `/transcribe_chunk` and
  `/summarize` endpoints, configured via the plugin's admin setting. This
  backend is not included with the plugin.
- Browser support for `MediaRecorder` and `getUserMedia` (microphone access).

## Note on raw audio

This module intentionally does not persist the raw recorded audio: the
recorder always uploads with an empty `filecontent`, only the transcript and
AI summary are saved. Only those two artifacts are shown back to students.
