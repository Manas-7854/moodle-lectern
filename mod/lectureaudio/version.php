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

/**
 * Version information for mod_lectureaudio.
 *
 * @package    mod_lectureaudio
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_lectureaudio';
$plugin->version   = 2024102401;       // The current module version (Date: YYYYMMDDXX).
$plugin->requires  = 2024100700;       // Requires Moodle 4.5+.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = 'v0.1-alpha';
$plugin->cron      = 0;
