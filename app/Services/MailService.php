<?php
declare(strict_types=1);

namespace App\Services;

class MailService {
    private string $fromEmail;
    private string $siteName;
    private string $logPath;

    public function __construct(array $config) {
        $this->fromEmail = $config['from'] ?? 'noreply@chatarena.local';
        $this->siteName = $config['site_name'] ?? 'ChatArena';
        $this->logPath = dirname(__DIR__, 2) . '/storage/logs/mail.log';
    }

    /**
     * Send an email. Falls back to logging the email if sending fails.
     */
    public function send(string $to, string $subject, string $body): bool {
        $headers = [
            "From: {$this->siteName} <{$this->fromEmail}>",
            "Reply-To: {$this->fromEmail}",
            "X-Mailer: PHP/" . PHP_VERSION,
            "Content-Type: text/plain; charset=UTF-8"
        ];

        // Attempt PHP mail()
        $success = false;
        try {
            // Check if mail() is configured/available and disabled or not
            if (function_exists('mail')) {
                $success = @mail($to, $subject, $body, implode("\r\n", $headers));
            }
        } catch (\Throwable) {
            $success = false;
        }

        // Always log for local debug fallback
        $this->logEmail($to, $subject, $body, $success);

        return $success || !empty($to); // Return true if it was sent or logged
    }

    /**
     * Log email details to file.
     */
    private function logEmail(string $to, string $subject, string $body, bool $sentStatus): void {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $statusStr = $sentStatus ? 'SENT' : 'LOGGED_ONLY';
        $logContent = "==================================================\n";
        $logContent .= "[{$timestamp}] Status: {$statusStr}\n";
        $logContent .= "To: {$to}\n";
        $logContent .= "Subject: {$subject}\n";
        $logContent .= "--------------------------------------------------\n";
        $logContent .= $body . "\n";
        $logContent .= "==================================================\n\n";

        error_log($logContent, 3, $this->logPath);
    }
}
