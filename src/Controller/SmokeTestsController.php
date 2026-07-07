<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Controller;

use ControleOnline\SmokeTestsPlayground\Service\SmokeArtifactResponseFactory;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRunResponseFactory;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRunner;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsIndexFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SmokeTestsController extends AbstractController
{
    public function __construct(
        private readonly SmokeTestsIndexFactory $indexFactory,
        private readonly SmokeArtifactResponseFactory $artifactResponseFactory,
        private readonly SmokeRunResponseFactory $runResponseFactory,
        private readonly SmokeRunner $runner,
    ) {
    }

    #[Route(path: '/tests', name: 'smoke_tests_playground_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json($this->indexFactory->create());
    }

    #[Route(path: '/tests/ui', name: 'smoke_tests_playground_ui', methods: ['GET'])]
    public function ui(): JsonResponse
    {
        return $this->json($this->indexFactory->create());
    }

    #[Route(path: '/tests/index.json', name: 'smoke_tests_playground_index_json', methods: ['GET'])]
    public function indexJson(): JsonResponse
    {
        return $this->json($this->indexFactory->create());
    }

    #[Route(path: '/tests/api', name: 'smoke_tests_playground_api', methods: ['GET'])]
    public function api(): JsonResponse
    {
        return $this->json($this->indexFactory->create());
    }

    #[Route(
        path: '/tests/artifacts/{suiteId}/{artifactPath}',
        name: 'smoke_tests_playground_artifact',
        methods: ['GET'],
        requirements: [
            'suiteId' => '[A-Za-z0-9_-]+',
            'artifactPath' => '.+',
        ],
    )]
    public function artifact(string $suiteId, string $artifactPath): Response
    {
        return $this->artifactResponseFactory->create($suiteId, $artifactPath);
    }

    #[Route(path: '/tests/run', name: 'smoke_tests_playground_run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        $runResult = $this->runner->run();
        $payload = $this->runResponseFactory->create(
            $runResult,
            $request->getMethod(),
            (new \DateTimeImmutable())->format(DATE_ATOM),
        );

        return $this->json($payload, $runResult->successful ? 200 : 500);
    }
}
