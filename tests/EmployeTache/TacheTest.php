<?php

namespace App\Tests\EmployeTache;

use App\Entity\EmployeTache\Tache;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TacheTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;
    private ?ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    public function testControleSaisieTacheInvalide(): void
    {
        $tache = new Tache();
        // Une tâche sans titre devrait remonter une erreur due à l'Assert\NotBlank
        $tache->setTitre(''); 
        
        $errors = $this->validator->validate($tache);
        
        // On s'attend à au minimum une erreur sur la contrainte de saisie du titre
        $this->assertGreaterThanOrEqual(1, count($errors), 'La validation doit échouer si le titre est vide.');
    }

    public function testAjouterTache(): int
    {
        $tache = new Tache();
        $tache->setTitre('Nouvelle Tache Test');
        $tache->setDescription('Description de test');
        $tache->setStatut(Tache::STATUT_EN_ATTENTE);
        $tache->setDateDebut(new \DateTime('now'));
        
        // S'assurer qu'elle passe la validation
        $errors = $this->validator->validate($tache);
        $this->assertCount(0, $errors, 'La tâche doit être valide de base');

        $this->entityManager->persist($tache);
        $this->entityManager->flush();

        $id = $tache->getId();
        $this->assertNotNull($id, 'La Tâche doit avoir reçu un ID après l\'insertion.');
        
        return $id;
    }

    #[Depends('testAjouterTache')]
    public function testModifierTache(int $id): int
    {
        $tache = $this->entityManager->getRepository(Tache::class)->find($id);
        $this->assertNotNull($tache, 'La tâche créée lors du test précédent doit exister en base.');

        $tache->setTitre('Titre Modifie');
        $tache->setStatut(Tache::STATUT_EN_COURS);
        $this->entityManager->flush();

        $tacheModifiee = $this->entityManager->getRepository(Tache::class)->find($id);
        $this->assertEquals('Titre Modifie', $tacheModifiee->getTitre(), 'Le titre doit avoir été correctement modifié.');
        $this->assertEquals(Tache::STATUT_EN_COURS, $tacheModifiee->getStatut(), 'Le statut doit avoir été correctement modifié.');

        return $id;
    }

    #[Depends('testModifierTache')]
    public function testSupprimerTache(int $id): void
    {
        $tache = $this->entityManager->getRepository(Tache::class)->find($id);
        $this->assertNotNull($tache, 'La tâche à supprimer doit exister.');

        $this->entityManager->remove($tache);
        $this->entityManager->flush();

        $tacheSupprimee = $this->entityManager->getRepository(Tache::class)->find($id);
        $this->assertNull($tacheSupprimee, 'La tâche a bien été retirée et ne doit plus se trouver en base de données.');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
        $this->validator = null;
    }
}
