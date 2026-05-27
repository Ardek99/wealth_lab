<?php

namespace App\Repository;

use App\Entity\RecurringTransaction;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\BudgetGroupType;
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
            ->setParameter('start', (new \DateTime())->modify('first day of this month')->format('Y-m-d'))
            ->setParameter('end', (new \DateTime())->modify('last day of this month')->format('Y-m-d'))
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
            ->setParameter('start', (new \DateTime())->modify('first day of this month')->format('Y-m-d'))
            ->setParameter('end', (new \DateTime())->modify('last day of this month')->format('Y-m-d'))
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
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('user', $user)
            ->setParameter('type', TransactionType::EXPENSE)
            ->setParameter('start', (new \DateTime())->modify('first day of this month')->format('Y-m-d'))
            ->setParameter('end', (new \DateTime())->modify('last day of this month')->format('Y-m-d'))
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

    public function findFutureByRecurring(RecurringTransaction $recurring, \DateTime $from): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.recurringTransaction = :r AND t.date >= :from')
            ->setParameter('r', $recurring)
            ->setParameter('from', $from)
            ->getQuery()->getResult();
    }

    public function sumByType(User $user, TransactionType $type, \DateTime $start, \DateTime $end): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount) as total')
            ->join('t.account', 'a')
            ->where('a.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.date BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function sumByBudgetGroup(User $user, BudgetGroupType $group, \DateTime $start, \DateTime $end): float
    {
        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->fetchOne(
            'SELECT SUM(t.amount) FROM transactions t
             JOIN categories c ON t.category_id = c.id
             JOIN accounts a ON t.account_id = a.id
             WHERE a.user_id = :userId
               AND t.type = :type
               AND t.date BETWEEN :start AND :end
               AND c.budget_group = :group',
            [
                'userId' => $user->getId(),
                'type'   => 'expense',
                'start'  => $start->format('Y-m-d'),
                'end'    => $end->format('Y-m-d'),
                'group'  => $group->value,
            ]
        );

        return (float) ($result ?? 0);
    }
}
