<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Entity\UserAndDiag\User;
use App\Form\Parcelles_Cultures\Type\ParcelleFormType;
use App\Repository\Parcelles_Cultures\ParcelleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/farmer/parcelles', name: 'farmer_parcelle_')]
#[IsGranted('ROLE_AGRICULTEUR')]
class ParcelleFarmerController extends AbstractController
{
    public function __construct(
        private ParcelleRepository $parcelleRepository,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $query = $this->parcelleRepository->searchAndFilter(
            $user,
            $request->query->get('q'),
            $request->query->get('typeSol')
        );

        $parcelles = $this->paginator->paginate($query, $request->query->getInt('page', 1), 10);
        $stats = $this->parcelleRepository->getStatsByAgriculteur($user);

        if ($request->isXmlHttpRequest()) {
            return $this->render('parcelles_cultures/farmer/parcelles/_list.html.twig', [
                'parcelles' => $parcelles,
            ]);
        }

        return $this->render('parcelles_cultures/farmer/parcelles/index.html.twig', [
            'parcelles' => $parcelles,
            'stats' => $stats,
            'filters' => $request->query->all(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $parcelle = new Parcelle();
        $form = $this->createForm(ParcelleFormType::class, $parcelle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            assert($user instanceof User);
            $parcelle->setAgriculteur($user);
            
            // Sauvegarder le GeoJSON du polygone
            if ($request->request->has('polygon_geojson_data')) {
                $geojsonData = $request->request->get('polygon_geojson_data');
                if ($geojsonData) {
                    $parcelle->setPolygonGeojson(json_decode($geojsonData, true));
                }
            }
            
            $this->em->persist($parcelle);
            $this->em->flush();

            $this->addFlash('success', 'Parcelle créée avec succès.');
            return $this->redirectToRoute('farmer_parcelle_show', ['id' => $parcelle->getId()]);
        }

        return $this->render('parcelles_cultures/farmer/parcelles/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Parcelle $parcelle): Response
    {
        $this->denyAccessUnlessGranted('view', $parcelle);
        return $this->render('parcelles_cultures/farmer/parcelles/show.html.twig', ['parcelle' => $parcelle]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Parcelle $parcelle): Response
    {
        $this->denyAccessUnlessGranted('edit', $parcelle);
        
        $form = $this->createForm(ParcelleFormType::class, $parcelle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarder le GeoJSON du polygone
            if ($request->request->has('polygon_geojson_data')) {
                $geojsonData = $request->request->get('polygon_geojson_data');
                if ($geojsonData) {
                    $parcelle->setPolygonGeojson(json_decode($geojsonData, true));
                }
            }
            
            $parcelle->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();
            $this->addFlash('success', 'Parcelle modifiée avec succès.');
            return $this->redirectToRoute('farmer_parcelle_show', ['id' => $parcelle->getId()]);
        }

        return $this->render('parcelles_cultures/farmer/parcelles/edit.html.twig', [
            'form' => $form,
            'parcelle' => $parcelle,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Parcelle $parcelle): Response
    {
        $this->denyAccessUnlessGranted('delete', $parcelle);

        if ($this->isCsrfTokenValid('delete' . $parcelle->getId(), $request->request->get('_token'))) {
            $this->em->remove($parcelle);
            $this->em->flush();
            $this->addFlash('success', 'Parcelle supprimée.');
        }

        return $this->redirectToRoute('farmer_parcelle_index');
    }
}
