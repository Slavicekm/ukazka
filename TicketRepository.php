<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\TicketComponent;
use App\Services\Limiter;
use Doctrine;
use Nette;

/**
 * Class TicketRepository
 * @package App\Repository
 */
class TicketRepository extends Doctrine\ORM\EntityRepository
{
    use Nette\SmartObject;

    const DEFAULT_SORT = '-createdAt';
    const DEFAULT_LIMIT = 10;

    /**
     * Finds ticket by id
     * @param integer $id
     * @return object|null
     */
    public function findTicketById(int $id): ?object
    {
        return $this->findOneBy([
            'id' => $id,
        ]);
    }

    /**
     * Find ticket by package id and month
     *
     * @param integer $packageId
     * @param integer $month
     * @return array
     */
    public function findCurrentYearTicketsByPackageIdAndMonth(int $packageId, int $month): array
    {
        return $this->createQueryBuilder('t')
            ->where('SUBSTRING(t.closestMatchDate, 6, 2) = :month')
            ->andWhere('SUBSTRING(t.closestMatchDate, 1, 4) = :year')
            ->andWhere('t.package = :package')
            ->andWhere('t.finalState != :new')
            ->setParameters([
                'month' => $month,
                'year' => date('Y'),
                'package' => $packageId,
                'new' => TicketComponent::STATE_NEW,
            ])
            ->groupBy('t.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds tickets by year, month and packageId
     *
     * @param integer $year
     * @param integer $month
     * @param integer $packageId
     * @return array
     */
    public function findCompletedTicketsByYearMonthAndPackageId(int $year, int $month, int $packageId): array
    {
        return $this->createQueryBuilder('t')
            ->where('SUBSTRING(t.closestMatchDate, 6, 2) = :month')
            ->andWhere('SUBSTRING(t.closestMatchDate, 1, 4) = :year')
            ->andWhere('t.package = :package')
            ->andWhere('t.finalState != :new')
            ->setParameters([
                'month' => $month,
                'year' => $year,
                'package' => $packageId,
                'new' => TicketComponent::STATE_NEW,
            ])
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds filtered tickets
     *
     * @param Limiter $limiter
     * @return array
     */
    public function findLimitedTickets(Limiter $limiter): array
    {
        $limiter->setDefaultSort(self::DEFAULT_SORT);
        $limiter->setLimit(self::DEFAULT_LIMIT);

        $sortCol = $limiter->getSortCol();

        if (!in_array($sortCol, ['createdAt', 'finalCourse', 'finalState', 'closestMatchDate', 'packageName'])) {
            $sortCol = 'createdAt';
        }

        $query = $this->createQueryBuilder('t');

        $fulltext = $limiter->getFulltext();
        if ($fulltext) {
            $fulltext = preg_replace('!\s+!', '%', $fulltext);
            $query->join('App\Model\Admin', 'a', 'WITH', 't.admin = a.id')
                ->where('CONCAT(a.firstName , \' \' , a.lastName) like :query OR CONCAT(a.lastName , \' \' , a.firstName) like :query')->setParameter('query', '%' . $fulltext . '%');
        }

        $createdAtFrom = $limiter->getCriteriaByIndex('createdAtFrom');
        if ($createdAtFrom) {
            $query->andWhere('t.createdAt >= :createdAtFrom')
                ->setParameter('createdAtFrom', $createdAtFrom);
        }

        $createdAtTo = $limiter->getCriteriaByIndex('createdAtTo');
        if ($createdAtTo) {
            $query->andWhere('t.createdAt <= :createdAtTo')
                ->setParameter('createdAtTo', $createdAtTo);
        }


        $packageId = $limiter->getCriteriaByIndex('packageId');
        if ($packageId) {
            $query->andWhere('t.package = :packageId')
                ->setParameter('packageId', $packageId);
        }

        $states = $limiter->getCriteriaByIndex('states');
        if ($states) {
            $query->andWhere('t.finalState in (:states)')
                ->setParameter('states', $states);
        }

        $state = $limiter->getCriteriaByIndex('state');
        if ($state) {
            $query->andWhere('t.finalState = :state')
                ->setParameter('state', $state);
        }

        $countQuery = clone $query;
        $count = $countQuery->select('count(t.id)')->getQuery()->getSingleScalarResult();

        $limiter->setTotal(intval($count));

        $sortDir = $limiter->getSortDir();

        if ($sortCol == 'packageName') {
            $query->join('App\Model\Package', 'p', 'WITH', 't.package = p.id')
                ->orderBy('p.name', $sortDir);
        } else {
            $query->orderBy('t.' . $sortCol, $sortDir);
        }
        $unlimited = $limiter->getCriteriaByIndex('unlimited');
        if (!$unlimited) {
            $query->setFirstResult($limiter->getOffset())
                ->setMaxResults($limiter->getLimit());
        }
        return $query->getQuery()
            ->getResult();
    }

    /**
     * @param Limiter $limiter
     * @return array
     */
    public function findLimitedHistoryTickets(Limiter $limiter): array
    {
        $limiter->setDefaultSort('-closestMatchDate');
        $limiter->setLimit(self::DEFAULT_LIMIT);

        $sortCol = $limiter->getDefaultSortCol();

        $query = $this->createQueryBuilder('t')
            ->join('App\Model\Package', 'p', 'WITH', 't.package = p.id')
            ->where('p.isActive = :isActive')
            ->setParameter('isActive', true);

        $createdAtFrom = $limiter->getCriteriaByIndex('createdAtFrom');
        if ($createdAtFrom) {
            $query->andWhere('t.closestMatchDate >= :createdAtFrom')
                ->setParameter('createdAtFrom', $createdAtFrom);
        }

        $createdAtTo = $limiter->getCriteriaByIndex('createdAtTo');
        if ($createdAtTo) {
            $query->andWhere('t.closestMatchDate <= :createdAtTo')
                ->setParameter('createdAtTo', $createdAtTo);
        }

        $packageId = $limiter->getCriteriaByIndex('packageId');
        if ($packageId) {
            $query->andWhere('t.package = :packageId')
                ->setParameter('packageId', $packageId);
        }

        $states = $limiter->getCriteriaByIndex('states');
        if ($states) {
            $query->andWhere('t.finalState in (:states)')
                ->setParameter('states', $states);
        } else {
            $query->andWhere('t.finalState != :new')
                ->setParameter('new', TicketComponent::STATE_NEW);
        }

        $countQuery = clone $query;
        $count = $countQuery->select('count(t.id)')->getQuery()->getSingleScalarResult();

        $limiter->setTotal(intval($count));

        $sortDir = $limiter->getSortDir();

        return $query->orderBy('t.' . $sortCol, $sortDir)
            ->setFirstResult($limiter->getOffset())
            ->setMaxResults($limiter->getLimit())
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds tickets by array of package ids
     *
     * @param array $ids
     * @return array
     */
    public function findUnresolvedTicketsByPackagesId(array $ids): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.package in (:ids)')
            ->andWhere('t.finalState = :new')
            ->setParameters([
                'ids' => $ids,
                'new' => TicketComponent::STATE_NEW,
            ])
            ->orderBy('t.closestMatchDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array
     */
    public function findUncheckedTickets(): array
    {
        return $this->findBy([
            'checked' => false,
        ]);
    }

    /**
     * @param int $packageId
     * @return array
     */
    public function findUncheckedTicketsByPackageId(int $packageId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.checked = :checked')
            ->andWhere('t.package = :packageId')
            ->setParameters([
                'checked' => false,
                'packageId' => $packageId,
            ])
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $packageId
     * @return int
     */
    public function findUncheckedTicketsCountByPackageId(int $packageId): int
    {
        $count = $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->where('t.checked = :checked')
            ->andWhere('t.package = :packageId')
            ->setParameters([
                'checked' => false,
                'packageId' => $packageId,
            ])
            ->getQuery()
            ->getSingleScalarResult();
        return intval($count);
    }

    /**
     * @param int $limit
     * @return array
     */
    public function findLastHistoryTickets(int $limit = 6): array
    {
        return $this->createQueryBuilder('t')
            ->join('App\Model\Package', 'p', 'WITH', 't.package = p.id')
            ->where('t.finalState != :state')
            ->andWhere('p.isActive = :active')
            ->setParameters([
                'state' => TicketComponent::STATE_NEW,
                'active' => true,
            ])
            ->setMaxResults($limit)
            ->orderBy('t.closestMatchDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
