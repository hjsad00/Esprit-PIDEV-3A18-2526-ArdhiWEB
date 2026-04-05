<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Entity\Parcelles_Cultures\Culture;
use App\Form\Parcelles_Cultures\CultureType;
use App\Repository\Parcelles_Cultures\CultureRepository;
use App\Repository\Parcelles_Cultures\ParceleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/farmer/cultures', name: 'farmer_culture_')]
#[IsGranted('ROLE_FARMER')]
class CultureFarmerController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        CultureRepository $repository,
        ParceleRepository $parceleRepository,
        PaginatorInterface $paginator,
        Request $request
    ): Response {
        $user = $this->getUser();

        // Récupère les parcelles de l'agriculteur
        $parcelles = $parceleRepository->findByAgriculteur($user->getId());
        $parcelleIds = array_map(fn($p) => $p->getId(), $parcelles);

        $query = $repository->createQueryBuilder('c')
            ->where('c.parcelle IN (:parcelleIds)')
            ->setParameter('parcelleIds', $parcelleIds)
            ->getQuery();
        
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('parcelles_cultures/farmer/culture/index.html.twig', [
            'pagination' => $pagination
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ParceleRepository $parceleRepository
    ): Response {
        $culture = new Culture();
        $form = $this->createForm(CultureType::class, $culture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifie que la parcelle appartient à l'utilisateur
            $parcelle = $form->getData()->getParcelle();
            if ($parcelle->getAgriculteur() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }

            $em->persist($culture);
            $em->flush();

            return $this->redirectToRoute('farmer_culture_show', [
                'id' => $culture->getId()
            ]);
        }

        return $this->render('parcelles_cultures/farmer/culture/form.html.twig', [
            'form' => $form,
            'culture' => $culture
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Culture $culture,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // Vérifie la propriété
        if ($culture->getParcelle()->getAgriculteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(CultureType::class, $culture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('farmer_culture_show', ['id' => $culture->getId()]);
        }

        return $this->render('parcelles_cultures/farmer/culture/form.html.twig', [
            'form' => $form,
            'culture' => $culture
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Culture $culture): Response
    {
        if ($culture->getParcelle()->getAgriculteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('parcelles_cultures/farmer/culture/show.html.twig', [
            'culture' => $culture
        ]);
    }
}
