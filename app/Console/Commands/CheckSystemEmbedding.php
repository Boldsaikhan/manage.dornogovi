<?php

namespace App\Console\Commands;

use App\Models\System;
use App\Services\EmbedChecker;
use Illuminate\Console\Command;

class CheckSystemEmbedding extends Command
{
    protected $signature = 'systems:check-embed {slug? : Зөвхөн нэг системийг шалгах}';

    protected $description = 'Систем бүр iframe дотор нээгдэх боломжтой эсэхийг шалгаж тэмдэглэнэ';

    public function handle(EmbedChecker $checker): int
    {
        $query = System::query();

        if ($slug = $this->argument('slug')) {
            $query->where('slug', $slug);
        }

        $systems = $query->orderBy('sort_order')->get();

        if ($systems->isEmpty()) {
            $this->warn('Систем олдсонгүй.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($systems as $system) {
            $checker->refresh($system);

            $rows[] = [
                $system->name,
                match ($system->is_embeddable) {
                    true => 'Тийм',
                    false => 'Үгүй',
                    default => '?',
                },
                $system->embed_blocked_by ?? '—',
            ];
        }

        $this->table(['Систем', 'Дотор нээгдэх', 'Шалтгаан'], $rows);

        return self::SUCCESS;
    }
}
