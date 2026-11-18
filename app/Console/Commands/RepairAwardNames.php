<?php

namespace App\Console\Commands;

use App\Models\Award;
use App\Models\AwardType;
use App\Support\AwardNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Шагналын нэрийн mojibake (CP1251) засвар + давхардлыг нэгтгэнэ.
 *
 *   php artisan awards:repair-names
 *   php artisan awards:repair-names --dry-run
 */
class RepairAwardNames extends Command
{
    protected $signature = 'awards:repair-names {--dry-run : Зөвхөн тайлан гаргана}';

    protected $description = 'Шагналын нэрийн encoding засварлаж, давхардлыг canonical руу нэгтгэнэ';

    /** @var list<string> */
    private const AWARD_FIELDS = [
        'nominated_award',
        'award_name',
        'last_award',
        'received_award',
    ];

    public function handle(AwardNameNormalizer $normalizer): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($dry) {
            $this->comment('Dry-run — өгөгдөл өөрчлөхгүй.');
        }

        $typeRenames = $this->planTypeRenames($normalizer);
        $fieldUpdates = $this->planFieldUpdates($normalizer);

        $this->info(sprintf(
            'award_types: %d нэр өөрчлөгдөнө / нэгтгэгдэнэ',
            count($typeRenames),
        ));
        $this->info(sprintf(
            'awards талбарууд: %d утга шинэчлэгдэнэ',
            array_sum(array_map('count', $fieldUpdates)),
        ));

        foreach ($typeRenames as $from => $to) {
            $this->line("  · «{$from}» → «{$to}»");
        }

        if ($dry) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($typeRenames, $fieldUpdates, $normalizer) {
            foreach (self::AWARD_FIELDS as $field) {
                foreach ($fieldUpdates[$field] ?? [] as $from => $to) {
                    Award::query()->where($field, $from)->update([$field => $to]);
                }
            }

            $this->mergeAwardTypes($typeRenames, $normalizer);
        });

        $remaining = AwardType::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter(fn (string $name) => $normalizer->looksLikeMojibake($name));

        if ($remaining->isNotEmpty()) {
            $this->warn('Mojibake үлдсэн нэрс:');
            $remaining->each(fn (string $name) => $this->line('  · '.$name));
        }

        $this->info(sprintf(
            'Дууслаа. Одоо award_types: %d',
            AwardType::query()->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> oldName => canonical
     */
    private function planTypeRenames(AwardNameNormalizer $normalizer): array
    {
        $renames = [];

        foreach (AwardType::query()->orderBy('id')->get(['id', 'name']) as $type) {
            $canonical = $normalizer->canonicalize($type->name);

            if ($canonical === null || $canonical === $type->name) {
                continue;
            }

            $renames[$type->name] = $canonical;
        }

        return $renames;
    }

    /**
     * @return array<string, array<string, string>> field => [from => to]
     */
    private function planFieldUpdates(AwardNameNormalizer $normalizer): array
    {
        $updates = [];

        foreach (self::AWARD_FIELDS as $field) {
            $values = Award::query()
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->distinct()
                ->orderBy($field)
                ->pluck($field);

            foreach ($values as $value) {
                if (! is_string($value)) {
                    continue;
                }

                $canonical = $normalizer->canonicalize($value);

                if ($canonical === null || $canonical === $value) {
                    continue;
                }

                // Оны тэмдэглэлтэй утгыг бүтнээр нь солихгүй — зөвхөн mojibake/alias plain нэрс.
                if (! $normalizer->isPlainAwardName($value) && ! $normalizer->looksLikeMojibake($value)) {
                    continue;
                }

                if (! $normalizer->isPlainAwardName($value) && $normalizer->looksLikeMojibake($value)) {
                    // Можибейктой урт/оны утга — зөвхөн encoding засвар.
                    $fixed = $normalizer->fixMojibake($value);
                    if ($fixed !== $value) {
                        $updates[$field][$value] = $fixed;
                    }

                    continue;
                }

                $updates[$field][$value] = $canonical;
            }
        }

        return $updates;
    }

    /**
     * @param  array<string, string>  $renames
     */
    private function mergeAwardTypes(array $renames, AwardNameNormalizer $normalizer): void
    {
        if ($renames === []) {
            // Encoding-гүй ч fingerprint-ээр ижил үлдсэн байж болно.
            $this->collapseDuplicateTypes($normalizer);

            return;
        }

        foreach ($renames as $from => $to) {
            $source = AwardType::query()->where('name', $from)->first();

            if (! $source) {
                continue;
            }

            $target = AwardType::query()->where('name', $to)->first();

            if ($target && $target->id !== $source->id) {
                // Canonical аль хэдийн байгаа — эх сурвалжийг устгана.
                // awards reference-ийг дээр шинэчилсэн.
                $source->delete();

                continue;
            }

            if ($target && $target->id === $source->id) {
                continue;
            }

            // Canonical байхгүй — нэрийг шууд солино (unique).
            $conflict = AwardType::query()->where('name', $to)->where('id', '!=', $source->id)->exists();

            if ($conflict) {
                $source->delete();

                continue;
            }

            $source->update(['name' => $to]);
        }

        $this->collapseDuplicateTypes($normalizer);
    }

    /**
     * Ижил fingerprint-тэй үлдсэн мөрүүдийг нэгтгэнэ.
     */
    private function collapseDuplicateTypes(AwardNameNormalizer $normalizer): void
    {
        $groups = [];

        foreach (AwardType::query()->orderBy('id')->get(['id', 'name', 'sort_order', 'is_active']) as $type) {
            $fp = $normalizer->fingerprint($type->name);
            $groups[$fp][] = $type;
        }

        foreach ($groups as $types) {
            if (count($types) < 2) {
                continue;
            }

            $canonicalName = $normalizer->canonicalize($types[0]->name) ?? $types[0]->name;
            $keeper = null;

            foreach ($types as $type) {
                if ($type->name === $canonicalName) {
                    $keeper = $type;
                    break;
                }
            }

            $keeper ??= $types[0];

            if ($keeper->name !== $canonicalName) {
                $exists = AwardType::query()
                    ->where('name', $canonicalName)
                    ->where('id', '!=', $keeper->id)
                    ->first();

                if ($exists) {
                    $keeper = $exists;
                } else {
                    $keeper->update(['name' => $canonicalName]);
                }
            }

            foreach ($types as $type) {
                if ($type->id === $keeper->id) {
                    continue;
                }

                foreach (self::AWARD_FIELDS as $field) {
                    Award::query()->where($field, $type->name)->update([$field => $keeper->name]);
                }

                $type->delete();
            }
        }
    }
}
