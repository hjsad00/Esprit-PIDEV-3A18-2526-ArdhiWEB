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

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $secret,
            'response' => $token
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $verifyResponse = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        $data = json_decode((string) $verifyResponse, true);

        if (empty($data['success']) || (isset($data['score']) && $data['score'] < 0.5)) {
            throw new CustomUserMessageAuthenticationException('Échec reCAPTCHA. Raw: ' . substr((string) $verifyResponse, 0, 100) . ' cURL Error: ' . $error);
        }
    }
}
