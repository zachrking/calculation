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

namespace App\Database;

/**
 * Database to search cites for OpenWeatherMap.
 *
 * @psalm-type OpenWeatherCityType = array{
 *      id: int,
 *      name: string,
 *      country: string,
 *      latitude: float,
 *      longitude: float}
 */
class OpenWeatherDatabase extends AbstractDatabase
{
    /**
     * SQL statement to create the city table.
     *
     * @noinspection SqlResolve
     */
    private const CREATE_CITY = <<<'SQL'
        CREATE TABLE city (
            id        INTEGER NOT NULL,
            name      TEXT NOT NULL,
            country   TEXT NOT NULL,
            latitude  REAL NOT NULL,
            longitude REAL NOT NULL,
            PRIMARY KEY("id")
        ) WITHOUT ROWID
        SQL;

    /**
     * SQL statement to delete all cities.
     */
    private const DELETE_CITIES = 'DELETE FROM city';

    /**
     * SQL statement to add a city into the table.
     */
    private const INSERT_CITY = <<<'SQL'
        INSERT INTO city(id, name, country, latitude, longitude)
            VALUES(:id, :name, :country, :latitude, :longitude)
        SQL;

    /**
     * SQL statement to find a city.
     */
    private const SEARCH_CITY = <<<'SQL'
        SELECT
            id,
            name,
            country,
            latitude,
            longitude
        FROM city
        WHERE name LIKE :value
        ORDER BY
            name
        LIMIT :limit
        SQL;

    /**
     * SQL statement to find a city.
     */
    private const SEARCH_CITY_COUNTRY = <<<'SQL'
        SELECT
            id,
            name,
            country,
            latitude,
            longitude
        FROM city
        WHERE name LIKE :name AND country LIKE :country
        ORDER BY
            name
        LIMIT :limit
        SQL;

    /**
     * Delete all cities.
     *
     * @return bool true on success
     */
    public function deleteCities(): bool
    {
        return $this->exec(self::DELETE_CITIES);
    }

    /**
     * Finds cities by name.
     *
     * @param string $name  the name to search for
     * @param int    $limit the maximum number of rows to return
     *
     * @pslam-return array<int, OpenWeatherCityType>
     */
    public function findCity(string $name, int $limit = 25): array
    {
        $values = \explode(',', $name);
        if (2 === \count($values)) {
            return $this->findCityCountry($values[0], $values[1], $limit);
        }

        return $this->search(self::SEARCH_CITY, $name, $limit);
    }

    /**
     * Finds cities by name and country.
     *
     * @param string $city    the city to search for
     * @param string $country the country to search for
     * @param int    $limit   the maximum number of rows to return
     *
     * @pslam-return array<int, OpenWeatherCityType>
     *
     * @psalm-suppress PossiblyNullReference
     */
    public function findCityCountry(string $city, string $country, int $limit = 25): array
    {
        $city = $this->likeValue($city);
        $country = $this->likeValue($country);
        $stmt = $this->getStatement(self::SEARCH_CITY_COUNTRY);
        $stmt->bindParam(':name', $city);
        $stmt->bindParam(':country', $country);
        $stmt->bindParam(':limit', $limit, \SQLITE3_INTEGER);

        return $this->executeAndFetch($stmt);
    }

    /**
     * Insert a city.
     *
     * @param int    $id        the city identifier
     * @param string $name      the city name
     * @param string $country   the 2 letters ISO code of the country
     * @param float  $latitude  the city latitude
     * @param float  $longitude the city longitude
     *
     * @return bool true if success
     *
     * @psalm-suppress PossiblyNullReference
     */
    public function insertCity(int $id, string $name, string $country, float $latitude, float $longitude): bool
    {
        $stmt = $this->getStatement(self::INSERT_CITY);
        $stmt->bindParam(':id', $id, \SQLITE3_INTEGER);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':country', $country);
        $stmt->bindParam(':latitude', $latitude, \SQLITE3_FLOAT);
        $stmt->bindParam(':longitude', $longitude, \SQLITE3_FLOAT);

        // execute
        return false !== $stmt->execute();
    }

    /**
     * {@inheritdoc}
     */
    protected function createSchema(): void
    {
        $this->exec(self::CREATE_CITY);
        $this->createIndex('city', 'name');
    }
}
