<?php

namespace App\Controller;

use App\DTO\TransactionDTO;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use \App\Entity\User;
use DateTime;

#[Route('/api')]
final class TransactionController extends AbstractController
{
    #[Route('/transactions', methods: ['GET'], name: 'app_transactions')]
    public function getTransactions(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $accountId = $request->query->get('accountId');
        $categoryId = $request->query->get('categoryId');
        $from = $request->query->get('from') ? new DateTime($request->query->get('from')) : null;
        $to = $request->query->get('to') ? new DateTime($request->query->get('to')) : null;
        $limit = $request->query->get('limit') ? (int) $request->query->get('limit') : null;
        $offset = $request->query->get('offset') ? (int) $request->query->get('offset') : null;

        if ($accountId) {
            $account = $em->getRepository(Account::class)->findOneBy(['id' => $accountId, 'user' => $user]);
            if (!$account) {
                return $this->json(['message' => 'Account not found'], 404);
            }
        }

        $transactions = $em->getRepository(Transaction::class)->findWithFilters($user, $accountId, $categoryId, $from, $to, $limit, $offset);
        $transactionsCount = $em->getRepository(Transaction::class)->countWithFilters($user, $accountId, $categoryId, $from, $to);

        return $this->json([
            'data' => $transactions,
            'total' => $transactionsCount,
            'page' => (int) ($request->query->get('page', 1)),
            'pages' => $limit ? (int) ceil($transactionsCount / $limit) : 1,
        ], 200, [], ['groups' => ['transaction:read']]);
    }

    #[Route('/transactions/{id}', methods: ['GET'], name: 'app_transaction')]
    public function getOne(int $id, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $transaction = $em->getRepository(Transaction::class)->find($id);

        if (!$transaction || $transaction->getAccount()->getUser() !== $user) {
            return $this->json(['message' => 'Transaction not found'], 404);
        }

        return $this->json($transaction, 200, [], ['groups' => ['transaction:read']]);
    }

    #[Route('/transactions', methods: ['POST'], name: 'app_transactions_create')]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em
    ): Response {
        $dto = $serializer->deserialize($request->getContent(), TransactionDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        $account = $em->getRepository(Account::class)->findOneBy(['id' => $dto->getAccountId(), 'user' => $user]);
        if (!$account) {
            return $this->json(['message' => 'Account not found'], 404);
        }

        $category = $em->getRepository(Category::class)->find($dto->getCategoryId());
        if (!$category) {
            return $this->json(['message' => 'Category not found'], 404);
        }

        if ($category->getType()->value !== $dto->getType()->value) {
            return $this->json(['message' => 'Category type does not match transaction type'], 400);
        }

        $transaction = new Transaction();
        $transaction->setName($dto->getName());
        $transaction->setType($dto->getType());
        $transaction->setAmount($dto->getAmount());
        $transaction->setDate($dto->getDate());
        $transaction->setDescription($dto->getDescription());
        $transaction->setAccount($account);
        $transaction->setCategory($category);

        $em->persist($transaction);
        $em->flush();

        return $this->json($transaction, 201, [], ['groups' => ['transaction:read']]);
    }

    #[Route('/transactions/{id}', methods: ['PUT'], name: 'app_transaction_update')]
    public function update(
        int $id,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em
    ): Response {
        $dto = $serializer->deserialize($request->getContent(), TransactionDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        $transaction = $em->getRepository(Transaction::class)->find($id);

        if (!$transaction || $transaction->getAccount()->getUser() !== $user) {
            return $this->json(['message' => 'Transaction not found'], 404);
        }

        $account = $em->getRepository(Account::class)->findOneBy(['id' => $dto->getAccountId(), 'user' => $user]);
        if (!$account) {
            return $this->json(['message' => 'Account not found'], 404);
        }

        $category = $em->getRepository(Category::class)->find($dto->getCategoryId());
        if (!$category) {
            return $this->json(['message' => 'Category not found'], 404);
        }

        if ($category->getType()->value !== $dto->getType()->value) {
            return $this->json(['message' => 'Category type does not match transaction type'], 400);
        }

        $transaction->setName($dto->getName());
        $transaction->setType($dto->getType());
        $transaction->setAmount($dto->getAmount());
        $transaction->setDate($dto->getDate());
        $transaction->setDescription($dto->getDescription());
        $transaction->setAccount($account);
        $transaction->setCategory($category);

        $em->flush();

        return $this->json($transaction, 200, [], ['groups' => ['transaction:read']]);
    }


    #[Route('/transactions/{id}', methods: ['DELETE'], name: 'app_transaction_delete')]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $transaction = $em->getRepository(Transaction::class)->find($id);

        if (!$transaction || $transaction->getAccount()->getUser() !== $user) {
            return $this->json(['message' => 'Transaction not found'], 404);
        }

        $em->remove($transaction);
        $em->flush();

        return $this->json(null, 204);
    }
}
