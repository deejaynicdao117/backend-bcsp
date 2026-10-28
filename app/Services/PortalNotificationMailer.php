<?php

namespace App\Services;

use App\Models\User;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

class PortalNotificationMailer
{
    public function send(User $user, string $subject, string $title, string $message): void
    {
        if (app()->environment('testing') || blank($user->email)) {
            return;
        }

        try {
            $mailer = new PHPMailer(true);
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
                'tls', 'starttls', 'smtp' => PHPMailer::ENCRYPTION_STARTTLS,
                'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
                default => '',
            };
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $mailer->setFrom((string) config('mail.from.address'), (string) config('mail.from.name'));
            $mailer->addAddress($user->email, $user->name);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = view('emails.portal-notification', [
                'name' => $user->name,
                'title' => $title,
                'message' => $message,
            ])->render();
            $mailer->AltBody = "Hello {$user->name},\n\n{$title}\n{$message}";
            $mailer->send();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}