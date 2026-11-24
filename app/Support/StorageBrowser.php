<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

/**
 * Админд зориулсан хавтас үзэгч — зөвшөөрөгдсөн үндсэн замаас гадуур явахгүй.
 */
class StorageBrowser
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public static function rootOptions(): array
    {
        $options = [];

        foreach (config('storage_browser.roots', []) as $key => $root) {
            $absolute = self::rootAbsolutePath($key);

            if ($absolute === null || ! is_dir($absolute)) {
                continue;
            }

            $options[] = [
                'key' => $key,
                'label' => (string) ($root['label'] ?? $key),
            ];
        }

        return $options;
    }

    /**
     * @return array{
     *     root: string,
     *     path: string,
     *     breadcrumbs: list<array{label: string, path: string}>,
     *     directories: list<array{name: string, path: string}>,
     *     files: list<array{name: string, path: string, size: int, size_label: string, mime: ?string, extension: string, is_image: bool, modified_at: ?string, preview_url: ?string, download_url: string}>
     * }
     */
    public static function browse(string $rootKey, string $relativePath = ''): array
    {
        $absoluteRoot = self::rootAbsolutePath($rootKey);
        abort_unless($absoluteRoot !== null && is_dir($absoluteRoot), 404);

        $relativePath = self::normalizeRelative($relativePath);
        $absolute = self::resolveWithinRoot($absoluteRoot, $relativePath);

        abort_unless(is_dir($absolute), 404);

        $blocked = array_map('strtolower', config('storage_browser.blocked_names', []));
        $imageExt = array_map('strtolower', config('storage_browser.image_extensions', []));

        $directories = [];
        $files = [];

        foreach (scandir($absolute) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            if (in_array(strtolower($name), $blocked, true) || str_starts_with($name, '.')) {
                continue;
            }

            $childAbsolute = $absolute.DIRECTORY_SEPARATOR.$name;
            $childRelative = $relativePath === '' ? $name : $relativePath.'/'.$name;

            if (is_dir($childAbsolute)) {
                $directories[] = [
                    'name' => $name,
                    'path' => $childRelative,
                ];

                continue;
            }

            if (! is_file($childAbsolute)) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $isImage = in_array($extension, $imageExt, true);
            $size = (int) filesize($childAbsolute);
            $mime = self::detectMime($childAbsolute);

            $files[] = [
                'name' => $name,
                'path' => $childRelative,
                'size' => $size,
                'size_label' => self::formatBytes($size),
                'mime' => $mime,
                'extension' => $extension,
                'is_image' => $isImage,
                'modified_at' => date('Y-m-d H:i', (int) filemtime($childAbsolute)),
                'preview_url' => $isImage
                    ? route('admin.storage.preview', ['root' => $rootKey, 'path' => $childRelative])
                    : null,
                'download_url' => route('admin.storage.download', ['root' => $rootKey, 'path' => $childRelative]),
            ];
        }

        usort($directories, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        usort($files, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return [
            'root' => $rootKey,
            'path' => $relativePath,
            'breadcrumbs' => self::breadcrumbs($relativePath),
            'directories' => $directories,
            'files' => $files,
        ];
    }

    public static function absoluteFile(string $rootKey, string $relativePath): string
    {
        $absoluteRoot = self::rootAbsolutePath($rootKey);
        abort_unless($absoluteRoot !== null, 404);

        $relativePath = self::normalizeRelative($relativePath);
        abort_if($relativePath === '', 404);

        $absolute = self::resolveWithinRoot($absoluteRoot, $relativePath);
        abort_unless(is_file($absolute), 404);

        $base = basename($absolute);
        $blocked = array_map('strtolower', config('storage_browser.blocked_names', []));
        abort_if(in_array(strtolower($base), $blocked, true) || str_starts_with($base, '.'), 404);

        return $absolute;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes;
        $unit = 'B';

        foreach ($units as $candidate) {
            $value /= 1024;
            $unit = $candidate;

            if ($value < 1024) {
                break;
            }
        }

        return round($value, $value >= 10 ? 0 : 1).' '.$unit;
    }

    private static function rootAbsolutePath(string $rootKey): ?string
    {
        $root = config('storage_browser.roots.'.$rootKey);

        if (! is_array($root)) {
            return null;
        }

        if (isset($root['path'])) {
            $path = realpath((string) $root['path']);

            return $path !== false ? $path : null;
        }

        $disk = (string) ($root['disk'] ?? '');
        $prefix = trim((string) ($root['prefix'] ?? ''), '/');

        if ($disk === '' || ! array_key_exists($disk, config('filesystems.disks', []))) {
            return null;
        }

        $diskRoot = Storage::disk($disk)->path($prefix === '' ? '' : $prefix);
        $path = realpath($diskRoot);

        if ($path === false && $prefix === '' && is_dir($diskRoot)) {
            return $diskRoot;
        }

        return $path !== false ? $path : null;
    }

    private static function normalizeRelative(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, '/');

        if ($path === '' || $path === '.') {
            return '';
        }

        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                throw new InvalidArgumentException('Invalid path.');
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private static function resolveWithinRoot(string $absoluteRoot, string $relativePath): string
    {
        $candidate = $relativePath === ''
            ? $absoluteRoot
            : $absoluteRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $real = realpath($candidate);

        if ($real === false) {
            abort(404);
        }

        $rootReal = realpath($absoluteRoot) ?: $absoluteRoot;
        $prefix = rtrim(str_replace('\\', '/', $rootReal), '/');
        $target = str_replace('\\', '/', $real);

        if ($target !== $prefix && ! str_starts_with($target, $prefix.'/')) {
            abort(404);
        }

        return $real;
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    private static function breadcrumbs(string $relativePath): array
    {
        $crumbs = [['label' => 'Үндэс', 'path' => '']];

        if ($relativePath === '') {
            return $crumbs;
        }

        $built = [];

        foreach (explode('/', $relativePath) as $part) {
            $built[] = $part;
            $crumbs[] = [
                'label' => $part,
                'path' => implode('/', $built),
            ];
        }

        return $crumbs;
    }

    private static function detectMime(string $absolute): ?string
    {
        try {
            return (new SymfonyFile($absolute))->getMimeType();
        } catch (\Throwable) {
            return null;
        }
    }
}
