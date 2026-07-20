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

namespace gradereport_csvanalytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Computes descriptive statistics and correlations for grade CSV data.
 *
 * @package    gradereport_csvanalytics
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analyzer {
    /**
     * Calculates descriptive statistics (mean, median, std dev, skewness,
     * kurtosis, IQR, outliers, percentiles) for a list of grade values.
     *
     * @param array $values raw values, non-numeric/blank entries are ignored
     * @param string $name display name for the component being analysed
     * @return array|null the statistics, or null if there are no numeric values
     */
    public function calculate_stats(array $values, string $name = ''): ?array {
        $values = array_filter($values, fn($s) => is_numeric($s) && $s !== '-' && $s !== '');
        $values = array_map('floatval', $values);

        $n = count($values);
        if ($n == 0) {
            return null;
        }

        sort($values);
        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $n;
        $stddev = sqrt($variance);

        $median = $n % 2 == 0 ? ($values[$n / 2 - 1] + $values[$n / 2]) / 2 : $values[floor($n / 2)];

        // Skewness
        $skewness = $stddev > 0 ? array_sum(array_map(fn($x) => pow(($x - $mean) / $stddev, 3), $values)) / $n : 0;

        // Kurtosis
        $kurtosis = $stddev > 0 ? array_sum(array_map(fn($x) => pow(($x - $mean) / $stddev, 4), $values)) / $n - 3 : 0;

        // IQR and outliers
        $q1 = $values[max(0, floor($n * 0.25))];
        $q3 = $values[max(0, floor($n * 0.75))];
        $iqr = $q3 - $q1;
        $lowerbound = $q1 - 1.5 * $iqr;
        $upperbound = $q3 + 1.5 * $iqr;
        $outliers = array_filter($values, fn($v) => $v < $lowerbound || $v > $upperbound);

        return [
            'name' => $name,
            'count' => $n,
            'mean' => round($mean, 2),
            'median' => round($median, 2),
            'stdDev' => round($stddev, 2),
            'variance' => round($variance, 2),
            'min' => round(min($values), 2),
            'max' => round(max($values), 2),
            'range' => round(max($values) - min($values), 2),
            'skewness' => round($skewness, 2),
            'kurtosis' => round($kurtosis, 2),
            'q1' => round($q1, 2),
            'q3' => round($q3, 2),
            'iqr' => round($iqr, 2),
            'cv' => $mean > 0 ? round(($stddev / $mean) * 100, 1) : 0,
            'outlierCount' => count($outliers),
            'values' => $values,
            'percentiles' => [
                'p5' => round($values[max(0, floor($n * 0.05))], 2),
                'p10' => round($values[max(0, floor($n * 0.10))], 2),
                'p25' => round($q1, 2),
                'p50' => round($median, 2),
                'p75' => round($q3, 2),
                'p90' => round($values[max(0, floor($n * 0.90))], 2),
                'p95' => round($values[max(0, floor($n * 0.95))], 2),
            ],
        ];
    }

    /**
     * Calculates the Pearson correlation coefficient between two series.
     *
     * @param array $x first series of values
     * @param array $y second series of values
     * @return float correlation coefficient in [-1, 1], or 0.0 if too few points
     */
    public function correlation(array $x, array $y): float {
        $n = min(count($x), count($y));
        if ($n < 3) {
            return 0.0;
        }

        $x = array_slice($x, 0, $n);
        $y = array_slice($y, 0, $n);

        $meanx = array_sum($x) / $n;
        $meany = array_sum($y) / $n;

        $num = 0;
        $denx = 0;
        $deny = 0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanx;
            $dy = $y[$i] - $meany;
            $num += $dx * $dy;
            $denx += $dx * $dx;
            $deny += $dy * $dy;
        }

        $den = sqrt($denx * $deny);
        return $den > 0 ? round($num / $den, 3) : 0.0;
    }

    /**
     * Detects grade columns in the parsed CSV rows and computes statistics
     * and cross-component correlations for each of them.
     *
     * @param array $data parsed CSV rows, keyed by column header
     * @return array component_stats, correlations and the raw_data
     */
    public function analyze(array $data): array {
        if (empty($data)) {
            return [];
        }

        $headers = array_keys($data[0]);

        // Dynamically find grade columns: look for columns containing "Real", "Assignment", "Quiz", "Total", etc.
        // Also support columns like "Course total (Real)" or "Assignment: Assignment 1 (Real)"
        $componentdata = [];
        $componentstats = [];

        foreach ($headers as $header) {
            // Skip non-grade columns
            $lowerheader = strtolower($header);
            if (in_array($lowerheader, ['first name', 'last name', 'id number', 'institution', 'department', 'email address', 'last downloaded from this course'])) {
                continue;
            }

            // Check if this looks like a grade column
            $isgradecolumn = (
                stripos($header, 'Real') !== false ||
                stripos($header, 'Assignment') !== false ||
                stripos($header, 'Quiz') !== false ||
                stripos($header, 'Total') !== false ||
                stripos($header, 'Grade') !== false ||
                stripos($header, 'Score') !== false ||
                stripos($header, 'Midsem') !== false ||
                stripos($header, 'Endsem') !== false
            );

            if ($isgradecolumn) {
                // Extract a cleaner name for display
                $displayname = $header;
                // Try to extract just the activity name from patterns like "Assignment: Assignment 1 (Real)"
                if (preg_match('/^(Assignment|Quiz|Forum|Lesson):\s*(.+?)\s*\(Real\)$/i', $header, $matches)) {
                    $displayname = $matches[2];
                } else if (preg_match('/^(.+?)\s*\(Real\)$/i', $header, $matches)) {
                    $displayname = $matches[1];
                }

                // Extract values
                $values = [];
                foreach ($data as $row) {
                    if (isset($row[$header])) {
                        $values[] = $row[$header];
                    }
                }

                $stats = $this->calculate_stats($values, $displayname);
                if ($stats) {
                    $componentstats[$displayname] = $stats;
                    $componentdata[$displayname] = array_map('floatval', $values);
                }
            }
        }

        // Calculate correlations
        $correlations = [];
        $compnames = array_keys($componentdata);
        foreach ($compnames as $c1) {
            foreach ($compnames as $c2) {
                if (!isset($correlations[$c1])) {
                    $correlations[$c1] = [];
                }
                // We need aligned arrays for correlation.
                // The original code calculated stats on filtered arrays but for correlation
                // it used `array_values($componentdata[$c1])`.
                // However, `data` is an array of rows. `componentData` above was extracted from rows order.
                // So the indices match (0 to N).
                $correlations[$c1][$c2] = $this->correlation(
                    $componentdata[$c1],
                    $componentdata[$c2]
                );
            }
        }

        return [
            'component_stats' => $componentstats,
            'correlations' => $correlations,
            'raw_data' => $data,
        ];
    }
}
