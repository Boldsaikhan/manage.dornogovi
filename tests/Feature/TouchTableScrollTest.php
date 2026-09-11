<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Мэдрэгчтэй дэлгэц дээр хүснэгтийг хуруугаар гүйлгэх.
 *
 * iPad дээр хүснэгтийн доод талын нимгэн зурвасыг хуруугаар барих
 * боломжгүй тул биеийг нь өөрийг нь хөндлөн гүйдэг болгосон.
 */
class TouchTableScrollTest extends TestCase
{
    public function test_the_scroll_body_does_not_lock_horizontal_scrolling(): void
    {
        $component = file_get_contents(resource_path('js/Components/TableScrollViewport.vue'));

        // Tailwind-ийн энэ анги нь CSS дэх зохицуулалтыг дардаг.
        $this->assertStringNotContainsString('overflow-x-hidden', $component);
        $this->assertStringContainsString('ui-table-scroll-body', $component);
    }

    public function test_the_stylesheet_opens_horizontal_scrolling_on_touch(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('@media (hover: none) and (pointer: coarse)', $css);

        $touchBlock = substr($css, strpos($css, '@media (hover: none) and (pointer: coarse)'));
        $touchBlock = substr($touchBlock, 0, 900);

        $this->assertStringContainsString('overflow-x: auto', $touchBlock);
        // Хуруугаар барихад хэцүү нимгэн зурвасыг нууна.
        $this->assertStringContainsString('.ui-table-hscroll', $touchBlock);
    }
}
