<?php

namespace App\Support;

/**
 * AI хариултын цэс/мөр рүү шилжих холбоос.
 */
class AiNavLink
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{label: string, module: string, route: string, params: array<string, mixed>, href: string}|null
     */
    public static function forModule(string $moduleKey, array $params = [], ?string $label = null): ?array
    {
        $def = ModuleAccess::find($moduleKey);

        if (! $def || empty($def['route'])) {
            return null;
        }

        return self::make(
            $label ?? (string) $def['label'],
            (string) $def['route'],
            $params,
            $moduleKey,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{label: string, module: ?string, route: string, params: array<string, mixed>, href: string}
     */
    public static function make(string $label, string $routeName, array $params = [], ?string $module = null): array
    {
        return [
            'label' => $label,
            'module' => $module,
            'route' => $routeName,
            'params' => $params,
            'href' => route($routeName, $params),
        ];
    }
}
