<?php

namespace Tests\Unit;

use App\Support\MongolianRegister;
use PHPUnit\Framework\TestCase;

class MongolianRegisterTest extends TestCase
{
    public function test_it_parses_age_and_gender_from_register(): void
    {
        // 1985-03-15, дараалал 12 → эм (тэгш)
        $parsed = MongolianRegister::parse('УБ85031512', 2026);

        $this->assertNotNull($parsed);
        $this->assertSame('УБ85031512', $parsed['register']);
        $this->assertSame('1985-03-15', $parsed['birth']);
        $this->assertSame(41, $parsed['age']);
        $this->assertSame('эм', $parsed['gender']);
    }

    public function test_odd_sequence_is_male(): void
    {
        $parsed = MongolianRegister::parse('УБ99120101', 2026);

        $this->assertNotNull($parsed);
        $this->assertSame(27, $parsed['age']);
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
