<?php

namespace App\Controller;

use App\DTO\AccountDTO;
use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class AccountController extends AbstractController
{
    #[Route('/accounts', methods: ['GET'], name: 'accounts_list')]
    public function getAccounts(EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $accounts = $em->getRepository(Account::class)->findBy(['user' => $user]);

        return $this->json($accounts, 200, [], ['groups' => ['account:read']]);
    }

    #[Route('/accounts/{id}', methods: ['GET'], name: 'one_account')]
    public function getAccount(int $id, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $account = $em->getRepository(Account:: class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$account) {
            return $this->json(['message' => 'Account not found'], 404);
        }

        return $this->json($account, 200, [], ['groups' => ['account:read']]);
    }

    #[Route('/accounts', methods: ['POST'], name: 'accounts_create')]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
    ): Response {
        $dto = $serializer->deserialize($request->getContent(), AccountDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $account = new Account();
        $account->setName($dto->getName());
        $account->setType($dto->getType());
        $account->setBalance($dto->getBalance());
        $account->setUser($user);

        $em->persist($account);
        $em->flush();

        return $this->json($account, 201, [], ['groups' => ['account:read']]);
    }

    #[Route('/accounts/{id}', methods: ['PUT'], name: 'accounts_update')]
    public function update(
        int $id,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em
    ): Response {
        $dto = $serializer->deserialize($request->getContent(), AccountDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $account = $em->getRepository(Account::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$account) {
            return $this->json(['message' => 'Account not found'], 404);
        }

        $account->setName($dto->getName());
        $account->setType($dto->getType());
        $account->setBalance($dto->getBalance());

        $em->flush();

        return $this->json($account, 200, [], ['groups' => ['account:read']]);
    }

    #[Route('/accounts/{id}', methods: ['DELETE'], name: 'accounts_delete')]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $account = $em->getRepository(Account::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$account) {
            return $this->json(['message' => 'Account not found'], 404);
        }

        $em->remove($account);
        $em->flush();

        return $this->json(null, 204);
    }
}
