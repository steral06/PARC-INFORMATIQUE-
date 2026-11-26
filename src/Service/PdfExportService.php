<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Service de génération de fichiers PDF à partir de templates Twig.
 * Utilise la bibliothèque Dompdf pour le rendu HTML → PDF.
 */
class PdfExportService
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * Génère un PDF à partir d'un template Twig et retourne une Response HTTP.
     *
     * @param string $template  Chemin du template Twig (ex: 'pdf/equipement.html.twig')
     * @param array  $variables Variables à passer au template
     * @param string $filename  Nom du fichier téléchargé (ex: 'Fiche_PC-01.pdf')
     * @param string $paperSize Format papier ('A4', 'letter', etc.)
     * @param string $orientation 'portrait' ou 'landscape'
     */
    public function generatePdfResponse(
        string $template,
        array $variables,
        string $filename,
        string $paperSize = 'A4',
        string $orientation = 'portrait',
    ): Response {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        // Pas de pénalité réseau, pas de remote
        $options->setChroot(dirname(__DIR__, 2) . '/public');

        $dompdf = new Dompdf($options);

        $html = $this->twig->render($template, $variables);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paperSize, $orientation);
        $dompdf->render();

        $pdfContent = $dompdf->output();

        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
            'Cache-Control'       => 'private, max-age=0, must-revalidate',
        ]);
    }
}

