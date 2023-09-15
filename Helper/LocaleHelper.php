<?php

declare(strict_types=1);

namespace Owl\Bundle\TinymceBundle\Helper;

/**
 * LocaleHelper.
 */
class LocaleHelper
{
    private static array $locales = [
        'bn' => 'bn_BD',
        'bg' => 'bg_BG',
        'cn' => 'zh_CN',
        'fr' => 'fr_FR',
        'hu' => 'hu_HU',
        'il' => 'he_IL',
        'is' => 'is_IS',
        'sl' => 'sl_SI',
        'tr' => 'tr_TR',
        'tw' => 'zh_TW',
        'uk' => 'uk_UA',
        'pl' => 'pl_PL',
    ];

    /**
     * @param string $locale
     */
    public static function getLanguage($locale): string
    {
        return self::$locales[$locale] ?? $locale;
    }
}
