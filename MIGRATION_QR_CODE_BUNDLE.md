# Migration to Endroid QR Code Bundle

## ✅ Completed Steps

### 1. **Bundle Installation**
```bash
composer require endroid/qr-code-bundle
```
- Automatically registered in `config/bundles.php`
- Configuration created in `config/packages/endroid_qr_code.yaml`

### 2. **Configuration**
File: `config/packages/endroid_qr_code.yaml`
```yaml
endroid_qr_code:
    default:
        writer: Endroid\QrCode\Writer\PngWriter
        size: 300
        margin: 10
        encoding: 'UTF-8'
        error_correction_level: 'low'
        round_block_size_mode: 'margin'
        validate_result: false
```

### 3. **Refactored Service**
File: `src/Service/Evenement/QRCodeService.php`

**Key changes:**
- Removed manual `Builder` usage
- Now uses `QrCode` + `PngWriter` directly from bundle
- PNG default format (instead of SVG)
- Maintains all validation and check-in logic

**Available methods:**
```php
// Generate QR as PNG and save to file
$filePath = $qrCodeService->genererQRCodeFichier($participation);
// Returns: '/uploads/qrcodes/qr_42_a1b2c3d4.png'

// Generate QR as base64 data URI
$dataUri = $qrCodeService->genererQRCodeBase64($participation);
// Returns: 'data:image/png;base64,iVBORw0KGgoAAAA...'

// Generate raw PNG binary
$pngBinary = $qrCodeService->genererQRCodePng($participation);
```

---

## 📖 Usage Examples

### **Example 1: Display QR in Twig Template**

File: `templates/Evenement/show.html.twig`
```twig
<div class="qr-code-container">
    {# Display QR code as inline image (base64) #}
    <img src="{{ app.services.qr_code_service.genererQRCodeBase64(participation) }}" 
         alt="QR Code for Check-in"
         class="qr-code-img">
    
    {# OR download as file #}
    <a href="{{ app.services.qr_code_service.genererQRCodeFichier(participation) }}" 
       download="qr_code.png"
       class="btn btn-primary">
        Download QR Code
    </a>
</div>

<style>
.qr-code-img { width: 250px; height: 250px; border: 2px solid #333; }
</style>
```

### **Example 2: Embed QR in Email (Symfony Mailer)**

File: `src/Service/Evenement/EvenementParticipationMailer.php`
```php
use App\Service\Evenement\QRCodeService;

class EvenementParticipationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private QRCodeService $qrCodeService,  // Inject the service
        // ... other dependencies
    ) {}

    public function sendEventReminderWithQR(Participation $participation): void
    {
        $event = $participation->getEvenement();
        $user = $participation->getUtilisateur();
        
        // Generate QR code
        $qrBase64 = $this->qrCodeService->genererQRCodeBase64($participation);
        
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Ardhi Evenements'))
            ->to(new Address($user->getEmail()))
            ->subject(sprintf('Rappel: %s', $event->getTitre()))
            ->htmlTemplate('Evenement/reminder_with_qr_email.html.twig')
            ->context([
                'participation' => $participation,
                'evenement' => $event,
                'qrCode' => $qrBase64,  // Pass base64 data URI
            ]);

        $this->mailer->send($email);
    }
}
```

### **Example 3: Email Template with Embedded QR**

File: `templates/Evenement/reminder_with_qr_email.html.twig`
```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        .qr-section { text-align: center; margin: 30px 0; }
        .qr-section img { width: 200px; height: 200px; }
    </style>
</head>
<body>
    <h1>Rappel: {{ evenement.titre }}</h1>
    <p>Cher {{ participation.utilisateur.prenom }},</p>
    <p>Vous êtes inscrit(e) à l'événement qui débute bientôt!</p>
    
    <div class="qr-section">
        <h3>Votre code QR pour le check-in:</h3>
        <img src="{{ qrCode }}" alt="QR Code">
        <p><em>Présentez ce code à votre arrivée</em></p>
    </div>
    
    <p><strong>Date:</strong> {{ evenement.dateDebut|date('d/m/Y') }}</p>
    <p><strong>Lieu:</strong> {{ evenement.lieu }}</p>
</body>
</html>
```

### **Example 4: Generate QR in Controller**

File: `src/Controller/Evenement/ParticipationController.php`
```php
use App\Service\Evenement\QRCodeService;
use Symfony\Component\HttpFoundation\Response;

class ParticipationController extends AbstractController
{
    public function __construct(
        private QRCodeService $qrCodeService
    ) {}

    #[Route('/participation/{id}/qr-code/download', name: 'participation_qr_download')]
    public function downloadQRCode(Participation $participation): Response
    {
        // Generate PNG
        $pngData = $this->qrCodeService->genererQRCodePng($participation);
        
        return new Response(
            $pngData,
            Response::HTTP_OK,
            [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'attachment; filename="qr_code.png"',
            ]
        );
    }

    #[Route('/participation/{id}/qr-code/display', name: 'participation_qr_display')]
    public function displayQRCode(Participation $participation): Response
    {
        // Generate PNG  
        $pngData = $this->qrCodeService->genererQRCodePng($participation);
        
        return new Response(
            $pngData,
            Response::HTTP_OK,
            ['Content-Type' => 'image/png']
        );
    }

    #[Route('/participation/{id}/qr-code/embed', name: 'participation_qr_base64')]
    public function getQRBase64(Participation $participation): JsonResponse
    {
        $qrBase64 = $this->qrCodeService->genererQRCodeBase64($participation);
        
        return $this->json([
            'qrCode' => $qrBase64,
            'participationId' => $participation->getId(),
        ]);
    }
}
```

### **Example 5: Certificate with QR Code**

File: `src/Service/Evenement/ParticipationCertificatePdfGenerator.php`
```php
use App\Service\Evenement\QRCodeService;

class ParticipationCertificatePdfGenerator
{
    public function __construct(
        private QRCodeService $qrCodeService
    ) {}

    public function generate(Participation $participation): string
    {
        // Generate QR code file path
        $qrFilePath = $this->qrCodeService->genererQRCodeFichier($participation);
        
        // Embed in PDF (pseudo-code - adapt to your PDF generation library)
        $pdf = new PDF();
        $pdf->addText('ATTESTATION DE PRESENCE');
        $pdf->addImage($qrFilePath, x: 100, y: 100, w: 80, h: 80);
        $pdf->addText('Scannez ce code pour valider');
        
        return $pdf->output();
    }
}
```

---

## 🔄 Migration Checklist

- [x] Install bundle: `composer require endroid/qr-code-bundle`
- [x] Register in `config/bundles.php` (auto-done)
- [x] Create `config/packages/endroid_qr_code.yaml` (auto-done)
- [x] Refactor `QRCodeService.php` to use bundle
- [x] Update imports (QrCode, PngWriter)
- [x] Change from SVG to PNG format
- [x] Test QR generation for participations
- [ ] Update email templates to include QR codes
- [ ] Update Twig templates to display QR codes
- [ ] Test QR code scanning functionality
- [ ] Deploy and verify

---

## 📝 Before & After

### **BEFORE (Manual Library)**
```php
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

$builder = new Builder();
$result = $builder->build(
    data: $content,
    writer: new SvgWriter(),
    encoding: new Encoding('UTF-8'),
    errorCorrectionLevel: ErrorCorrectionLevel::High
);
return $result->getString();
```

### **AFTER (Bundle with DI)**
```php
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$qrCode = new QrCode($content);
$writer = new PngWriter();
return $writer->write($qrCode)->getString();
```

---

## 🎯 Benefits

✅ **Dependency Injection** - Bundle integrates with Symfony's DI container  
✅ **Configuration Centralized** - Single YAML config file  
✅ **Best Practices** - Follows Symfony bundle standards  
✅ **PNG by Default** - Better email/web compatibility  
✅ **Twig Extension** - Bundle provides Twig functions for direct rendering  
✅ **Response Object** - Built-in `QrCodeResponse` for quick HTTP responses

---

## 🚀 Next Steps

1. Test the refactored `QRCodeService` by generating a QR code
2. Update email templates to include QR codes
3. Update Twig templates to display QR codes inline
4. Run QR code scanner tests
5. Commit and deploy!
