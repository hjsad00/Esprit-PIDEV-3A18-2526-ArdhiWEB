<?php

namespace App\Controller\Marketplace\Admin;

use App\Entity\Marketplace\Coupon;
use App\Repository\Marketplace\CouponRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/marketplace/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminCouponController extends AbstractController
{
    #[Route('/coupons', name: 'admin_marketplace_coupons')]
    public function coupons(CouponRepository $couponRepo): Response
    {
        $coupons = $couponRepo->findBy([], ['id' => 'DESC']);
        return $this->render('Marketplace/admin/coupons.html.twig', [
            'coupons' => $coupons
        ]);
    }

    #[Route('/coupon/save', name: 'admin_marketplace_coupon_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em, CouponRepository $couponRepo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['code']) || empty($data['valeur'])) {
            return $this->json(['success' => false, 'message' => 'Le code et la valeur sont obligatoires.'], 400);
        }

        $id = $data['id'] ?? null;
        if ($id) {
            $coupon = $couponRepo->find($id);
            if (!$coupon) {
                return $this->json(['success' => false, 'message' => 'Coupon introuvable.'], 404);
            }
            // Check explicit uniqueness if code changed
            if ($coupon->getCode() !== $data['code']) {
                $existing = $couponRepo->findOneBy(['code' => $data['code']]);
                if ($existing) {
                    return $this->json(['success' => false, 'message' => 'Ce code existe déjà.'], 400);
                }
            }
        } else {
            $existing = $couponRepo->findOneBy(['code' => $data['code']]);
            if ($existing) {
                return $this->json(['success' => false, 'message' => 'Ce code existe déjà.'], 400);
            }
            $coupon = new Coupon();
            $em->persist($coupon);
        }

        $typeReduction = $data['typeReduction'];
        $valeur = floatval($data['valeur']);

        if ($typeReduction === 'POURCENTAGE' && ($valeur < 1 || $valeur > 100)) {
            return $this->json(['success' => false, 'message' => 'Pour un pourcentage, la valeur doit être entre 1 et 100.'], 400);
        }

        $dateDebut = new \DateTime($data['dateDebut']);
        $dateFin = new \DateTime($data['dateFin']);

        if ($dateDebut > $dateFin) {
            return $this->json(['success' => false, 'message' => 'La date de début doit être avant la date de fin.']);
        }

        $coupon->setCode(strtoupper($data['code']));
        $coupon->setTypeReduction($typeReduction);
        $coupon->setValeur($valeur);
        $coupon->setDateDebut($dateDebut);
        $coupon->setDateFin($dateFin);
        $coupon->setUtilisationMax((int) $data['utilisationMax']);
        $coupon->setMontantMin(floatval($data['montantMin']));
        $coupon->setLimiteParUser((int) $data['limiteParUser']);

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => $id ? 'Coupon modifié avec succès.' : 'Coupon ajouté avec succès.'
        ]);
    }

    #[Route('/coupon/{id}/toggle', name: 'admin_marketplace_coupon_toggle', methods: ['POST'])]
    public function toggle(int $id, CouponRepository $couponRepo, EntityManagerInterface $em): JsonResponse
    {
        $coupon = $couponRepo->find($id);
        if (!$coupon) {
            return $this->json(['success' => false, 'message' => 'Coupon introuvable.'], 404);
        }

        $coupon->setActif(!$coupon->isActif());
        $em->flush();

        return $this->json([
            'success' => true,
            'actif' => $coupon->isActif(),
            'message' => 'Statut du coupon mis à jour.'
        ]);
    }

    #[Route('/coupon/{id}/delete', name: 'admin_marketplace_coupon_delete', methods: ['DELETE'])]
    public function delete(int $id, CouponRepository $couponRepo, EntityManagerInterface $em): JsonResponse
    {
        $coupon = $couponRepo->find($id);
        if (!$coupon) {
            return $this->json(['success' => false, 'message' => 'Coupon introuvable.'], 404);
        }

        $em->remove($coupon);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Coupon supprimé.'
        ]);
    }

    #[Route('/coupon/{id}', name: 'admin_marketplace_coupon_get', methods: ['GET'])]
    public function getCoupon(int $id, CouponRepository $couponRepo): JsonResponse
    {
        $coupon = $couponRepo->find($id);
        if (!$coupon) {
            return $this->json(['success' => false, 'message' => 'Coupon introuvable.'], 404);
        }

        return $this->json([
            'success' => true,
            'coupon' => [
                'id' => $coupon->getId(),
                'code' => $coupon->getCode(),
                'typeReduction' => $coupon->getTypeReduction(),
                'valeur' => $coupon->getValeur(),
                'dateDebut' => $coupon->getDateDebut()->format('Y-m-d'),
                'dateFin' => $coupon->getDateFin()->format('Y-m-d'),
                'utilisationMax' => $coupon->getUtilisationMax(),
                'montantMin' => $coupon->getMontantMin(),
                'limiteParUser' => $coupon->getLimiteParUser(),
            ]
        ]);
    }
}
