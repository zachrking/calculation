<?php
/*
 * This file is part of the Calculation package.
 *
 * (c) bibi.nu. <bibi@bibi.nu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Service;

use App\Entity\Log;
use App\Traits\CacheTrait;
use App\Util\FileUtils;
use App\Util\FormatUtils;
use App\Util\Utils;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Service to read and cache log file.
 *
 * @author Laurent Muller
 */
class LogService
{
    use CacheTrait;

    /**
     * The key for channels.
     */
    public const KEY_CHANNELS = 'channels';

    /**
     * The key for file.
     */
    public const KEY_FILE = 'file';

    /**
     * The key for levels.
     */
    public const KEY_LEVELS = 'levels';

    /**
     * The key for logs.
     */
    public const KEY_LOGS = 'logs';

    /**
     * The application channel.
     */
    private const APP_CHANNEL = 'app';

    /**
     * The date format.
     */
    private const DATE_FORMAT = 'd.m.Y H:i:s';

    /**
     * The cache keys.
     */
    private const KEYS = [
        self::KEY_FILE,
        self::KEY_LOGS,
        self::KEY_CHANNELS,
        self::KEY_LEVELS,
    ];

    /**
     * The values separator.
     */
    private const VALUES_SEP = '|';

    /**
     * The log file name.
     */
    private string $fileName;

    /**
     * Constructor.
     *
     * @param KernelInterface  $kernel  the kernel used to get the log file
     * @param AdapterInterface $adapter the adapter to cache logs
     */
    public function __construct(KernelInterface $kernel, AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        $this->fileName = $this->buildLogFile($kernel);
    }

    /**
     * Clear the cached values.
     */
    public function clearCache(): self
    {
        $this->deleteCacheItems(self::KEYS);

        return $this;
    }

    /**
     * Filters the given logs.
     *
     * @param Log[]  $logs        the logs to search in
     * @param string $value       the value to search for
     * @param bool   $skipChannel true to skip search in channel
     * @param bool   $skipLevel   true to skip search in level
     *
     * @return Log[] the filtered logs
     */
    public static function filter(array $logs, ?string $value, bool $skipChannel, bool $skipLevel): array
    {
        if (Utils::isString($value)) {
            $filter = static function (Log $log) use ($value, $skipChannel, $skipLevel) {
                if (!$skipChannel) {
                    $channel = self::getChannel($log->getChannel());
                    if (Utils::contains($channel, $value, true)) {
                        return true;
                    }
                }

                if (!$skipLevel) {
                    $level = self::getLevel($log->getLevel());
                    if (Utils::contains($level, $value, true)) {
                        return true;
                    }
                }

                $date = self::getCreatedAt($log->getCreatedAt());
                if (Utils::contains($date, $value, true)) {
                    return true;
                }

                if (Utils::contains($log->getMessage(), $value, true)) {
                    return true;
                }

                return false;
            };

            return \array_filter($logs, $filter);
        }

        return $logs;
    }

    /**
     * Filters the log for the given channel.
     *
     * @param Log[]  $logs  the logs to search in
     * @param string $value the channel value to search for
     *
     * @return Log[] the filtered logs
     */
    public static function filterChannel(array $logs, ?string $value): array
    {
        if (Utils::isString($value)) {
            return \array_filter($logs, function (Log $log) use ($value) {
                return 0 === \strcasecmp($value, $log->getChannel());
            });
        }

        return $logs;
    }

    /**
     * Filters the log for the given level.
     *
     * @param Log[]  $logs  the logs to search in
     * @param string $value the level value to search for
     *
     * @return Log[] the filtered logs
     */
    public static function filterLevel(array $logs, ?string $value): array
    {
        if (Utils::isString($value)) {
            return \array_filter($logs, function (Log $log) use ($value) {
                return 0 === \strcasecmp($value, $log->getLevel());
            });
        }

        return $logs;
    }

    /**
     * Gets the log channel.
     *
     * @param string $value      the source
     * @param bool   $capitalize true to capitlize the channel
     *
     * @return string the channel
     */
    public static function getChannel(string $value, bool $capitalize = false): string
    {
        $value = self::APP_CHANNEL === $value ? 'application' : \strtolower($value);
        if ($capitalize) {
            return Utils::capitalize($value);
        }

        return $value;
    }

    /**
     * Formats the log date.
     */
    public static function getCreatedAt(\DateTimeInterface $value): string
    {
        return FormatUtils::formatDateTime($value, null, \IntlDateFormatter::MEDIUM);
    }

    /**
     * Gets the entries.
     *
     * @return array|bool an array with the file name, the logs, the levels and the channels;
     *                    <code>false</code> if an error occurs or if the file is empty
     */
    public function getEntries()
    {
        if ($entries = $this->getCachedValues()) {
            return $entries;
        }

        if ($entries = $this->readFile()) {
            return $this->setCachedValues($entries);
        }

        return false;
    }

    /**
     * Gets the log file name.
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * Gets the log level.
     *
     * @param string $value      the source
     * @param bool   $capitalize true to capitlize the level
     *
     * @return string the level
     */
    public static function getLevel(string $value, bool $capitalize = false): string
    {
        $value = \strtolower($value);
        if ($capitalize) {
            return Utils::capitalize($value);
        }

        return $value;
    }

    /**
     * Gets the log for the given identifier.
     *
     * @param int $id the log identifier to find
     *
     * @return Log|null the log, if found; null otherwise
     */
    public function getLog(int $id): ?Log
    {
        if ($entries = $this->getEntries()) {
            return $entries[self::KEY_LOGS][$id] ?? null;
        }

        return null;
    }

    /**
     * Checks if this log file name exist and is not empty.
     *
     * @return bool true if valid
     */
    public function isFileValid(): bool
    {
        return FileUtils::exists($this->fileName) && 0 !== \filesize($this->fileName);
    }

    /**
     * Builds the log file name.
     */
    private function buildLogFile(KernelInterface $kernel): string
    {
        $dir = $kernel->getLogDir();
        $env = $kernel->getEnvironment();
        $sep = \DIRECTORY_SEPARATOR;
        $file = "$dir$sep$env.log";

        return \str_replace(['\\', '/'], \DIRECTORY_SEPARATOR, $file);
    }

    /**
     * Gets the cached values.
     *
     * @return array|bool the values, if cached; false otherwise
     */
    private function getCachedValues()
    {
        $entries = [];
        $items = $this->getCacheItems(self::KEYS);
        foreach ($items as $item) {
            if ($item->isHit()) {
                $entries[$item->getKey()] = $item->get();
            } else {
                return false;
            }
        }

        return $entries;
    }

    /**
     * Increment by one the given array.
     *
     * @param array  $array the array to update
     * @param string $key   the array's key to incremente
     */
    private function increment(array &$array, string $key): void
    {
        $array[$key] = ($array[$key] ?? 0) + 1;
    }

    /**
     * Gets the log date.
     *
     * @param string $value the source
     *
     * @return \DateTime|null a new DateTime instance or null on failure
     */
    private function parseDate(string $value): ?\DateTime
    {
        $date = \DateTime::createFromFormat(self::DATE_FORMAT, $value);

        return false === $date ? null : $date;
    }

    /**
     * Decode the given JSON string.
     *
     * @param string $value the value to decode
     *
     * @return array the decoded value
     */
    private function parseJson(string $value): array
    {
        try {
            $result = \json_decode($value, true);
            if ($result && \JSON_ERROR_NONE === \json_last_error()) {
                return $result;
            }
        } catch (\Exception $e) {
        }

        return [];
    }

    /**
     * Gets the log message.
     *
     * @param string $value the source
     *
     * @return string the message
     */
    private function parseMessage(string $value): string
    {
        return \trim($value);
    }

    /**
     * Gets all lines of the log file.
     *
     * @return array|bool an array with the logs, the levels and the channels; <code>false</code> if an error occurs or if the file is empty
     */
    private function readFile()
    {
        // check file
        if (!$this->isFileValid()) {
            return false;
        }

        $handle = false;

        try {
            // open
            if (false === $handle = \fopen($this->fileName, 'r')) {
                return false;
            }

            $id = 1;
            $logs = [];
            $levels = [];
            $channels = [];

            // read line by line
            while (false !== ($line = \fgets($handle))) {
                $values = \explode(self::VALUES_SEP, $line);
                if (6 !== \count($values)) {
                    continue;
                }
                if (!$date = self::parseDate($values[0])) {
                    continue;
                }

                $channel = self::getChannel($values[1]);
                $level = self::getLevel($values[2]);

                // add
                $log = new Log();
                $log->setId($id)
                    ->setCreatedAt($date)
                    ->setChannel($channel)
                    ->setLevel($level)
                    ->setMessage($this->parseMessage($values[3]))
                    ->setContext($this->parseJson($values[4]))
                    ->setExtra($this->parseJson($values[5]));
                $logs[$id++] = $log;

                // update
                $this->increment($levels, $level);
                $this->increment($channels, $channel);
            }
        } catch (\Exception $e) {
            return false;
        } finally {
            if (\is_resource($handle)) {
                \fclose($handle);
            }
        }

        // logs?
        if (!empty($logs)) {
            // sort
            \ksort($levels, \SORT_LOCALE_STRING);
            \ksort($channels, \SORT_LOCALE_STRING);

            // result
            return [
                self::KEY_FILE => $this->fileName,
                self::KEY_LOGS => $logs,
                self::KEY_LEVELS => $levels,
                self::KEY_CHANNELS => $channels,
            ];
        }

        return false;
    }

    /**
     * Save entries to cache.
     *
     * @param array $entries the entries to save
     *
     * @return array the entries argument
     */
    private function setCachedValues(array $entries): array
    {
        foreach ($entries as $key => $value) {
            $this->setCacheValue($key, $value);
        }

        return $entries;
    }
}
