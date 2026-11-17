<?php

namespace Tests\Unit;

use App\Support\MongolianRegister;
use PHPUnit\Framework\TestCase;

class MongolianRegisterTest extends TestCase
{
    public function test_it_parses_age_and_gender_from_register(): void
    {
        // 1985-03-15, сүүлийн өмнөх орон 1 → эр
        $parsed = MongolianRegister::parse('УБ85031512', 2026);

        $this->assertNotNull($parsed);
        $this->assertSame('УБ85031512', $parsed['register']);
        $this->assertSame('1985-03-15', $parsed['birth']);
        $this->assertSame(41, $parsed['age']);
        $this->assertSame('эр', $parsed['gender']);
    }

    public function test_odd_penultimate_digit_is_male(): void
    {
        // УХ92092534 → 1992-09-25, орон 3 сондгой → эр
        $parsed = MongolianRegister::parse('УХ92092534', 2026);

        $this->assertNotNull($parsed);
        $this->assertSame('1992-09-25', $parsed['birth']);
        $this->assertSame(34, $parsed['age']);
        $this->assertSame('эр', $parsed['gender']);
    }

    public function test_even_penultimate_digit_is_female(): void
    {
        // Сүүлийн өмнөх орон 0 → эм
        $parsed = MongolianRegister::parse('УБ99120101', 2026);

        $this->assertNotNull($parsed);
        $this->assertSame(27, $parsed['age']);
        $this->assertSame('эм', $parsed['gender']);
    }

    public function test_year_2000_plus_uses_month_offset(): void
    {
        // 2005-03-15 → сар 23 (03+20), сүүлийн өмнөх 1 → эр
        $parsed = MongolianRegister::parse('УБ05231512', 2026);

        $this->assertNotNull($parsed);
        $this->assertSame('2005-03-15', $parsed['birth']);
        $this->assertSame(21, $parsed['age']);
        $this->assertSame('эр', $parsed['gender']);
    }

    public function test_latin_letters_are_normalized(): void
    {
        $parsed = MongolianRegister::parse('UB85031512', 2026);

        $this->assertNotNull($parsed);
        $this->assertSame('УБ85031512', $parsed['register']);
    }

    public function test_invalid_register_returns_null(): void
    {
        $this->assertNull(MongolianRegister::parse('ABC'));
        $this->assertNull(MongolianRegister::parse('УБ99133101'));
    }
}
