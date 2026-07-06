<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class SmokeTestsPageRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly SmokeTestsPageContextFactory $contextFactory,
    ) {
    }

    public function render(): Response
    {
        return new Response($this->twig->render('@SmokeTestsPlayground/smoke_tests_playground/index.html.twig', $this->contextFactory->create()));
    }
}
