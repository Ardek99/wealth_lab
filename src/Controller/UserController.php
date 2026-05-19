<?php

namespace App\Controller;

use App\DTO\UserDTO;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserController extends AbstractController
{
    #[Route('/api/register', name: 'register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): Response
    {
        $dto = $serializer->deserialize($request->getContent(), UserDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $user = new User();
        $user->setFirstName($dto->firstName);
        $user->setLastName($dto->lastName);
        $user->setEmail($dto->email);
        $user->setPassword($passwordHasher->hashPassword($user, $dto->password));

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $dto->email]);
        if ($existing) {
            return $this->json(['errors' => 'Email already in use'], 409);
        }
         
        $em->persist($user);
        $em->flush();

        return $this->json(['message' => 'User registered successfully'], 201);
    }

    #[Route('/api/profile', methods: ['GET'], name: 'profile')]
    public function profile(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        return $this->json([
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
        ]);
    }
}
