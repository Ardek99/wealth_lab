<?php

namespace App\Controller;

use App\DTO\GoalDTO;
use App\Entity\Goal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class GoalController extends AbstractController
{
    #[Route('/api/goals', methods: ['GET'], name: 'app_goal')]
    public function getAll(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $goals = $em->getRepository(Goal::class)->findBy(['user' => $user]);

        return $this->json($goals, 200, [], ['groups' => ['goal:read']]);
    }

    #[Route('/api/goals/{id}', methods: ['GET'], name: 'app_goal_one')]
    public function getOne(int $id, EntityManagerInterface $em): Response
    {        
        /** @var User $user */
        $user = $this->getUser();

        $goal = $em->getRepository(Goal::class)->findOneBy(['id' => $id, 'user' => $user]);

        return $this->json($goal, 200, [], ['groups' => ['goal:read']]);
    }

    #[Route('/api/goals', methods: ['POST'], name: 'app_goal_create')]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
    ): Response
    {
        $dto = $serializer->deserialize($request->getContent(), GoalDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $goal = new Goal();
        $goal->setName($dto->getName());
        $goal->setTargetAmount($dto->getTargetAmount());
        $goal->setCurrentAmount($dto->getCurrentAmount());
        $goal->setTargetDate($dto->getTargetDate());
        $goal->setUser($user);

        $em->persist($goal);
        $em->flush();

        return $this->json($goal, 201, [], ['groups' => ['goal:read']]);
    }

    #[Route('/api/goals/{id}', methods: ['PUT'], name: 'app_goal_update')]
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

        $goal = $em->getRepository(Goal::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$goal) {
            return $this->json(['message' => 'Goal not found'], 404);
        }

        $dto = $serializer->deserialize($request->getContent(), GoalDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $goal->setName($dto->getName());
        $goal->setTargetAmount($dto->getTargetAmount());
        $goal->setCurrentAmount($dto->getCurrentAmount());
        $goal->setTargetDate($dto->getTargetDate());

        $em->flush();

        return $this->json($goal, 200, [], ['groups' => ['goal:read']]);
    }

    #[Route('/api/goals/{id}', methods: ['DELETE'], name: 'app_goal_delete')]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $goal = $em->getRepository(Goal::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$goal) {
            return $this->json(['message' => 'Goal not found'], 404);
        }

        $em->remove($goal);
        $em->flush();

        return $this->json(null, 204);
    }
}
