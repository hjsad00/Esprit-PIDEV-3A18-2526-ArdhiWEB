<?php

namespace App\Command;

use App\Repository\Evenement\ParticipationRepository;
use App\Service\EvenementParticipationMailer;
use App\Service\EvenementStatusSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:evenement:send-notifications',
    description: 'Send J-3/J-1 reminders and presence certificates for events.',
)]
class EvenementNotificationCommand extends Command
{
    public function __construct(
        private ParticipationRepository $participationRepository,
        private EvenementParticipationMailer $mailer,
        private EvenementStatusSyncService $statusSyncService,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->statusSyncService->syncAll();

        $j3Count = $this->sendReminders(3, $io);
        $j1Count = $this->sendReminders(1, $io);
        $certificateCount = $this->sendCertificates($io);

        $this->em->flush();

        $io->success(sprintf(
            'Notifications sent. J-3: %d, J-1: %d, certificates: %d',
            $j3Count,
            $j1Count,
            $certificateCount
        ));

        return Command::SUCCESS;
    }

    private function sendReminders(int $daysBefore, SymfonyStyle $io): int
    {
        $count = 0;
        $candidates = $this->participationRepository->findReminderCandidates($daysBefore);

        foreach ($candidates as $participation) {
            try {
                $this->mailer->sendEventReminder($participation, $daysBefore);
                if ($daysBefore === 3) {
                    $participation->setRappelJ3Envoye(true);
                } else {
                    $participation->setRappelJ1Envoye(true);
                }
                $count++;
            } catch (\Throwable $e) {
                $io->warning(sprintf(
                    'Reminder J-%d failed for participation #%d: %s',
                    $daysBefore,
                    $participation->getId(),
                    $e->getMessage()
                ));
            }
        }

        return $count;
    }

    private function sendCertificates(SymfonyStyle $io): int
    {
        $count = 0;
        $candidates = $this->participationRepository->findCertificateCandidates();

        foreach ($candidates as $participation) {
            try {
                $this->mailer->sendPresenceCertificateAndReviewInvite($participation);
                $participation->setAttestationEnvoyee(true);
                $count++;
            } catch (\Throwable $e) {
                $io->warning(sprintf(
                    'Certificate email failed for participation #%d: %s',
                    $participation->getId(),
                    $e->getMessage()
                ));
            }
        }

        return $count;
    }
}

