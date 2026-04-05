<?php

namespace App\Controller\Parcelles_Cultures\Admin;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Form\Parcelles_Cultures\ParceleType;
use App\Repository\Parcelles_Cultures\ParceleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/parcelles', name: 'admin_parcelle_')]
#[IsGranted('ROLE_ADMIN')]
class ParceleAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        ParceleRepository $repository,
        PaginatorInterface $paginator,
        Request $request
    ): Response {
        $query = $repository->createQueryBuilder('p')->getQuery();
        
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('parcelles_cultures/admin/parcelle/index.html.twig', [
            'pagination' => $pagination
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $parcelle = new Parcelle();
        $form = $this->createForm(ParceleType::class, $parcelle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($parcelle);
            $em->flush();

            return $this->redirectToRoute('admin_parcelle_show', [
                'id' => $parcelle->getId()
            ]);
        }

        return $this->render('parcelles_cultures/admin/parcelle/form.html.twig', [
            'form' => $form,
            'parcelle' => $parcelle
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Parcelle $parcelle,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('EDIT', $parcelle);

        $form = $this->createForm(ParceleType::class, $parcelle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('admin_parcelle_show', ['id' => $parcelle->getId()]);
        }

        return $this->render('parcelles_cultures/admin/parcelle/form.html.twig', [
            'form' => $form,
            'parcelle' => $parcelle
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Parcelle $parcelle): Response
    {
        return $this->render('parcelles_cultures/admin/parcelle/show.html.twig', [
            'parcelle' => $parcelle
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Parcelle $parcelle,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('DELETE', $parcelle);

        if ($this->isCsrfTokenValid('delete' . $parcelle->getId(), $request->getPayload()->get('_token'))) {
            $em->remove($parcelle);
            $em->flush();
        }

        return $this->redirectToRoute('admin_parcelle_index');
    }
}
