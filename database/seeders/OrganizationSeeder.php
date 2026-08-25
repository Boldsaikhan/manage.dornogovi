<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DocumentStandard;
use App\Models\Training;
use App\Models\User;
use App\Support\ModuleAccess;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Засаг даргын Тамгын газар', 'code' => 'ZDTG', 'sort_order' => 1],
            ['name' => 'Хууль, эрх зүйн хэлтэс', 'code' => 'HEZ', 'sort_order' => 2],
            ['name' => 'Санхүү, төсвийн хэлтэс', 'code' => 'ST', 'sort_order' => 3],
            ['name' => 'Нийгмийн бодлогын хэлтэс', 'code' => 'NB', 'sort_order' => 4],
            ['name' => 'Хөрөнгө оруулалт, хөгжлийн хэлтэс', 'code' => 'HOH', 'sort_order' => 5],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(['code' => $dept['code']], $dept + ['is_active' => true]);
        }

        $admin = User::query()->where('email', env('ADMIN_EMAIL', 'admin@dornogovi.gov.mn'))->first();
        if ($admin) {
            $admin->update([
                'department_id' => Department::query()->where('code', 'ZDTG')->value('id'),
                'position' => 'Системийн админ',
                'is_department_head' => true,
                'phone' => $admin->phone ?: '99001122',
            ]);

            foreach (ModuleAccess::definitions() as $module) {
                if ($module['key'] === 'systems') {
                    continue;
                }
                $admin->modulePermissions()->updateOrCreate(
                    ['module_key' => $module['key']],
                    ['level' => 'manage']
                );
            }
        }

        DocumentStandard::query()->firstOrCreate(
            ['title' => 'Албан бичгийн үндсэн стандарт'],
            ['body' => "Албан бланк, гарчиг, дугаарлалт, гарын үсгийн байрлал зэргийг энд тайлбарлана.", 'sort_order' => 1]
        );

        Training::query()->firstOrCreate(
            ['title' => 'Шинэ албан хаагчийн гарын авлага'],
            [
                'body' => "Системд хэрхэн нэвтэрч, модулиудыг хэрхэн ашиглахыг энд бичнэ.",
                'for_new_hires' => true,
                'sort_order' => 1,
            ]
        );
    }
}
