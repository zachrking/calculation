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

namespace App\Repository;

use App\Util\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

/**
 * Base repository.
 *
 * @method T|null find($id, $lockMode = null, $lockVersion = null)
 * @method T|null findOneBy(array $criteria, array $orderBy = null)
 * @method T[]    findAll()
 * @method T[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @author Laurent Muller
 * @template T of \App\Entity\AbstractEntity
 * @template-extends ServiceEntityRepository<T>
 */
abstract class AbstractRepository extends ServiceEntityRepository
{
    /**
     * The default entity alias used to create query builder (value = 'e') .
     */
    public const DEFAULT_ALIAS = 'e';

    /**
     * Creates a default query builder.
     *
     * @param string $alias the entity alias
     */
    public function createDefaultQueryBuilder(string $alias = self::DEFAULT_ALIAS): QueryBuilder
    {
        return $this->createQueryBuilder($alias);
    }

    /**
     * Gets sorted, distinct and not null values of the given column.
     *
     * @param string $field the field name (column) to get values for
     * @param string $value a value to search within the column or <code>null</code> for all
     * @param int    $limit the maximum number of results to retrieve (the "limit") or <code>-1</code> for all
     *
     * @return array an array, maybe empty; of matching values
     */
    public function getDistinctValues(string $field, ?string $value = null, int $limit = -1): array
    {
        // name
        $name = self::DEFAULT_ALIAS . '.' . $field;

        // select and order
        $builder = $this->createQueryBuilder(self::DEFAULT_ALIAS)
            ->select($name)
            ->distinct()
            ->orderBy($name);

        // search
        $expr = $builder->expr();
        if (Utils::isString($value)) {
            $param = 'search';
            $like = $expr->like($name, ':' . $param);
            $builder->where($like)
                ->setParameter($param, "%{$value}%");
        } else {
            $builder->where($expr->isNotNull($name));
        }

        // limit
        if ($limit > 0) {
            $builder->setMaxResults($limit);
        }

        return $builder->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Gets the next identifier.
     */
    public function getNextId(): int
    {
        $maxId = (int) $this->createQueryBuilder('e')
            ->select('MAX(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $maxId + 1;
    }

    /**
     * Gets the database search fields.
     *
     * The default implementation returns the alias and the field separated by a dot ('.') character.
     *
     * @param string $field the field name
     * @param string $alias the entity alias
     *
     * @return string|string[] one on more database search fields
     */
    public function getSearchFields(string $field, string $alias = self::DEFAULT_ALIAS)
    {
        return "$alias.$field";
    }

    /**
     * Creates a search query.
     *
     * @param array  $sortedFields the sorted fields where key is the field name and value is the sort mode ("ASC" or "DESC")
     * @param array  $criterias    the filter criterias (the where clause)
     * @param string $alias        the entity alias
     *
     * @see AbstractRepository::createDefaultQueryBuilder()
     */
    public function getSearchQuery(array $sortedFields = [], array $criterias = [], string $alias = self::DEFAULT_ALIAS): Query
    {
        // builder
        $builder = $this->createDefaultQueryBuilder($alias);

        // criterias
        if (!empty($criterias)) {
            foreach ($criterias as $criteria) {
                if ($criteria instanceof Criteria) {
                    $builder->addCriteria($criteria);
                } else {
                    $builder->andWhere($criteria);
                }
            }
        }

        // order by clause
        if (!empty($sortedFields)) {
            foreach ($sortedFields as $name => $order) {
                $field = $this->getSortField($name, $alias);
                $builder->addOrderBy($field, $order);
            }
        }

        // query
        return $builder->getQuery();
    }

    /**
     * Gets the name of the single id field. Note that this only works on
     * entity classes that have a single-field primary key.
     *
     * @throws \Doctrine\ORM\Mapping\MappingException if the class doesn't have an identifier or it has a composite primary key
     */
    public function getSingleIdentifierFieldName(): string
    {
        return $this->_class->getSingleIdentifierFieldName();
    }

    /**
     * Gets the database sort field.
     *
     * The default implementation returns the alias and the field separated by a dot ('.') character.
     *
     * @param string $field the field name
     * @param string $alias the entity alias
     *
     * @return string the sort field
     */
    public function getSortField(string $field, string $alias = self::DEFAULT_ALIAS): string
    {
        return "$alias.$field";
    }

    /**
     * Add alias to the given fields.
     *
     * @param string   $alias the entity alias
     * @param string[] $names the fields to add alias
     *
     * @return string[] the fields with alias
     */
    protected function addPrefixes(string $alias, array $names): array
    {
        return \array_map(function (string $name) use ($alias): string {
            return "{$alias}.{$name}";
        }, $names);
    }

    /**
     * Concat fields.
     *
     * @param string   $alias   the entity prefix
     * @param string[] $fields  the fields to concat
     * @param string   $default the default value to use when a field is null
     *
     * @return string the concatened fields
     */
    protected function concat(string $alias, array $fields, string $default = ''): string
    {
        foreach ($fields as &$field) {
            $field = "COALESCE($alias.$field, '$default')";
        }

        return 'CONCAT(' . \implode(', ', $fields) . ')';
    }
}
