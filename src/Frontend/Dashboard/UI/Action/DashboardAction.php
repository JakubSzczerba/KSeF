<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\UI\Action;

use Ksef\Frontend\Dashboard\Application\GetInvoiceOverview\GetInvoiceOverviewHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class DashboardAction
{
    public function __construct(
        private readonly GetInvoiceOverviewHandler $getInvoiceOverviewHandler,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {}

    #[Route(path: '/', name: 'frontend_invoice_dashboard', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $bootstrap = [
            'rows' => $this->getInvoiceOverviewHandler->provide(),
            'sendEndpoint' => $this->urlGenerator->generate('frontend_invoice_send'),
            'rowsEndpoint' => $this->urlGenerator->generate('frontend_invoice_rows'),
            'downloadInvoiceEndpointTemplate' => $this->urlGenerator->generate('frontend_invoice_download', ['ksefNumber' => '__KSEF_NUMBER__']),
            'downloadInvoicePdfEndpointTemplate' => $this->urlGenerator->generate('frontend_invoice_download_pdf', ['ksefNumber' => '__KSEF_NUMBER__']),
        ];

        $html = $this->twig->render('ui/dashboard.html.twig', [
            'bootstrap' => $bootstrap,
        ]);

        return new Response($html);
    }
}
