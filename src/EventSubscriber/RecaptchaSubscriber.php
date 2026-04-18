<?php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\HttpFoundation\RequestStack;

class RecaptchaSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassport', -10],
        ];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // We only care about form_login submission
        if (!$request || !in_array($request->attributes->get('_route'), ['app_login', 'app_login_check'])) {
            return;
        }

        $token = $request->request->get('g-recaptcha-response');
        if (empty($token)) {
            throw new CustomUserMessageAuthenticationException('Veuillez activer la sécurité reCAPTCHA.');
        }

        $secret = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

        // Skip SSL certificate issues on local Windows environments
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $verifyResponse = @file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$token}", false, $context);
        $data = json_decode((string) $verifyResponse, true);

        if (empty($data['success']) || ($data['score'] ?? 0) < 0.5) {
            throw new CustomUserMessageAuthenticationException('Échec de validation reCAPTCHA. Requête suspecte (Robot).');
        }
    }
}
