<?php

namespace App\Controller\EmployeTache;

use App\Repository\UserAndDiag\UserRepository;
use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;
use App\Service\EmployeTache\AgriculteurContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/agriculteurs')]
#[IsGranted('ROLE_ADMIN')]
class AdminAgriculteurController extends AbstractController
{
    public function __construct(
        private AgriculteurContextService $ctx,
    ) {}

    /**
     * Liste de tous les agriculteurs — identique à la vue desktop "Gestion des Agriculteurs"
     */
    #[Route('', name: 'admin_agriculteurs_employe')]
    public function index(
        UserRepository   $userRepo,
        EmployeRepository $empRepo,
        TacheRepository  $tacheRepo,
        Request          $request
    ): Response {
        $search = $request->query->get('search', '');

        // Récupère tous les utilisateurs AGRICULTEUR
        $agriculteurs = $userRepo->findByRole('AGRICULTEUR', $search);

        // Enrichit chaque agriculteur avec le nb d'employés et de tâches
        $data = [];
        foreach ($agriculteurs as $agri) {
            $data[] = [
                'user'       => $agri,
                'nbEmployes' => $empRepo->countByAgriculteur($agri->getId()),
                'nbTaches'   => count($tacheRepo->findByAgriculteur($agri->getId())),
            ];
        }

        return $this->render('EmployeTache/admin/agriculteurs.html.twig', [
            'data'   => $data,
            'search' => $search,
            'total'  => count($data),
        ]);
    }

    /**
     * L'admin choisit de gérer les EMPLOYÉS d'un agriculteur.
     * → Stocke le contexte en session puis redirige vers la liste des employés.
     */
    #[Route('/{id}/employes', name: 'admin_gerer_employes')]
    public function gererEmployes(int $id, UserRepository $userRepo): Response
    {
        $agri = $userRepo->find($id);
        if (!$agri) throw $this->createNotFoundException('Agriculteur introuvable.');

        // Identique à AgriculteurContext.setAgriculteur()
        $this->ctx->setSupervision($id, $agri->getPrenom() . ' ' . $agri->getNom());

        $this->addFlash('info', '👤 Supervision de ' . $agri->getPrenom() . ' ' . $agri->getNom());
        return $this->redirectToRoute('employe_index');
    }

    /**
     * L'admin choisit de gérer les TÂCHES d'un agriculteur.
     */
    #[Route('/{id}/taches', name: 'admin_gerer_taches')]
    public function gererTaches(int $id, UserRepository $userRepo): Response
    {
        $agri = $userRepo->find($id);
        if (!$agri) throw $this->createNotFoundException('Agriculteur introuvable.');

        $this->ctx->setSupervision($id, $agri->getPrenom() . ' ' . $agri->getNom());

        $this->addFlash('info', '📋 Tâches de ' . $agri->getPrenom() . ' ' . $agri->getNom());
        return $this->redirectToRoute('tache_index');
    }

    /**
     * Quitter le mode supervision — identique à AgriculteurContext.clear()
     */
    #[Route('/quitter-supervision', name: 'admin_quitter_supervision')]
    public function quitterSupervision(): Response
    {
        $this->ctx->clearSupervision();
        $this->addFlash('success', 'Mode supervision terminé.');
        return $this->redirectToRoute('admin_agriculteurs_employe');
    }
}