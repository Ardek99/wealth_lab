<?php

namespace App\Controller;

use App\DTO\InvestorProfileDTO;
use App\Entity\InvestorProfile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InvestorProfileController extends AbstractController
{
    #[Route('/api/investor-profile', methods: ['GET'], name: 'app_investor_profile_get')]
    public function index(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $profile = $em->getRepository(InvestorProfile::class)->findOneBy(['user' => $user]);

        return $this->json($profile, 200, [], ['groups' => ['investor_profile:read']]);
    }

    #[Route('/api/investor-profile', methods: ['POST'], name: 'app_investor_profile_upsert')]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em
    ): Response
    {
        $dto = $serializer->deserialize($request->getContent(), InvestorProfileDTO::class, 'json');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        $profile = $em->getRepository(InvestorProfile::class)->findOneBy(['user' => $user]);

        if (!$profile) {
            $profile = new InvestorProfile();
            $profile->setUser($user);
            $em->persist($profile);
        }

        $profile->setObjective($dto->getObjective());
        $profile->setHorizon($dto->getHorizon());
        $profile->setReactionToLoss($dto->getReactionToLoss());
        $profile->setTargetReturn($dto->getTargetReturn());
        $profile->setMaxLoss($dto->getMaxLoss());
        $profile->setEmergencyFund($dto->getEmergencyFund());
        $profile->setSavingsRate($dto->getSavingsRate());
        $profile->setKnownProducts($dto->getKnownProducts());
        $profile->setInvestingExperience($dto->getInvestingExperience());
        $profile->setProfessionalStatus($dto->getProfessionalStatus());
        $profile->setFamilyStatus($dto->getFamilyStatus());
        $profile->setAgeRange($dto->getAgeRange());
        $profile->setScore($profile->computeScore());
        $em->flush();

        return $this->json($profile, 201, [], ['groups' => ['investor_profile:read']]);
    }
}
