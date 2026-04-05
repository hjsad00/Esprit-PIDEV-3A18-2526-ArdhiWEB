<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Entity\Parcelles_Cultures\Culture;
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

#[Route('/farmer/cultures', name: 'farmer_culture_')]
#[IsGranted('ROLE_AGRICULTEUR')]
class CultureFarmerController extends AbstractController
{
    public function __construct(
        private CultureRepository $cultureRepository,
        private ParcelleRepository $parcelleRepository,
        private CultureService $cultureService,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $parcelles = $this->parcelleRepository->findByAgriculteur($user);
        $cultures = [];

        foreach ($parcelles as $parcelle) {
            $cultures = array_merge($cultures, $this->cultureRepository->findByParcelle($parcelle->getId()));
        }

        return $this->render('parcelles_cultures/farmer/cultures/index.html.twig', [
            'cultures' => $cultures,
            'parcelles' => $parcelles,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->getUser();
        $parcelles = $this->parcelleRepository->findByAgriculteur($user);

        if (empty($parcelles)) {
            $this->addFlash('warning', 'Vous devez créer une parcelle avant d\'ajouter une culture.');
            return $this->redirectToRoute('farmer_parcelle_new');
        }

        $dto = new CultureDTO();
        $form = $this->createForm(CultureFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $parcelleId = $request->request->get('parcelle_id');
            $parcelle = $this->parcelleRepository->find($parcelleId);

            if (!$parcelle || $parcelle->getAgriculteur() !== $user) {
                throw $this->createAccessDeniedException();
            }

            $culture = new Culture();
            $culture->setNomCulture($dto->nom_culture);
            $culture->setTypeCulture($dto->type_culture);
            $culture->setSaison($dto->saison);
            $culture->setDatePlantation($dto->date_plantation);
            $culture->setDateRecolteProvue($dto->date_recolte_prevue);
            $culture->setSurfaceUtilisee($dto->surface_utilisee);
            $culture->setRendementEstime($dto->rendement_estime);
            $culture->setParcelle($parcelle);

            $this->cultureService->mettreAJourProductionEstimee($culture);

            $this->em->persist($culture);
            $this->em->flush();

            $this->addFlash('success', 'Culture créée avec succès.');
            return $this->redirectToRoute('farmer_culture_show', ['id' => $culture->getId()]);
        }

        return $this->render('parcelles_cultures/farmer/cultures/new.html.twig', [
            'form' => $form,
            'parcelles' => $parcelles,
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
        $dto->nom_culture = $culture->getNomCulture();
        $dto->type_culture = $culture->getTypeCulture();
        $dto->saison = $culture->getSaison();
        $dto->date_plantation = $culture->getDatePlantation();
        $dto->date_recolte_prevue = $culture->getDateRecolteProvue();
        $dto->surface_utilisee = $culture->getSurfaceUtilisee();
        $dto->rendement_estime = $culture->getRendementEstime();

        $form = $this->createForm(CultureFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $culture->setNomCulture($dto->nom_culture);
            $culture->setTypeCulture($dto->type_culture);
            $culture->setSaison($dto->saison);
            $culture->setDatePlantation($dto->date_plantation);
            $culture->setDateRecolteProvue($dto->date_recolte_prevue);
            $culture->setSurfaceUtilisee($dto->surface_utilisee);
            $culture->setRendementEstime($dto->rendement_estime);
            $culture->setUpdatedAt(new \DateTimeImmutable());

            $this->cultureService->mettreAJourProductionEstimee($culture);

            $this->em->flush();
            $this->addFlash('success', 'Culture modifiée avec succès.');
            return $this->redirectToRoute('farmer_culture_show', ['id' => $culture->getId()]);
        }

        return $this->render('parcelles_cultures/farmer/cultures/edit.html.twig', [
            'form' => $form,
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
