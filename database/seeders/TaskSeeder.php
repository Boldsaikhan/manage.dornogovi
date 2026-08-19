<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskSource;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Эх сурвалжууд — өмнөх статик сайтын `SECTIONS`.
     */
    private const SOURCES = [
        1 => ['name' => '2026.07.09 — Аймгийн Засаг даргын үүрэг, чиглэл', 'period' => '07.09'],
        2 => ['name' => '2026.07.28 — Өвөлжилт, хаваржилтын бэлтгэл (2026-2027 он)', 'period' => '07.28-09.28'],
    ];

    /**
     * Хариуцагч нь байгууллага/хэлтэс өөрөө бол түүнийг шууд хэлтэс болгоно.
     * Үгийн ТӨГСГӨЛӨӨР шалгана — "Ц.Сансармаа" мэтийн хүний нэрэнд санамсаргүй
     * таарахаас сэргийлнэ.
     */
    private const ORG_WORDS = [
        'хэлтэс', 'газар', 'ххк', 'хк', 'здтг', 'хороо', 'нэгтгэл',
        'алба', 'төв', 'сургууль', 'эмнэлэг',
    ];

    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('data/uureg_tasks.json')), true);

        $sources = [];
        foreach (self::SOURCES as $key => $source) {
            $sources[$key] = TaskSource::updateOrCreate(
                ['name' => $source['name']],
                ['period' => $source['period'], 'sort_order' => $key],
            );
        }

        foreach ($rows as $index => $row) {
            Task::updateOrCreate(
                [
                    'task_source_id' => $sources[$row['sec']]->id,
                    'text' => $row['text'],
                ],
                [
                    'period' => $row['period'],
                    'responsible' => $row['responsible'],
                    'collaborator' => $row['collaborator'],
                    'sector' => $row['sector'],
                    'department' => $this->autoDepartment($row['responsible']),
                    'indicator' => $row['indicator'],
                    'baseline' => $row['baseline'],
                    'target' => $row['target'],
                    'note' => $row['note'],
                    'sort_order' => $index + 1,
                ],
            );
        }
    }

    /**
     * Үндсэн (эхний) хариуцагч байгууллагын нэр мэт бол түүнийг хэлтэс болгож
     * буцаана. Хүний нэр бол хоосон — хэрэглэгч дараа нь өөрөө оноож өгнө.
     */
    private function autoDepartment(?string $responsible): ?string
    {
        $owner = trim(explode(',', (string) $responsible)[0]);

        if ($owner === '') {
            return null;
        }

        $tokens = preg_split('/[\s,]+/u', mb_strtolower(str_replace(['"', "'", '«', '»', '(', ')'], ' ', $owner)));

        foreach ($tokens as $token) {
            foreach (self::ORG_WORDS as $word) {
                if ($token !== '' && str_ends_with($token, $word)) {
                    return $owner;
                }
            }
        }

        return null;
    }
}
