<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\RecurringTransaction;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\TransactionType;
use Doctrine\ORM\EntityManagerInterface;

class RecurringTransactionDetector
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function detectForUser(User $user): void
    {
        $conn = $this->em->getConnection();

        $rows = $conn->fetchAllAssociative(
            'SELECT t.id, t.name, t.amount, t.type, t.date, t.account_id, t.recurring_transaction_id
             FROM transactions t
             JOIN accounts a ON t.account_id = a.id
             WHERE a.user_id = :userId
             ORDER BY t.account_id, t.date',
            ['userId' => $user->getId()]
        );

        $groups = [];
        foreach ($rows as $row) {
            $normalized = $this->normalizeLabel($row['name']);
            if ($normalized === '') continue;
            $key = $row['account_id'] . '|' . $row['type'] . '|' . $normalized;
            $groups[$key][] = $row;
        }

        foreach ($groups as $txs) {
            if (count($txs) < 2) continue;

            usort($txs, fn($a, $b) => strcmp($a['date'], $b['date']));

            $isRecurring = true;
            for ($i = 1; $i < count($txs); $i++) {
                $diff = (int) (new \DateTime($txs[$i - 1]['date']))->diff(new \DateTime($txs[$i]['date']))->days;
                if ($diff < 25 || $diff > 40) {
                    $isRecurring = false;
                    break;
                }
            }

            if (!$isRecurring) continue;

            $existingId = null;
            foreach ($txs as $tx) {
                if ($tx['recurring_transaction_id']) {
                    $existingId = (int) $tx['recurring_transaction_id'];
                    break;
                }
            }

            if ($existingId) {
                $recurring = $this->em->find(RecurringTransaction::class, $existingId);
                if (!$recurring) continue;
            } else {
                $first = $txs[0];
                $account = $this->em->find(Account::class, (int) $first['account_id']);
                if (!$account) continue;

                $days = array_map(fn($tx) => (int) (new \DateTime($tx['date']))->format('j'), $txs);
                $dayOfMonth = (int) round(array_sum($days) / count($days));

                $amounts = array_map(fn($tx) => abs((float) $tx['amount']), $txs);
                sort($amounts);
                $medianAmount = $amounts[(int) floor(count($amounts) / 2)];
                $medianAmount = $first['type'] === 'expense' ? -$medianAmount : $medianAmount;

                $recurring = (new RecurringTransaction())
                    ->setName($this->normalizeLabel($first['name']))
                    ->setType(TransactionType::from($first['type']))
                    ->setAmount((string) $medianAmount)
                    ->setDayOfMonth($dayOfMonth)
                    ->setStartDate(new \DateTime($first['date']))
                    ->setAccount($account)
                    ->setUser($user);

                $this->em->persist($recurring);
                $this->em->flush();
            }

            foreach ($txs as $tx) {
                if ($tx['recurring_transaction_id']) continue;
                $transaction = $this->em->find(Transaction::class, (int) $tx['id']);
                if ($transaction) {
                    $transaction->setRecurringTransaction($recurring);
                }
            }
        }

        $this->em->flush();
    }

    private function normalizeLabel(string $label): string
    {
        $label = mb_strtoupper($label, 'UTF-8');

        $months = [
            'JANVIER', 'FÉVRIER', 'FEVRIER', 'MARS', 'AVRIL', 'MAI', 'JUIN',
            'JUILLET', 'AOÛT', 'AOUT', 'SEPTEMBRE', 'OCTOBRE', 'NOVEMBRE',
            'DÉCEMBRE', 'DECEMBRE',
        ];
        $label = str_replace($months, '', $label);

        // années (2024, 2025…)
        $label = preg_replace('/\b20\d{2}\b/', '', $label) ?? $label;

        // patterns date : 01/25, 01/2025, 01-25
        $label = preg_replace('/\b\d{1,2}[\/\-]\d{2,4}\b/', '', $label) ?? $label;

        // nombres isolés de 2+ chiffres
        $label = preg_replace('/\b\d{2,}\b/', '', $label) ?? $label;

        // nettoyage des espaces et caractères parasites
        $label = preg_replace('/[\s\*\.\-\_\/]+/', ' ', $label) ?? $label;

        return trim($label);
    }
}
