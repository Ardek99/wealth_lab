<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;

final class DashboardController extends AbstractController
{
    #[Route('/api/dashboard/overview', methods: ['GET'], name: 'app_dashboard_overview')]
    public function overview(
        TransactionRepository $transactionRepository,
        AccountRepository $accountRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'currentBalance'     => $accountRepository->getTotalBalance($user),
            'income'             => $transactionRepository->getMonthlyIncome($user),
            'expenses'           => $transactionRepository->getMonthlyExpenses($user),
            'topCategories'      => $transactionRepository->getTopCategories($user),
            'recentTransactions' => $transactionRepository->getRecentTransactions($user),
        ], 200, [], ['groups' => ['transaction:read']]);
    }
}
