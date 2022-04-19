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

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension to generate CSP nonce key.
 *
 * @author Laurent Muller
 */
final class NonceExtension extends AbstractExtension
{
    /**
     *  The generated nonce.
     */
    private ?string $nonce = null;

    /**
     * Constructor.
     *
     * @param int $length the length of generated bytes
     * @psalm-param positive-int $length
     */
    public function __construct(private readonly int $length = 16)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', fn (): string => $this->getNonce()),
        ];
    }

    /**
     * Generates a random nonce parameter.
     */
    public function getNonce(): string
    {
        if (!$this->nonce) {
            $this->nonce = \bin2hex(\random_bytes($this->length));
        }

        return $this->nonce;
    }
}
