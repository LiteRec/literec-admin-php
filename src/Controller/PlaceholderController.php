<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a shared "coming soon" stub for every nav destination that does not
 * yet have a real implementation. The route table lives in
 * config/routes/placeholders.yaml — one entry per destination, each supplying
 * its own `sectionTitle` default — so adding or retiring a stub is a routing
 * change, not a new method here.
 */
final class PlaceholderController extends AbstractController
{
    public function __invoke(string $sectionTitle): Response
    {
        return $this->render('placeholder/coming_soon.html.twig', [
            'section_title' => $sectionTitle,
        ]);
    }
}
