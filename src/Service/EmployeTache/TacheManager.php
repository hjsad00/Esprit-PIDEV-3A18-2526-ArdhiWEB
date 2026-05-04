<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Tache;

/**
 * Service métier pour la gestion des règles de validation de l'entité Tache.
 *
 * Ce service valide les règles métier de manière isolée (sans base de données),
 * ce qui le rend parfaitement testable via des tests unitaires PHPUnit (TestCase).
 */
class TacheManager
{
    /**
     * Valide toutes les règles métier d'une tâche.
     *
     * @param Tache $tache L'entité à valider
     * @return bool true si toutes les règles sont respectées
     *
     * @throws \InvalidArgumentException si une règle métier est violée
     */
    public function valider(Tache $tache): bool
    {
        $this->validerTitre($tache->getTitre());
        $this->validerStatut($tache->getStatut());
        $this->validerPriorite($tache->getPriorite());
        $this->validerCategorie($tache->getCategorie());
        $this->validerDates($tache->getDateDebut(), $tache->getDateFin());

        return true;
    }

    // ─── Règles métier individuelles ──────────────────────────────────────────

    /**
     * Règle 1 : Le titre est obligatoire (non vide).
     *
     * @throws \InvalidArgumentException
     */
    public function validerTitre(?string $titre): void
    {
        if (empty(trim((string) $titre))) {
            throw new \InvalidArgumentException('Le titre de la tâche est obligatoire.');
        }
    }

    /**
     * Règle 2 : Le statut doit faire partie des valeurs autorisées.
     *
     * @throws \InvalidArgumentException
     */
    public function validerStatut(string $statut): void
    {
        if (!in_array($statut, Tache::STATUTS, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Le statut "%s" est invalide. Valeurs autorisées : %s.',
                    $statut,
                    implode(', ', Tache::STATUTS)
                )
            );
        }
    }

    /**
     * Règle 3 : La priorité doit être entre 1 (Basse) et 4 (Critique).
     *
     * @throws \InvalidArgumentException
     */
    public function validerPriorite(?int $priorite): void
    {
        if ($priorite === null || !array_key_exists($priorite, Tache::PRIORITES)) {
            throw new \InvalidArgumentException(
                'La priorité doit être un entier entre 1 (Basse) et 4 (Critique).'
            );
        }
    }

    /**
     * Règle 4 : La catégorie doit faire partie des catégories agricoles définies.
     *
     * @throws \InvalidArgumentException
     */
    public function validerCategorie(?string $categorie): void
    {
        if (empty($categorie) || !in_array($categorie, Tache::CATEGORIES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'La catégorie "%s" est invalide. Valeurs autorisées : %s.',
                    (string) $categorie,
                    implode(', ', Tache::CATEGORIES)
                )
            );
        }
    }

    /**
     * Règle 5 : Si les deux dates sont définies, la date de fin doit être
     *            postérieure ou égale à la date de début.
     *
     * @throws \InvalidArgumentException
     */
    public function validerDates(
        ?\DateTimeInterface $dateDebut,
        ?\DateTimeInterface $dateFin
    ): void {
        if ($dateDebut !== null && $dateFin !== null && $dateFin < $dateDebut) {
            throw new \InvalidArgumentException(
                'La date de fin doit être postérieure ou égale à la date de début.'
            );
        }
    }

    /**
     * Affecte un employé à la tâche.
     *
     * Règle métier : une tâche annulée ou terminée ne peut plus être réaffectée.
     *
     * @throws \InvalidArgumentException
     */
    public function affecterEmploye(Tache $tache, int $idEmploye): void
    {
        if (in_array($tache->getStatut(), [Tache::STATUT_TERMINE, Tache::STATUT_ANNULE, Tache::STATUT_VALIDE], true)) {
            throw new \InvalidArgumentException(
                'Impossible d\'affecter un employé à une tâche terminée, validée ou annulée.'
            );
        }

        $tache->setIdEmploye($idEmploye);
    }

    /**
     * Marque la tâche comme terminée.
     *
     * Règle : seule une tâche "En cours" peut être terminée.
     *
     * @throws \InvalidArgumentException
     */
    public function terminer(Tache $tache): void
    {
        if ($tache->getStatut() !== Tache::STATUT_EN_COURS) {
            throw new \InvalidArgumentException(
                'Seule une tâche "En cours" peut être marquée comme terminée.'
            );
        }

        $tache->setStatut(Tache::STATUT_TERMINE);
    }

    /**
     * Valide une tâche terminée.
     *
     * Règle : seule une tâche "Terminée" peut être validée.
     *
     * @throws \InvalidArgumentException
     */
    public function validerTache(Tache $tache): void
    {
        if ($tache->getStatut() !== Tache::STATUT_TERMINE) {
            throw new \InvalidArgumentException(
                'Seule une tâche "Terminée" peut être validée.'
            );
        }

        $tache->setStatut(Tache::STATUT_VALIDE);
    }
}
