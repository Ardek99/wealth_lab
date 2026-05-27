<?php

namespace App\Controller;

use App\DTO\RecurringTransactionDTO;
use App\Entity\Account;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\RecurringTransaction;
use App\Entity\Transaction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RecurringTransactionController extends AbstractController
{
    #[Route('/api/recurring-transactions', methods: ['GET'], name: 'app_recurring_transactions')]
    public function getAll(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $em->getRepository(RecurringTransaction::class)->findByUser($user);
        return $this->json($data, 200, [], ['groups' => ['recurring_transaction:read']]);
    }

    #[Route('/api/recurring-transactions', methods: ['POST'], name: 'app_recurring_transaction_create')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $dto = $serializer->deserialize($request->getContent(), RecurringTransactionDTO::class, 'json');
        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
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

        $reccuringTransaction = new RecurringTransaction();
        $reccuringTransaction->setName($dto->getName());
        $reccuringTransaction->setType($dto->getType());
        $reccuringTransaction->setAmount((string) $dto->getAmount());
        $reccuringTransaction->setDayOfMonth($dto->getDayOfMonth());
        $reccuringTransaction->setStartDate($dto->getStartDate());
        if ($dto->getEndDate()) $reccuringTransaction->setEndDate($dto->getEndDate());
        $reccuringTransaction->setAccount($account);
        $reccuringTransaction->setCategory($category);
        $reccuringTransaction->setUser($user);

        $em->persist($reccuringTransaction);
        $em->flush();

        return $this->json($reccuringTransaction, 201, [], ['groups' => ['recurring_transaction:read']]);
    }


    #[Route('/api/recurring-transactions/{id}', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $recurring = $em->getRepository(RecurringTransaction::class)->find($id);
        if (!$recurring || $recurring->getUser() !== $user) {
            return $this->json(['message' => 'Not found'], 404);
        }

        $dto = $serializer->deserialize($request->getContent(), RecurringTransactionDTO::class, 'json');
        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $account = $em->getRepository(Account::class)->findOneBy(['id' => $dto->getAccountId(), 'user' => $user]);
        if (!$account) return $this->json(['message' => 'Account not found'], 404);

        $category = $em->getRepository(Category::class)->find($dto->getCategoryId());
        if (!$category) return $this->json(['message' => 'Category not found'], 404);

        $recurring->setName($dto->getName());
        $recurring->setType($dto->getType());
        $recurring->setAmount((string) $dto->getAmount());
        $recurring->setDayOfMonth($dto->getDayOfMonth());
        $recurring->setStartDate($dto->getStartDate());
        $recurring->setEndDate($dto->getEndDate());
        $recurring->setAccount($account);
        $recurring->setCategory($category);

        // Propage les changements aux transactions futures
        $fromDate = new \DateTime('today');
        $futureTx = $em->getRepository(Transaction::class)->findFutureByRecurring($recurring, $fromDate);

        foreach ($futureTx as $tx) {
            $tx->setName($dto->getName());
            $tx->setAmount((string) $dto->getAmount());
            $tx->setCategory($category);
            $tx->setAccount($account);
        }

        $em->flush();
        return $this->json($recurring, 200, [], ['groups' => ['recurring_transaction:read']]);
    }

    #[Route('/api/recurring-transactions/{id}', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $recurring = $em->getRepository(RecurringTransaction::class)->find($id);
        if (!$recurring || $recurring->getUser() !== $user) {
            return $this->json(['message' => 'Not found'], 404);
        }

        $linkedTx = $em->getRepository(Transaction::class)->findBy(['recurringTransaction' => $recurring]);
        foreach ($linkedTx as $tx) {
            $em->remove($tx);
        }

        $em->remove($recurring);
        $em->flush();

        return $this->json(null, 204);
    }
}
