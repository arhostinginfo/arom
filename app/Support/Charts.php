<?php

namespace App\Support;

/**
 * Geometry for the dashboard's SVG charts, worked out here so the views only draw (no logic in views).
 * Colours are CSS classes .cs1 to .cs8 in public/css/app.css; every chart also has a table of its numbers.
 */
class Charts
{
    public const SERIES = 8;

    /**
     * Pie chart: [label => value] into slices with an SVG path in a 100 × 100 box (centre 50,50, radius 46).
     * Slices after the seventh are joined into "Others" so the legend stays readable.
     */
    public static function pie(array $values): array
    {
        arsort($values);
        $values = array_filter($values, fn ($v) => $v > 0);
        if (count($values) > self::SERIES) {
            $head = array_slice($values, 0, self::SERIES - 1, true);
            $head['Others'] = array_sum(array_slice($values, self::SERIES - 1));
            $values = $head;
        }
        $total = array_sum($values);
        $slices = [];
        $angle = -M_PI / 2;
        $i = 0;
        foreach ($values as $label => $value) {
            $share = $total ? $value / $total : 0;
            $end = $angle + $share * 2 * M_PI;
            $slices[] = [
                'label' => (string) $label,
                'value' => $value,
                'percent' => round($share * 100, 1),
                'class' => 'cs'.($i % self::SERIES + 1),
                'path' => $share >= 0.9999 ? null : self::arc($angle, $end),
            ];
            $angle = $end;
            $i++;
        }

        return ['total' => $total, 'slices' => $slices];
    }

    /**
     * Grouped bar chart: $labels (x axis) and $series [name => [values in label order]], drawn in a
     * 600 × 220 box with 28px at the bottom for labels and 32px on the left for the scale.
     */
    public static function bars(array $labels, array $series): array
    {
        $w = 600;
        $h = 220;
        $left = 32;
        $bottom = 28;
        $top = 10;
        $max = max(1, ...array_map(fn ($s) => max([0, ...$s]), array_values($series)));
        $step = self::niceStep($max);
        $scaleMax = (int) (ceil($max / $step) * $step);
        $plotH = $h - $bottom - $top;
        $band = ($w - $left) / max(1, count($labels));
        $barW = min(18, ($band - 8) / max(1, count($series)));

        $bars = [];
        $s = 0;
        foreach ($series as $name => $values) {
            foreach (array_values($values) as $i => $v) {
                $bh = $scaleMax ? $v / $scaleMax * $plotH : 0;
                $bars[] = [
                    'x' => round($left + $i * $band + ($band - $barW * count($series)) / 2 + $s * $barW, 1),
                    'y' => round($top + $plotH - $bh, 1),
                    'w' => round($barW - 2, 1),
                    'h' => round($bh, 1),
                    'class' => 'cs'.($s + 1),
                    'title' => $labels[$i].': '.$name.' '.Money::indian($v),
                ];
            }
            $s++;
        }
        $ticks = [];
        for ($t = 0; $t <= $scaleMax; $t += $step) {
            $ticks[] = ['y' => round($top + $plotH - $t / $scaleMax * $plotH, 1), 'label' => Money::indian($t)];
        }
        $xlabels = [];
        foreach ($labels as $i => $l) {
            $xlabels[] = ['x' => round($left + $i * $band + $band / 2, 1), 'label' => $l];
        }

        return ['width' => $w, 'height' => $h, 'left' => $left, 'base' => $top + $plotH, 'bars' => $bars, 'ticks' => $ticks, 'labels' => $xlabels,
            'legend' => array_map(fn ($n, $i) => ['label' => $n, 'class' => 'cs'.($i + 1)], array_keys($series), array_keys(array_keys($series)))];
    }

    private static function arc(float $from, float $to): string
    {
        $r = 46;
        $x1 = 50 + $r * cos($from);
        $y1 = 50 + $r * sin($from);
        $x2 = 50 + $r * cos($to);
        $y2 = 50 + $r * sin($to);
        $large = $to - $from > M_PI ? 1 : 0;

        return sprintf('M50 50 L%.2f %.2f A%d %d 0 %d 1 %.2f %.2f Z', $x1, $y1, $r, $r, $large, $x2, $y2);
    }

    private static function niceStep(float $max): int
    {
        foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000, 10000] as $step) {
            if ($max / $step <= 5) {
                return $step;
            }
        }

        return (int) ceil($max / 5);
    }
}
