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

use App\Util\FileUtils;

/**
 * Extended the SQLite3 database with transaction support.
 */
abstract class AbstractDatabase extends \SQLite3 implements \Stringable
{
    /**
     * The in-memory database file name.
     */
    final public const IN_MEMORY = ':memory:';

    /**
     * The opened statements.
     *
     * @var \SQLite3Stmt[]
     */
    protected array $statements = [];

    /**
     * The transaction state.
     */
    protected bool $transaction = false;

    /**
     * Instantiates and opens the database.
     *
     * @param string $filename       Path to the SQLite database, or <code>:memory:</code> to use in-memory database.
     *                               If filename is an empty string, then a private, temporary on-disk database will be created.
     *                               This private database will be automatically deleted as soon as the database connection is closed.
     * @param bool   $readonly       true open the database for reading only. Note that if the file name
     *                               does not exist, the database is opened with the
     *                               <code>SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE</code> flags.
     * @param string $encryption_key An optional encryption key used when encrypting and decrypting an SQLite database. If the
     *                               SQLite's encryption module is not installed, this parameter will have no effect.
     */
    public function __construct(protected string $filename, bool $readonly = false, string $encryption_key = '')
    {
        // check creation state
        $create = '' === $filename || self::IN_MEMORY === $filename || !FileUtils::exists($filename) || 0 === \filesize($filename);

        if ($create) {
            $flags = \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE;
        } elseif ($readonly) {
            $flags = \SQLITE3_OPEN_READONLY;
        } else {
            $flags = \SQLITE3_OPEN_READWRITE;
        }

        parent::__construct($filename, $flags, $encryption_key);

        // create schema
        if ($create) {
            $this->createSchema();
        }
    }

    /**
     * Returns a string representing this object.
     */
    public function __toString(): string
    {
        return $this->filename;
    }

    /**
     * Begin a transaction.
     *
     * @return bool true if success, false on failure
     */
    public function beginTransaction(): bool
    {
        if (!$this->isTransaction() && $this->exec('BEGIN TRANSACTION;')) {
            $this->transaction = true;

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        // close statements
        foreach ($this->statements as $statement) {
            $statement->close();
        }
        $this->statements = [];

        // cancel transaction
        if ($this->isTransaction()) {
            $this->rollbackTransaction();
        }

        return parent::close();
    }

    /**
     * Commit the current transaction (if any).
     *
     * @return bool true if success, false on failure
     */
    public function commitTransaction(): bool
    {
        if ($this->isTransaction() && $this->exec('COMMIT TRANSACTION;')) {
            $this->transaction = false;

            return true;
        }

        return false;
    }

    /**
     * Compact the database.
     *
     * <b>NB:</b> Make sure that there is no transaction open when the command is executed. For more information
     * see: <a href="https://www.sqlitetutorial.net/sqlite-vacuum/" target="_blank">SQLite VACUUM</a>
     *
     * @return bool true if success
     */
    public function compact(): bool
    {
        return $this->exec('VACUUM;');
    }

    /**
     * Gets the file name.
     *
     * @return string the file name
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Returns if a transaction is active.
     *
     * @return bool true if a transaction is active
     */
    public function isTransaction(): bool
    {
        return $this->transaction;
    }

    /**
     * Set a pragma statement.
     *
     * @param string     $name  the pragma name
     * @param mixed|null $value the optional pragma value
     *
     * @return bool true if the succeeded, false on failure
     */
    public function pragma(string $name, mixed $value = null): bool
    {
        if (null !== $value) {
            return $this->exec("PRAGMA $name = $value");
        }

        return $this->exec("PRAGMA $name");
    }

    /**
     * Rollback the current transaction (if any).
     *
     * @return bool true if success, false on failure
     */
    public function rollbackTransaction(): bool
    {
        if ($this->isTransaction() && $this->exec('ROLLBACK TRANSACTION;')) {
            $this->transaction = false;

            return true;
        }

        return false;
    }

    /**
     * Binds a parameter to the given statement variable.
     *
     * @param \SQLite3Stmt $stmt  the statement to bind parameter with
     * @param int|string   $name  either a string or an int identifying the statement variable to which the parameter should be bound
     * @param mixed        $value the parameter to bind to a statement variable
     * @param ?int         $type  the optional data type of the parameter to bind
     *
     * @return bool true if the parameter is bound to the statement variable, false
     *              on failure
     */
    protected function bindParam(\SQLite3Stmt $stmt, int|string $name, mixed $value, int $type = null): bool
    {
        if (null !== $type) {
            return $stmt->bindParam($name, $value, $type);
        }

        return $stmt->bindParam($name, $value);
    }

    /**
     * Creates an index.
     *
     * @param string $table  the table name
     * @param string $column the column name
     *
     * @return bool true if the creation succeeded, false on failure
     */
    protected function createIndex(string $table, string $column): bool
    {
        $name = \sprintf('idx_%s_%s', $table, $column);
        $query = "CREATE INDEX IF NOT EXISTS $name ON $table($column)";

        return $this->exec($query);
    }

    /**
     * Creates the database schema.
     *
     * This function is called when the database is opened with the <code>SQLITE3_OPEN_CREATE</code> flag.
     */
    abstract protected function createSchema(): void;

    /**
     * Execute the given statement and fetch result to an associative array.
     *
     * @param \SQLite3Stmt $stmt the statement to execute
     * @param int          $mode controls how the next row will be returned to the caller. This value
     *                           must be one of either SQLITE3_ASSOC (default), SQLITE3_NUM, or SQLITE3_BOTH.
     *
     * @pslam-template T of array<string, mixed>
     *
     * @pslam-return array<int, T>
     */
    protected function executeAndFetch(\SQLite3Stmt $stmt, int $mode = \SQLITE3_ASSOC): array
    {
        $rows = [];
        $result = $stmt->execute();
        if ($result instanceof \SQLite3Result) {
            while ($row = $result->fetchArray($mode)) {
                $rows[] = $row;
            }
            $result->finalize();
        }

        return $rows;
    }

    /**
     * Gets a statement for the given query.
     *
     * <p>
     * NB: The statement is created only once and is cached for future use.
     * </p>
     *
     * @param string $query the SQL query to prepare
     *
     * @return \SQLite3Stmt|null the statement object on success or false on failure
     */
    protected function getStatement(string $query): ?\SQLite3Stmt
    {
        if (!isset($this->statements[$query])) {
            $statement = $this->prepare($query);
            if (false !== $statement) {
                $this->statements[$query] = $statement;

                return $statement;
            }

            return null;
        }

        return $this->statements[$query];
    }

    /**
     * Build a like value parameter.
     *
     * @param string $value the value parameter
     *
     * @return string the like value parameter
     */
    protected function likeValue(string $value): string
    {
        return '%' . \trim($value) . '%';
    }

    /**
     * Search data.
     * <p>
     * <b>NB</b>: The SQL query must contain 2 parameters:
     * <ul>
     * <li>"<code>:value</code>" - The search parameter.</li>
     * <li>"<code>:limit</code>" - The limit parameter.</li>
     * </ul>
     * </p>.
     *
     * @param string $query the SQL query to prepare
     * @param string $value the value to search for
     * @param int    $limit the maximum number of rows to return
     * @param int    $mode  controls how the next row will be returned to the caller. This value
     *                      must be one of either SQLITE3_ASSOC (default), SQLITE3_NUM, or SQLITE3_BOTH.
     *
     * @pslam-template T of array<string, mixed>
     *
     * @pslam-return array<int, T>
     */
    protected function search(string $query, string $value, int $limit, int $mode = \SQLITE3_ASSOC): array
    {
        // parameter
        $value = $this->likeValue($value);

        // create statement
        /** @var \SQLite3Stmt $stmt */
        $stmt = $this->getStatement($query);
        $stmt->bindParam(':value', $value);
        $stmt->bindParam(':limit', $limit, \SQLITE3_INTEGER);

        // execute
        return $this->executeAndFetch($stmt, $mode);
    }
}
