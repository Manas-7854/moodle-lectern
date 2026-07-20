<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace gradereport_csvanalytics;

/**
 * Unit tests for the CSV grade analytics statistics engine.
 *
 * @package    gradereport_csvanalytics
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \gradereport_csvanalytics\analyzer
 */
final class analyzer_test extends \advanced_testcase {
    public function test_calculate_stats_known_values(): void {
        $analyzer = new analyzer();
        $stats = $analyzer->calculate_stats([10, 20, 30, 40, 50], 'Total');

        $this->assertSame(5, $stats['count']);
        $this->assertEqualsWithDelta(30.0, $stats['mean'], 0.01);
        $this->assertEqualsWithDelta(30.0, $stats['median'], 0.01);
        $this->assertEqualsWithDelta(14.14, $stats['stdDev'], 0.01);
        $this->assertEqualsWithDelta(0.0, $stats['skewness'], 0.01);
        $this->assertEqualsWithDelta(-1.3, $stats['kurtosis'], 0.01);
        $this->assertEqualsWithDelta(20.0, $stats['iqr'], 0.01);
        $this->assertSame(0, $stats['outlierCount']);
        $this->assertEqualsWithDelta(47.1, $stats['cv'], 0.1);
        $this->assertEqualsWithDelta(20.0, $stats['percentiles']['p25'], 0.01);
        $this->assertEqualsWithDelta(40.0, $stats['percentiles']['p75'], 0.01);
    }

    public function test_calculate_stats_ignores_non_numeric_and_blank_values(): void {
        $analyzer = new analyzer();
        // '-' and '' are how the grade CSV export marks missing grades.
        $stats = $analyzer->calculate_stats(['10', '-', '', '20', '30'], 'Total');

        $this->assertSame(3, $stats['count']);
        $this->assertEqualsWithDelta(20.0, $stats['mean'], 0.01);
    }

    public function test_calculate_stats_empty_input_returns_null(): void {
        $analyzer = new analyzer();
        $this->assertNull($analyzer->calculate_stats([]));
    }

    public function test_calculate_stats_detects_outliers(): void {
        $analyzer = new analyzer();
        // 1000 is far outside the IQR fence of the rest of the values.
        $stats = $analyzer->calculate_stats([10, 12, 11, 13, 12, 11, 1000], 'Total');

        $this->assertGreaterThanOrEqual(1, $stats['outlierCount']);
    }

    public function test_correlation_perfect_positive(): void {
        $analyzer = new analyzer();
        $r = $analyzer->correlation([1, 2, 3, 4, 5], [2, 4, 6, 8, 10]);
        $this->assertEqualsWithDelta(1.0, $r, 0.001);
    }

    public function test_correlation_perfect_negative(): void {
        $analyzer = new analyzer();
        $r = $analyzer->correlation([1, 2, 3, 4, 5], [5, 4, 3, 2, 1]);
        $this->assertEqualsWithDelta(-1.0, $r, 0.001);
    }

    public function test_correlation_insufficient_data_returns_zero(): void {
        $analyzer = new analyzer();
        $this->assertSame(0.0, $analyzer->correlation([1, 2], [1, 2]));
    }

    public function test_analyze_builds_component_stats_and_correlations(): void {
        $analyzer = new analyzer();
        $data = [
            ['First name' => 'A', 'Last name' => 'One', 'Quiz 1 (Real)' => '10', 'Course total (Real)' => '20'],
            ['First name' => 'B', 'Last name' => 'Two', 'Quiz 1 (Real)' => '20', 'Course total (Real)' => '40'],
            ['First name' => 'C', 'Last name' => 'Three', 'Quiz 1 (Real)' => '30', 'Course total (Real)' => '60'],
        ];

        $result = $analyzer->analyze($data);

        $this->assertArrayHasKey('Quiz 1', $result['component_stats']);
        $this->assertArrayHasKey('Course total', $result['component_stats']);
        $this->assertEqualsWithDelta(20.0, $result['component_stats']['Quiz 1']['mean'], 0.01);
        // Course total is exactly double Quiz 1 for every student, so correlation is perfect.
        $this->assertEqualsWithDelta(1.0, $result['correlations']['Quiz 1']['Course total'], 0.001);
    }
}
