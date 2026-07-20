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
 * Upload a grades CSV and view exploratory data analysis and a grade
 * cutoff calculator.
 *
 * @package    gradereport_csvanalytics
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

$courseid = optional_param('id', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($course->id);
require_capability('gradereport/csvanalytics:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/grade/report/csvanalytics/index.php', ['id' => $course->id]));
$PAGE->set_title(get_string('pluginname', 'gradereport_csvanalytics'));
$PAGE->set_heading(get_string('pluginname', 'gradereport_csvanalytics'));
$PAGE->set_pagelayout('standard');

require_once(__DIR__ . '/classes/analyzer.php');

/**
 * Builds the set of preset grade-cutoff strategies for the given total-score
 * statistics (absolute, relative/std-dev, percentile-based and IIIT-style).
 *
 * @package    gradereport_csvanalytics
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @param array $stats statistics for the course total, as returned by analyzer::calculate_stats()
 * @return array keyed by strategy id, each with a name, description and cutoffs
 */
function get_grading_strategies($stats) {
    $mean = $stats['mean'];
    $std = $stats['stdDev'];

    return [
        'absolute_strict' => [
            'name' => 'Absolute (Strict)',
            'description' => 'A≥90, B≥80, C≥70, D≥60',
            'cutoffs' => ['A' => 90, 'A-' => 85, 'B' => 80, 'B-' => 75, 'C' => 70, 'C-' => 65, 'D' => 60, 'F' => 0],
        ],
        'absolute_relaxed' => [
            'name' => 'Absolute (Relaxed)',
            'description' => 'A≥85, B≥70, C≥55, D≥40',
            'cutoffs' => ['A' => 85, 'A-' => 80, 'B' => 70, 'B-' => 62, 'C' => 55, 'C-' => 48, 'D' => 40, 'F' => 0],
        ],
        'relative_1std' => [
            'name' => 'Relative (μ±σ)',
            'description' => 'Mean ± standard deviation',
            'cutoffs' => [
                'A' => round($mean + 1.5 * $std, 1),
                'A-' => round($mean + 1.0 * $std, 1),
                'B' => round($mean + 0.5 * $std, 1),
                'B-' => round($mean, 1),
                'C' => round($mean - 0.5 * $std, 1),
                'C-' => round($mean - 1.0 * $std, 1),
                'D' => round($mean - 1.5 * $std, 1),
                'F' => 0,
            ],
        ],
        'percentile_based' => [
            'name' => 'Percentile',
            'description' => 'Top 10% A, etc.',
            'cutoffs' => [
                'A' => $stats['percentiles']['p90'],
                'A-' => round(($stats['percentiles']['p90'] + $stats['percentiles']['p75']) / 2, 1),
                'B' => $stats['percentiles']['p75'],
                'B-' => round(($stats['percentiles']['p75'] + $stats['percentiles']['p50']) / 2, 1),
                'C' => $stats['percentiles']['p50'],
                'C-' => $stats['percentiles']['p25'],
                'D' => $stats['percentiles']['p10'],
                'F' => 0,
            ],
        ],
        'iiit_style' => [
            'name' => 'IIIT Relative',
            'description' => 'A≥μ+σ, B≥μ, C≥μ-σ',
            'cutoffs' => [
                'A' => round($mean + $std, 1),
                'A-' => round($mean + 0.5 * $std, 1),
                'B' => round($mean, 1),
                'B-' => round($mean - 0.25 * $std, 1),
                'C' => round($mean - 0.5 * $std, 1),
                'C-' => round($mean - $std, 1),
                'D' => round($mean - 1.5 * $std, 1),
                'F' => 0,
            ],
        ],
    ];
}

// Process data
$data = [];
$stats = null;
$componentstats = [];
$strategies = [];
$selectedstrategy = 'relative_1std';
$students = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] === 0) {
    $csvcontent = file_get_contents($_FILES['csvfile']['tmp_name']);

    // Parse CSV
    $lines = explode("\n", trim($csvcontent));
    $headers = str_getcsv(array_shift($lines));

    foreach ($lines as $line) {
        if (empty(trim($line))) {
            continue;
        }
        $row = str_getcsv($line);
        if (count($row) >= count($headers)) {
            $data[] = array_combine($headers, array_slice($row, 0, count($headers)));
        }
    }

    if (!empty($data)) {
        $analyzer = new \gradereport_csvanalytics\analyzer();
        $results = $analyzer->analyze($data);
        $componentstats = $results['component_stats'] ?? [];

        // Find "Total" or "Course total" stats
        foreach ($componentstats as $name => $s) {
            if (stripos($name, 'total') !== false) {
                $stats = $s;
                break;
            }
        }

        // Build students array for JS
        foreach ($data as $row) {
            $score = 0;
            // Find total score column
            foreach ($row as $key => $val) {
                if (stripos($key, 'total') !== false && is_numeric($val)) {
                    $score = floatval($val);
                    break;
                }
            }
            if ($score > 0) {
                $students[] = [
                    'name' => ($row['First name'] ?? '') . ' ' . ($row['Last name'] ?? ''),
                    'id' => $row['ID number'] ?? '',
                    'score' => $score,
                ];
            }
        }

        if ($stats) {
            $strategies = get_grading_strategies($stats);
        }
    }
}

if (isset($_POST['strategy']) && !empty($_POST['strategy'])) {
    $selectedstrategy = $_POST['strategy'];
}

$currentcutoffs = $strategies[$selectedstrategy]['cutoffs'] ?? null;

echo $OUTPUT->header();
?>

<style>
    * { box-sizing: border-box; }
    .container { max-width: 1600px; margin: 0 auto; }
    h2 { color: #0d6efd; margin: 20px 0 15px; border-bottom: 2px solid #0d6efd; padding-bottom: 5px; }
    h3 { color: #dc3545; margin: 15px 0 10px; }
    
    .upload-section { background: #f8f9fa; padding: 25px; border-radius: 10px; margin-bottom: 20px; text-align: center; border: 2px dashed #dee2e6; }
    .upload-section input[type="file"] { margin: 10px; padding: 10px; }
    
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 1200px) { .grid-2 { grid-template-columns: 1fr; } }
    
    .card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 15px; }
    .stat-value { font-size: 1.6em; color: #0d6efd; font-weight: bold; }
    .stat-label { color: #6c757d; font-size: 0.85em; }
    
    .chart-container { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .chart-wrapper { position: relative; height: 350px; }
    .chart-wrapper-lg { position: relative; height: 450px; }
    
    .strategy-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
    .strategy-tab { padding: 10px 20px; background: #e9ecef; border: none; color: #495057; cursor: pointer; border-radius: 5px; font-weight: 500; }
    .strategy-tab.active { background: #0d6efd; color: #fff; }
    .strategy-tab:hover { background: #0d6efd; color: #fff; }
    
    .slider-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 20px 0; }
    .slider-group { background: #f8f9fa; padding: 12px; border-radius: 8px; }
    .slider-group label { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9em; }
    .slider-group input[type="range"] { width: 100%; cursor: pointer; }
    .slider-value { font-weight: bold; color: #0d6efd; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9em; }
    th, td { padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6; }
    th { background: #f8f9fa; color: #495057; }
    
    .grade-A { color: #00ff88; } .grade-A- { color: #44dd88; }
    .grade-B { color: #88cc44; } .grade-B- { color: #aaaa44; }
    .grade-C { color: #ddaa44; } .grade-C- { color: #dd8844; }
    .grade-D { color: #dd6644; } .grade-F { color: #ff4444; }
    
    .grade-distribution { display: flex; height: 40px; border-radius: 5px; overflow: hidden; margin: 15px 0; }
    .grade-bar { display: flex; align-items: center; justify-content: center; font-size: 0.8em; font-weight: bold; color: #000; transition: width 0.3s; }
    
    .info-box { background: #e7f3ff; border-left: 4px solid #0d6efd; padding: 15px; margin: 10px 0; font-size: 0.9em; border-radius: 0 8px 8px 0; }
    .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 0 8px 8px 0; }
    .success-box { background: #d1e7dd; border-left: 4px solid #198754; padding: 15px; margin: 15px 0; border-radius: 0 8px 8px 0; }
    
    .btn-export { background: #dc3545; color: #fff; border: none; padding: 10px 25px; border-radius: 5px; cursor: pointer; font-weight: bold; }
    .btn-reset { background: #6c757d; color: #fff; border: none; padding: 10px 25px; border-radius: 5px; cursor: pointer; margin-left: 10px; }
    
    .highlight { background: #e7f3ff !important; }
    
    .correlation-cell { padding: 8px; text-align: center; font-weight: bold; }
    .corr-high { background: #00ff88; color: #000; }
    .corr-medium { background: #88cc44; color: #000; }
    .corr-low { background: #ddaa44; color: #000; }
    .corr-weak { background: #dd6644; color: #fff; }
    .corr-negative { background: #ff4444; color: #fff; }
</style>

<div class="container">
    <h1 style="text-align: center; color: #0d6efd; margin-bottom: 30px;">📊 Grade Analytics & Cutoff Calculator</h1>
    
    <!-- Upload Section -->
    <div class="upload-section">
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csvfile" accept=".csv">
            <button type="submit" class="btn btn-primary btn-lg">📤 Upload & Analyze</button>
        </form>
        <?php if (!empty($students)) : ?>
            <p style="margin-top: 10px; color: #198754; font-weight: bold;">✅ Loaded <?= count($students) ?> students</p>
        <?php endif; ?>
    </div>
    
    <?php if ($stats) : ?>
    <!-- ===================== EDA SECTION ===================== -->
    <h2>📈 Exploratory Data Analysis</h2>
    
    <!-- Overall Statistics -->
    <div class="grid">
        <div class="card"><div class="stat-value"><?= $stats['count'] ?></div><div class="stat-label">Students</div></div>
        <div class="card"><div class="stat-value"><?= $stats['mean'] ?></div><div class="stat-label">Mean (μ)</div></div>
        <div class="card"><div class="stat-value"><?= $stats['median'] ?></div><div class="stat-label">Median</div></div>
        <div class="card"><div class="stat-value"><?= $stats['stdDev'] ?></div><div class="stat-label">Std Dev (σ)</div></div>
        <div class="card"><div class="stat-value"><?= $stats['min'] ?></div><div class="stat-label">Min</div></div>
        <div class="card"><div class="stat-value"><?= $stats['max'] ?></div><div class="stat-label">Max</div></div>
        <div class="card"><div class="stat-value"><?= $stats['skewness'] ?></div><div class="stat-label">Skewness</div></div>
        <div class="card"><div class="stat-value"><?= $stats['iqr'] ?></div><div class="stat-label">IQR</div></div>
        <div class="card"><div class="stat-value"><?= $stats['cv'] ?>%</div><div class="stat-label">CV</div></div>
        <div class="card"><div class="stat-value"><?= $stats['outlierCount'] ?></div><div class="stat-label">Outliers</div></div>
    </div>
    
    <!-- Distribution Interpretation -->
    <div class="<?= abs($stats['skewness']) < 0.5 ? 'success-box' : 'warning-box' ?>">
        <strong>Distribution Analysis:</strong>
        <?php if ($stats['skewness'] < -0.5) { ?>
            Left-skewed (negatively skewed) - More students scored high, with a tail towards lower scores.
        <?php } else if ($stats['skewness'] > 0.5) { ?>
            Right-skewed (positively skewed) - More students scored low, with a tail towards higher scores.
        <?php } else { ?>
            Approximately symmetric - Scores are fairly normally distributed around the mean.
        <?php } ?>
        | Kurtosis: <?= $stats['kurtosis'] ?> (<?= $stats['kurtosis'] > 0 ? 'heavy-tailed' : 'light-tailed' ?>)
    </div>
    
    <!-- Histogram using Moodle Chart API -->
    <div class="chart-container">
        <h3>📊 Score Distribution (Histogram)</h3>
            <?php
            $values = $stats['values'];
            $bins = array_fill_keys(range(0, 100, 5), 0);
            foreach ($values as $v) {
                $v = min(100, max(0, $v));
                $b = floor($v / 5) * 5;
                if (isset($bins[$b])) {
                    $bins[$b]++;
                }
            }

            $chart1 = new \core\chart_bar();
            $series1 = new \core\chart_series('Students', array_values($bins));
            $chart1->add_series($series1);
            $labels = array_map(fn($k) => "$k-" . ($k + 4), array_keys($bins));
            $chart1->set_labels($labels);
            echo $OUTPUT->render($chart1);
        ?>
    </div>
    
    <!-- Component-wise Statistics -->
    <div class="card">
        <h3>📋 Component-wise Statistics</h3>
        <table>
            <thead>
                <tr>
                    <th>Component</th>
                    <th>N</th>
                    <th>Mean</th>
                    <th>Median</th>
                    <th>Std Dev</th>
                    <th>Min</th>
                    <th>Max</th>
                    <th>CV%</th>
                    <th>Skewness</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($componentstats as $name => $cs) : ?>
                <tr class="<?= stripos($name, 'total') !== false ? 'highlight' : '' ?>">
                    <td><strong><?= htmlspecialchars($name) ?></strong></td>
                    <td><?= $cs['count'] ?></td>
                    <td><?= $cs['mean'] ?></td>
                    <td><?= $cs['median'] ?></td>
                    <td><?= $cs['stdDev'] ?></td>
                    <td><?= $cs['min'] ?></td>
                    <td><?= $cs['max'] ?></td>
                    <td><?= $cs['cv'] ?>%</td>
                    <td><?= $cs['skewness'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Component Performance Chart -->
            <?php if (count($componentstats) > 1) : ?>
    <div class="chart-container">
        <h3>📉 Component Performance Comparison</h3>
                <?php
                $complabels = [];
                $compmeans = [];
                foreach ($componentstats as $name => $s) {
                    $complabels[] = $name;
                    $compmeans[] = $s['mean'];
                }

                $chart2 = new \core\chart_bar();
                $series2 = new \core\chart_series('Mean Score', $compmeans);
                $chart2->add_series($series2);
                $chart2->set_labels($complabels);
                echo $OUTPUT->render($chart2);
        ?>
    </div>
            <?php endif; ?>
    
    <!-- Percentile Distribution -->
    <div class="card">
        <h3>📈 Percentile Distribution (Total Score)</h3>
        <table>
            <tr>
                <th>P5</th><th>P10</th><th>P25 (Q1)</th><th>P50 (Median)</th><th>P75 (Q3)</th><th>P90</th><th>P95</th>
            </tr>
            <tr>
                <?php foreach ($stats['percentiles'] as $p) : ?>
                    <td><?= $p ?></td>
                <?php endforeach; ?>
            </tr>
        </table>
    </div>
    
    <!-- ===================== GRADING SECTION ===================== -->
    <h2>🎯 Grade Cutoff Configuration</h2>
    
    <!-- Strategy Presets -->
    <div class="card">
        <h3>Load Preset Strategy</h3>
        <div class="strategy-tabs">
            <?php foreach ($strategies as $key => $strategy) : ?>
                <button type="button" class="strategy-tab <?= $selectedstrategy === $key ? 'active' : '' ?>" 
                        onclick="loadStrategy('<?= $key ?>')">
                    <?= $strategy['name'] ?>
                </button>
            <?php endforeach; ?>
            <button type="button" class="btn-reset" onclick="resetCutoffs()">🔄 Reset</button>
        </div>
        <div class="info-box">
            <strong id="strategyName"><?= $strategies[$selectedstrategy]['name'] ?? '' ?>:</strong> 
            <span id="strategyDesc"><?= $strategies[$selectedstrategy]['description'] ?? '' ?></span>
        </div>
    </div>
    
    <!-- Interactive Sliders -->
    <div class="card">
        <h3>Adjust Cutoffs (Drag to modify)</h3>
        <div class="slider-grid">
            <?php foreach (['A', 'A-', 'B', 'B-', 'C', 'C-', 'D'] as $grade) : ?>
            <div class="slider-group">
                <label><?= $grade ?> ≥ <span class="slider-value" id="val<?= str_replace('-', 'm', $grade) ?>"><?= $currentcutoffs[$grade] ?? 0 ?></span></label>
                <input type="range" id="cutoff<?= str_replace('-', 'm', $grade) ?>" 
                       min="0" max="100" step="0.5" 
                       value="<?= $currentcutoffs[$grade] ?? 0 ?>"
                       oninput="updateCutoff('<?= $grade ?>', this.value)">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Main Scatter Plot with Cutoffs -->
    <div class="chart-container">
        <h3>📍 Score Distribution with Grade Cutoffs</h3>
        <p style="color: #6c757d; font-size: 0.9em;">Each dot = 1 student. Horizontal lines = grade cutoffs. Drag sliders above to adjust.</p>
        <div class="chart-wrapper-lg">
            <canvas id="mainScatterChart"></canvas>
        </div>
    </div>
    
    <!-- Grade Distribution -->
    <div class="card">
        <h3>Grade Distribution</h3>
        <div class="grade-distribution" id="gradeDistBar"></div>
        <table id="gradeTable">
            <tr>
                <th>Grade</th>
                <th class="grade-A">A</th><th class="grade-A-">A-</th>
                <th class="grade-B">B</th><th class="grade-B-">B-</th>
                <th class="grade-C">C</th><th class="grade-C-">C-</th>
                <th class="grade-D">D</th><th class="grade-F">F</th>
            </tr>
            <tr id="countRow"><td>Count</td></tr>
            <tr id="pctRow"><td>%</td></tr>
        </table>
    </div>
    
    <!-- Boundary Students -->
    <div class="warning-box">
        <h4>⚠️ Students Just Below Cutoffs (within 1.5 marks)</h4>
        <div id="boundaryList" style="margin-top: 10px;"></div>
    </div>
    
    <!-- Export -->
    <div style="text-align: center; margin: 20px 0;">
        <button class="btn-export" onclick="exportGrades()">📥 Export Grades CSV</button>
    </div>
    
    <?php endif; ?>
</div>

<?php if ($stats) : ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation"></script>
<script>
const students = <?= json_encode($students) ?>;
const strategies = <?= json_encode($strategies) ?>;
let selectedStrategy = '<?= $selectedstrategy ?>';

const gradeColors = {
    'A': '#00ff88', 'A-': '#44dd88', 'B': '#88cc44', 'B-': '#aaaa44',
    'C': '#ddaa44', 'C-': '#dd8844', 'D': '#dd6644', 'F': '#ff4444'
};

let currentCutoffs = <?= json_encode($currentcutoffs ?? []) ?>;
let mainChart;

function loadStrategy(key) {
    selectedStrategy = key;
    currentCutoffs = { ...strategies[key].cutoffs };
    
    // Update UI
    document.querySelectorAll('.strategy-tab').forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('strategyName').textContent = strategies[key].name + ':';
    document.getElementById('strategyDesc').textContent = strategies[key].description;
    
    // Update sliders
    ['A', 'A-', 'B', 'B-', 'C', 'C-', 'D'].forEach(g => {
        const id = g.replace('-', 'm');
        document.getElementById('cutoff' + id).value = currentCutoffs[g];
        document.getElementById('val' + id).textContent = currentCutoffs[g];
    });
    
    updateCharts();
}

function initMainScatter() {
    const ctx = document.getElementById('mainScatterChart').getContext('2d');
    
    const data = students.map((s, i) => ({
        x: Math.random() * 0.8 + 0.1,
        y: s.score,
        name: s.name,
        id: s.id
    }));
    
    mainChart = new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Students',
                data: data,
                backgroundColor: data.map(d => getColorForScore(d.y)),
                pointRadius: 6,
                pointHoverRadius: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: { label: ctx => `${ctx.raw.name}: ${ctx.raw.y.toFixed(1)} (${assignGrade(ctx.raw.y)})` }
                },
                annotation: { annotations: generateAnnotations() }
            },
            scales: {
                x: { display: false, min: 0, max: 1 },
                y: { 
                    title: { display: true, text: 'Total Score' },
                    min: 0, max: 100
                }
            }
        }
    });
}

function generateAnnotations() {
    const ann = {};
    ['A', 'A-', 'B', 'B-', 'C', 'C-', 'D'].forEach(g => {
        const val = currentCutoffs[g];
        if (val > 0) {
            ann[g] = {
                type: 'line',
                yMin: val, yMax: val,
                borderColor: gradeColors[g],
                borderWidth: 2,
                borderDash: g.includes('-') ? [5, 5] : [],
                label: {
                    display: true,
                    content: `${g} ≥ ${val}`,
                    position: 'start',
                    backgroundColor: gradeColors[g],
                    color: '#000',
                    font: { size: 10, weight: 'bold' }
                }
            };
        }
    });
    return ann;
}

function getColorForScore(score) {
    if (score >= currentCutoffs['A']) return gradeColors['A'];
    if (score >= currentCutoffs['A-']) return gradeColors['A-'];
    if (score >= currentCutoffs['B']) return gradeColors['B'];
    if (score >= currentCutoffs['B-']) return gradeColors['B-'];
    if (score >= currentCutoffs['C']) return gradeColors['C'];
    if (score >= currentCutoffs['C-']) return gradeColors['C-'];
    if (score >= currentCutoffs['D']) return gradeColors['D'];
    return gradeColors['F'];
}

function assignGrade(score) {
    for (const g of ['A', 'A-', 'B', 'B-', 'C', 'C-', 'D']) {
        if (score >= currentCutoffs[g]) return g;
    }
    return 'F';
}

function updateCutoff(grade, value) {
    currentCutoffs[grade] = parseFloat(value);
    document.getElementById('val' + grade.replace('-', 'm')).textContent = value;
    updateCharts();
}

function updateCharts() {
    mainChart.data.datasets[0].backgroundColor = mainChart.data.datasets[0].data.map(d => getColorForScore(d.y));
    mainChart.options.plugins.annotation.annotations = generateAnnotations();
    mainChart.update('none');
    
    updateGradeDistribution();
    updateBoundaryStudents();
}

function updateGradeDistribution() {
    const counts = { 'A': 0, 'A-': 0, 'B': 0, 'B-': 0, 'C': 0, 'C-': 0, 'D': 0, 'F': 0 };
    students.forEach(s => counts[assignGrade(s.score)]++);
    const total = students.length;
    
    document.getElementById('gradeDistBar').innerHTML = Object.entries(counts)
        .filter(([g, c]) => c > 0)
        .map(([g, c]) => `<div class="grade-bar" style="width:${(c/total*100).toFixed(1)}%;background:${gradeColors[g]}">${g}:${c}</div>`)
        .join('');
    
    document.getElementById('countRow').innerHTML = '<td>Count</td>' + Object.values(counts).map(c => `<td>${c}</td>`).join('');
    document.getElementById('pctRow').innerHTML = '<td>%</td>' + Object.values(counts).map(c => `<td>${(c/total*100).toFixed(1)}%</td>`).join('');
}

function updateBoundaryStudents() {
    const boundary = 1.5;
    const counts = { 'A': 0, 'A-': 0, 'B': 0, 'B-': 0, 'C': 0, 'C-': 0, 'D': 0 };
    
    students.forEach(s => {
        ['A', 'A-', 'B', 'B-', 'C', 'C-', 'D'].forEach(g => {
            const diff = s.score - currentCutoffs[g];
            // Only count students just below the cutoff (negative diff, within boundary)
            if (diff < 0 && diff >= -boundary) {
                counts[g]++;
            }
        });
    });
    
    const list = document.getElementById('boundaryList');
    const hasAny = Object.values(counts).some(c => c > 0);
    
    if (!hasAny) {
        list.innerHTML = '<p style="color:#6c757d">No students within 1.5 marks below any cutoff.</p>';
    } else {
        let html = '<table style="width:100%;"><tr><th>Grade</th><th>Students Just Below</th></tr>';
        ['A', 'A-', 'B', 'B-', 'C', 'C-', 'D'].forEach(g => {
            if (counts[g] > 0) {
                html += `<tr><td><strong style="color:${gradeColors[g]}">${g}</strong> (cutoff: ${currentCutoffs[g]})</td><td>${counts[g]}</td></tr>`;
            }
        });
        html += '</table>';
        list.innerHTML = html;
    }
}

function resetCutoffs() {
    if (strategies[selectedStrategy]) {
        currentCutoffs = { ...strategies[selectedStrategy].cutoffs };
        ['A', 'A-', 'B', 'B-', 'C', 'C-', 'D'].forEach(g => {
            const id = g.replace('-', 'm');
            document.getElementById('cutoff' + id).value = currentCutoffs[g];
            document.getElementById('val' + id).textContent = currentCutoffs[g];
        });
        updateCharts();
    }
}

function exportGrades() {
    let csv = 'Name,ID,Score,Grade\n';
    students.forEach(s => csv += `"${s.name}","${s.id}",${s.score},"${assignGrade(s.score)}"\n`);
    csv += '\n# Cutoffs:\n';
    Object.entries(currentCutoffs).forEach(([g, v]) => csv += `# ${g} >= ${v}\n`);
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'grades_export.csv';
    a.click();
}

// Initialize
if (students.length > 0) {
    initMainScatter();
    updateGradeDistribution();
    updateBoundaryStudents();
}
</script>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
