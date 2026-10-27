<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Windows ширээний програмын суулгац.
 *
 * Суулгацыг `desktop-app` төслөөс (npm run dist) бэлдээд админ нь
 * системээр дамжуулан байршуулна — файл нь `storage/app/desktop/` дотор
 * хадгалагдах тул git-д ордоггүй.
 *
 * Суулгац ~80 МБ байдаг ба nginx-ийн биеийн хязгаар 32 МБ тул хөтчөөс
 * хэсэг хэсгээр нь илгээж, сервер дээр залгана.
 */
class DesktopAppController extends Controller
{
    public const FILE = 'Manage-Dornogovi-Setup.exe';

    /**
     * Нэг хэсгийн дээд хэмжээ (КБ).
     *
     * nginx (32 МБ) төдийгүй PHP-ийн анхдагч post_max_size (8 МБ)-д
     * багтаахын тулд жижиг байлгана — хөтөч 4 МБ-аар хуваан илгээнэ.
     */
    private const CHUNK_MAX_KB = 6144;

    /** Дуусгаагүй хэсгүүдийг ийм хугацааны дараа хаяна. */
    private const STALE_SECONDS = 3600;

    /** Админы байршуулсан суулгац (байвал түүнийг түгээнэ). */
    public static function path(): string
    {
        return storage_path('app/desktop/'.self::FILE);
    }

    /**
     * Кодтой хамт тараагддаг суулгац.
     *
     * Сервер рүү гараар файл хуулах шаардлагагүй байхын тулд суулгацыг
     * агуулахад хамт хадгална. Админ шинээр байршуулбал түүнийг дээр нь
     * тавина.
     */
    public static function bundledPath(): string
    {
        return base_path('resources/desktop/'.self::FILE);
    }

    /** Түгээх суулгацын зам — байхгүй бол null. */
    public static function servedPath(): ?string
    {
        foreach ([self::path(), self::bundledPath()] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Цэсэнд харуулах төлөв.
     *
     * @return array{available: bool, size: int|null, updated_at: string|null}
     */
    public static function status(): array
    {
        $path = self::servedPath();

        if ($path === null) {
            return ['available' => false, 'size' => null, 'updated_at' => null];
        }

        clearstatcache(true, $path);

        return [
            'available' => true,
            'size' => (int) filesize($path),
            'updated_at' => date('c', (int) filemtime($path)),
        ];
    }

    public function download(): BinaryFileResponse|RedirectResponse
    {
        $path = self::servedPath();

        if ($path === null) {
            return back()->with('warning', 'Суулгац хараахан байршуулаагүй байна. Системийн админд хандана уу.');
        }

        return response()->download($path, self::FILE, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * Админ суулгацын нэг хэсгийг илгээнэ.
     *
     * Сүүлийн хэсэг ирэхэд бүгдийг залгаж, суулгацыг солино.
     */
    public function upload(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        $data = $request->validate([
            'chunk' => ['required', 'file', 'max:'.self::CHUNK_MAX_KB],
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9]{8,64}$/'],
            'index' => ['required', 'integer', 'min:0', 'max:999'],
            'total' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $dir = dirname(self::path());
        $partDir = $dir.'/parts';

        if (! is_dir($partDir) && ! mkdir($partDir, 0775, true) && ! is_dir($partDir)) {
            abort(500, 'Түр хавтас үүсгэж чадсангүй.');
        }

        $this->clearStaleParts($partDir);

        $part = $partDir.'/'.$data['upload_id'].'.part';

        // Эхний хэсэг өмнөх оролдлогыг цэвэрлэнэ.
        if ($data['index'] === 0) {
            @unlink($part);
        }

        $in = fopen($request->file('chunk')->getRealPath(), 'rb');
        $out = fopen($part, 'ab');

        if ($in === false || $out === false) {
            abort(500, 'Хэсгийг бичиж чадсангүй.');
        }

        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        if ($data['index'] + 1 < $data['total']) {
            return response()->json([
                'done' => false,
                'received' => $data['index'] + 1,
                'total' => $data['total'],
            ]);
        }

        // Сүүлийн хэсэг — .exe мөн эсэхийг гарын үсгээр нь шалгана.
        $signature = (string) file_get_contents($part, false, null, 0, 2);

        if ($signature !== 'MZ') {
            @unlink($part);

            return response()->json([
                'message' => 'Зөвхөн Windows суулгац (.exe) байршуулна.',
            ], 422);
        }

        if (! @rename($part, self::path())) {
            @unlink($part);
            abort(500, 'Суулгацыг хадгалж чадсангүй.');
        }

        return response()->json([
            'done' => true,
            ...self::status(),
        ]);
    }

    /** Дуусгаагүй хуучин хэсгүүдийг цэвэрлэнэ. */
    private function clearStaleParts(string $partDir): void
    {
        foreach (glob($partDir.'/*.part') ?: [] as $file) {
            if (time() - (int) filemtime($file) > self::STALE_SECONDS) {
                @unlink($file);
            }
        }
    }
}
