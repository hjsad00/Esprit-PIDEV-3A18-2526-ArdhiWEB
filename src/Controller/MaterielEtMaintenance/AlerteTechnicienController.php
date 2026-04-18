<?php

namespace App\Controller\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use App\Entity\MaterielEtMaintenance\AlerteTechnicien;
use App\Repository\MaterielEtMaintenance\AlerteTechnicienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/materiel-et-maintenance/alertes')]
class AlerteTechnicienController extends AbstractController
{
    /**
     * Espace Agriculteur : Liste toutes ses alertes et les marque comme lues.
     */
    #[Route('/agriculteur', name: 'app_agriculteur_alertes', methods: ['GET'])]
    #[IsGranted('ROLE_AGRICULTEUR')]
    public function indexAgriculteur(AlerteTechnicienRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $alertes = $repo->findBy(['agriculteur' => $user], ['dateSignalement' => 'DESC']);

        // Marquage automatique de toutes les alertes non-lues comme lues
        $em->createQueryBuilder()
            ->update(AlerteTechnicien::class, 'a')
            ->set('a.statut', ':lu')
            ->where('a.agriculteur = :user')
            ->andWhere('a.statut = :non_lu')
            ->setParameter('lu', 'lu')
            ->setParameter('user', $user)
            ->setParameter('non_lu', 'non_lu')
            ->getQuery()
            ->execute();

        return $this->render('MaterielEtMaintenance/alerte_technicien/index_agriculteur.html.twig', [
            'alertes' => $alertes,
        ]);
    }

    /**
     * Liste des alertes pour un matériel spécifique.
     * Accessible par l'administrateur OU l'agriculteur propriétaire.
     */
    #[Route('/consulter/materiel/{id}', name: 'app_admin_alertes_materiel', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listForMateriel(Materiel $materiel, AlerteTechnicienRepository $repo, EntityManagerInterface $em): Response
    {
        // Sécurité : Si l'utilisateur n'est pas ADMIN, il doit être le propriétaire du matériel
        if (!$this->isGranted('ROLE_ADMIN')) {
             if ($materiel->getUserId() !== $this->getUser()->getId()) {
                 throw $this->createAccessDeniedException("Vous n'êtes pas le propriétaire de ce matériel.");
             }
        }

        // Marquage automatique comme lu si c'est le propriétaire ou l'admin qui consulte
        $em->createQueryBuilder()
            ->update(AlerteTechnicien::class, 'a')
            ->set('a.statut', ':lu')
            ->where('a.materiel = :materiel')
            ->andWhere('a.statut = :non_lu')
            ->setParameter('lu', 'lu')
            ->setParameter('materiel', $materiel)
            ->setParameter('non_lu', 'non_lu')
            ->getQuery()
            ->execute();

        $alertes = $repo->findBy(['materiel' => $materiel], ['dateSignalement' => 'DESC']);

        return $this->render('MaterielEtMaintenance/alerte_technicien/list_admin.html.twig', [
            'alertes' => $alertes,
            'materiel' => $materiel,
        ]);
    }

    /**
     * API pour le rafraîchissement automatique (Polling).
     * Renvoie le nombre total d'alertes non lues pour l'agriculteur connecté.
     */
    #[Route('/api/unread-count', name: 'app_alerte_technicien_count', methods: ['GET'])]
    #[IsGranted('ROLE_AGRICULTEUR')]
    public function getUnreadCount(AlerteTechnicienRepository $repo): Response
    {
        $count = $repo->countUnreadForAgriculteur($this->getUser()->getId());
        return $this->json(['count' => $count]);
    }
}
