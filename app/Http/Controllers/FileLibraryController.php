<?php

namespace App\Http\Controllers;

use App\Models\LibraryFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File as FileRule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Хуучин «Файлын сан» API — одоо зөвхөн админ.
 * Үзэх UI: Системийн тохиргоо → Файлууд.
 */
class FileLibraryController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        return redirect()->route('admin.systems.index', ['tab' => 'files']);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $extensions = config('file_library.extensions', []);
        $maxKb = (int) config('file_library.max_kb', 20480);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'string', 'max:120', 'regex:/^[\p{L}\p{N}\s\-_.\/]+$/u'],
            'file' => [
                'required',
                FileRule::types($extensions)->max($maxKb),
            ],
        ], [], [
            'title' => 'гарчиг',
            'folder' => 'хавтас',
            'file' => 'файл',
        ]);

        $upload = $request->file('file');
        $extension = strtolower((string) $upload->getClientOriginalExtension());

        abort_unless(in_array($extension, $extensions, true), 422);

        $baseFolder = trim((string) config('file_library.folder', 'file-library'), '/');
        $storedPath = $upload->store($baseFolder, 'local');

        if (! $this->isSafeStoredPath($storedPath)) {
            Storage::disk('local')->delete($storedPath);

            return back()->with('error', 'Файлыг хадгалах боломжгүй.');
        }

        $folder = filled($data['folder'] ?? null)
            ? trim(str_replace(['\\', '..'], ['/', ''], (string) $data['folder']), '/')
            : null;

        if ($folder === '') {
            $folder = null;
        }

        LibraryFile::query()->create([
            'title' => filled($data['title'] ?? null) ? trim((string) $data['title']) : null,
            'original_name' => $upload->getClientOriginalName(),
            'path' => $storedPath,
            'mime' => $upload->getClientMimeType() ?: $upload->getMimeType(),
            'size' => (int) $upload->getSize(),
            'folder' => $folder,
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.systems.index', ['tab' => 'files'])
            ->with('success', 'Файл байршууллаа.');
    }

    public function update(Request $request, LibraryFile $libraryFile): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'string', 'max:120', 'regex:/^[\p{L}\p{N}\s\-_.\/]+$/u'],
        ], [], [
            'title' => 'гарчиг',
            'folder' => 'хавтас',
        ]);

        $folder = filled($data['folder'] ?? null)
            ? trim(str_replace(['\\', '..'], ['/', ''], (string) $data['folder']), '/')
            : null;

        if ($folder === '') {
            $folder = null;
        }

        $libraryFile->update([
            'title' => filled($data['title'] ?? null) ? trim((string) $data['title']) : null,
            'folder' => $folder,
        ]);

        return back()->with('success', 'Мэдээлэл шинэчлэгдлээ.');
    }

    public function destroy(Request $request, LibraryFile $libraryFile): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $libraryFile->delete();

        return redirect()
            ->route('admin.systems.index', ['tab' => 'files'])
            ->with('success', 'Файл устгалаа.');
    }

    public function download(Request $request, LibraryFile $libraryFile): StreamedResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        abort_unless($this->isSafeStoredPath($libraryFile->path), 404);
        abort_unless(Storage::disk('local')->exists($libraryFile->path), 404);

        $name = $libraryFile->original_name ?: basename($libraryFile->path);
        $ascii = preg_replace('/[^\x20-\x7E]+/', '_', $name) ?: 'file';
        $mime = $libraryFile->mime ?: (Storage::disk('local')->mimeType($libraryFile->path) ?: 'application/octet-stream');

        return Storage::disk('local')->download($libraryFile->path, $name, [
            'Content-Type' => $mime,
            'Content-Disposition' => "attachment; filename=\"{$ascii}\"; filename*=UTF-8''".rawurlencode($name),
        ]);
    }

    private function isSafeStoredPath(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        if (str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }

        $base = trim((string) config('file_library.folder', 'file-library'), '/');

        return Str::startsWith($path, $base.'/');
    }
}
