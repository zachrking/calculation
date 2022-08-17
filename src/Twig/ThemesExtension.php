<?php
/*
 * This file is part of the Calculation package.
 *
 * (c) bibi.nu <bibi@bibi.nu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Twig;

use App\Form\User\ThemeType;
use App\Model\Theme;
use App\Service\ThemeService;
use Symfony\Component\HttpFoundation\Request;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for UI themes.
 */
final class ThemesExtension extends AbstractExtension
{
    /**
     * Constructor.
     *
     * @param ThemeService $service the theme service
     */
    public function __construct(private readonly ThemeService $service)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_css', $this->getThemeCss(...)),
            new TwigFunction('theme_name', $this->getThemeName(...)),
            new TwigFunction('theme_default', $this->isDefaultTheme(...)),
            new TwigFunction('theme_background', $this->getThemeBackground(...)),
            new TwigFunction('theme_dark', $this->isDarkTheme(...)),
        ];
    }

    /**
     * Gets the current theme.
     *
     * @param ?Request $request the request
     *
     * @return Theme the current theme, if any; the default theme otherwise
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    private function getCurrentTheme(?Request $request = null): Theme
    {
        return $this->service->getCurrentTheme($request);
    }

    /**
     * Gets the theme background.
     */
    private function getThemeBackground(?Request $request = null): string
    {
        // get background
        $background = $this->service->getThemeBackground($request);
        [$nav_foreground, $nav_background] = \explode(' ', $background);

        // check if exists
        if (!\in_array($nav_foreground, ThemeType::FOREGROUND_CHOICES, true)) {
            return ThemeService::DEFAULT_BACKGROUND;
        }
        if (!\in_array($nav_background, ThemeType::BACKGROUND_CHOICES, true)) {
            return ThemeService::DEFAULT_BACKGROUND;
        }

        return $background;
    }

    /**
     * Gets the theme CSS.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    private function getThemeCss(?Request $request = null): string
    {
        // get CSS
        $theme = $this->getCurrentTheme($request);
        if ($theme->exists()) {
            return $theme->getCss();
        }

        return ThemeService::DEFAULT_CSS;
    }

    /**
     * Gets the theme name.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    private function getThemeName(?Request $request = null): string
    {
        return $this->getCurrentTheme($request)->getName();
    }

    /**
     * Returns if the selected theme is dark.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    private function isDarkTheme(?Request $request = null): bool
    {
        return $this->service->isDarkTheme($request);
    }

    /**
     * Returns if the selected theme is the default theme (Boostrap).
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    private function isDefaultTheme(?Request $request = null): bool
    {
        return $this->getCurrentTheme($request)->isDefault();
    }
}
