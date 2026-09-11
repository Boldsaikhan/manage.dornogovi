<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Заавраргүй <template> таг нь браузерт харагдахгүй элемент үүсгэдэг.
 *
 * Ийм тагийн доторх талбарууд зурагдахгүй, маягт хоосон илгээгдэж,
 * товч ажиллахгүй мэт харагддаг тул урьдчилан хориглоно.
 */
class VueTemplateGuardTest extends TestCase
{
    public function test_no_component_has_a_directiveless_template_tag(): void
    {
        $offenders = [];

        foreach ($this->vueFiles() as $file) {
            $lines = file($file);

            // Эхний мөр нь бүрэлдэхүүний үндсэн <template> — түүнийг алгасна.
            foreach ($lines as $index => $line) {
                if (! preg_match('/^\s+<template\s*>\s*$/', $line)) {
                    continue;
                }

                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).':'.($index + 1);
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Заавраргүй <template> олдлоо. v-if / v-for / v-slot нэмэх, эсвэл <div> болгоно уу:'],
            $offenders,
        )));
    }

    /** @return list<string> */
    private function vueFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'vue') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
