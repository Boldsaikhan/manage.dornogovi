<?php

namespace App\Services\Ai\Tools;

use App\Models\DocumentStandard;
use App\Models\User;
use App\Support\DocumentStandardChecker;

class DocumentStandardTools
{
    public function __construct(private DocumentStandardChecker $checker) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function check(User $user, array $args): array
    {
        $text = trim((string) ($args['text'] ?? ''));
        $result = $this->checker->inspect(
            $text,
            isset($args['kind']) ? (string) $args['kind'] : null,
            isset($args['slug']) ? (string) $args['slug'] : null,
        );

        return $result + [
            'headline' => 'Бичиг хэргийн стандартын шалгалт',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function list(User $user, array $args): array
    {
        $status = (string) ($args['status'] ?? 'current');
        $query = DocumentStandard::query()->whereNull('archived_at')->orderBy('sort_order');

        if ($status === 'repealed') {
            $query->where('status', DocumentStandard::STATUS_REPEALED);
        } else {
            $query->whereIn('status', [DocumentStandard::STATUS_IN_FORCE, DocumentStandard::STATUS_REFERENCED]);
        }

        $items = $query->limit(30)->get()->map(fn (DocumentStandard $row) => [
            'title' => $row->title,
            'code' => $row->code,
            'status_label' => match ($row->status) {
                DocumentStandard::STATUS_REPEALED => 'хүчингүй',
                DocumentStandard::STATUS_REFERENCED => 'журамд дурдсан',
                default => 'хүчин төгөлдөр',
            },
            'source' => $row->source_number,
        ])->all();

        return [
            'items' => $items,
            'headline' => 'Бичиг хэргийн стандарт',
        ];
    }
}
