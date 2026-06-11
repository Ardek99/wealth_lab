<?php

namespace App\Controller;

use App\DTO\InvestmentDTO;
use App\Entity\Investment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Entity\Account;
use App\Entity\User;
use App\Enum\InvestmentType;
use App\Service\PriceFetcherService;

final class InvestmentController extends AbstractController
{
    #[Route('/api/investments', methods: ['GET'], name: 'app_investment_all')]
    public function getAll(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $investments = $em->getRepository(Investment::class)->findBy(['user' => $user]);

        return $this->json($investments, 200, [], ['groups' => ['investment:read']]);
    }

    #[Route('/api/investments/search', methods: ['GET'], name: 'app_investment_search')]
    public function search(Request $request, PriceFetcherService $priceFetcher): Response
    {
        $searchQuery = $request->query->get('q', '');
        $type = $request->query->get('type', 'stock');

        if (strlen($searchQuery) < 2) {
            return $this->json([]);
        }

        $results = $priceFetcher->searchAssets($searchQuery, $type);

        return $this->json($results);
    }

    #[Route('/api/investments/{id}', methods: ['GET'], name: 'app_investment_one')]
    public function getOne(int $id, EntityManagerInterface $em): Response
    {        
        /** @var User $user */
        $user = $this->getUser();

        $investment = $em->getRepository(Investment::class)->findOneBy(['id' => $id, 'user' => $user]);

        return $this->json($investment, 200, [], ['groups' => ['investment:read']]);
    }

    #[Route('/api/investments', methods: ['POST'], name: 'app_investment_create')]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        PriceFetcherService $priceFetcher,
    ): Response
    {
        $dto = $serializer->deserialize($request->getContent(), InvestmentDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $investment = new Investment();
        $investment->setName($dto->getName());
        $investment->setType($dto->getType());
        $investment->setTicker($dto->getTicker());
        $investment->setQuantity($dto->getQuantity());
        $investment->setPurchasePrice($dto->getPurchasePrice());
        $investment->setPurchaseDate($dto->getPurchaseDate());
        $account = $dto->getAccountId()
            ? $em->getRepository(Account::class)->find($dto->getAccountId())
            : null;
        $investment->setAccount($account);
        $investment->setUser($user);

        $em->persist($investment);

        $price = match($investment->getType()) {
            InvestmentType::CRYPTO => $priceFetcher->fetchCryptoPrice($investment->getTicker()),
            InvestmentType::OTHER  => null,
            default                => $priceFetcher->fetchStockPrice($investment->getTicker()),
        };

        if ($price !== null && $investment->getTicker()) {
            $investment->setCurrentPrice((string) $price);
            $investment->setCurrentPriceUpdatedAt(new \DateTime());
        }
        $em->flush();

        return $this->json($investment, 201, [], ['groups' => ['investment:read']]);
    }

    #[Route('/api/investments/{id}', methods: ['PUT'], name: 'app_investment_update')]
    public function update(
        int $id,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $investment = $em->getRepository(Investment::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$investment) {
            return $this->json(['message' => 'Investment not found'], 404);
        }

        $dto = $serializer->deserialize($request->getContent(), InvestmentDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $investment->setName($dto->getName());
        $investment->setTicker($dto->getTicker());
        $investment->setQuantity($dto->getQuantity());
        $investment->setPurchasePrice($dto->getPurchasePrice());
        $investment->setPurchaseDate($dto->getPurchaseDate());
        $account = $dto->getAccountId()
            ? $em->getRepository(Account::class)->find($dto->getAccountId())
            : null;
        $investment->setAccount($account);
        $investment->setType($dto->getType());

        $em->flush();

        return $this->json($investment, 200, [], ['groups' => ['investment:read']]);
    }

    #[Route('/api/investments/{id}', methods: ['DELETE'], name: 'app_investment_delete')]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $investment = $em->getRepository(Investment::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$investment) {
            return $this->json(['message' => 'Investment not found'], 404);
        }

        $em->remove($investment);
        $em->flush();

        return $this->json(null, 204);
    }
}
