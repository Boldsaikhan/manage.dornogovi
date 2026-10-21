<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Тайлбар (tooltip) нь хулганы дээд талд гарах бүрэлдэхүүн.
 */
class HoverTipTest extends TestCase
{
    public function test_the_layout_mounts_the_tooltip_layer(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString('HoverTip', $layout);
    }

    public function test_the_tooltip_sits_above_the_element(): void
    {
        $source = file_get_contents(resource_path('js/Components/HoverTip.vue'));

        // Элементийн дээд ирмэгээс дээш байрлуулна.
        $this->assertStringContainsString('window.innerHeight - rect.top', $source);

        // Хөтчийн өөрийн тайлбар давхар гарахгүй.
        $this->assertStringContainsString("removeAttribute('title')", $source);
        $this->assertStringContainsString("setAttribute('title'", $source);
    }
}
