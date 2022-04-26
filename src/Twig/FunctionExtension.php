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

use App\Controller\AbstractController;
use App\Interfaces\RoleInterface;
use App\Service\UrlGeneratorService;
use App\Traits\RoleTranslatorTrait;
use App\Util\FileUtils;
use App\Util\Utils;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for the application service.
 */
final class FunctionExtension extends AbstractExtension
{
    use RoleTranslatorTrait;

    /**
     * The asset extension.
     */
    private ?AssetExtension $asset = null;

    /**
     * The nonce value.
     */
    private ?string $nonce = null;

    /**
     * The file version.
     */
    private readonly int $version;

    /**
     * The real path of the public directory.
     */
    private readonly string $webDir;

    /**
     * Constructor.
     */
    public function __construct(KernelInterface $kernel, TranslatorInterface $translator, private readonly UrlGeneratorService $generator)
    {
        $this->setTranslator($translator);
        $projectDir = $kernel->getProjectDir();
        $filename = FileUtils::buildPath($projectDir, 'composer.lock');
        $this->webDir = (string) \realpath(FileUtils::buildPath($projectDir, 'public'));
        $this->version = $this->fileExists($filename) ? (int) \filemtime($filename) : Kernel::VERSION_ID;
    }

    /**
     * Checks if the given asset path exists.
     *
     * @param string|null $path the path to be verified
     *
     * @return bool true if exists
     */
    public function assetExists(?string $path): bool
    {
        // path?
        if (empty($path)) {
            return false;
        }

        // web directory?
        if (empty($this->webDir)) {
            return false;
        }

        // real path?
        if (!$file = \realpath($this->webDir . $path)) {
            return false;
        }

        // file exist?
        if (!FileUtils::isFile($file)) {
            return false;
        }

        // check if file is well contained in public/ directory (prevents ../ in paths)
        return 0 === \strncmp($this->webDir, $file, \strlen($this->webDir));
    }

    /**
     * Returns the given asset path, if existing; the default path otherwise.
     *
     * @param string|null $path    the path to be verified
     * @param string|null $default the default path
     *
     * @return string|null the path, if existing, the default path otherwise
     */
    public function assetIf(?string $path = null, ?string $default = null): ?string
    {
        if ($this->assetExists($path)) {
            return $path;
        }
        if ($this->assetExists($default)) {
            return $default;
        }

        return null;
    }

    /**
     * Gets the cancel URL.
     *
     * @param Request $request      the request
     * @param int     $id           the entity identifier
     * @param string  $defaultRoute the default route to use
     *
     * @return string the cancel URL
     */
    public function cancelUrl(Request $request, int $id = 0, string $defaultRoute = AbstractController::HOME_PAGE): string
    {
        return $this->generator->cancelUrl($request, $id, $defaultRoute);
    }

    /**
     * Checks the existence of file or directory.
     *
     * @param string|null $filename the path to the file or directory
     *
     * @return bool true if the file or directory exists, false otherwise
     */
    public function fileExists(?string $filename): bool
    {
        return $filename && FileUtils::exists($filename);
    }

    /**
     * Output a link style sheet tag with a nonce value.
     *
     * @param Environment $env         the Twig environnement
     * @param string      $path        a public path
     * @param array       $parameters  additional parameters
     * @param string|null $packageName the name of the asset package to use
     */
    public function getAssetCss(Environment $env, string $path, array $parameters = [], ?string $packageName = null): string
    {
        $url = $this->getAssetUrl($env, $path, $packageName);
        $version = $this->version;
        $nonce = $this->getNonce($env);
        $params = $this->reduceParameters($parameters);

        return \sprintf('<link rel="stylesheet" href="%s?v=%d" nonce="%s" %s>', $url, $version, $nonce, $params);
    }

    /**
     * Output a script source tag with a nonce value.
     *
     * @param Environment $env         the Twig environment
     * @param string      $path        a public path
     * @param array       $parameters  additional parameters
     * @param string|null $packageName the name of the asset package to use
     */
    public function getAssetJs(Environment $env, string $path, array $parameters = [], ?string $packageName = null): string
    {
        $url = $this->getAssetUrl($env, $path, $packageName);
        $version = $this->version;
        $nonce = $this->getNonce($env);
        $params = $this->reduceParameters($parameters);

        return \sprintf('<script src="%s?v=%d" nonce="%s" %s></script>', $url, $version, $nonce, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('trans_role', fn (RoleInterface|string $role): string => $this->translateRole($role)),
            new TwigFilter('var_export', fn (mixed $expression): ?string => Utils::exportVar($expression)),
            new TwigFilter('normalize_whitespace', fn (string $value): string => $this->normalizeWhitespace($value), ['preserves_safety' => ['html']]),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        $assetOptions = [
            'needs_environment' => true,
            'is_safe' => ['html'],
        ];

        return [
            // assets
            new TwigFunction('asset_exists', fn (?string $path): bool => $this->assetExists($path)),
            new TwigFunction('file_exists', fn (?string $filename): bool => $this->fileExists($filename)),

            new TwigFunction('asset_if', fn (?string $path = null, ?string $default = null): ?string => $this->assetIf($path, $default)),
            new TwigFunction('asset_js', fn (Environment $env, string $path, array $parameters = [], ?string $packageName = null): string => $this->getAssetJs($env, $path, $parameters, $packageName), $assetOptions),
            new TwigFunction('asset_css', fn (Environment $env, string $path, array $parameters = [], ?string $packageName = null): string => $this->getAssetCss($env, $path, $parameters, $packageName), $assetOptions),

            // images
            new TwigFunction('image_height', fn (string $path): int => $this->getImageHeight($path)),
            new TwigFunction('image_width', fn (string $path): int => $this->getImageWidth($path)),

            // routes
            new TwigFunction('cancel_url', fn (Request $request, int $id = 0, string $defaultRoute = AbstractController::HOME_PAGE): string => $this->cancelUrl($request, $id, $defaultRoute)),
            new TwigFunction('route_params', fn (Request $request, int $id = 0): array => $this->routeParams($request, $id)),

            // php
            new TwigFunction('is_int', 'is_int'),
        ];
    }

    /**
     * Gets the image height.
     *
     * @param string $path an existing image path relative to the public directory
     */
    public function getImageHeight(string $path): int
    {
        $fullPath = (string) \realpath($this->webDir . $path);
        $size = (array) \getimagesize($fullPath);

        return (int) $size[1];
    }

    /**
     * Gets the image width.
     *
     * @param string $path an existing image path relative to the public directory
     */
    public function getImageWidth(string $path): int
    {
        $fullPath = (string) \realpath($this->webDir . $path);
        $size = (array) \getimagesize($fullPath);

        return (int) $size[0];
    }

    /**
     * This filter replaces duplicated spaces and/or linebreaks with single space.
     *
     * It also removes whitespace from the beginning and at the end of the string.
     *
     * @param string $value the value to clean
     *
     * @return string the cleaned value
     */
    public function normalizeWhitespace(string $value): string
    {
        // attributes
        $value = (string) \preg_replace('/\s+=\s+/u', '=', $value);

        // space and new lines
        $value = (string) \preg_replace('/\s+/u', ' ', $value);

        return \trim($value);
    }

    /**
     * Gets the route parameters.
     *
     * @param Request $request the request
     * @param int     $id      the entity identifier
     */
    public function routeParams(Request $request, int $id = 0): array
    {
        return $this->generator->routeParams($request, $id);
    }

    /**
     * Returns the public url/path of an asset.
     *
     * If the package used to generate the path is an instance of
     * UrlPackage, you will always get a URL and not a path.
     *
     * @param Environment $env         the Twig environnement
     * @param string      $path        a public path
     * @param string|null $packageName the optional name of the asset package to use
     *
     * @return string the public path of the asset
     */
    private function getAssetUrl(Environment $env, string $path, ?string $packageName = null): string
    {
        if (null === $this->asset) {
            $this->asset = $this->getExtension($env, AssetExtension::class);
        }

        return $this->asset->getAssetUrl($path, $packageName);
    }

    /**
     * @template T of \Twig\Extension\ExtensionInterface
     * @psalm-param class-string<T> $className
     * @psalm-return T
     */
    private function getExtension(Environment $env, string $className)
    {
        return $env->getExtension($className);
    }

    /**
     * Generates a random nonce parameter.
     *
     * @param Environment $env the Twig environnement
     */
    private function getNonce(Environment $env): string
    {
        if (null === $this->nonce) {
            $extension = $this->getExtension($env, NonceExtension::class);
            $this->nonce = $extension->getNonce();
        }

        return $this->nonce;
    }

    /**
     * Reduce parameters with a key/value tags.
     *
     * @param array $parameters the parameters to process
     *
     * @return string the reduced parameters
     */
    private function reduceParameters(array $parameters): string
    {
        if (!empty($parameters)) {
            $callback = fn (string $carry, string $key, mixed $value): string => $carry . ' ' . $key . '="' . \htmlspecialchars((string) $value) . '"';

            return (string) Utils::arrayReduceKey($parameters, $callback, '');
        }

        return '';
    }
}
