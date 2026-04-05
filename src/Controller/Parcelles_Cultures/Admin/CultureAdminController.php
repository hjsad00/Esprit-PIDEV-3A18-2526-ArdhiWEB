<?php

namespace App\Controller\Parcelles_Cultures\Admin;

use App\Entity\Parcelles_Cultures\Culture;
use App\Repository\Parcelles_Cultures\CultureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin/cultures', name: 'admin_culture_')]
#[IsGranted('ROLE_ADMIN')]
class CultureAdminController extends AbstractController
{
    public function __construct(
        private CultureRepository $cultureRepository,
        private PaginatorInterface $paginator
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $this->cultureRepository->searchAndFilter(
            null, // No user for admin
            $request->query->get('q'),
            $request->query->get('typeCulture'),
            $request->query->get('saison')
        );

        $cultures = $this->paginator->paginate($query, $request->query->getInt('page', 1), 10);

        return $this->render('parcelles_cultures/admin/cultures/index.html.twig', [
            'cultures' => $cultures,
            'filters' => $request->query->all()
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Culture $culture): Response
    {
        return $this->render('parcelles_cultures/admin/cultures/show.html.twig', ['culture' => $culture]);
    }
}
