<?php

namespace Tests\Feature;

use App\Models\TravelAssignment;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Үнэмлэхийн бичвэрийг хэвлэх хуудсан дээр нь засах.
 */
class AssignmentSheetEditTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(): TravelAssignment
    {
        return TravelAssignment::create([
            'approver' => 'chief',
            'person_name' => 'Н.Гарамжав',
            'destination' => 'Эрдэнэ сум',
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-09',
            'status' => 'approved',
        ]);
    }

    public function test_an_editor_sees_the_editable_text(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('assignments.sheet', $this->assignment()))
            ->assertOk()
            ->assertSee('contenteditable="true"', false)
            ->assertSee('Хадгалах');
    }

    public function test_a_viewer_cannot_edit_the_text(): void
    {
        $viewer = User::factory()->create(['is_admin' => false]);

        UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'assignments',
            'level' => 'view',
        ]);

        $row = $this->assignment();

        $this->actingAs($viewer->fresh())
            ->get(route('assignments.sheet', $row))
            ->assertOk()
            ->assertDontSee('contenteditable="true"', false);

        $this->actingAs($viewer->fresh())
            ->patch(route('assignments.sheet.text', $row), ['certificate_text' => 'Оролдлого'])
            ->assertForbidden();

        $this->assertNull($row->fresh()->certificate_text);
    }

    public function test_the_text_can_be_saved_from_the_sheet(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->assignment();

        $this->actingAs($admin)
            ->from(route('assignments.sheet', $row))
            ->patch(route('assignments.sheet.text', $row), [
                'certificate_text' => 'Гараар засварласан бичвэр.',
            ])
            ->assertRedirect();

        $this->assertSame('Гараар засварласан бичвэр.', $row->fresh()->certificate_text);

        // Хуудсан дээр нь тэр бичвэр гарна.
        $this->actingAs($admin)
            ->get(route('assignments.sheet', $row))
            ->assertSee('Гараар засварласан бичвэр.');
    }

    public function test_clearing_the_text_brings_back_the_built_sentence(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->assignment(['certificate_text' => 'Түр бичвэр.']);

        $this->actingAs($admin)
            ->from(route('assignments.sheet', $row))
            ->patch(route('assignments.sheet.text', $row), ['certificate_text' => '   ']);

        $this->assertNull($row->fresh()->certificate_text);

        // Хоосон болсон тул бүртгэлээс өгүүлбэр нь дахин бүрдэнэ.
        $this->actingAs($admin)
            ->get(route('assignments.sheet', $row))
            ->assertSee('ажиллуулахаар томилов.');
    }
}
