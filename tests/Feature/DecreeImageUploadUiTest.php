<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Захирамжид зураг оруулах — хөтөч дээр нь PDF болгоно.
 */
class DecreeImageUploadUiTest extends TestCase
{
    public function test_the_picker_accepts_images(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Modules/Decrees.vue'));

        $this->assertStringContainsString('image/png,image/jpeg', $page);
        $this->assertStringContainsString('.png,.jpg,.jpeg', $page);
    }

    public function test_images_are_turned_into_pdf_before_sending(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Modules/Decrees.vue'));

        $this->assertStringContainsString('imageToPdf', $page);

        $helper = file_get_contents(resource_path('js/Support/pdfCompress.js'));

        // A4-д багтаан PDF болгож, хэмжээг нь хязгаарт оруулна.
        $this->assertStringContainsString('export async function imageToPdf', $helper);
        $this->assertStringContainsString('buildPdfFromJpegs', $helper);
    }
}
