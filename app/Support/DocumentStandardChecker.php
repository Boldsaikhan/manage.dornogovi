<?php

namespace App\Support;

use App\Models\DocumentStandard;

/**
 * Албан бичгийн төслийг зөвхөн хүчин төгөлдөр, эхтэй дүрмээр шалгана.
 * Хүчингүй журмын дүрэм, эхгүй хэмжээ орохгүй.
 */
class DocumentStandardChecker
{
    /**
     * @return array{note: string, kind: string, findings: list<array<string, string>>, checklist: list<array<string, string>>, skipped: list<array<string, string>>}
     */
    public function inspect(string $text, ?string $kind = null, ?string $slug = null): array
    {
        $kind = $this->kind($kind, $text);
        $query = DocumentStandard::query()->whereNull('archived_at');

        if ($slug) {
            $query->where('slug', $slug);
        }

        $standards = $query->orderBy('sort_order')->get();
        $findings = [];
        $checklist = [];
        $skipped = [];

        foreach ($standards as $standard) {
            if (! $standard->applies()) {
                $skipped[] = [
                    'title' => $standard->title,
                    'status' => $standard->status,
                    'reason' => 'Хүчингүй эсвэл архивласан тул шалгалтад оруулаагүй.',
                ];
                continue;
            }

            foreach ($standard->checks ?? [] as $check) {
                if (! is_array($check) || ! $this->matchesKind($check['when'] ?? null, $kind)) {
                    continue;
                }

                $type = (string) ($check['type'] ?? '');
                $row = [
                    'clause' => (string) ($check['clause'] ?? $standard->code ?? ''),
                    'message' => (string) ($check['message'] ?? ''),
                    'source' => (string) ($standard->source_number ?? ''),
                    'title' => $standard->title,
                    'slug' => (string) $standard->slug,
                ];

                if ($type === 'checklist') {
                    $checklist[] = $row;
                    continue;
                }

                if ($type !== 'present' || empty($check['pattern'])) {
                    continue;
                }

                $pattern = '~'.str_replace('~', '\\~', (string) $check['pattern']).'~u';
                if (@preg_match($pattern, $text) === 1) {
                    $findings[] = $row + ['level' => (string) ($check['level'] ?? 'warn')];
                }
            }
        }

        return [
            'note' => 'Шалгалт зөвхөн системд бүртгэсэн, хүчин төгөлдөр эхтэй дүрмийг харна. 2024 оны 214 дүгээр тогтоол хуудасны зах, үсгийн цэг, мөр хоорондын зайг тоогоор заагаагүй тул тэдгээрийг шалгахгүй. Хүчингүй 2020 оны 246 дугаар тогтоолын дүрэм ороогүй.',
            'kind' => $kind,
            'findings' => $findings,
            'checklist' => $checklist,
            'skipped' => $slug ? $skipped : [],
        ];
    }

    private function kind(?string $kind, string $text): string
    {
        $kind = $kind ?: '';
        if (in_array($kind, ['letter', 'order', 'minutes', 'act', 'contract', 'report'], true)) {
            return $kind;
        }

        $q = mb_strtolower($text);
        if (str_contains($q, 'тушаал') || str_contains($q, 'захирамж')) {
            return 'order';
        }
        if (str_contains($q, 'тэмдэглэл') || str_contains($q, 'хурал')) {
            return 'minutes';
        }
        if (str_contains($q, 'гэрээ')) {
            return 'contract';
        }
        if (str_contains($q, 'тайлан')) {
            return 'report';
        }

        return 'letter';
    }

    private function matchesKind(mixed $when, string $kind): bool
    {
        if ($when === null || $when === '' || $when === 'any') {
            return true;
        }

        return (string) $when === $kind;
    }
}
