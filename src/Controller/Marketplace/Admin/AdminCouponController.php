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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
    public function save(Request $request, EntityManagerInterface $em, CouponRepository $couponRepo, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $id = $data['id'] ?? null;
        if ($id) {
            $coupon = $couponRepo->find($id);
            if (!$coupon) {
                return $this->json(['success' => false, 'message' => 'Coupon introuvable.'], 404);
            }
        } else {
            $coupon = new Coupon();
            $em->persist($coupon);
        }

        // Hydratation
        $coupon->setCode(strtoupper($data['code'] ?? ''));
        $coupon->setTypeReduction($data['typeReduction'] ?? '');
        $coupon->setValeur((float) ($data['valeur'] ?? 0));
        
        try {
            if (!empty($data['dateDebut'])) $coupon->setDateDebut(new \DateTime($data['dateDebut']));
            if (!empty($data['dateFin'])) $coupon->setDateFin(new \DateTime($data['dateFin']));
        } catch (\Exception $e) {
            // Les erreurs de format de date seront capturées par le Validateur (NotBlank ou autre)
            // ou on peut laisser l'objet avec ses valeurs précédentes/nulles.
        }

        $coupon->setUtilisationMax(isset($data['utilisationMax']) ? (int)$data['utilisationMax'] : 0);
        $coupon->setMontantMin(isset($data['montantMin']) ? floatval($data['montantMin']) : 0.0);
        $coupon->setLimiteParUser(isset($data['limiteParUser']) ? (int)$data['limiteParUser'] : 1);

        // Validation Symfony
        $violations = $validator->validate($coupon);

        // Vérification unicité manuelle pour le code (si changé ou nouveau)
        $existing = $couponRepo->findOneBy(['code' => $coupon->getCode()]);
        if ($existing && $existing->getId() !== $coupon->getId()) {
            return $this->json(['success' => false, 'message' => 'Ce code promo existe déjà.'], 400);
        }

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }
            return $this->json(['success' => false, 'message' => implode(' ', $errors)], 400);
        }

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
