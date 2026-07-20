# Moodle Plugins Portfolio

A collection of four Moodle plugins that reduce the time teachers spend
preparing and administering courses: planning a timeline, building slides from
a textbook, capturing and summarising lectures, and analysing grade
distributions.

Three of them delegate their AI work to a self-hosted companion service,
**Moodle Lectern**. The fourth is standalone.

---

## The plugins

| Plugin | Type | Install path | What it does | Needs backend |
| --- | --- | --- | --- | --- |
| **AI Course Timeline Generator**<br>`local_coursetimeline` | Local | `local/coursetimeline` | Finds open teaching resources for a topic, then generates and saves a week-by-week course plan. | Yes |
| **Slides Maker**<br>`local_slidesmaker` | Local | `local/slidesmaker` | Splits an uploaded textbook PDF into chapters and generates a downloadable slide deck from the chapters you pick. | Yes |
| **Lecture Audio**<br>`mod_lectureaudio` | Activity module | `mod/lectureaudio` | Records a lecture in the browser, transcribes it live, and produces an AI summary saved with the activity. | Yes |
| **CSV Grade Analytics**<br>`gradereport_csvanalytics` | Grade report | `grade/report/csvanalytics` | Uploads a grades CSV and charts distributions to compare grading strategies. | No |

Each plugin has its own README with detailed feature and capability notes.

---

## Architecture

```
   ┌────────────────────────────────────────────────────────────┐
   │                        Moodle site                          │
   │                                                             │
   │   local_coursetimeline    local_slidesmaker                 │
   │        mod_lectureaudio         gradereport_csvanalytics    │
   │              │                          │                   │
   └──────────────┼──────────────────────────┼──────────────────┘
                  │                          │
                  │ HTTP                     └── self-contained,
                  │ "AI backend URL" setting      no external calls
                  ▼
   ┌────────────────────────────────────────────┐
   │   Moodle Lectern (separate service)         │
   │   FastAPI · self-hosted · not included here │
   └───────────────────┬────────────────────────┘
                       │
                       ▼
              LLM provider (OpenAI)
              speech-to-text runs locally
```

The three AI plugins never talk to an LLM provider directly. They call the
companion service, which holds the API credentials. No API key is ever stored
in Moodle or exposed to the browser.

**Companion service:** `https://github.com/<your-org>/moodle-lectern`

> Replace the placeholder above once the backend repository is published.

---

## Repository layout

The tree mirrors Moodle's own directory structure, so each plugin drops into
the matching location in your Moodle codebase:

```
local/coursetimeline/          →  <moodle>/local/coursetimeline/
local/slidesmaker/             →  <moodle>/local/slidesmaker/
mod/lectureaudio/              →  <moodle>/mod/lectureaudio/
grade/report/csvanalytics/     →  <moodle>/grade/report/csvanalytics/
```

This repository is **not** a Moodle installation. Copy the plugin directories
you want; there is no need to install all four.

---

## Compatibility

| Plugin | Requires | Version | Maturity |
| --- | --- | --- | --- |
| `local_coursetimeline` | Moodle 4.1+ (2022112800) | v0.3 | Alpha |
| `local_slidesmaker` | Moodle 4.0+ (2022041900) | v1.0 | Alpha |
| `mod_lectureaudio` | Moodle 4.5+ (2024100700) | v0.1-alpha | Alpha |
| `gradereport_csvanalytics` | Moodle 4.0+ (2022041900) | v0.1 | Alpha |

Installing all four requires **Moodle 4.5 or later**, the highest common
requirement.

`mod_lectureaudio` additionally needs a browser supporting `MediaRecorder` and
`getUserMedia`, and microphone permission. Browsers only grant microphone
access on secure origins, so **the Moodle site must be served over HTTPS**
(or accessed via `localhost`).

---

## Installation

### 1. Install the plugins

```bash
# from your Moodle root
cp -r /path/to/Moodle-Plugins-Portfolio/local/coursetimeline      local/
cp -r /path/to/Moodle-Plugins-Portfolio/local/slidesmaker         local/
cp -r /path/to/Moodle-Plugins-Portfolio/mod/lectureaudio          mod/
cp -r /path/to/Moodle-Plugins-Portfolio/grade/report/csvanalytics grade/report/
```

Then visit **Site administration → Notifications** to complete the install.

### 2. Start the companion service

Required for the three AI plugins. See the
[Moodle Lectern](https://github.com/<your-org>/moodle-lectern) README; the
short version:

```bash
git clone https://github.com/<your-org>/moodle-lectern.git
cd moodle-lectern
cp .env.example .env        # set OPENAI_API_KEY
docker compose up --build
```

### 3. Point the plugins at it

Each AI plugin has its own **AI backend URL** admin setting. Set all three to
the address where the service is reachable *from the Moodle server*.

| Plugin | Settings location | Default |
| --- | --- | --- |
| `local_coursetimeline` | Plugins → Local plugins → AI Course Timeline Generator | `http://localhost:8000` |
| `local_slidesmaker` | Plugins → Local plugins → Slides Maker | `http://127.0.0.1:8000` |
| `mod_lectureaudio` | Plugins → Activity modules → Lecture Audio | `http://localhost:8000` |

The defaults assume the service runs on the same host as Moodle. If it runs
elsewhere — or Moodle runs in a container — replace them with a reachable
hostname. Note the transcription and slide calls are made **from the browser**,
so the URL must be reachable by end users too, not only by the server.

### 4. Grant capabilities

| Capability | Grant to |
| --- | --- |
| `local/coursetimeline:view` | Teachers, managers |
| `local/slidesmaker:generate` | Teachers, managers |
| `moodle/course:manageactivities` | Controls who can record in Lecture Audio (standard capability) |

---

## Testing without an LLM key

The companion service ships a mock backend that answers all six endpoints with
canned data. It requires no API key and incurs no cost — useful for reviewing
the plugins, developing against them, or CI:

```bash
cd moodle-lectern
docker compose -f docker-compose.mock.yml up
```

Point the plugins at it exactly as you would the real service. Every feature is
exercisable end to end; responses are fixed sample data rather than real AI
output.

---

## Privacy

- **`mod_lectureaudio` does not keep raw audio.** The recorder uploads an empty
  `filecontent`; only the transcript and summary are stored and shown to
  students.
- **Speech-to-text runs on your own server** inside the companion service.
  Lecture audio is not sent to any third party.
- **Text is sent to an LLM provider.** Transcripts, course topics and selected
  chapter content are transmitted to whichever provider the service is
  configured to use. Account for this in your institution's data protection
  assessment before deploying.

---

## Development

Two of the plugins ship AMD JavaScript modules that must be rebuilt after
editing `amd/src/`:

```bash
# from your Moodle root, after editing amd/src/*.js
grunt amd

# helper scripts included with these plugins
python local/coursetimeline/update_build.py
python mod/lectureaudio/update_build.py
```

PHPUnit tests live under each plugin's `tests/` directory and run through
Moodle's standard PHPUnit setup.

---

## License

GPL v3 or later, consistent with Moodle itself. See the header of any source
file for the full notice.

The companion service is MIT-licensed and runs as a separate process
communicating over HTTP, so the two licenses do not conflict.

---

## Maintenance status

**Solo project, best-effort maintenance.** All four plugins are marked
`MATURITY_ALPHA` and should be treated as such: usable and useful, but not
hardened by production deployment at scale.

Before running these on a site that matters:

- **The companion service has no authentication.** Anyone who can reach it can
  spend your LLM credits. Keep it off the public internet and firewall it to
  your Moodle host.
- **AI output needs review.** Generated timelines, slides and summaries are
  drafts. Teachers should read them before showing them to students.
- **LLM usage costs real money** and is uncapped by these plugins. Set a
  spending limit on your provider account.
- Issues and pull requests are welcome, but response times are not guaranteed.
