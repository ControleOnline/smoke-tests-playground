<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Controller;

use ControleOnline\SmokeTestsPlayground\Service\SmokeRunner;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsPageRenderer;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsPublicStateFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SmokeTestsController extends AbstractController
{
    public function __construct(
        private readonly SmokeTestsPageRenderer $pageRenderer,
        private readonly SmokeTestsPublicStateFactory $publicStateFactory,
        private readonly SmokeRunner $runner,
    ) {
    }

    #[Route(path: '/tests', name: 'smoke_tests_playground_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->pageRenderer->render();
    }

    #[Route(path: '/tests/ui', name: 'smoke_tests_playground_ui', methods: ['GET'])]
    public function ui(): Response
    {
        return $this->pageRenderer->render();
    }

    #[Route(path: '/tests/api', name: 'smoke_tests_playground_api', methods: ['GET'])]
    public function api(): JsonResponse
    {
        return $this->json($this->publicStateFactory->create());
    }

    #[Route(path: '/tests/run', name: 'smoke_tests_playground_run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        $runResult = $this->runner->run();
        $payload = $this->publicStateFactory->createRunResponse(
            $runResult,
            $request->getMethod(),
            (new \DateTimeImmutable())->format(DATE_ATOM),
        );

        return $this->json($payload, $runResult->successful ? 200 : 500);
    }
}
