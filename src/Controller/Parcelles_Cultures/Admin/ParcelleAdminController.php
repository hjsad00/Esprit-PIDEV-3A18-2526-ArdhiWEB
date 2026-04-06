<?php

namespace App\Controller\Parcelles_Cultures\Admin;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Form\Parcelles_Cultures\Type\ParcelleFormType;
use App\Repository\Parcelles_Cultures\ParcelleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin/parcelles', name: 'admin_parcelle_')]
#[IsGranted('ROLE_ADMIN')]
class ParcelleAdminController extends AbstractController
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
        $query = $this->parcelleRepository->searchAndFilter(
            null, // No user for admin
            $request->query->get('q'),
            $request->query->get('typeSol')
        );

        $parcelles = $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('parcelles_cultures/admin/parcelles/index.html.twig', [
            'parcelles' => $parcelles,
            'filters' => $request->query->all()
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Parcelle $parcelle): Response
    {
        return $this->render('parcelles_cultures/admin/parcelles/show.html.twig', ['parcelle' => $parcelle]);
    }
}
