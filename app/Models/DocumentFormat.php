<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Албан хэрэг хөтлөлтийн хуудасны стандарт (A4, A5).
 *
 * Системээс гаргаж байгаа бүх Word файл эдгээр утгыг ашиглана.
 */
class DocumentFormat extends Model
{
    /** 1 мм = 56.6929 twip (1 инч = 1440 twip). */
    private const TWIP_PER_MM = 56.6929;

    protected $fillable = [
        'key', 'label', 'width_mm', 'height_mm',
        'margin_top_mm', 'margin_right_mm', 'margin_bottom_mm', 'margin_left_mm',
        'font_name', 'font_size_pt', 'line_spacing', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'font_size_pt' => 'float',
            'line_spacing' => 'float',
        ];
    }

    public static function defaultFormat(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->orderBy('id')->first();
    }

    public static function toTwip(float $mm): int
    {
        return (int) round($mm * self::TWIP_PER_MM);
    }

    /** Хуудасны өргөн (twip) — хэвтээ үед талуудыг сольж өгнө. */
    public function pageWidthTwip(bool $landscape = false): int
    {
        return self::toTwip($landscape ? $this->height_mm : $this->width_mm);
    }

    public function pageHeightTwip(bool $landscape = false): int
    {
        return self::toTwip($landscape ? $this->width_mm : $this->height_mm);
    }

    /** Бичвэр байрлах цэвэр өргөн (twip) — хүснэгтийн багана хуваарилахад хэрэглэнэ. */
    public function contentWidthTwip(bool $landscape = false): int
    {
        return max(
            1000,
            $this->pageWidthTwip($landscape)
                - self::toTwip($this->margin_left_mm)
                - self::toTwip($this->margin_right_mm)
        );
    }

    /** Word-ийн хагас цэгээр илэрхийлсэн фонтын хэмжээ. */
    public function fontHalfPoints(): int
    {
        return (int) round($this->font_size_pt * 2);
    }

    /** Мөр хоорондын зай — 240 = нэг мөр. */
    public function lineSpacingTwip(): int
    {
        return (int) round(max(1.0, $this->line_spacing) * 240);
    }
}
