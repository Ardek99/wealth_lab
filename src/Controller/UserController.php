<?php

namespace App\Controller;

use App\DTO\UserDTO;
use App\DTO\UserUpdateDTO;
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
            $errorMessages = [];
            foreach ($errors as $error) {
                $field = str_replace('Object(App\DTO\UserDTO).', '', $error->getPropertyPath());
                $errorMessages[$field] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], 400);
        }

        $user = new User();
        $user->setFirstName($dto->firstName);
        $user->setLastName($dto->lastName);
        $user->setEmail($dto->email);
        $user->setPassword($passwordHasher->hashPassword($user, $dto->password));
        $user->setPhoneNumber($dto->phoneNumber);

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $dto->email]);
        if ($existing) {
            return $this->json(['errors' => 'Unable to complete registration. Please check your information.'], 400);
        }

        $existingPhone = $em->getRepository(User::class)->findOneBy(['phoneNumber' => $dto->phoneNumber]);
        if ($existingPhone) {
            return $this->json(['errors' => 'Unable to complete registration. Please check your information.'], 400);
        }
         
        $em->persist($user);
        $em->flush();

        return $this->json([
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'phoneNumber' => $user->getPhoneNumber(),
        ], 201);
    }

    #[Route('/api/profile', methods: ['PUT'], name: 'profile_update')]
    public function update(
        Request $request,
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $dto = $serializer->deserialize($request->getContent(), UserUpdateDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $user->setFirstName($dto->getFirstName());
        $user->setLastName($dto->getLastName());
        $user->setEmail($dto->getEmail());
        $user->setPhoneNumber($dto->getPhoneNumber());

        $em->flush();

        return $this->json([
            'firstName'   => $user->getFirstName(),
            'lastName'    => $user->getLastName(),
            'email'       => $user->getEmail(),
            'phoneNumber' => $user->getPhoneNumber(),
        ]);
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
            'phoneNumber' => $user->getPhoneNumber(),
        ]);
    }
}
