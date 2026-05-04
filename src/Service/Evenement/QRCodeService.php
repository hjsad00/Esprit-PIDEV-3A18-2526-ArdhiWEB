<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\Participation;
use App\Repository\Evenement\EvenementRepository;
use App\Repository\Evenement\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Psr\Log\LoggerInterface;

class QRCodeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ParticipationRepository $participationRepo,
        private EvenementRepository $evenementRepo,
        private LoggerInterface $logger,
        private string $projectDir
    ) {}

    public function getOrCreateToken(Participation $participation): string
    {
        if ($participation->getQrCodeToken()) {
            return $participation->getQrCodeToken();
        }

        $token = bin2hex(random_bytes(16));
        $participation->setQrCodeToken($token);
        $this->em->flush();

        return $token;
    }

    public function genererQRCodePng(Participation $participation): string
    {
        $participationId = $participation->getId();
        $evenementId = $participation->getEvenement()?->getId();

        if ($participationId === null || $evenementId === null) {
            throw new \LogicException('La participation et son événement doivent être persistés avant de générer un QR code.');
        }

        $content = $this->buildQRContent(
            $this->getOrCreateToken($participation),
            $participationId,
            $evenementId
        );

        return $this->generateSvgData($content);
    }

    public function genererQRCodeBase64(Participation $participation): string
    {
        $svgData = $this->genererQRCodePng($participation);

        return 'data:image/svg+xml;base64,' . base64_encode($svgData);
    }

    public function genererQRCodeFichier(Participation $participation): string
    {
        $uploadsDir = $this->projectDir . '/public/uploads/qrcodes';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $participationId = $participation->getId();
        if ($participationId === null) {
            throw new \LogicException('La participation doit être persistée avant de sauvegarder un QR code.');
        }

        $token = $this->getOrCreateToken($participation);
        $filename = 'qr_' . $participationId . '_' . substr($token, 0, 8) . '.svg';
        $filepath = $uploadsDir . '/' . $filename;

        file_put_contents($filepath, $this->genererQRCodePng($participation));

        $this->logger->info('QR code saved: {file}', ['file' => $filename]);

        return '/uploads/qrcodes/' . $filename;
    }

    /**
     * @return array<string, mixed>
     */
    public static function makeResult(
        bool $success,
        string $message,
        ?Participation $participation = null,
        ?Evenement $evenement = null
    ): array {
        return [
            'success' => $success,
            'message' => $message,
            'participation' => $participation,
            'evenement' => $evenement,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function processQRScan(string $qrContent): array
    {
        if (!str_starts_with($qrContent, 'ARDHI_CHECKIN|')) {
            return self::makeResult(false, 'QR code invalide — format non reconnu');
        }

        $parts = explode('|', $qrContent);
        if (count($parts) !== 4) {
            return self::makeResult(false, 'QR code invalide — données incomplètes');
        }

        [, $token, $pPart, $ePart] = $parts;
        $participationId = (int) ltrim($pPart, 'P');
        $evenementId = (int) ltrim($ePart, 'E');

        $participation = $this->participationRepo->findOneBy([
            'id' => $participationId,
            'qrCodeToken' => $token,
        ]);

        if (!$participation instanceof Participation) {
            return self::makeResult(false, 'QR code invalide — token non trouvé');
        }

        $evenement = $this->evenementRepo->find($evenementId);
        if (!$evenement instanceof Evenement) {
            return self::makeResult(false, 'Événement introuvable', $participation);
        }

        if ($participation->getStatut() === 'PRESENT') {
            return self::makeResult(
                false,
                'Participant déjà marqué présent',
                $participation,
                $evenement
            );
        }

        if ($participation->getStatut() === 'ANNULE') {
            return self::makeResult(
                false,
                'Inscription annulée',
                $participation,
                $evenement
            );
        }

        $participation->setStatut('PRESENT');
        $this->em->flush();

        $this->logger->info('Check-in OK: participation #{id}', ['id' => $participation->getId()]);

        return self::makeResult(true, 'Check-in réussi', $participation, $evenement);
    }

    /**
     * @return array<string, mixed>
     */
    public function validateToken(string $token): array
    {
        $participation = $this->participationRepo->findOneBy(['qrCodeToken' => $token]);

        if (!$participation instanceof Participation) {
            return self::makeResult(false, 'Token inconnu');
        }

        $participationId = $participation->getId();
        $evenementId = $participation->getEvenement()?->getId();
        if ($participationId === null || $evenementId === null) {
            return self::makeResult(false, 'Participation invalide');
        }

        $qrContent = $this->buildQRContent($token, $participationId, $evenementId);

        return $this->processQRScan($qrContent);
    }

    /**
     * @return array<string, int|float>
     */
    public function getCheckInStats(Evenement $evenement): array
    {
        /** @var list<Participation> $all */
        $all = array_values(array_filter(
            $this->participationRepo->findBy(['evenement' => $evenement]),
            static fn (mixed $participation): bool => $participation instanceof Participation
        ));

        $presents = count(array_filter($all, static fn (Participation $p): bool => $p->getStatut() === 'PRESENT'));
        $inscrits = count(array_filter($all, static fn (Participation $p): bool => $p->getStatut() !== 'ANNULE'));
        $enAttente = count(array_filter($all, static fn (Participation $p): bool => $p->getStatut() === 'EN_ATTENTE'));
        $taux = $inscrits > 0 ? round($presents / $inscrits * 100) : 0;

        return compact('presents', 'inscrits', 'enAttente', 'taux');
    }

    private function generateSvgData(string $content): string
    {
        $qrCode = new QrCode($content);
        $writer = new SvgWriter();

        return $writer->write($qrCode)->getString();
    }

    private function buildQRContent(string $token, int $participationId, int $evenementId): string
    {
        return "ARDHI_CHECKIN|{$token}|P{$participationId}|E{$evenementId}";
    }
}
