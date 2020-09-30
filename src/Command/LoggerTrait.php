<?php
/*
 * This file is part of the Calculation package.
 *
 * Copyright (c) 2019 bibi.nu. All rights reserved.
 *
 * This computer code is protected by copyright law and international
 * treaties. Unauthorised reproduction or distribution of this code, or
 * any portion of it, may result in severe civil and criminal penalties,
 * and will be prosecuted to the maximum extent possible under the law.
 */

declare(strict_types=1);

namespace App\Command;

use App\Util\Utils;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Trait to write output messages.
 *
 * @author Laurent Muller
 */
trait LoggerTrait
{
    /**
     * The installer name.
     *
     * @var string
     */
    protected $installerName;

    /**
     * The symfony style.
     *
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * Concat this installer name and the message.
     *
     * @param string $message the message to output
     *
     * @return string the concated message
     */
    protected function concat(string $message): string
    {
        return $this->getInstallerName() . ': ' . $message;
    }

    /**
     * Gets the installer name.
     *
     * @return string the installer name
     */
    protected function getInstallerName(): string
    {
        if (!$this->installerName) {
            $this->installerName = Utils::getShortName(static::class);
        }

        return $this->installerName;
    }

    /**
     * Returns whether verbosity is verbose (-v).
     *
     * @return bool true if verbosity is set to VERBOSITY_VERBOSE, false otherwise
     */
    protected function isVerbose(): bool
    {
        return $this->isIO() && $this->io->isVerbose();
    }

    /**
     * Returns whether verbosity is very verbose (-vv).
     *
     * @return bool true if verbosity is set to VERBOSITY_VERY_VERBOSE, false otherwise
     */
    protected function isVeryVerbose(): bool
    {
        return $this->isIO() && $this->io->isVeryVerbose();
    }

    /**
     * Sets the installer name.
     *
     * @param string $installerName the installer name to set
     */
    protected function setInstallerName(string $installerName): void
    {
        $this->installerName = $installerName;
    }

    /**
     * Writes the given message.
     *
     * @param string $message the message to write
     * @param string $tag     the external tag (info, error, etc)
     */
    protected function write(string $message, string $tag = 'info'): void
    {
        if ($this->isIO()) {
            $concat = $this->concat($message);
            $this->io->writeln("<$tag>$concat</$tag>");
        }
    }

    /**
     * Writes the given error message.
     *
     * @param string $message the message to write
     */
    protected function writeError(string $message): void
    {
        if ($this->isIO()) {
            $concat = $this->concat($message);
            $this->io->error($concat);
        }
    }

    /**
     * Writes the given error message.
     *
     * @param string $message the message to write
     */
    protected function writeNote(string $message): void
    {
        if ($this->isIO()) {
            $concat = $this->concat($message);
            $this->io->note($concat);
        }
    }

    /**
     * Writes the given success message.
     *
     * @param string $message the message to write
     */
    protected function writeSuccess(string $message): void
    {
        if ($this->isIO()) {
            $concat = $this->concat($message);
            $this->io->success($concat);
        }
    }

    /**
     * Writes the given message.
     *
     * @param string $message the message to write
     * @param string $tag     the external tag (info, error, etc)
     */
    protected function writeVerbose(string $message, string $tag = 'info'): void
    {
        if ($this->isVerbose()) {
            $this->write($message, $tag);
        }
    }

    /**
     * Writes the given message.
     *
     * @param string $message the message to write
     * @param string $tag     the external tag (info, error, etc)
     */
    protected function writeVeryVerbose(string $message, string $tag = 'info'): void
    {
        if ($this->isVeryVerbose()) {
            $this->write($message, $tag);
        }
    }

    private function isIO(): bool
    {
        return null !== $this->io;
    }
}
