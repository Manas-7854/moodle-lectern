<?php
namespace gradereport_csvanalytics;

defined('MOODLE_INTERNAL') || die();

class analyzer {

    public function calculate_stats(array $values, string $name = ''): ?array {
        $values = array_filter($values, fn($s) => is_numeric($s) && $s !== '-' && $s !== '');
        $values = array_map('floatval', $values);
        
        $n = count($values);
        if ($n == 0) return null;
        
        sort($values);
        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $n;
        $stdDev = sqrt($variance);
        
        $median = $n % 2 == 0 ? ($values[$n/2 - 1] + $values[$n/2]) / 2 : $values[floor($n/2)];
        
        // Skewness
        $skewness = $stdDev > 0 ? array_sum(array_map(fn($x) => pow(($x - $mean) / $stdDev, 3), $values)) / $n : 0;
        
        // Kurtosis
        $kurtosis = $stdDev > 0 ? array_sum(array_map(fn($x) => pow(($x - $mean) / $stdDev, 4), $values)) / $n - 3 : 0;
        
        // IQR and outliers
        $q1 = $values[max(0, floor($n * 0.25))];
        $q3 = $values[max(0, floor($n * 0.75))];
        $iqr = $q3 - $q1;
        $lowerBound = $q1 - 1.5 * $iqr;
        $upperBound = $q3 + 1.5 * $iqr;
        $outliers = array_filter($values, fn($v) => $v < $lowerBound || $v > $upperBound);
        
        return [
            'name' => $name,
            'count' => $n,
            'mean' => round($mean, 2),
            'median' => round($median, 2),
            'stdDev' => round($stdDev, 2),
            'variance' => round($variance, 2),
            'min' => round(min($values) , 2),
            'max' => round(max($values), 2),
            'range' => round(max($values) - min($values), 2),
            'skewness' => round($skewness, 2),
            'kurtosis' => round($kurtosis, 2),
            'q1' => round($q1, 2),
            'q3' => round($q3, 2),
            'iqr' => round($iqr, 2),
            'cv' => $mean > 0 ? round(($stdDev / $mean) * 100, 1) : 0,
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
            ]
        ];
    }

    public function correlation(array $x, array $y): float {
        $n = min(count($x), count($y));
        if ($n < 3) return 0.0;
        
        $x = array_slice($x, 0, $n);
        $y = array_slice($y, 0, $n);
        
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        
        $num = 0;
        $denX = 0;
        $denY = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $num += $dx * $dy;
            $denX += $dx * $dx;
            $denY += $dy * $dy;
        }
        
        $den = sqrt($denX * $denY);
        return $den > 0 ? round($num / $den, 3) : 0.0;
    }

    public function analyze(array $data): array {
        if (empty($data)) {
            return [];
        }

        $headers = array_keys($data[0]);
        
        // Dynamically find grade columns: look for columns containing "Real", "Assignment", "Quiz", "Total", etc.
        // Also support columns like "Course total (Real)" or "Assignment: Assignment 1 (Real)"
        $componentData = [];
        $componentStats = [];
        
        foreach ($headers as $header) {
            // Skip non-grade columns
            $lowerHeader = strtolower($header);
            if (in_array($lowerHeader, ['first name', 'last name', 'id number', 'institution', 'department', 'email address', 'last downloaded from this course'])) {
                continue;
            }
            
            // Check if this looks like a grade column
            $isGradeColumn = (
                stripos($header, 'Real') !== false ||
                stripos($header, 'Assignment') !== false ||
                stripos($header, 'Quiz') !== false ||
                stripos($header, 'Total') !== false ||
                stripos($header, 'Grade') !== false ||
                stripos($header, 'Score') !== false ||
                stripos($header, 'Midsem') !== false ||
                stripos($header, 'Endsem') !== false
            );
            
            if ($isGradeColumn) {
                // Extract a cleaner name for display
                $displayName = $header;
                // Try to extract just the activity name from patterns like "Assignment: Assignment 1 (Real)"
                if (preg_match('/^(Assignment|Quiz|Forum|Lesson):\s*(.+?)\s*\(Real\)$/i', $header, $matches)) {
                    $displayName = $matches[2];
                } elseif (preg_match('/^(.+?)\s*\(Real\)$/i', $header, $matches)) {
                    $displayName = $matches[1];
                }
                
                // Extract values
                $values = [];
                foreach ($data as $row) {
                    if (isset($row[$header])) {
                        $values[] = $row[$header];
                    }
                }
                
                $stats = $this->calculate_stats($values, $displayName);
                if ($stats) {
                    $componentStats[$displayName] = $stats;
                    $componentData[$displayName] = array_map('floatval', $values);
                }
            }
        }

        // Calculate correlations
        $correlations = [];
        $compNames = array_keys($componentData);
        foreach ($compNames as $c1) {
            foreach ($compNames as $c2) {
                if (!isset($correlations[$c1])) $correlations[$c1] = [];
                // We need aligned arrays for correlation. 
                // The original code calculated stats on filtered arrays but for correlation 
                // it used `array_values($componentData[$c1])`.
                // However, `data` is an array of rows. `componentData` above was extracted from rows order.
                // So the indices match (0 to N).
                $correlations[$c1][$c2] = $this->correlation(
                    $componentData[$c1], 
                    $componentData[$c2]
                );
            }
        }

        return [
            'component_stats' => $componentStats,
            'correlations' => $correlations,
            'raw_data' => $data
        ];
    }
}
