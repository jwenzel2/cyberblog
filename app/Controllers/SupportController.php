<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\EmailService;
use App\Services\LoginNotificationService;

final class SupportController
{
    public function showContact(): void
    {
        View::render('public/support-contact', [
            'title' => 'Contact Admin',
            'flash' => Session::flash('status'),
            'error' => Session::flash('error'),
            'email' => trim((string) ($_GET['email'] ?? '')),
            'smtpEnabled' => (new EmailService())->enabled(),
        ]);
    }

    public function submitContact(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::abort(419, 'Invalid CSRF token.');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($email === '' || $message === '') {
            Session::flash('error', 'Email and message are required.');
            Response::redirect('/support/contact?email=' . urlencode($email));
        }

        $mailer = new EmailService();
        if (!$mailer->enabled()) {
            Session::flash('error', 'Support email is currently unavailable because SMTP notifications are disabled.');
            Response::redirect('/support/contact?email=' . urlencode($email));
        }

        (new LoginNotificationService())->sendSupportRequest($email, $message);
        Session::flash('status', 'Your message was sent to the administrators.');
        Response::redirect('/support/contact?email=' . urlencode($email));
    }
}
