<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../bootstrap/init.php';

use App\Helpers\IpGeolocation;
use App\FileStore\MessageBuffer;
use App\Database\Connection;

echo "=== ChatArena Country Flags & File Attachments Tests ===\n\n";

// 1. IP Geolocation Tests
echo "1. Testing IP Geolocation...\n";
$localFlag = IpGeolocation::getFlagEmoji('127.0.0.1');
echo "   Localhost (127.0.0.1) flag: {$localFlag} (Expected: 🇺🇸)\n";

$usFlag = IpGeolocation::getFlagEmoji('8.8.8.8');
echo "   US IP (8.8.8.8) flag: {$usFlag} (Expected: 🇺🇸)\n";

if ($localFlag === '🇺🇸' && $usFlag === '🇺🇸') {
    echo "   [PASS] IP Geolocation resolved correctly.\n";
} else {
    echo "   [FAIL] IP Geolocation mismatch.\n";
}

// 2. Message Attachment Database Insertion Test
echo "\n2. Testing Message Attachment Persistence...\n";
$db = $container->get(Connection::class);
$messageBuffer = $container->get(MessageBuffer::class);

$testRoomId = 'test_room_' . bin2hex(random_bytes(6));
$testSenderId = 'test_sender_' . bin2hex(random_bytes(6));
$testContent = "Check out this attachment!";
$testAttachmentPath = "test_file_" . bin2hex(random_bytes(6)) . ".png";
$testAttachmentType = "image";

try {
    // We insert a dummy room first to avoid foreign key or lookup constraint if any
    $pdo = $db->getPdo();
    $pdo->exec("INSERT INTO rooms (room_id, user_a_id, user_b_id, created_at, status) VALUES ('{$testRoomId}', '{$testSenderId}', 'stranger_123', NOW(), 'active')");
    
    // Call pushMessage with attachment details
    $msg = $messageBuffer->pushMessage($testRoomId, $testSenderId, $testContent, $testAttachmentPath, $testAttachmentType);
    
    echo "   Pushed message ID: {$msg['id']}\n";
    echo "   Attachment Path: {$msg['attachment_path']}\n";
    echo "   Attachment Type: {$msg['attachment_type']}\n";

    // Query DB to verify persistence
    $stmt = $pdo->prepare("SELECT content, attachment_path, attachment_type FROM messages WHERE room_id = ? AND sender_id = ?");
    $stmt->execute([$testRoomId, $testSenderId]);
    $row = $stmt->fetch();

    if ($row && $row['attachment_path'] === $testAttachmentPath && $row['attachment_type'] === $testAttachmentType) {
        echo "   [PASS] Message and attachment details successfully saved to MySQL database.\n";
    } else {
        echo "   [FAIL] Database retrieval failed or mismatch in saved columns.\n";
    }

    // Clean up test data
    $pdo->exec("DELETE FROM messages WHERE room_id = '{$testRoomId}'");
    $pdo->exec("DELETE FROM rooms WHERE room_id = '{$testRoomId}'");
    echo "   [INFO] Cleaned up temporary test database records.\n";
} catch (\Exception $e) {
    echo "   [FAIL] Test encountered exception: " . $e->getMessage() . "\n";
} finally {
    $db->disconnect();
}

echo "\n=== All Extensions Tests Complete ===\n";
