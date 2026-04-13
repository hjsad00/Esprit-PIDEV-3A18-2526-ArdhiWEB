<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\NotifMarket;
use App\Entity\Marketplace\Produits;
use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotifMarket>
 *
 * @method NotifMarket|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotifMarket|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotifMarket[]    findAll()
 * @method NotifMarket[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotifMarketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotifMarket::class);
    }

    /**
     * Récupère les notifications d'un utilisateur, triées de la plus récente à la plus ancienne.
     */
    public function findByUser(User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.dateCreation', 'DESC');

        if ($limit !== null && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Cree une notification.
     */
    public function creerNotification(NotifMarket $notif): bool
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($notif);
            $em->flush();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Notifie un vendeur qu'une commande vient d'etre passee.
     */
    public function notifierNouvelleCommande(
        int $idVendeur,
        int $idCommande,
        int $idProduit,
        string $nomAcheteur,
        float $total
    ): bool {
        $em = $this->getEntityManager();

        $notif = new NotifMarket();
        $notif
            ->setUser($em->getReference(User::class, $idVendeur))
            ->setCommande($em->getReference(Commande::class, $idCommande))
            ->setProduit($em->getReference(Produits::class, $idProduit))
            ->setType(NotifMarket::TYPE_ACHAT)
            ->setTitre('Nouvelle commande recue')
            ->setMessage(sprintf(
                '%s vient de passer une commande (#%d) pour un total de %.2f DT.',
                $nomAcheteur,
                $idCommande,
                $total
            ));

        return $this->creerNotification($notif);
    }

    /**
     * Notifie un vendeur qu'un avis a ete depose sur son produit.
     */
    public function notifierNouvelAvis(
        int $idVendeur,
        int $idProduit,
        string $nomProduit,
        string $nomAuteur,
        int $note
    ): bool {
        $em = $this->getEntityManager();

        $noteBorned = max(1, min($note, 5));

        $notif = new NotifMarket();
        $notif
            ->setUser($em->getReference(User::class, $idVendeur))
            ->setProduit($em->getReference(Produits::class, $idProduit))
            ->setType(NotifMarket::TYPE_AVIS)
            ->setTitre('Nouvel avis sur votre produit')
            ->setMessage(sprintf(
                '%s a laisse un avis (%d/5) sur "%s".',
                $nomAuteur,
                $noteBorned,
                $nomProduit
            ));

        return $this->creerNotification($notif);
    }

    /**
     * Notifie un acheteur que le statut de sa commande a change.
     */
    public function notifierChangementStatutCommande(
        int $idAcheteur,
        int $idCommande,
        string $nouveauStatut,
        float $totalCommande
    ): bool {
        $em = $this->getEntityManager();

        $labelMap = [
            'en_attente' => 'En attente',
            'en_cours' => 'En cours',
            'livree' => 'Livree',
            'annulee' => 'Annulee',
        ];

        $statutLabel = $labelMap[$nouveauStatut] ?? ucfirst($nouveauStatut);

        $notif = new NotifMarket();
        $notif
            ->setUser($em->getReference(User::class, $idAcheteur))
            ->setCommande($em->getReference(Commande::class, $idCommande))
            ->setType(NotifMarket::TYPE_ACHAT)
            ->setTitre('Mise a jour de votre commande')
            ->setMessage(sprintf(
                'Votre commande #%d est maintenant: %s (Total: %.2f DT).',
                $idCommande,
                $statutLabel,
                $totalCommande
            ));

        return $this->creerNotification($notif);
    }

    /**
     * Retourne toutes les notifications d'un vendeur, non lues en premier.
     */
    public function getNotificationsParVendeur(int $idVendeur): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('IDENTITY(n.user) = :userId')
            ->setParameter('userId', $idVendeur)
            ->orderBy('n.lue', 'ASC')
            ->addOrderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne uniquement les notifications non lues d'un vendeur.
     */
    public function getNonLuesParVendeur(int $idVendeur): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('IDENTITY(n.user) = :userId')
            ->andWhere('n.lue = :isRead')
            ->setParameter('userId', $idVendeur)
            ->setParameter('isRead', false)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les notifications non lues d'un vendeur.
     */
    public function compterNonLues(int $idVendeur): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('IDENTITY(n.user) = :userId')
            ->andWhere('n.lue = :isRead')
            ->setParameter('userId', $idVendeur)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marque une notification precise comme lue.
     */
    public function marquerCommeLue(int $idNotif): bool
    {
        $updatedRows = $this->createQueryBuilder('n')
            ->update()
            ->set('n.lue', ':isRead')
            ->andWhere('n.id = :idNotif')
            ->setParameter('isRead', true)
            ->setParameter('idNotif', $idNotif)
            ->getQuery()
            ->execute();

        return $updatedRows > 0;
    }

    /**
     * Marque toutes les notifications d'un vendeur comme lues.
     */
    public function marquerToutesCommeLues(int $idVendeur): bool
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.lue', ':isRead')
            ->andWhere('IDENTITY(n.user) = :userId')
            ->setParameter('isRead', true)
            ->setParameter('userId', $idVendeur)
            ->getQuery()
            ->execute();

        return true;
    }

    /**
     * Supprime une notification.
     */
    public function supprimerNotification(int $idNotif): bool
    {
        $deletedRows = $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.id = :idNotif')
            ->setParameter('idNotif', $idNotif)
            ->getQuery()
            ->execute();

        return $deletedRows > 0;
    }

    /**
     * Récupère les notifications non lues d'un utilisateur.
     */
    public function findUnreadByUser(User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.lue = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->orderBy('n.dateCreation', 'DESC');

        if ($limit !== null && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les notifications non lues d'un utilisateur.
     */
    public function countUnreadByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.lue = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
