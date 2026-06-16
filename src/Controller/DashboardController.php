<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Repository\TransactionRepository;

final class DashboardController extends AbstractController
{
    #[Route('/api/dashboard/overview', methods: ['GET'], name: 'app_dashboard_overview')]
    public function overview(
        Request $request,
        TransactionRepository $transactionRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $from = $request->query->get('from')
            ? new \DateTime($request->query->get('from'))
            : (new \DateTime())->modify('first day of this month');
        $to = $request->query->get('to')
            ? new \DateTime($request->query->get('to'))
            : (new \DateTime())->modify('last day of this month');

        return $this->json([
            'income'             => $transactionRepository->getMonthlyIncome($user, $from, $to),
            'expenses'           => $transactionRepository->getMonthlyExpenses($user, $from, $to),
            'topCategories'      => $transactionRepository->getTopCategories($user, $from, $to),
            'recentTransactions' => $transactionRepository->getRecentTransactions($user),
            'monthlyEvolution'   => $transactionRepository->getMonthlyEvolution($user, 6, $user->getPeriodStartDay()),
        ], 200, [], ['groups' => ['transaction:read']]);
    }
}
