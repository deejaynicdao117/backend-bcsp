<?php

namespace App\Services;

use App\Models\User;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

class VerificationCodeMailer
{
    public function send(User $user, string $code): void
    {
        $mailer = new PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host = (string) config('mail.mailers.smtp.host');
            $mailer->Port = (int) config('mail.mailers.smtp.port');
            $mailer->SMTPAuth = filled(config('mail.mailers.smtp.username'));

            if ($mailer->SMTPAuth) {
                $mailer->Username = (string) config('mail.mailers.smtp.username');
                $mailer->Password = (string) config('mail.mailers.smtp.password');
            }

            $encryption = config('mail.mailers.smtp.encryption')
                ?: config('mail.mailers.smtp.scheme');

            $mailer->SMTPSecure = match ($encryption) {
                'tls', 'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
                'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
                'smtp' => PHPMailer::ENCRYPTION_STARTTLS,
                default => '',
            };
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $mailer->setFrom(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            );
            $mailer->addAddress($user->email, $user->name);
            $mailer->isHTML(true);
            $mailer->Subject = 'Your barangay portal verification code';
            $mailer->Body = view('emails.verification-code', [
                'name' => $user->name,
                'code' => $code,
            ])->render();
            $mailer->AltBody = "Hello {$user->name}, your verification code is {$code}. It expires in 10 minutes.";
            $mailer->send();
        } catch (Exception $exception) {
            report($exception);

            throw new RuntimeException('Unable to send the verification email. Check the SMTP settings and try again.');
        }
    }
}
