<?php

namespace App\EventSubscriber;

use App\Entity\MaterielEtMaintenance\Materiel;
use App\Entity\MaterielEtMaintenance\NotificationMaintenance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;

class MaterielLifecycleSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.materiel_lifecycle.transition.confirmer_maintenance' => 'onConfirmerMaintenance',
            'workflow.materiel_lifecycle.transition.mettre_en_maintenance' => 'onMettreEnMaintenance',
        ];
    }

    public function onConfirmerMaintenance(TransitionEvent $event): void
    {
        $materiel = $event->getSubject();

        if ($materiel instanceof Materiel) {
            // 1. Mise à jour de la date de dernière maintenance
            $materiel->setDerniereMaintenance(new \DateTime());

            // 2. Recalcul de la prochaine maintenance (+6 mois)
            $materiel->calculerProchaineMaintenance();

            // 3. Mise à jour de l'état "santé" si besoin (on le remet à Bon)
            $materiel->setEtat('Bon');

            // 4. Création d'une notification de confirmation
            $this->createNotification(
                $materiel, 
                "Maintenance confirmée", 
                "La maintenance a été enregistrée. Prochain rappel prévu le " . $materiel->getDateProchaineMaintenance()->format('d/m/Y') . "."
            );
        }
    }

    public function onMettreEnMaintenance(TransitionEvent $event): void
    {
        $materiel = $event->getSubject();

        if ($materiel instanceof Materiel) {
            // On peut notifier l'agriculteur que la machine est maintenant "Indisponible"
            $this->createNotification(
                $materiel, 
                "Machine en maintenance", 
                "Le matériel " . $materiel->getNom() . " est désormais marqué en maintenance."
            );
        }
    }

    private function createNotification(Materiel $materiel, string $titre, string $message): void
    {
        if (!$materiel->getUserId()) return;

        // Récupération de l'objet User à partir de l'ID (on utilise getReference pour la performance)
        $user = $this->entityManager->getReference('App\Entity\UserAndDiag\User', $materiel->getUserId());

        $notif = new NotificationMaintenance();
        $notif->setUser($user);
        $notif->setMateriel($materiel);
        $notif->setTitre($titre);
        $notif->setMessage($message);
        $notif->setNouveauStatut($materiel->getStatut());
        $notif->setCreatedAt(new \DateTimeImmutable());
        $notif->setRead(false);

        $this->entityManager->persist($notif);
    }
}
