<?php

namespace Tests\Unit;

use App\Support\PhoneDirectoryDocxParser;
use ReflectionMethod;
use Tests\TestCase;

class PhoneDirectorySumColumnOrderTest extends TestCase
{
    public function test_sum_org_maps_position_then_name(): void
    {
        $parser = app(PhoneDirectoryDocxParser::class);

        $setOrder = new ReflectionMethod(PhoneDirectoryDocxParser::class, 'columnOrder');
        // columnOrder is private property — use toEntry via reflection after setting order.

        $ref = new \ReflectionClass($parser);
        $prop = $ref->getProperty('columnOrder');
        $prop->setAccessible(true);
        $prop->setValue($parser, 'position_first');

        $method = $ref->getMethod('toEntry');
        $method->setAccessible(true);

        $entry = $method->invoke($parser, ['1', 'ИТХ-ын дарга', 'А.Жаргалбаяр', '88067905', '']);

        $this->assertSame('А.Жаргалбаяр', $entry['person_name']);
        $this->assertSame('ИТХ-ын дарга', $entry['position']);
        $this->assertSame('88067905', $entry['office_phone']);
    }

    public function test_auto_swap_when_name_and_position_inverted(): void
    {
        $parser = app(PhoneDirectoryDocxParser::class);
        $ref = new \ReflectionClass($parser);
        $prop = $ref->getProperty('columnOrder');
        $prop->setAccessible(true);
        $prop->setValue($parser, 'name_first');

        $method = $ref->getMethod('toEntry');
        $method->setAccessible(true);

        $entry = $method->invoke($parser, ['ИТХ-ын дарга', 'Н.Бат-Эрдэнэ', '99112233', null]);

        $this->assertSame('Н.Бат-Эрдэнэ', $entry['person_name']);
        $this->assertSame('ИТХ-ын дарга', $entry['position']);
    }
}
