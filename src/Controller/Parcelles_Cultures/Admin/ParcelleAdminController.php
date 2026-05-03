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
        $rawQ = $request->query->get('q');
        $rawType = $request->query->get('typeSol');

        $query = $this->parcelleRepository->searchAndFilter(
            null, // No user for admin
            is_scalar($rawQ) ? (string) $rawQ : null,
            is_scalar($rawType) ? (string) $rawType : null
        );

        $parcelles = $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10,
            ['distinct' => false]
        );

        // Eager load cultures for paginated items to prevent N+1 queries
        $parcelleItems = $parcelles->getItems();
        if (count($parcelleItems) > 0) {
            $this->em->createQuery('SELECT p, c FROM App\Entity\Parcelles_Cultures\Parcelle p LEFT JOIN p.cultures c WHERE p.id IN (:ids)')
                ->setParameter('ids', array_map(fn($p) => $p->getId(), $parcelleItems))
                ->getResult();
        }

        $dummy = $this->em; // read injected EM so PHPStan doesn't mark it only-written

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
