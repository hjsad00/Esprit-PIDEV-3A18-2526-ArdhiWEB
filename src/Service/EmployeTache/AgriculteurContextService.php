<?php

namespace App\Service\EmployeTache;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Équivalent PHP de AgriculteurContext.java
 *
 * Gère le contexte multi-rôle :
 *   - AGRICULTEUR connecté → utilise son propre ID
 *   - ADMIN supervisant un agriculteur → utilise l'ID stocké en session
 */
class AgriculteurContextService
{
    private const SESSION_KEY = 'admin_supervise_agriculteur_id';
    private const SESSION_NOM = 'admin_supervise_agriculteur_nom';

    public function __construct(
        private RequestStack          $requestStack,
        private TokenStorageInterface $tokenStorage,
    ) {}

    // ── Admin : démarrer la supervision ──────────────────────────────

    public function setSupervision(int $idAgriculteur, string $nomComplet): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY, $idAgriculteur);
        $session->set(self::SESSION_NOM, $nomComplet);
    }

    public function clearSupervision(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_KEY);
        $session->remove(self::SESSION_NOM);
    }

    // ── Lecture du contexte actif ─────────────────────────────────────

    /**
     * Retourne l'ID agriculteur à utiliser dans les requêtes.
     *   - Admin en supervision → ID de l'agriculteur supervisé (session)
     *   - Agriculteur connecté → son propre ID
     *   - Autre → null
     */
    public function getActiveAgriculteurId(): ?int
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) return null;

        $user  = $token->getUser();
        $roles = $user?->getRoles() ?? [];

        // Admin en mode supervision → ID de l'agriculteur choisi
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $id = $this->requestStack->getSession()->get(self::SESSION_KEY);
            return $id ? (int)$id : null;
        }

        // Agriculteur connecté → son propre ID
        if (in_array('ROLE_AGRICULTEUR', $roles, true)) {
            return $user?->getId();
        }

        return null;
    }

    /**
     * Retourne le nom de l'agriculteur supervisé (pour affichage admin)
     */
    public function getNomAgriculteurSupervise(): ?string
    {
        return $this->requestStack->getSession()->get(self::SESSION_NOM);
    }

    /**
     * Vérifie si l'admin est en mode supervision
     */
    public function isSupervisionMode(): bool
    {
        $token = $this->tokenStorage->getToken();
        $roles = $token?->getUser()?->getRoles() ?? [];

        return in_array('ROLE_ADMIN', $roles, true)
            && $this->requestStack->getSession()->has(self::SESSION_KEY);
    }

    /**
     * Vérifie que l'utilisateur a le droit d'accéder au module employés/tâches.
     * Retourne false pour CLIENT et AGRONOME.
     */
    public function hasAccess(): bool
    {
        $token = $this->tokenStorage->getToken();
        $roles = $token?->getUser()?->getRoles() ?? [];

        return in_array('ROLE_ADMIN', $roles, true)
            || in_array('ROLE_AGRICULTEUR', $roles, true);
    }
}
