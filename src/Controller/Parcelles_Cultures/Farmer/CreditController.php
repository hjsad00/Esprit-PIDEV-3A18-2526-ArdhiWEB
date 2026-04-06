<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Entity\Parcelles_Cultures\CreditDossier;
use App\DTO\Parcelles_Cultures\CreditDossierDTO;
use App\Form\Parcelles_Cultures\Type\CreditDossierFormType;
use App\Repository\Parcelles_Cultures\CreditDossierRepository;
use App\Repository\Parcelles_Cultures\ParcelleRepository;
use App\Service\Parcelles_Cultures\CreditAnalysisService;
use App\Service\Parcelles_Cultures\PdfCreditExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/farmer/credit', name: 'farmer_credit_')]
#[IsGranted('ROLE_AGRICULTEUR')]
class CreditController extends AbstractController
{
    public function __construct(
        private ParcelleRepository $parcelleRepository,
        private CreditDossierRepository $creditRepository,
        private CreditAnalysisService $creditService,
        private PdfCreditExportService $pdfService,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        $query = $this->creditRepository->createQueryBuilder('cd')
            ->join('cd.parcelle', 'p')
            ->where('p.agriculteur = :user')
            ->setParameter('user', $user)
            ->orderBy('cd.created_at', 'DESC')
            ->getQuery();

        $dossiers = $this->paginator->paginate($query, $request->query->getInt('page', 1), 10);

        return $this->render('parcelles_cultures/farmer/credit/index.html.twig', ['dossiers' => $dossiers]);
    }

    #[Route('/new', name: 'new', methods: ['GET'])]
    public function new(): Response
    {
        $user = $this->getUser();
        $parcelles = $this->parcelleRepository->findByAgriculteur($user);

        return $this->render('parcelles_cultures/farmer/credit/new.html.twig', [
            'parcelles' => $parcelles
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(CreditDossier $dossier): Response
    {
        $this->denyAccessUnlessGranted('view', $dossier);
        return $this->render('parcelles_cultures/farmer/credit/show.html.twig', ['dossier' => $dossier]);
    }

    #[Route('/{id}/generate', name: 'generate', methods: ['GET', 'POST'])]
    public function generate(Request $request, Parcelle $parcelle): Response
    {
        $this->denyAccessUnlessGranted('view', $parcelle);

        $dto = new CreditDossierDTO();
        $form = $this->createForm(CreditDossierFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Utiliser le service d'analyse pour obtenir les scores réels si possible
            // Pour l'instant on garde une simulation basée sur le DTO ou des défauts
            $margeBrute = 5000; 
            
            $dossier = $this->creditService->genererDossier(
                $parcelle,
                $this->getUser(),
                $dto->duree_annees,
                (float) ($dto->score_rentabilite ?? 7.5),
                (float) ($dto->score_stabilite_climat ?? 8.0),
                (float) ($dto->score_diversification ?? 7.0),
                (float) ($dto->score_historique ?? 9.0),
                $margeBrute
            );

            $this->em->persist($dossier);
            $this->em->flush();

            $this->addFlash('success', 'Dossier de crédit généré avec succès.');
            return $this->redirectToRoute('farmer_credit_show', ['id' => $dossier->getId()]);
        }

        return $this->render('parcelles_cultures/farmer/credit/generate.html.twig', [
            'form' => $form->createView(),
            'parcelle' => $parcelle,
        ]);
    }

    #[Route('/{id}/export-pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(CreditDossier $dossier): Response
    {
        $this->denyAccessUnlessGranted('view', $dossier);

        $pdf = $this->pdfService->exporterDossierCreditPdf($dossier);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="dossier_credit_' . $dossier->getId() . '.pdf"',
        ]);
    }
}
