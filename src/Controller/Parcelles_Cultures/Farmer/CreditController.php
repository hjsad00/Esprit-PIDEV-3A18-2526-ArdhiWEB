<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Entity\Parcelles_Cultures\CreditDossier;
use App\Service\Parcelles_Cultures\CreditAnalysisService;
use App\Service\Parcelles_Cultures\PdfCreditExportService;
use App\Repository\Parcelles_Cultures\CreditDossierRepository;
use App\Repository\Parcelles_Cultures\ParceleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/farmer/credit', name: 'farmer_credit_')]
#[IsGranted('ROLE_FARMER')]
class CreditController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        CreditDossierRepository $repository,
        PaginatorInterface $paginator,
        Request $request
    ): Response {
        $user = $this->getUser();
        $query = $repository->createQueryBuilder('cd')
            ->where('cd.user = :user')
            ->setParameter('user', $user)
            ->getQuery();
        
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('parcelles_cultures/farmer/credit/index.html.twig', [
            'pagination' => $pagination
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(CreditDossier $dossier): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $dossier);

        return $this->render('parcelles_cultures/farmer/credit/show.html.twig', [
            'dossier' => $dossier
        ]);
    }

    #[Route('/{id}/generate', name: 'generate', methods: ['POST'])]
    public function generate(
        int $id,
        CreditAnalysisService $analysisService,
        ParceleRepository $parceleRepository,
        EntityManagerInterface $em
    ): Response {
        $parcelle = $parceleRepository->find($id);
        
        if (!$parcelle || $parcelle->getAgriculteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $dureeAnnees = 5; // Default
        $dossier = $analysisService->genererDossier($parcelle, $this->getUser(), $dureeAnnees);
        
        $em->persist($dossier);
        $em->flush();

        return $this->redirectToRoute('farmer_credit_show', ['id' => $dossier->getId()]);
    }

    #[Route('/{id}/export-pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(
        CreditDossier $dossier,
        PdfCreditExportService $pdfService,
        EntityManagerInterface $em
    ): StreamedResponse {
        $this->denyAccessUnlessGranted('EXPORT', $dossier);

        $pdfContent = $pdfService->exporterDossierCreditPdfStream($dossier);

        // Update export date
        $pdfService->sauvegarderDossierPdf($dossier, $pdfService->genererHtml($dossier));
        $em->flush();

        $response = new StreamedResponse(function() use ($pdfContent) {
            echo $pdfContent;
        });

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="credit_' . $dossier->getId() . '.pdf"');

        return $response;
    }
}
