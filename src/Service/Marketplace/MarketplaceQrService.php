<?php

declare(strict_types=1);

namespace App\Service\Marketplace;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MarketplaceQrService
{
    private const QR_PUBLIC_HOST_ENV = 'MARKETPLACE_QR_PUBLIC_HOST';
    private const PUBLIC_LINK_TTL_SECONDS = 604800;
    private const SIGNATURE_BYTES = 16;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $appSecret,
    ) {
    }

    public function generateInvoiceQrDataUri(int $commandeId, Request $request): string
    {
        $publicInvoiceUrl = $this->buildSignedPublicInvoiceUrl($commandeId, $request);

        $result = (new Builder(
            writer: new SvgWriter(),
            data: $publicInvoiceUrl,
            size: 320,
            margin: 16,
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        return $result->getDataUri();
    }

    private function buildSignedPublicInvoiceUrl(int $commandeId, Request $request): string
    {
        $expiresAt = time() + self::PUBLIC_LINK_TTL_SECONDS;
        $signature = $this->createShortSignature($commandeId, $expiresAt);

        $invoiceUrl = $this->urlGenerator->generate(
            'app_marketplace_commande_pdf_public',
            [
                'id' => $commandeId,
                'expires' => $expiresAt,
                'signature' => $signature,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->replaceLocalhostWithLanIp($invoiceUrl, $request);
    }

    public function isValidPublicInvoiceSignature(int $commandeId, int $expires, string $signature): bool
    {
        if ($expires <= 0 || $expires < time()) {
            return false;
        }

        return hash_equals($this->createShortSignature($commandeId, $expires), $signature);
    }

    private function createShortSignature(int $commandeId, int $expires): string
    {
        $payload = sprintf('marketplace-invoice|%d|%d', $commandeId, $expires);
        $hmac = hash_hmac('sha256', $payload, $this->appSecret, true);
        $truncated = substr($hmac, 0, self::SIGNATURE_BYTES);

        return rtrim(strtr(base64_encode($truncated), '+/', '-_'), '=');
    }

    private function replaceLocalhostWithLanIp(string $url, Request $request): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return $url;
        }

        $host = strtolower((string) $parts['host']);
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return $url;
        }

        $publicHost = $this->resolvePublicHost($request);
        if ($publicHost === null) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'http';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return sprintf('%s://%s%s%s%s%s', $scheme, $publicHost, $port, $path, $query, $fragment);
    }

    private function resolvePublicHost(Request $request): ?string
    {
        $configuredHost = $this->getConfiguredPublicHost();
        if ($configuredHost !== null) {
            return $configuredHost;
        }

        $serverAddr = $request->server->get('SERVER_ADDR');
        if (is_string($serverAddr) && $this->isPrivateIpv4($serverAddr)) {
            return $serverAddr;
        }

        $hostname = gethostname();
        if (is_string($hostname) && $hostname !== '') {
            $ips = @gethostbynamel($hostname);
            if (is_array($ips)) {
                $preferred = null;
                foreach ($ips as $ip) {
                    if ($this->isPrivateIpv4($ip)) {
                        if (!preg_match('/\.1$/', $ip)) {
                            return $ip;
                        }

                        $preferred ??= $ip;
                    }
                }

                if ($preferred !== null) {
                    return $preferred;
                }
            }
        }

        return null;
    }

    private function getConfiguredPublicHost(): ?string
    {
        $raw = $_SERVER[self::QR_PUBLIC_HOST_ENV] ?? $_ENV[self::QR_PUBLIC_HOST_ENV] ?? null;
        if (!is_string($raw)) {
            return null;
        }

        $host = trim($raw);
        if ($host === '') {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $host;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return $host;
        }

        return null;
    }

    private function isPrivateIpv4(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (str_starts_with($ip, '10.')) {
            return true;
        }

        if (str_starts_with($ip, '192.168.')) {
            return true;
        }

        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip) === 1) {
            return true;
        }

        return false;
    }
}
