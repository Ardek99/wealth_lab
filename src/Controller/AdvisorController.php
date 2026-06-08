<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Service\AdvisorService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdvisorController extends AbstractController
{
    #[Route('/api/advisor/chat', methods:['POST'], name: 'app_advisor')]
    public function index(AdvisorService $advisorService, Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $messages = json_decode($request->getContent(), true)['messages'] ?? [];

        return new StreamedResponse(function() use ($user, $messages, $advisorService) {
            $advisorService->streamChat($user, $messages);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
