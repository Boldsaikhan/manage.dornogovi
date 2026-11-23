<?php

namespace App\Http\Controllers;

use App\Models\LibraryFile;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File as FileRule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileLibraryController extends Controller
{
    public const MODULE = 'file_library';

    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $q = trim($request->string('q')->toString());
        $folder = trim($request->string('folder')->toString());

        $files = LibraryFile::query()
            ->with('uploader:id,name')
            ->when($folder !== '', fn ($query) => $query->where('folder', $folder))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('title', 'like', $like)
                        ->orWhere('original_name', 'like', $like)
                        ->orWhere('folder', 'like', $like)
                        ->orWhere('mime', 'like', $like);
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (LibraryFile $file) => $this->serialize($file));

        $folders = LibraryFile::query()
            ->whereNotNull('folder')
            ->where('folder', '!=', '')
            ->distinct()
            ->orderBy('folder')
            ->pluck('folder')
            ->values()
            ->all();

        return Inertia::render('Modules/FileLibrary', [
            'files' => $files,
            'folders' => $folders,
            'filters' => [
                'q' => $q,
                'folder' => $folder,
            ],
            'canManage' => ModuleAccess::canEdit($request->user(), self::MODULE),
            'maxUploadMb' => (int) ceil(((int) config('file_library.max_kb', 20480)) / 1024),
            'allowedExtensions' => config('file_library.extensions', []),
            'stats' => [
                'count' => $files->count(),
                'total_size' => $files->sum('size'),
                'total_size_label' => $this->formatBytes((int) $files->sum('size')),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

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
            ->route('file-library.index', array_filter([
                'folder' => $folder,
            ]))
            ->with('success', 'Файл байршууллаа.');
    }

    public function update(Request $request, LibraryFile $libraryFile): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

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
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $libraryFile->delete();

        return redirect()
            ->route('file-library.index')
            ->with('success', 'Файл устгалаа.');
    }

    public function download(Request $request, LibraryFile $libraryFile): StreamedResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);
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

    /**
     * @return array<string, mixed>
     */
    private function serialize(LibraryFile $file): array
    {
        return [
            'id' => $file->id,
            'title' => $file->title,
            'name' => $file->displayName(),
            'original_name' => $file->original_name,
            'extension' => $file->extension() ?: '—',
            'mime' => $file->mime,
            'size' => $file->size,
            'size_label' => $this->formatBytes((int) $file->size),
            'folder' => $file->folder,
            'uploaded_by' => $file->uploader?->name,
            'created_at' => optional($file->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'created_at_label' => optional($file->created_at)?->timezone(config('app.timezone'))->format('Y.m.d H:i'),
            'download_url' => route('file-library.download', $file),
        ];
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

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $value >= 10 ? 0 : 1, '.', '').' '.$units[$unit];
    }
}
