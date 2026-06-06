<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\MatchService;
use App\Helpers\Sanitizer;

class MatchController {
    public function __construct(private readonly MatchService $matchService) {}

    /**
     * Join the matchmaking waiting queue with optional interest tags.
     */
    public function join(): string {
        $userId = $_SESSION['user_id'] ?? '';
        if ($userId === '') {
            http_response_code(400);
            return json_encode(['error' => 'Active session required']);
        }

        $rawInterests = $_POST['interests'] ?? '';
        $interests = [];
        
        if (is_string($rawInterests) && trim($rawInterests) !== '') {
            // Split comma-separated tags, sanitizing each one
            $parts = explode(',', $rawInterests);
            foreach ($parts as $part) {
                $clean = Sanitizer::clean($part);
                if ($clean !== '') {
                    $interests[] = $clean;
                }
            }
        }

        $result = $this->matchService->joinQueue($userId, $interests);
        
        header('Content-Type: application/json');
        return json_encode($result);
    }

    /**
     * Leave the matchmaking queue.
     */
    public function leave(): string {
        $userId = $_SESSION['user_id'] ?? '';
        if ($userId === '') {
            http_response_code(400);
            return json_encode(['error' => 'Active session required']);
        }

        $this->matchService->leaveQueue($userId);

        header('Content-Type: application/json');
        return json_encode(['status' => 'idle']);
    }
}
