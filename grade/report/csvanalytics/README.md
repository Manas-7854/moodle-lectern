# CSV Grade Analytics (gradereport_csvanalytics)

A Moodle grade report plugin that lets a teacher upload a CSV export of grades
and get instant exploratory data analysis plus an interactive grade-cutoff
calculator (absolute, relative/std-dev, percentile, and IIIT-style presets).

## Features

- Upload a CSV of student grades (e.g. a gradebook export).
- Descriptive statistics per component and for the course total (mean,
  median, std dev, skewness, kurtosis, IQR, CV, outliers, percentiles).
- Histogram and component-comparison charts using Moodle's built-in Chart API.
- Interactive grade-cutoff sliders with live grade-distribution preview and
  boundary-student ("just missed the cutoff") reporting.
- CSV export of the resulting grades.

## Installation

1. Copy this plugin to `grade/report/csvanalytics` in your Moodle codebase.
2. Visit *Site administration &gt; Notifications* to complete the install.
3. Assign the `gradereport/csvanalytics:view` capability (granted by default
   to Teacher and Manager roles) to any additional roles that need it.
4. Open a course's gradebook and select this report from the report picker.

## Dependencies

- Moodle 4.0 (2022041900) or later.
- Chart.js and the chartjs-plugin-annotation library, loaded from a CDN
  (`cdn.jsdelivr.net`) for the client-side scatter plot. No server-side
  third-party dependencies.
