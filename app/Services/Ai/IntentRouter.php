<?php

namespace App\Services\Ai;

/**
 * Монгол хэлний энгийн intent / entity ялгалт.
 * LLM байхгүй үед ч tool сонгоход ашиглана.
 */
class IntentRouter
{
    /**
     * @return array{intent: string, tools: array<int, array{name: string, args: array}>}
     */
    public function route(string $message): array
    {
        $q = mb_strtolower(trim($message));

        if ($this->isInjection($q)) {
            return ['intent' => 'blocked', 'tools' => []];
        }

        if ($this->matches($q, ['товч', 'өнөөдөр', 'анхаарах', 'нэгтгэл', 'briefing', 'dashboard'])) {
            return ['intent' => 'briefing', 'tools' => [['name' => 'get_dashboard_briefing', 'args' => []]]];
        }

        if ($this->matches($q, ['чөлөө үүсг', 'чөлөө бэлд', 'чөлөөний хүсэлт гарга', 'чөлөө авах хүсэлт'])) {
            $args = $this->extractLeaveDates($q);

            return ['intent' => 'prepare_leave', 'tools' => [['name' => 'prepare_leave_request', 'args' => $args]]];
        }

        if ($this->matches($q, ['миний чөлөө', 'миний амралт'])) {
            return ['intent' => 'my_leave', 'tools' => [['name' => 'get_my_leave', 'args' => []]]];
        }

        if ($this->matches($q, ['чөлөө', 'амралт'])) {
            $args = ['this_month' => $this->matches($q, ['энэ сар', 'сарын'])];
            if ($this->matches($q, ['хүлээгдэж', 'pending'])) {
                $args['status'] = 'pending';
            }

            return ['intent' => 'leaves', 'tools' => [['name' => 'search_leaves', 'args' => $args]]];
        }

        if ($this->matches($q, ['миний томилолт', 'томилолтоо', 'миний томилол'])) {
            return [
                'intent' => 'my_trips',
                'tools' => [['name' => 'get_my_business_trips', 'args' => [
                    'this_month' => $this->matches($q, ['энэ сар', 'сарын']),
                ]]],
            ];
        }

        if ($this->matches($q, ['томилолт'])) {
            return [
                'intent' => 'trips',
                'tools' => [['name' => 'get_my_business_trips', 'args' => [
                    'this_month' => $this->matches($q, ['энэ сар', 'сарын']),
                ]]],
            ];
        }

        if ($this->matches($q, ['тайлан', 'статистик', 'гүйцэтгэл']) && $this->matches($q, ['үүрэг', 'даалгавар'])) {
            return ['intent' => 'task_report', 'tools' => [['name' => 'get_task_report', 'args' => []]]];
        }

        if ($this->matches($q, ['хугацаа хэтэрсэн', 'дуусаагүй үүрэг', 'overdue'])) {
            return ['intent' => 'overdue_tasks', 'tools' => [['name' => 'get_overdue_tasks', 'args' => []]]];
        }

        if ($this->matches($q, ['миний үүрэг', 'миний даалгавар'])) {
            return ['intent' => 'my_tasks', 'tools' => [['name' => 'get_my_tasks', 'args' => []]]];
        }

        if ($this->matches($q, ['үүрэг', 'даалгавар', 'төлөвлөгөө ханг', 'бэлтгэл ажил'])) {
            $args = [];
            if ($this->matches($q, ['чиглэл'])) {
                $args['kind'] = 'directive';
            }
            if ($this->matches($q, ['бэлтгэл', 'төлөвлөгөө'])) {
                $args['kind'] = $args['kind'] ?? 'prep_plan';
            }

            return ['intent' => 'tasks', 'tools' => [['name' => 'search_tasks', 'args' => $args]]];
        }

        if ($this->matches($q, ['захирамж', 'тушаал'])) {
            $args = [
                'q' => $this->extractSearchPhrase($q, [
                    'захирамжийн', 'тушаалын', 'захирамж', 'тушаал',
                    'бүртгэлт', 'бүртгэл', 'мэдээлэл', 'жагсаалт', 'жагсаа',
                    'харуул', 'гаргаж өг', 'гарга', 'хай', 'ол', 'өг',
                    'байгаа', 'сүүлийн', 'хоног', 'хоногийн',
                ]),
            ];
            if (preg_match('/(20\d{2})/u', $q, $m)) {
                $args['year'] = (int) $m[1];
            }
            if ($this->matches($q, ['30 хоног', 'сүүлийн 30'])) {
                $args['days'] = 30;
            }
            if ($this->matches($q, ['захирамж']) && ! $this->matches($q, ['тушаал'])) {
                $args['kind'] = 'zahiramj_';
            } elseif ($this->matches($q, ['тушаал']) && ! $this->matches($q, ['захирамж'])) {
                $args['kind'] = 'tushaal_';
            }

            return ['intent' => 'orders', 'tools' => [['name' => 'search_orders', 'args' => $args]]];
        }

        if ($this->matches($q, ['утасны жагсаалт', 'утасны дугаар', 'гар утас', 'утас', 'холбоо барих'])) {
            return ['intent' => 'phone_directory', 'tools' => [['name' => 'search_phone_directory', 'args' => [
                'q' => $this->extractSearchPhrase($q, [
                    'утасны жагсаалтаас', 'утасны жагсаалт', 'утасны дугаараас', 'утасны дугаар',
                    'гар утас', 'утасны', 'утас', 'холбоо барих',
                    'олж өг', 'олж', 'өгөөч', 'хайж', 'хай', 'харуул',
                    'жагсаалтаас', 'жагсаалт', 'жагсаа', 'аас', 'ол', 'өг',
                ]),
            ]]]];
        }

        if ($this->matches($q, ['журам', 'стандарт'])) {
            return ['intent' => 'documents', 'tools' => [['name' => 'search_documents', 'args' => [
                'q' => $this->extractSearchPhrase($q, ['журам', 'стандарт', 'харуул', 'хай']),
            ]]]];
        }

        if ($this->matches($q, ['архив'])) {
            return ['intent' => 'archive', 'tools' => [['name' => 'search_archive', 'args' => [
                'q' => $this->extractSearchPhrase($q, ['архив', 'харуул', 'хай']),
            ]]]];
        }

        if ($this->matches($q, ['гэрээ'])) {
            return ['intent' => 'contracts', 'tools' => [['name' => 'search_contracts', 'args' => [
                'q' => $this->extractSearchPhrase($q, ['гэрээ', 'харуул', 'хай']),
            ]]]];
        }

        if ($this->matches($q, ['төлөвлөгөө'])) {
            return ['intent' => 'plans', 'tools' => [['name' => 'search_plans', 'args' => [
                'active' => $this->matches($q, ['идэвхтэй']),
            ]]]];
        }

        if ($this->matches($q, ['хурал', 'уулзалт'])) {
            return ['intent' => 'meetings', 'tools' => [['name' => 'search_meetings', 'args' => [
                'today' => $this->matches($q, ['өнөөдөр']),
            ]]]];
        }

        if ($this->matches($q, ['тайлан'])) {
            return ['intent' => 'reports', 'tools' => [['name' => 'search_reports', 'args' => []]]];
        }

        if ($this->matches($q, ['хэлтэс', 'албан хаагч', 'ажилтан', 'хэрэглэгч'])) {
            if ($this->matches($q, ['хэлтэс'])) {
                return ['intent' => 'departments', 'tools' => [['name' => 'search_departments', 'args' => [
                    'q' => $this->extractSearchPhrase($q, ['хэлтэс', 'харуул', 'хай', 'жагсаа']),
                ]]]];
            }

            return ['intent' => 'employees', 'tools' => [['name' => 'search_employees', 'args' => [
                'q' => $this->extractSearchPhrase($q, ['албан хаагч', 'ажилтан', 'хэрэглэгч', 'харуул', 'хай']),
            ]]]];
        }

        if ($this->matches($q, ['статистик', 'тоо', 'хэдэн'])) {
            return ['intent' => 'stats', 'tools' => [['name' => 'get_system_statistics', 'args' => []]]];
        }

        // Ерөнхий асуултад товч мэдээлэл + статистик
        return [
            'intent' => 'general',
            'tools' => [
                ['name' => 'get_dashboard_briefing', 'args' => []],
            ],
        ];
    }

    private function isInjection(string $q): bool
    {
        $patterns = [
            'system prompt',
            'систем промпт',
            'ignore previous',
            'өмнөх зааврыг үл тоо',
            'бүх database',
            'select * from',
            'api key',
            'нууц түлхүүр гарга',
            'prompt-оо харуул',
            'промптоо харуул',
        ];

        return $this->matches($q, $patterns);
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($n !== '' && str_contains($haystack, mb_strtolower($n))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $stop
     */
    private function extractSearchPhrase(string $q, array $stop): string
    {
        $clean = $q;
        // Урт stop үгсээ эхэнд нь хасна («захирамжийн» → «захирамж»-аас өмнө).
        usort($stop, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($stop as $s) {
            $clean = str_replace(mb_strtolower($s), ' ', $clean);
        }
        $clean = preg_replace('/\s+/u', ' ', trim($clean)) ?? '';

        // Жил, тоо зэргийг үлдээнэ; хэт богино бол хоосон.
        return mb_strlen($clean) >= 2 ? $clean : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function extractLeaveDates(string $q): array
    {
        $args = [];
        if (preg_match_all('/(20\d{2})[.\-\/](\d{1,2})[.\-\/](\d{1,2})/u', $q, $m, PREG_SET_ORDER)) {
            if (isset($m[0])) {
                $args['start_date'] = sprintf('%s-%02d-%02d', $m[0][1], (int) $m[0][2], (int) $m[0][3]);
            }
            if (isset($m[1])) {
                $args['end_date'] = sprintf('%s-%02d-%02d', $m[1][1], (int) $m[1][2], (int) $m[1][3]);
            }
        }

        return $args;
    }
}
