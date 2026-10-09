<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Наалдсан хүснэгтийн толгой нь толгойн самбараас доогуур байх.
 *
 * Эс бөгөөс мэдэгдэл, хэрэглэгчийн цэс зэрэг нь хүснэгтийн толгойн ард
 * нуугдана.
 */
class StickyHeaderLayerTest extends TestCase
{
    /** Толгойн самбарын давхарга. */
    private const HEADER_LAYER = 20;

    public function test_the_app_header_still_sits_at_twenty(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString('<header class="sticky top-0 z-20', $layout);
    }

    public function test_no_sticky_table_layer_reaches_the_header(): void
    {
        $offenders = [];

        foreach ($this->vueFiles() as $file) {
            foreach (file($file) as $number => $line) {
                // Хуудас өөрийн толгойн самбартай бол (нэвтрэхийн өмнөх
                // хуудас) энэ дүрэм хамаарахгүй.
                if (str_contains($line, '<header')) {
                    continue;
                }

                // «sticky … z-20» — толгойн самбартай тэнцэх буюу түүнээс дээш.
                preg_match_all('/sticky[^"\']*\sz-(\d+)/', $line, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    if ((int) $match[1] >= self::HEADER_LAYER) {
                        $offenders[] = basename($file).':'.($number + 1).' — '.$match[0];
                    }
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Наалдсан элемент толгойн самбарыг (z-20) дарж байна:'],
            $offenders,
        )));
    }

    public function test_the_sticky_head_script_stays_below_the_header(): void
    {
        foreach ([
            'js/Pages/Modules/Decrees.vue',
            'js/Pages/Modules/ResourceIndex.vue',
            'js/Pages/Modules/AnnualLeaves.vue',
        ] as $path) {
            $source = file_get_contents(resource_path($path));

            $this->assertStringContainsString('String(14 - index)', $source, $path);
            $this->assertStringNotContainsString('String(30 - index)', $source, $path);
        }
    }

    /** @return list<string> */
    private function vueFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js/Pages'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'vue') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
