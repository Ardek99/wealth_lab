<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Enum\BudgetGroupType;
use App\Enum\TransactionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class BudgetController extends AbstractController
{
    #[Route('/api/budgets/overview', methods:['GET'], name: 'app_budget')]
    public function index(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $start = new \DateTime('first day of this month');
        $end = new \DateTime('last day of this month');

        $repo   = $em->getRepository(Transaction::class);
        $income = $repo->sumByType($user, TransactionType::INCOME, $start, $end);
        $needs  = $repo->sumByBudgetGroup($user, BudgetGroupType::NEEDS, $start, $end);
        $wants  = $repo->sumByBudgetGroup($user, BudgetGroupType::WANTS, $start, $end);

        return $this->json([
            'income'  => round($income, 2),
            'needs'   => round($needs, 2),
            'wants'   => round($wants, 2),
            'savings' => round($income - $needs - $wants, 2),
        ]);
    }
}
