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

use App\Entity\Customer;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for customer entity.
 *
 * @method Customer|null find($id, $lockMode = null, $lockVersion = null)
 * @method Customer|null findOneBy(array $criteria, array $orderBy = null)
 * @method Customer[]    findAll()
 * @method Customer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @author Laurent Muller
 *
 * @see \App\Entity\Customer
 */
class CustomerRepository extends AbstractRepository
{
    /**
     * The first name, last name and company field name.
     */
    public const NAME_COMPANY_FIELD = 'nameAndCompany';

    /**
     * The first name, last name and company fields.
     */
    public const NAME_COMPANY_FIELDS = ['lastName', 'firstName', 'company'];

    /**
     * The zip and city field name.
     */
    public const ZIP_CITY_FIELD = 'zipCity';

    /**
     * The zip code and city fields.
     */
    public const ZIP_CITY_FIELDS = ['zipCode', 'city'];

    /**
     * Constructor.
     *
     * @param ManagerRegistry $registry The connections and entity managers registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * Gets all customers order by name and company.
     *
     * @return Customer[]
     */
    public function findAllByNameAndCompany(): array
    {
        $fields = $this->concat(self::DEFAULT_ALIAS, self::NAME_COMPANY_FIELDS, 'ZZZ');

        return $this->createQueryBuilder(self::DEFAULT_ALIAS)
            ->orderBy($fields, Criteria::ASC)
            ->getQuery()
            ->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchFields(string $field, string $alias = self::DEFAULT_ALIAS)
    {
        switch ($field) {
            case self::NAME_COMPANY_FIELD:
                return $this->addPrefixes($alias, self::NAME_COMPANY_FIELDS);
            case self::ZIP_CITY_FIELD:
                return $this->addPrefixes($alias, self::ZIP_CITY_FIELDS);
            default:
                return parent::getSearchFields($field, $alias);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSortFields(string $field, string $alias = self::DEFAULT_ALIAS)
    {
        switch ($field) {
            case self::NAME_COMPANY_FIELD:
                return $this->concat($alias, self::NAME_COMPANY_FIELDS);
            case self::ZIP_CITY_FIELD:
                return $this->concat($alias, self::ZIP_CITY_FIELDS);
            default:
                return parent::getSortFields($field, $alias);
        }
    }
}
