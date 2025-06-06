<?php

namespace CronManager;

class Mailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        $log = sprintf(
            "[%s] To: %s | Subject: %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $body
        );

        file_put_contents(__DIR__ . '/../data/mail.log', $log, FILE_APPEND);
    }
}
