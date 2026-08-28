<?php

namespace App\Support;

use App\Models\User;

/**
 * Excel: Овог | Нэр | Гар утас | И-мэйл — албан хаагчийн жагсаалт.
 */
class PhoneDirectoryStaffListParser
{
    /**
     * @return list<array{surname: string, given: string, mobile: string, email: string}>
     */
    public function parse(string $path): array
    {
        return $this->fromRows(app(XlsxTableReader::class)->rows($path));
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return list<array{surname: string, given: string, mobile: string, email: string}>
     */
    public function fromRows(array $rows): array
    {
        $map = null;
        $people = [];

        foreach ($rows as $row) {
            $cells = array_map(
                fn ($c) => trim((string) preg_replace('/\s+/u', ' ', (string) $c)),
                $row
            );

            if ($this->isHeader($cells)) {
                $map = $this->columnMap($cells);

                continue;
            }

            if ($map === null) {
                continue;
            }

            $surname = $cells[$map['surname']] ?? '';
            $given = $cells[$map['given']] ?? '';
            $mobile = $cells[$map['mobile']] ?? '';
            $email = strtolower($cells[$map['email']] ?? '');

            if ($surname === '' || $given === '') {
                continue;
            }

            $people[] = [
                'surname' => $surname,
                'given' => $given,
                'mobile' => User::normalizePhone($mobile) ?? '',
                'email' => $email,
            ];
        }

        return $people;
    }

    public function looksLikeStaffList(array $rows): bool
    {
        foreach (array_slice($rows, 0, 5) as $row) {
            if ($this->isHeader($row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isHeader(array $cells): bool
    {
        $joined = mb_strtolower(implode(' ', $cells));

        return str_contains($joined, 'овог') && (str_contains($joined, 'нэр') || str_contains($joined, 'утас'));
    }

    /**
     * @param  array<int, string>  $cells
     * @return array{surname: int, given: int, mobile: int, email: int}
     */
    private function columnMap(array $cells): array
    {
        $map = ['surname' => 0, 'given' => 1, 'mobile' => 2, 'email' => 3];

        foreach ($cells as $i => $cell) {
            $v = mb_strtolower($cell);

            if (str_contains($v, 'овог')) {
                $map['surname'] = $i;
            } elseif ($v === 'нэр' || str_contains($v, 'нэр')) {
                $map['given'] = $i;
            }

            if (str_contains($v, 'утас')) {
                $map['mobile'] = $i;
            }
            if (str_contains($v, 'мейл') || str_contains($v, 'мэйл') || str_contains($v, 'email')) {
                $map['email'] = $i;
            }
        }

        return $map;
    }
}
