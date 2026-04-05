<?php

namespace App\Controller\Parcelles_Cultures\Admin;

use App\Entity\Parcelles_Cultures\Culture;
use App\Entity\Parcelles_Cultures\Parcelle;
use App\Form\Parcelles_Cultures\CultureType;
use App\Repository\Parcelles_Cultures\CultureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/cultures', name: 'admin_culture_')]
#[IsGranted('ROLE_ADMIN')]
class CultureAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        CultureRepository $repository,
        PaginatorInterface $paginator,
        Request $request
    ): Response {
        $query = $repository->createQueryBuilder('c')->getQuery();
        
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('parcelles_cultures/admin/culture/index.html.twig', [
            'pagination' => $pagination
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $culture = new Culture();
        $form = $this->createForm(CultureType::class, $culture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($culture);
            $em->flush();

            return $this->redirectToRoute('admin_culture_show', [
                'id' => $culture->getId()
            ]);
        }

        return $this->render('parcelles_cultures/admin/culture/form.html.twig', [
            'form' => $form,
            'culture' => $culture
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Culture $culture,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(CultureType::class, $culture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('admin_culture_show', ['id' => $culture->getId()]);
        }

        return $this->render('parcelles_cultures/admin/culture/form.html.twig', [
            'form' => $form,
            'culture' => $culture
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Culture $culture): Response
    {
        return $this->render('parcelles_cultures/admin/culture/show.html.twig', [
            'culture' => $culture
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Culture $culture,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $culture->getId(), $request->getPayload()->get('_token'))) {
            $em->remove($culture);
            $em->flush();
        }

        return $this->redirectToRoute('admin_culture_index');
    }
}
