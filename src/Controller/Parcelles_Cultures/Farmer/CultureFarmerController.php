<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Entity\Parcelles_Cultures\Culture;
use App\Entity\UserAndDiag\User;
use App\DTO\Parcelles_Cultures\CultureDTO;
use App\Form\Parcelles_Cultures\Type\CultureFormType;
use App\Repository\Parcelles_Cultures\CultureRepository;
use App\Repository\Parcelles_Cultures\ParcelleRepository;
use App\Service\Parcelles_Cultures\CultureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/farmer/cultures', name: 'farmer_culture_')]
#[IsGranted('ROLE_AGRICULTEUR')]
class CultureFarmerController extends AbstractController
{
    public function __construct(
        private CultureRepository $cultureRepository,
        private ParcelleRepository $parcelleRepository,
        private CultureService $cultureService,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        
        $query = $this->cultureRepository->searchAndFilter(
            $user,
            $request->query->get('q'),
            $request->query->get('typeCulture'),
            $request->query->get('saison')
        );

        $cultures = $this->paginator->paginate($query, $request->query->getInt('page', 1), 10);

        if ($request->isXmlHttpRequest()) {
            return $this->render('parcelles_cultures/farmer/cultures/_list.html.twig', [
                'cultures' => $cultures,
            ]);
        }

        // Quick Stats for the header
        $stats = $this->cultureRepository->createQueryBuilder('c')
            ->join('c.parcelle', 'p')
            ->select('COUNT(c.id) as count, SUM(c.surface_utilisee) as surface')
            ->where('p.agriculteur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return $this->render('parcelles_cultures/farmer/cultures/index.html.twig', [
            'cultures' => $cultures,
            'summary' => $stats,
            'filters' => $request->query->all(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $parcelles = $this->parcelleRepository->findByAgriculteur($user);
        $remainingSurfaces = [];
        foreach ($parcelles as $p) {
            $used = $this->cultureService->getSurfaceUtiliseeParParcelle($p->getId());
            $remainingSurfaces[$p->getId()] = (float)$p->getSurface() - $used;
        }

        if (empty($parcelles)) {
            $this->addFlash('warning', 'Vous devez créer une parcelle avant d\'ajouter une culture.');
            return $this->redirectToRoute('farmer_parcelle_new');
        }

        $dto = new CultureDTO();
        $form = $this->createForm(CultureFormType::class, $dto, [
            'user_parcelles' => $parcelles,
            'remaining_surfaces' => $remainingSurfaces
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $parcelle = $dto->parcelle;

            if (!$parcelle || $parcelle->getAgriculteur() !== $user) {
                $form->get('parcelle')->addError(new \Symfony\Component\Form\FormError(
                    'Veuillez sélectionner une parcelle valide qui vous appartient.'
                ));
            }

            // Check surface constraint only when parcelle is valid
            if ($parcelle && $parcelle->getAgriculteur() === $user) {
                $remaining = $remainingSurfaces[$parcelle->getId()];
                if (!$this->cultureService->verifierContrainteSurface($parcelle->getId(), (float)$dto->surface_utilisee)) {
                    $form->get('surface_utilisee')->addError(new \Symfony\Component\Form\FormError(
                        sprintf('Surface insuffisante sur cette parcelle. Restant: %s ha', round($remaining, 2))
                    ));
                }
            }

            if ($form->isValid()) {
                $culture = new Culture();
                $culture->setNomCulture($dto->type_culture);
                $culture->setTypeCulture($dto->type_culture);
                $culture->setSaison($dto->saison);
                $culture->setDatePlantation($dto->date_plantation);
                $culture->setDateRecoltePrevue($dto->date_recolte_prevue);
                $culture->setSurfaceUtilisee($dto->surface_utilisee);
                $culture->setRendementEstime($dto->rendement_estime);
                $culture->setParcelle($parcelle);

                $this->cultureService->mettreAJourProductionEstimee($culture);

                $this->em->persist($culture);
                $this->em->flush();

                $this->addFlash('success', 'Culture créée avec succès.');
                return $this->redirectToRoute('farmer_culture_show', ['id' => $culture->getId()]);
            }
        }

        return $this->render('parcelles_cultures/farmer/cultures/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Culture $culture): Response
    {
        $this->denyAccessUnlessGranted('view', $culture, 'Accès refusé.');

        return $this->render('parcelles_cultures/farmer/cultures/show.html.twig', [
            'culture' => $culture,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Culture $culture): Response
    {
        $this->denyAccessUnlessGranted('edit', $culture, 'Accès refusé.');

        $dto = new CultureDTO();
        $dto->type_culture = $culture->getTypeCulture();
        $dto->saison = $culture->getSaison();
        $dto->date_plantation = $culture->getDatePlantation();
        $dto->date_recolte_prevue = $culture->getDateRecoltePrevue();
        $dto->surface_utilisee = $culture->getSurfaceUtilisee();
        $dto->rendement_estime = $culture->getRendementEstime();
        $dto->parcelle = $culture->getParcelle();

        $user = $this->getUser();
        assert($user instanceof User);
        $parcelles = $this->parcelleRepository->findByAgriculteur($user);
        $remainingSurfaces = [];
        foreach ($parcelles as $p) {
            $used = $this->cultureService->getSurfaceUtiliseeParParcelle($p->getId(), $culture->getId());
            $remainingSurfaces[$p->getId()] = (float)$p->getSurface() - $used;
        }

        $form = $this->createForm(CultureFormType::class, $dto, [
            'user_parcelles' => $parcelles,
            'remaining_surfaces' => $remainingSurfaces
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $parcelle = $dto->parcelle;
            if (!$parcelle || $parcelle->getAgriculteur() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }

            // Check surface constraint (excluding current culture)
            $remaining = $remainingSurfaces[$parcelle->getId()];
            if (!$this->cultureService->verifierContrainteSurface($parcelle->getId(), (float)$dto->surface_utilisee, $culture->getId())) {
                $form->get('surface_utilisee')->addError(new \Symfony\Component\Form\FormError(
                    sprintf('Surface insuffisante sur cette parcelle. Disponible: %s ha', round($remaining, 2))
                ));
            }

            if ($form->isValid()) {
                $culture->setNomCulture($dto->type_culture);
                $culture->setTypeCulture($dto->type_culture);
                $culture->setSaison($dto->saison);
                $culture->setDatePlantation($dto->date_plantation);
                $culture->setDateRecoltePrevue($dto->date_recolte_prevue);
                $culture->setSurfaceUtilisee($dto->surface_utilisee);
                $culture->setRendementEstime($dto->rendement_estime);
                $culture->setParcelle($parcelle);
                $culture->setUpdatedAt(new \DateTimeImmutable());

                $this->cultureService->mettreAJourProductionEstimee($culture);

                $this->em->flush();

                $this->addFlash('success', 'Culture modifiée avec succès.');
                return $this->redirectToRoute('farmer_culture_show', ['id' => $culture->getId()]);
            }
        }

        return $this->render('parcelles_cultures/farmer/cultures/edit.html.twig', [
            'form' => $form->createView(),
            'culture' => $culture,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Culture $culture): Response
    {
        $this->denyAccessUnlessGranted('delete', $culture, 'Accès refusé.');

        if ($this->isCsrfTokenValid('delete' . $culture->getId(), $request->request->get('_token'))) {
            $this->em->remove($culture);
            $this->em->flush();
            $this->addFlash('success', 'Culture supprimée avec succès.');
        }

        return $this->redirectToRoute('farmer_culture_index');
    }
}
