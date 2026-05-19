<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\TransactionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function findWithFilters(
        User $user, 
        ?int $accountId, 
        ?int $categoryId, 
        ?\DateTime $from, 
        ?\DateTime $to,
        ?int $limit = null,
        ?int $offset = null
    ): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.date', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($accountId) {
            $qb->andWhere('t.account = :accountId')
            ->setParameter('accountId', $accountId);
        }

        if ($categoryId) {
            $qb->andWhere('t.category = :categoryId')
            ->setParameter('categoryId', $categoryId);
        }

        if ($from) {
            $qb->andWhere('t.date >= :from')
            ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('t.date <= :to')
            ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    public function countWithFilters(User $user, ?int $accountId, ?int $categoryId, ?\DateTime $from, ?\DateTime $to): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user);

        if ($accountId) {
            $qb->andWhere('t.account = :accountId')
               ->setParameter('accountId', $accountId);
        }
        if ($categoryId) {
            $qb->andWhere('t.category = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }
        if ($from) {
            $qb->andWhere('t.date >= :from')
               ->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('t.date <= :to')
               ->setParameter('to', $to);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getMonthlyIncome(User $user): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount) as income')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('user', $user)
            ->setParameter('type', TransactionType::INCOME)
            ->setParameter('start', (new \DateTime())->modify('first day of this month')->setTime(0, 0))
            ->setParameter('end', (new \DateTime())->modify('last day of this month')->setTime(23, 59, 59))
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (float) $result;
    }

    public function getMonthlyExpenses(User $user): float
    {
            $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount) as expense')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('user', $user)
            ->setParameter('type', TransactionType::EXPENSE)
            ->setParameter('start', (new \DateTime())->modify('first day of this month')->setTime(0, 0))
            ->setParameter('end', (new \DateTime())->modify('last day of this month')->setTime(23, 59, 59))
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (float) $result;
    }

    public function getTopCategories(User $user): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount) as total, c.name as category')
            ->join('t.account', 'a')
            ->join('t.category', 'c')
            ->andWhere('a.user = :user')
            ->andWhere('t.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', TransactionType::EXPENSE)
            ->groupBy('c.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    public function getRecentTransactions(User $user): array
    {
        $result = $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.date', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }
}
