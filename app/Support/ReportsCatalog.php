<?php

namespace App\Support;

class ReportsCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        if (self::$config === null) {
            self::$config = config('reports_catalog', []);
        }

        return self::$config;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function sections(): array
    {
        return self::config()['sections'] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function templates(): array
    {
        return self::config()['templates'] ?? [];
    }

    public static function template(string $key): ?array
    {
        return self::templates()[$key] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function flatReports(): array
    {
        $flat = [];

        foreach (self::sections() as $section) {
            self::walkReports($section['reports'] ?? [], $flat, [
                'section_key' => $section['key'] ?? '',
                'section_label' => $section['label'] ?? '',
                'section_number' => $section['number'] ?? null,
            ]);
        }

        return $flat;
    }

    public static function find(string $key): ?array
    {
        $item = self::flatReports()[$key] ?? null;

        return $item ? self::enrichReport($item) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function dashboard(): array
    {
        $config = self::config();
        $dash = $config['dashboard'] ?? [];

        return [
            'period' => $config['period'] ?? null,
            'as_of' => $config['as_of'] ?? null,
            'kpis' => $dash['kpis'] ?? [],
            'sections' => $dash['sections'] ?? [],
            'departments' => $dash['departments'] ?? [],
            'official_assignments' => $dash['official_assignments'] ?? [],
            'report_count' => count(self::flatReports()),
            'source_count' => count($config['sources'] ?? []),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function sources(): array
    {
        return self::config()['sources'] ?? [];
    }

    public static function resolveSectionKey(?string $key, ?string $fallback = null): ?string
    {
        if ($key !== null) {
            foreach (self::sections() as $section) {
                if (($section['key'] ?? null) === $key) {
                    return $key;
                }
            }
        }

        return $fallback;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function navigationTree(): array
    {
        $tree = [];

        foreach (self::sections() as $section) {
            $tree[] = [
                'key' => $section['key'],
                'number' => $section['number'] ?? null,
                'label' => $section['label'],
                'template' => $section['template'] ?? null,
                'children' => self::mapNavReports($section['reports'] ?? []),
            ];
        }

        return $tree;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public static function enrichReport(array $report): array
    {
        $templateKey = $report['template'] ?? 'policy_tracking';
        $template = self::template($templateKey);

        $report['template'] = $templateKey;
        $report['template_label'] = $template['label'] ?? $templateKey;
        $report['columns'] = $template['columns'] ?? [];

        return $report;
    }

    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @param  array<string, array<string, mixed>>  $flat
     * @param  array<string, string|null>  $meta
     */
    private static function walkReports(array $reports, array &$flat, array $meta): void
    {
        foreach ($reports as $report) {
            if (! empty($report['key'])) {
                $flat[$report['key']] = array_merge($meta, $report);
            }

            if (! empty($report['children'])) {
                self::walkReports($report['children'], $flat, $meta);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @return array<int, array<string, mixed>>
     */
    private static function mapNavReports(array $reports): array
    {
        return array_map(function (array $report) {
            return [
                'key' => $report['key'] ?? null,
                'number' => $report['number'] ?? null,
                'label' => $report['label'] ?? '',
                'template' => $report['template'] ?? null,
                'department' => $report['department'] ?? null,
                'source_file' => $report['source_file'] ?? null,
                'children' => self::mapNavReports($report['children'] ?? []),
            ];
        }, $reports);
    }
}
