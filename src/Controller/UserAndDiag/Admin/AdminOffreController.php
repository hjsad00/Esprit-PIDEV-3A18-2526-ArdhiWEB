<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Offre;
use App\Repository\UserAndDiag\OffreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/offres')]
class AdminOffreController extends AbstractController
{
    #[Route('', name: 'admin_offre_index')]
    public function index(OffreRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Offres',
            'icon' => 'bi-tag-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Nom', 'field' => 'nom'],
                ['label' => 'Prix/mois', 'field' => 'prixMensuel'],
                ['label' => 'Active', 'field' => 'estActive', 'type' => 'bool'],
                ['label' => 'Recommandée', 'field' => 'estRecommandee', 'type' => 'bool'],
                ['label' => 'Diag/h', 'field' => 'diagnosticsParHeure'],
            ],
            'new_route' => 'admin_offre_new',
            'edit_route' => 'admin_offre_edit',
            'delete_route' => 'admin_offre_delete',
        ]);
    }

    #[Route('/new', name: 'admin_offre_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $item = new Offre();
            $this->handle($request, $item, $em);
            $this->addFlash('success', 'Offre créée.');
            return $this->redirectToRoute('admin_offre_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouvelle Offre', 'fields' => $this->getFields(), 'cancel_route' => 'admin_offre_index', 'csrf_token_id' => 'offre_form']);
    }

    #[Route('/{id}/edit', name: 'admin_offre_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, OffreRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em);
            $this->addFlash('success', 'Offre modifiée.');
            return $this->redirectToRoute('admin_offre_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Offre #' . $item->getId(), 'fields' => $this->getFields(), 'item' => $item, 'cancel_route' => 'admin_offre_index', 'csrf_token_id' => 'offre_form']);
    }

    #[Route('/{id}/delete', name: 'admin_offre_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, OffreRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Offre supprimée.');
        }
        return $this->redirectToRoute('admin_offre_index');
    }

    private function handle(Request $r, Offre $item, EntityManagerInterface $em): void
    {
        $item->setNom($r->request->get('nom', ''));
        $item->setDescription($r->request->get('description') ?: null);
        $item->setPrixMensuel((float) $r->request->get('prix_mensuel', 0));
        $item->setAvantages($r->request->get('avantages') ?: null);
        $item->setCouleurPrimaire($r->request->get('couleur_primaire') ?: '#6B7F3F');
        $item->setCouleurSecondaire($r->request->get('couleur_secondaire') ?: '#4A5A2B');
        $item->setEstActive($r->request->has('est_active'));
        $item->setEstRecommandee($r->request->has('est_recommandee'));
        $item->setDiagnosticsParHeure((int) $r->request->get('diagnostics_par_heure', 3));
        $item->setAccesTraitement($r->request->has('acces_traitement'));
        $item->setAccesPlanTraitement($r->request->has('acces_plan_traitement'));
        $em->persist($item);
        $em->flush();
    }

    private function getFields(): array
    {
        return [
            ['name' => 'nom', 'label' => 'Nom', 'getter' => 'nom', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'getter' => 'description'],
            ['name' => 'prix_mensuel', 'label' => 'Prix Mensuel', 'getter' => 'prixMensuel', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'avantages', 'label' => 'Avantages', 'getter' => 'avantages', 'type' => 'textarea'],
            ['name' => 'couleur_primaire', 'label' => 'Couleur Primaire', 'getter' => 'couleurPrimaire', 'default' => '#6B7F3F'],
            ['name' => 'couleur_secondaire', 'label' => 'Couleur Secondaire', 'getter' => 'couleurSecondaire', 'default' => '#4A5A2B'],
            ['name' => 'diagnostics_par_heure', 'label' => 'Diagnostics/heure', 'getter' => 'diagnosticsParHeure', 'type' => 'number', 'default' => '3'],
            ['name' => 'est_active', 'label' => 'Active', 'getter' => 'estActive', 'type' => 'checkbox'],
            ['name' => 'est_recommandee', 'label' => 'Recommandée', 'getter' => 'estRecommandee', 'type' => 'checkbox'],
            ['name' => 'acces_traitement', 'label' => 'Accès Traitement', 'getter' => 'accesTraitement', 'type' => 'checkbox'],
            ['name' => 'acces_plan_traitement', 'label' => 'Accès Plan Traitement', 'getter' => 'accesPlanTraitement', 'type' => 'checkbox'],
        ];
    }
}
