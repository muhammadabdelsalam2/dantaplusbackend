<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PdfText
{
    public static function hasArabic(mixed $value): bool
    {
        return preg_match('/\p{Arabic}/u', (string) $value) === 1;
    }

    public static function dir(mixed $value): string
    {
        return self::hasArabic($value) ? 'rtl' : 'ltr';
    }

    public static function align(mixed $value): string
    {
        return self::hasArabic($value) ? 'right' : 'left';
    }

    public static function span(mixed $value, ?string $class = null): HtmlString
    {
        $text = e((string) ($value ?? ''));
        $dir = self::dir($value);
        $align = self::align($value);
        $classAttr = $class ? ' class="' . e($class) . '"' : '';

        return new HtmlString('<span' . $classAttr . ' dir="' . $dir . '" style="direction:' . $dir . '; unicode-bidi:embed; text-align:' . $align . ';">' . $text . '</span>');
    }

    public static function money(mixed $amount): string
    {
        return '$' . number_format((float) $amount, 2);
    }

    public static function oneLine(array $parts, string $separator = ' | '): string
    {
        return collect($parts)
            ->filter(fn ($part) => filled($part))
            ->map(fn ($part) => trim((string) $part))
            ->implode($separator);
    }
}
