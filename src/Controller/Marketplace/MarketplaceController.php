<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\NotifMarket;
use App\Entity\UserAndDiag\User;
use App\Repository\Marketplace\NotifMarketRepository;
use App\Repository\Marketplace\ProduitsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur de la page d'accueil du marketplace.
 */
class MarketplaceController extends AbstractController
{
    #[Route('/marketplace', name: 'app_marketplace')]
    public function index(
        ProduitsRepository $produitsRepository,
        NotifMarketRepository $notifMarketRepository
    ): Response
    {
        $produits = $produitsRepository->findAll();

        $notifications = [];
        $unreadCount = 0;

        $user = $this->getUser();
        if ($user instanceof User) {
            $userId = (int) $user->getId();
            $notifications = $notifMarketRepository->getNotificationsParVendeur($userId);
            $unreadCount = $notifMarketRepository->compterNonLues($userId);
        }

        return $this->render('Marketplace/accueil.html.twig', [
            'produits' => $produits,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/marketplace/notifications/{id}/read', name: 'app_marketplace_notification_read', methods: ['POST'])]
    public function markNotificationRead(
        int $id,
        NotifMarketRepository $notifMarketRepository,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Authentification requise.'], 401);
            }

            return $this->redirectToRoute('app_login');
        }

        $updated = false;
        $notif = $notifMarketRepository->find($id);
        if ($notif instanceof NotifMarket && $notif->getUser()?->getId() === $user->getId()) {
            $notif->setLue(true);
            $entityManager->flush();
            $updated = true;
        }

        $unreadCount = $notifMarketRepository->compterNonLues((int) $user->getId());

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'updated' => $updated,
                'unreadCount' => $unreadCount,
                'notificationId' => $id,
            ]);
        }

        return $this->redirectToRoute('app_marketplace', ['_fragment' => 'market-notifications']);
    }

    #[Route('/marketplace/notifications/read-all', name: 'app_marketplace_notifications_read_all', methods: ['POST'])]
    public function markAllNotificationsRead(
        NotifMarketRepository $notifMarketRepository,
        Request $request
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Authentification requise.'], 401);
            }

            return $this->redirectToRoute('app_login');
        }

        $notifMarketRepository->marquerToutesCommeLues((int) $user->getId());

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'unreadCount' => 0,
            ]);
        }

        return $this->redirectToRoute('app_marketplace', ['_fragment' => 'market-notifications']);
    }

    #[Route('/marketplace/notifications/{id}/delete', name: 'app_marketplace_notification_delete', methods: ['POST'])]
    public function deleteNotification(
        int $id,
        NotifMarketRepository $notifMarketRepository,
        Request $request
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Authentification requise.'], 401);
            }

            return $this->redirectToRoute('app_login');
        }

        $notif = $notifMarketRepository->find($id);
        if (!$notif instanceof NotifMarket || $notif->getUser()?->getId() !== $user->getId()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Notification introuvable.'], 404);
            }

            return $this->redirectToRoute('app_marketplace');
        }

        $wasUnread = !$notif->isLue();
        $notifMarketRepository->supprimerNotification($id);

        $unreadCount = $notifMarketRepository->compterNonLues((int) $user->getId());

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'deleted' => true,
                'notificationId' => $id,
                'wasUnread' => $wasUnread,
                'unreadCount' => $unreadCount,
            ]);
        }

        return $this->redirectToRoute('app_marketplace', ['_fragment' => 'market-notifications']);
    }

    #[Route('/marketplace/notifications/feed', name: 'app_marketplace_notifications_feed', methods: ['GET'])]
    public function notificationsFeed(NotifMarketRepository $notifMarketRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Authentification requise.'], 401);
        }

        $notifications = $notifMarketRepository->getNotificationsParVendeur((int) $user->getId());
        $notifications = array_slice($notifications, 0, 12);

        $payload = array_map(static function (NotifMarket $notif): array {
            return [
                'id' => $notif->getId(),
                'titre' => $notif->getTitre(),
                'message' => $notif->getMessage(),
                'lue' => $notif->isLue(),
                'dateCreation' => $notif->getDateCreation()->format('d/m/Y H:i'),
            ];
        }, $notifications);

        return new JsonResponse([
            'success' => true,
            'unreadCount' => $notifMarketRepository->compterNonLues((int) $user->getId()),
            'notifications' => $payload,
        ]);
    }
}