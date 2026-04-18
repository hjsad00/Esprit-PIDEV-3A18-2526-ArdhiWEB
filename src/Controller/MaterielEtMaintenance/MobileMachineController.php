<?php

namespace App\Controller\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use App\Entity\MaterielEtMaintenance\Maintenance;
use App\Entity\MaterielEtMaintenance\AlerteTechnicien;
use App\Entity\UserAndDiag\User;
use App\Repository\MaterielEtMaintenance\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur pour l'accès mobile via QR Code.
 * Les routes ici sont publiques pour permettre le scan sur le terrain.
 */
class MobileMachineController extends AbstractController
{
    #[Route('/machine/{token}', name: 'app_mobile_machine_show', methods: ['GET'])]
    public function show(string $token, MaterielRepository $repo): Response
    {
        $materiel = $repo->findOneBy(['qrCodeToken' => $token]);

        if (!$materiel) {
            throw $this->createNotFoundException('Machine introuvable.');
        }

        // On affiche une vue simplifiée et optimisée pour mobile
        return $this->render('MaterielEtMaintenance/machine/mobile_show.html.twig', [
            'materiel' => $materiel,
        ]);
    }

    #[Route('/machine/{token}/signaler-panne', name: 'app_mobile_machine_report', methods: ['POST'])]
    public function report(string $token, Request $request, MaterielRepository $repo, EntityManagerInterface $em): Response
    {
        $materiel = $repo->findOneBy(['qrCodeToken' => $token]);

        if (!$materiel) {
            throw $this->createNotFoundException('Machine introuvable.');
        }

        $description = $request->request->get('description', 'Signalement via QR Code sur le terrain.');

        // On récupère l'agriculteur propriétaire
        $agriculteur = $materiel->getAgriculteur($em);

        if (!$agriculteur) {
             throw new \Exception("Impossible de trouver le propriétaire de ce matériel.");
        }

        // Création de l'Alerte Technicien pour l'agriculteur
        $alerte = new AlerteTechnicien();
        $alerte->setMateriel($materiel);
        $alerte->setAgriculteur($agriculteur);
        $alerte->setDateSignalement(new \DateTime());
        $alerte->setDescription($description);
        $alerte->setStatut('non_lu');

        // On peut toujours changer l'état du matériel pour visibilité
        $materiel->setEtat('En panne');
        
        $em->persist($alerte);
        $em->flush();

        $this->addFlash('success', 'Votre signalement a été envoyé directement à l\'agriculteur.');

        return $this->redirectToRoute('app_mobile_machine_show', ['token' => $token]);
    }
}
