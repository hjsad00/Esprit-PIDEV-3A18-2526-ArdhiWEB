<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\FarmHealthScan;
use App\Form\UserAndDiag\Admin\AdminFarmHealthScanType;
use App\Repository\UserAndDiag\FarmHealthScanRepository;
use App\Service\UserAndDiag\ImgBBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/farm-health-scans')]
class AdminFarmHealthScanController extends AbstractController
{
    #[Route('', name: 'admin_farm_health_scan_index')]
    public function index(FarmHealthScanRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Scans Santé',
            'icon' => 'bi-activity',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Aperçu', 'field' => 'photoCrops', 'type' => 'image'],
                ['label' => 'Culture', 'field' => 'cropType'],
                ['label' => 'Croissance', 'field' => 'growthStage'],
                ['label' => 'Date', 'field' => 'scanDate', 'type' => 'date'],
                ['label' => 'Statut', 'field' => 'status', 'type' => 'badge', 'color' => '#2ecc71'],
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
            ],
            'new_route' => 'admin_farm_health_scan_new',
            'edit_route' => 'admin_farm_health_scan_edit',
            'delete_route' => 'admin_farm_health_scan_delete',
        ]);
    }

    #[Route('/new', name: 'admin_farm_health_scan_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ImgBBService $imgBBService): Response
    {
        $item = new FarmHealthScan();
        $form = $this->createForm(AdminFarmHealthScanType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFields = [
                'photoCropsFile' => 'setPhotoCrops',
                'photoSoilFile' => 'setPhotoSoil',
                'photoEdgesFile' => 'setPhotoEdges',
                'photoInsectsFile' => 'setPhotoInsects',
                'photoSpacingFile' => 'setPhotoSpacing',
                'photoOverviewFile' => 'setPhotoOverview'
            ];
            $uploadError = false;
            foreach ($photoFields as $fField => $setter) {
                $file = $form->get($fField)->getData();
                if ($file) {
                    $u = $imgBBService->uploadImage($file);
                    if ($u) {
                        $item->$setter($u);
                    } else {
                        $uploadError = true;
                    }
                }
            }
            if ($uploadError) {
                $this->addFlash('danger', 'Certaines photos n\'ont pas pu être uploadées sur ImgBB.');
            }
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Scan créé.');
            return $this->redirectToRoute('admin_farm_health_scan_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Scan Santé',
            'form' => $form,
            'cancel_route' => 'admin_farm_health_scan_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_farm_health_scan_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, FarmHealthScanRepository $repo, ImgBBService $imgBBService): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminFarmHealthScanType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Photos
            $photoFields = [
                'photoCropsFile' => 'setPhotoCrops',
                'photoSoilFile' => 'setPhotoSoil',
                'photoEdgesFile' => 'setPhotoEdges',
                'photoInsectsFile' => 'setPhotoInsects',
                'photoSpacingFile' => 'setPhotoSpacing',
                'photoOverviewFile' => 'setPhotoOverview'
            ];
            $uploadError = false;
            foreach ($photoFields as $fField => $setter) {
                $file = $form->get($fField)->getData();
                if ($file) {
                    $u = $imgBBService->uploadImage($file);
                    if ($u) {
                        $item->$setter($u);
                    } else {
                        $uploadError = true;
                    }
                }
            }
            if ($uploadError) {
                $this->addFlash('danger', 'Certaines photos n\'ont pas pu être uploadées sur ImgBB.');
            }
            $em->flush();
            $this->addFlash('success', 'Scan modifié.');
            return $this->redirectToRoute('admin_farm_health_scan_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Scan #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_farm_health_scan_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_farm_health_scan_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, FarmHealthScanRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Scan supprimé.');
        }
        return $this->redirectToRoute('admin_farm_health_scan_index');
    }
}
