<?php

namespace Tests;

use App\Models\Role;
use App\Services\Ai\AiSettings;
use App\Support\ModuleOrder;
use App\Support\ModuleVisibility;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Хүсэлт бүрд нэг л удаа уншихаар хийсэн түр санах ойг тест бүрийн
     * өмнө хаяна. Тестүүд нэг процесст ажилладаг тул өмнөх тестийн
     * ролийн загвар дараагийнхад үлдэхээс сэргийлнэ.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Role::forgetOrdered();
        AiSettings::forgetDisplayName();
        ModuleVisibility::forget();
        ModuleOrder::forget();
    }
}
