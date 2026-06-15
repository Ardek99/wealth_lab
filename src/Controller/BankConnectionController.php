<?php

namespace App\Controller;

use App\DTO\BankConnectionDTO;
use App\Entity\BankConnection;
use App\Entity\User;
use App\Enum\BankConnectionStatus;
use App\Message\SyncBankConnectionMessage;
use App\Service\CredentialEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class BankConnectionController extends AbstractController
{
    #[Route('/bank-connections', methods: ['GET'], name: 'bank_connections_list')]
    public function list(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $connections = $em->getRepository(BankConnection::class)->findBy(['user' => $user]);

        return $this->json($connections, 200, [], ['groups' => ['bank_connection:read']]);
    }

    #[Route('/bank-connections', methods: ['POST'], name: 'bank_connections_create')]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CredentialEncryptionService $encryptionService,
        EntityManagerInterface $em,
    ): Response {
        $dto = $serializer->deserialize($request->getContent(), BankConnectionDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        $connection = new BankConnection();
        $connection->setModule($dto->getModule());
        $connection->setLabel($dto->getLabel());
        $connection->setCredentials($encryptionService->encrypt($dto->getConfig()));
        $connection->setUser($user);

        $em->persist($connection);
        $em->flush();

        return $this->json($connection, 201, [], ['groups' => ['bank_connection:read']]);
    }

    #[Route('/bank-connections/{id}/sync', methods: ['POST'], name: 'bank_connections_sync')]
    public function sync(
        int $id,
        EntityManagerInterface $em,
        MessageBusInterface $bus,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $connection = $em->getRepository(BankConnection::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (!$connection) {
            return $this->json(['message' => 'Bank connection not found'], 404);
        }

        $connection->setStatus(BankConnectionStatus::PENDING);
        $em->flush();

        $bus->dispatch(new SyncBankConnectionMessage($connection->getId()));

        return $this->json($connection, 200, [], ['groups' => ['bank_connection:read']]);
    }
}