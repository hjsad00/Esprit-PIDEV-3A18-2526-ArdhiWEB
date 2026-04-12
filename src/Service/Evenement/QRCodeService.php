<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Participation;
use App\Entity\Evenement\Evenement;
use App\Repository\Evenement\ParticipationRepository;
use App\Repository\Evenement\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Psr\Log\LoggerInterface;

/**
 * Generates QR codes for event check-in and validates scanned tokens.
 * Ported from the Java QRCodeService.
 *
 * QR content format: ARDHI_CHECKIN|<token>|P<participationId>|E<evenementId>
 *
 * Requires: endroid/qr-code  (composer require endroid/qr-code)
 */
class QRCodeService
{
    private const QR_SIZE = 300;

    public function __construct(
        private EntityManagerInterface  $em,
        private ParticipationRepository $participationRepo,
        private EvenementRepository     $evenementRepo,
        private LoggerInterface         $logger,
        private string                  $projectDir
    ) {}

    // ── Token helpers ────────────────────────────────────────────────────────

    /**
     * Returns the existing token for a participation, or creates + persists one.
     */
    public function getOrCreateToken(Participation $participation): string
    {
        if ($participation->getQrCodeToken()) {
            return $participation->getQrCodeToken();
        }

        $token = bin2hex(random_bytes(16)); // 32-char hex token
        $participation->setQrCodeToken($token);
        $this->em->flush();

        return $token;
    }

    // ── QR Generation ────────────────────────────────────────────────────────

    /**
     * Returns the QR code as a raw SVG string.
     */
    public function genererQRCodeSvg(Participation $participation): string
    {
        $token   = $this->getOrCreateToken($participation);
        $content = $this->buildQRContent($token, $participation->getId(), $participation->getEvenement()->getId());

        $builder = new Builder();
        $result = $builder->build(
            data: $content,
            writer: new SvgWriter(),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: self::QR_SIZE,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        return $result->getString();
    }

    /**
     * Returns the QR code as a base64-encoded data URI ready to embed in <img src="...">.
     * e.g.  data:image/svg+xml;base64,PHN2Zy...
     */
    public function genererQRCodeBase64(Participation $participation): string
    {
        $svg = $this->genererQRCodeSvg($participation);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Saves the QR code SVG to public/uploads/qrcodes/ and returns the web path.
     * e.g.  /uploads/qrcodes/qr_42_a1b2c3d4.svg
     */
    public function genererQRCodeFichier(Participation $participation): string
    {
        $uploadsDir = $this->projectDir . '/public/uploads/qrcodes';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $token    = $this->getOrCreateToken($participation);
        $filename = 'qr_' . $participation->getId() . '_' . substr($token, 0, 8) . '.svg';
        $filepath = $uploadsDir . '/' . $filename;

        file_put_contents($filepath, $this->genererQRCodeSvg($participation));

        $this->logger->info('QR code saved: {file}', ['file' => $filename]);

        return '/uploads/qrcodes/' . $filename;
    }

    // ── Validation / Check-in ────────────────────────────────────────────────

    /**
     * Result object returned by processQRScan() and validateToken().
     */
    public static function makeResult(
        bool          $success,
        string        $message,
        ?Participation $participation = null,
        ?Evenement    $evenement     = null
    ): array {
        return [
            'success'       => $success,
            'message'       => $message,
            'participation' => $participation,
            'evenement'     => $evenement,
        ];
    }

    /**
     * Parse and validate a raw QR code string, then mark the participant PRESENT.
     *
     * Expected format: ARDHI_CHECKIN|<token>|P<participationId>|E<evenementId>
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
        $evenementId     = (int) ltrim($ePart, 'E');

        // Verify token matches the participation in the DB
        $participation = $this->participationRepo->findOneBy([
            'id'           => $participationId,
            'qrCodeToken'  => $token,
        ]);

        if (!$participation) {
            return self::makeResult(false, 'QR code invalide — token non trouvé');
        }

        $evenement = $this->evenementRepo->find($evenementId);
        if (!$evenement) {
            return self::makeResult(false, 'Événement introuvable', $participation);
        }

        // Guard against duplicate check-ins
        if ($participation->getStatut() === 'PRESENT') {
            return self::makeResult(
                false,
                '⚠️ ' . $participation->getNomComplet() . ' est déjà marqué(e) présent(e) !',
                $participation,
                $evenement
            );
        }

        if ($participation->getStatut() === 'ANNULE') {
            return self::makeResult(
                false,
                '❌ L\'inscription de ' . $participation->getNomComplet() . ' est annulée',
                $participation,
                $evenement
            );
        }

        // Mark as present
        $participation->setStatut('PRESENT');
        $this->em->flush();

        $this->logger->info('Check-in OK: participation #{id}', ['id' => $participation->getId()]);

        return self::makeResult(
            true,
            '✅ ' . $participation->getNomComplet() . ' — Check-in réussi !',
            $participation,
            $evenement
        );
    }

    /**
     * Validate a raw token string (mobile scanner compatibility — same as Java validateToken).
     */
    public function validateToken(string $token): array
    {
        $participation = $this->participationRepo->findOneBy(['qrCodeToken' => $token]);

        if (!$participation) {
            return self::makeResult(false, 'Token inconnu');
        }

        $qrContent = $this->buildQRContent(
            $token,
            $participation->getId(),
            $participation->getEvenement()->getId()
        );

        return $this->processQRScan($qrContent);
    }

    // ── Stats helper ─────────────────────────────────────────────────────────

    /**
     * Returns [ presents, inscrits, enAttente, taux ] for a given event.
     */
    public function getCheckInStats(Evenement $evenement): array
    {
        $all       = $this->participationRepo->findBy(['evenement' => $evenement]);
        $presents  = count(array_filter($all, fn($p) => $p->getStatut() === 'PRESENT'));
        $inscrits  = count(array_filter($all, fn($p) => $p->getStatut() !== 'ANNULE'));
        $enAttente = count(array_filter($all, fn($p) => $p->getStatut() === 'EN_ATTENTE'));
        $taux      = $inscrits > 0 ? round($presents / $inscrits * 100) : 0;

        return compact('presents', 'inscrits', 'enAttente', 'taux');
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function buildQRContent(string $token, int $participationId, int $evenementId): string
    {
        return "ARDHI_CHECKIN|{$token}|P{$participationId}|E{$evenementId}";
    }
}
