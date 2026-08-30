<?php

namespace FlexiCore\Service;

use FlexiCore\Core\{StubStorage, Constants};

class CssStyleCompose
{
  public static function get(array $answers, $themingMode, $theme)
  {
    $icon = Constants::UI_ICONS[$answers['iconLibrary']];
    $mode = strtolower($themingMode);

    $headStyle = StubStorage::get("css.head-import");

    $plugin = "@plugin \"@iconify/tailwind4\" {\n  prefixes: $icon;\n  scale: 1.2;\n}\n";
    $darkVariant = "@custom-variant dark (&:is(.dark *));";

    $lightTheme = StubStorage::get('themes.light.' . $theme);
    $darkTheme = StubStorage::get('themes.dark.' . $theme);
    $grayPalette = StubStorage::get('themes.gray.' . $theme);
    $baseVars = StubStorage::get("css.base-vars");
    $configTheme = StubStorage::get("css.theme-config");
    $baseLayer = StubStorage::get("css.base-layer");

    $style = match ($mode) {
      'both' => self::scope(':root', $lightTheme, $grayPalette, $baseVars) . PHP_EOL . PHP_EOL . self::scope('.dark', $darkTheme),
      'dark' => self::scope(':root', $darkTheme, $grayPalette, $baseVars),
      default => self::scope(':root', $lightTheme, $grayPalette, $baseVars),
    };

    return implode(PHP_EOL . PHP_EOL, [
      $headStyle,
      $plugin,
      $darkVariant,
      $style,
      $configTheme,
      $baseLayer,
    ]);
  }

  private static function scope(string $selector, string ...$parts): string
  {
    $body = trim(implode(PHP_EOL, array_map('trim', $parts)));
    $lines = array_map(fn ($line) => $line === '' ? '' : '    ' . $line, explode(PHP_EOL, $body));

    return $selector . ' {' . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL . '}';
  }
}
