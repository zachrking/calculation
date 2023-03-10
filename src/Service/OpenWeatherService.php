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

namespace App\Service;

use App\Database\OpenWeatherDatabase;
use App\Traits\TranslatorTrait;
use App\Util\FormatUtils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service to get weather from OpenWeatherMap.
 *
 * @see https://openweathermap.org/api
 *
 * @psalm-import-type OpenWeatherCityType from OpenWeatherDatabase
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class OpenWeatherService extends AbstractHttpClientService
{
    use TranslatorTrait;

    /**
     * The imperial degree.
     */
    final public const DEGREE_IMPERIAL = '°F';

    /**
     * The metric degree.
     */
    final public const DEGREE_METRIC = '°C';

    /**
     * The parameter value to exclude the current data.
     */
    final public const EXCLUDE_CURRENT = 'current';

    /**
     * The parameter value to exclude the daily data.
     */
    final public const EXCLUDE_DAILY = 'daily';

    /**
     * The parameter value to exclude the hourly data.
     */
    final public const EXCLUDE_HOURLY = 'hourly';

    /**
     * The parameter value to exclude the minutely data.
     */
    final public const EXCLUDE_MINUTELY = 'minutely';

    /**
     * The maximum number of city identifiers to retrieve.
     */
    final public const MAX_GROUP = 20;

    /**
     * The imperial speed.
     */
    final public const SPEED_IMPERIAL = 'mph';

    /**
     * The metric speed.
     */
    final public const SPEED_METRIC = 'm/s';

    /**
     * The imperial units parameter value.
     */
    final public const UNIT_IMPERIAL = 'imperial';

    /**
     * The metric units parameter value.
     */
    final public const UNIT_METRIC = 'metric';

    /**
     * The cache timeout (15 minutes).
     */
    private const CACHE_TIMEOUT = 60 * 15;

    /**
     * The country flag URL.
     */
    private const COUNTRY_URL = 'https://openweathermap.org/images/flags/{0}.png';

    /**
     * The host name.
     */
    private const HOST_NAME = 'https://api.openweathermap.org/data/2.5/';

    /**
     * The big icon URL.
     */
    private const ICON_BIG_URL = 'https://openweathermap.org/img/wn/{0}@4x.png';

    /**
     * The small icon URL.
     */
    private const ICON_SMALL_URL = 'https://openweathermap.org/img/wn/{0}@2x.png';

    /**
     * The medium format used for dates.
     */
    private const TYPE_MEDIUM = \IntlDateFormatter::MEDIUM;

    /**
     * The short format used for dates.
     */
    private const TYPE_SHORT = \IntlDateFormatter::SHORT;

    /**
     * Current condition URI.
     */
    private const URI_CURRENT = 'weather';

    /**
     * 16 day / daily forecast URI.
     */
    private const URI_DAILY = 'forecast/daily';

    /**
     * 5 days / 3 hours forecast URI.
     */
    private const URI_FORECAST = 'forecast';

    /**
     * Current condition URI for a group (multiple cities).
     */
    private const URI_GROUP = 'group';

    /**
     * One call condition URI.
     */
    private const URI_ONECALL = 'onecall';

    /**
     * The wind directions.
     */
    private const WIND_DIRECTIONS = [
        'N',
        'N / N-E',
        'N-E',
        'E / N-E',
        'E',
        'E / S-E',
        'S-E',
        'S / S-E',
        'S',
        'S / S-W',
        'S-W',
        'W / S-W',
        'W',
        'W / N-W',
        'N-W',
        'N / N-W',
        'N',
    ];

    /**
     * Constructor.
     *
     * @throws ParameterNotFoundException if the API key parameter is not defined
     * @throws \InvalidArgumentException  if the API key is null or empty
     */
    public function __construct(
        #[\SensitiveParameter]
        #[Autowire('%open_weather_key%')]
        string $key,
        #[Autowire('%kernel.project_dir%/resources/data/openweather.sqlite')]
        private readonly string $databaseName,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct($key);
    }

    /**
     * Returns the current, the 5 days/3 hours forecast and 16 day/daily forecast
     * conditions data for a specific location.
     *
     * @param int    $id    the city identifier
     * @param int    $count the number of result to return or -1 for all
     * @param string $units the units to use
     *
     * @return array{current: array|false, forecast: array|false, daily: array|false}
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     * @throws \ReflectionException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function all(int $id, int $count = -1, string $units = self::UNIT_METRIC): array
    {
        return [
            'current' => $this->current($id, $units),
            'forecast' => $this->forecast($id, $count, $units),
            'daily' => $this->daily($id, $count, $units),
        ];
    }

    /**
     * Returns current conditions data for a specific location.
     *
     * @param int    $id    the city identifier
     * @param string $units the units to use
     *
     * @return array|false the current conditions if success; false on error
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     * @throws \ReflectionException
     */
    public function current(int $id, string $units = self::UNIT_METRIC): array|false
    {
        $query = [
            'id' => $id,
            'units' => $units,
        ];

        return $this->get(self::URI_CURRENT, $query);
    }

    /**
     * Returns 16 day / daily forecast conditions data for a specific location.
     *
     * @param int    $id    the city identifier
     * @param int    $count the number of result to return or -1 for all
     * @param string $units the units to use
     *
     * @return array|false the current conditions if success; false on error
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     * @throws \ReflectionException
     */
    public function daily(int $id, int $count = -1, string $units = self::UNIT_METRIC): array|false
    {
        $query = [
            'id' => $id,
            'units' => $units,
        ];
        if ($count > 0) {
            $query['cnt'] = $count;
        }

        return $this->get(self::URI_DAILY, $query);
    }

    /**
     * Returns 5 days / 3 hours forecast conditions data for a specific location.
     *
     * @param int    $id    the city identifier
     * @param int    $count the number of result to return or -1 for all
     * @param string $units the units to use
     *
     * @return array|false the current conditions if success; false on error
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     * @throws \ReflectionException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function forecast(int $id, int $count = -1, string $units = self::UNIT_METRIC): array|false
    {
        $query = [
            'id' => $id,
            'units' => $units,
        ];
        if ($count > 0) {
            $query['cnt'] = $count;
        }

        return $this->get(self::URI_FORECAST, $query);
    }

    /**
     * Gets the database.
     *
     * @param bool $readonly true open the database for reading only
     *
     * @return OpenWeatherDatabase the database
     */
    public function getDatabase(bool $readonly = false): OpenWeatherDatabase
    {
        return new OpenWeatherDatabase($this->getDatabaseName(), $readonly);
    }

    /**
     * Gets the database file name.
     */
    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    /**
     * Gets the degree units.
     */
    public function getDegreeUnit(string $units): string
    {
        return self::UNIT_METRIC === $units ? self::DEGREE_METRIC : self::DEGREE_IMPERIAL;
    }

    /**
     * Gets the speed units.
     */
    public function getSpeedUnit(string $units): string
    {
        return self::UNIT_METRIC === $units ? self::SPEED_METRIC : self::SPEED_IMPERIAL;
    }

    public function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    /**
     * Returns current conditions data for a group of cities.
     *
     * @param int[]  $cityIds the city identifiers. The maximum number of city identifiers are 20.
     * @param string $units   the units to use
     *
     * @return array|bool the conditions for the given cities if success; false on error
     *
     * @psalm-return array<array{
     *      cnt: int,
     *      units: array,
     *      list: array<int, array>
     *  }>|bool
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     * @throws \ReflectionException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function group(array $cityIds, string $units = self::UNIT_METRIC): array|bool
    {
        if (\count($cityIds) > self::MAX_GROUP) {
            throw new \InvalidArgumentException('The number of city identifiers is greater than 20.');
        }

        $query = [
            'id' => \implode(',', $cityIds),
            'units' => $units,
        ];

        /** @psalm-var bool|array<array{
         *      cnt: int,
         *      units: array,
         *      list: array<int, array>
         *  }> $result
         */
        $result = $this->get(self::URI_GROUP, $query);

        return $result;
    }

    /**
     * Returns all essential weather data for a specific location.
     *
     * @param float    $latitude  the latitude
     * @param float    $longitude the longitude
     * @param string[] $exclude   the parts to exclude from the response. Available values:
     *                            <ul>
     *                            <li>'current'</li>
     *                            <li>'minutely'</li>
     *                            <li>'hourly'</li>
     *                            <li>'daily'</li>
     *                            </ul>
     * @param string   $units     the units to use
     *
     * @return array|false the essential conditions if success; false on error
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     * @throws \ReflectionException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function oneCall(float $latitude, float $longitude, string $units = self::UNIT_METRIC, array $exclude = []): array|false
    {
        $query = [
            'lat' => $latitude,
            'lon' => $longitude,
            'units' => $units,
        ];
        if (!empty($exclude)) {
            $query['exclude'] = \implode(',', $exclude);
        }

        return $this->get(self::URI_ONECALL, $query);
    }

    /**
     * Search for cities.
     *
     * @param string $name  the name of the city to search for
     * @param string $units the units to use
     * @param int    $limit the maximum number of cities to return
     *
     * @pslam-return array<int, OpenWeatherCityType>
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function search(string $name, string $units = self::UNIT_METRIC, int $limit = 25): array
    {
        // find from cache
        $key = $this->getCacheKey('search', ['name' => $name, 'units' => $units]);

        /** @psalm-var array<int, OpenWeatherCityType>|false $result */
        $result = $this->getCacheValue($key, false);
        if (\is_array($result)) {
            return $result;
        }

        $db = null;

        try {
            $db = $this->getDatabase(true);
            $result = $db->findCity($name, $limit);
        } finally {
            $db?->close();
        }

        // update and cache
        $this->updateResult($result);
        $this->setCacheValue($key, $result, self::CACHE_TIMEOUT);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOptions(): array
    {
        return [self::BASE_URI => self::HOST_NAME];
    }

    /**
     * Adds units to the given array.
     *
     * @param array  $data  the data to update
     * @param string $units the query units
     */
    private function addUnits(array &$data, string $units): void
    {
        $data['units'] = [
            'system' => $units,
            'temperature' => $this->getDegreeUnit($units),
            'speed' => $this->getSpeedUnit($units),
            'pressure' => 'hPa',
            'degree' => '°',
            'percent' => '%',
            'volume' => 'mm',
        ];
    }

    /**
     * Checks if the response contains an error.
     *
     * @throws \ReflectionException
     */
    private function checkErrorCode(array $result): bool
    {
        if (isset($result['cod']) && Response::HTTP_OK !== (int) $result['cod']) {
            return $this->setLastError((int) $result['cod'], (string) $result['message']);
        }

        return true;
    }

    /**
     * Finds the time zone (shift in seconds from UTC).
     *
     * @param array $data the data search in
     *
     * @return int the offset, if found; 0 otherwise
     */
    private function findTimezone(array $data): int
    {
        /** @var mixed $value */
        foreach ($data as $key => $value) {
            if ('timezone' === $key) {
                return (int) $value;
            } elseif (\is_array($value) && $timeZone = $this->findTimeZone($value)) {
                return $timeZone;
            }
        }

        return 0;
    }

    /**
     * Make an HTTP-GET call.
     *
     * @param string $uri   the uri to append to the host name
     * @param array  $query an associative array of query string values to add to the request
     *
     * @return array|false the JSON response on success, false on failure
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     * @throws \ReflectionException
     */
    private function get(string $uri, array $query = []): array|false
    {
        // find from cache
        $key = $this->getCacheKey($uri, $query);
        /** @psalm-var array|null $result */
        $result = $this->getCacheValue($key);
        if (\is_array($result)) {
            return $result;
        }

        // add API key and language
        $query['appid'] = $this->key;
        $query['lang'] = self::getAcceptLanguage();

        // call
        $response = $this->requestGet($uri, [
            self::QUERY => $query,
        ]);

        // decode
        $result = $response->toArray(false);

        // check
        if (!$this->checkErrorCode($result)) {
            return false;
        }

        // update
        $offset = $this->findTimezone($result);
        $timezone = $this->offsetToTimZone($offset);
        $this->updateResult($result, $timezone);
        $this->addUnits($result, (string) $query['units']);

        // save to cache
        $this->setCacheValue($key, $result, self::CACHE_TIMEOUT);

        return $result;
    }

    /**
     * Gets the cache key for the given uri and query parameters.
     */
    private function getCacheKey(string $uri, array $query): string
    {
        return $uri . '?' . \http_build_query($query);
    }

    /**
     * Gets the country name from the alpha2 code.
     */
    private function getCountryName(string $country): ?string
    {
        try {
            return Countries::getName($country);
        } catch (MissingResourceException) {
            return null;
        }
    }

    /**
     * Gets the wind description for the given degrees.
     */
    private function getWindDescription(int $deg): string
    {
        $direction = $this->getWindDirection($deg);
        $search = [
            'N',
            'S',
            'E',
            'W',
        ];
        $replace = [
            $this->trans('openweather.direction.N'),
            $this->trans('openweather.direction.S'),
            $this->trans('openweather.direction.E'),
            $this->trans('openweather.direction.W'),
        ];

        return \str_replace($search, $replace, $direction);
    }

    /**
     * Gets the wind direction for the given degrees.
     */
    private function getWindDirection(int $deg): string
    {
        $deg %= 360;
        $index = (int) \floor($deg / 22.5 + 0.5);

        return self::WIND_DIRECTIONS[$index];
    }

    /**
     * Converts the given offset to a time zone.
     *
     * @param int $offset the timezone offset in seconds from UTC
     *
     * @return \DateTimeZone the timezone offset in +/-HH:MM form
     */
    private function offsetToTimZone(int $offset): \DateTimeZone
    {
        $sign = $offset < 0 ? '-' : '+';
        $minutes = \floor(\abs($offset) / 60) % 60;
        $hours = \floor(\abs($offset) / 3600);
        $id = \sprintf('%s%02d%02d', $sign, $hours, $minutes);

        return new \DateTimeZone($id);
    }

    private function replaceUrl(string $url, string $value): string
    {
        return \str_replace('{0}', $value, $url);
    }

    private function updateDates(array &$result, int $value, \DateTimeZone|\IntlTimeZone|string $timezone = null): void
    {
        // already set?
        if (isset($result['dt_date'])) {
            return;
        }

        $result['dt_date'] = FormatUtils::formatDate($value, self::TYPE_SHORT);
        $result['dt_date_locale'] = FormatUtils::formatDate($value, self::TYPE_SHORT, $timezone);

        $result['dt_time'] = FormatUtils::formatTime($value, self::TYPE_SHORT);
        $result['dt_time_locale'] = FormatUtils::formatTime($value, self::TYPE_SHORT, $timezone);

        $result['dt_date_time'] = FormatUtils::formatDateTime($value, self::TYPE_SHORT, self::TYPE_SHORT);
        $result['dt_date_time_locale'] = FormatUtils::formatDateTime($value, self::TYPE_SHORT, self::TYPE_SHORT, $timezone);

        $result['dt_date_time_medium'] = FormatUtils::formatDateTime($value, self::TYPE_MEDIUM, self::TYPE_SHORT);
        $result['dt_date_time_medium_locale'] = FormatUtils::formatDateTime($value, self::TYPE_MEDIUM, self::TYPE_SHORT, $timezone);

        unset($result['dt_txt']);
    }

    /**
     * Update result.
     *
     * @param array                                   $result   the result to process
     * @param \DateTimeZone|\IntlTimeZone|string|null $timezone the timezone identifier
     */
    private function updateResult(array &$result, \DateTimeZone|\IntlTimeZone|string $timezone = null): void
    {
        /** @psalm-var mixed $value */
        foreach ($result as $key => &$value) {
            switch ((string) $key) {
                case 'icon':
                    $result['icon_big'] = $this->replaceUrl(self::ICON_BIG_URL, (string) $value);
                    $result['icon_small'] = $this->replaceUrl(self::ICON_SMALL_URL, (string) $value);
                    break;

                case 'description':
                    $result[$key] = \ucfirst((string) $value);
                    break;

                case 'country':
                    $result['country_name'] = $this->getCountryName((string) $value) ?? '';
                    $result['country_flag'] = $this->replaceUrl(self::COUNTRY_URL, \strtolower((string) $value));
                    break;

                case 'dt':
                    $this->updateDates($result, (int) $value, $timezone);
                    break;

                case 'sunrise':
                    $result['sunrise_formatted'] = FormatUtils::formatTime((int) $value, self::TYPE_SHORT, $timezone);
                    break;

                case 'sunset':
                    $result['sunset_formatted'] = FormatUtils::formatTime((int) $value, self::TYPE_SHORT, $timezone);
                    break;

                case 'weather':
                    if (\is_array($value) && !empty($value)) {
                        $this->updateResult($value, $timezone);
                        $value = (array) \reset($value);
                    }
                    break;

                case 'deg':
                    $deg = (int) $value;
                    $result['deg_direction'] = $this->getWindDirection($deg);
                    $result['deg_description'] = $this->getWindDescription($deg);
                    break;

                default:
                    if (\is_array($value)) {
                        $this->updateResult($value, $timezone);
                    }
                    break;
            }
        }
    }
}
