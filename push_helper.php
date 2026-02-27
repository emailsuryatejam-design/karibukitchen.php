<?php
/**
 * Push Notification Helper
 * Sends web push notifications to subscribed users
 */
require_once 'config.php';

// Try to load composer autoload
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// VAPID keys
define('VAPID_PUBLIC_KEY', 'BBLfpB1Dh8FKBNnMdyh-LYAY7zriHOzBpzqW5hjexpN95DcsPlw7RPuNf-8vhlZRH2fg5fWywPXlyU4poPpTchU');
define('VAPID_PRIVATE_KEY', 'TLHPDRCLDxrbplR9Z6VrJhk8-wtnj5MRsukAWMiDGA8');
define('VAPID_SUBJECT', 'mailto:admin@karibukitchen.com');

/**
 * Send push notification to a specific user
 */
function sendPushToUser(int $userId, string $title, string $body, string $url = '/app.php') {
    $db = getDB();

    // Store notification in DB
    $stmt = $db->prepare("INSERT INTO pilot_notifications (user_id, title, body, url) VALUES (?,?,?,?)");
    $stmt->execute([$userId, $title, $body, $url]);

    // Check if web-push library is available
    if (!class_exists('Minishlink\WebPush\WebPush')) {
        return; // Library not installed, notification stored in DB for polling
    }

    // Get all push subscriptions for this user
    $subs = $db->prepare("SELECT * FROM pilot_push_subscriptions WHERE user_id = ?");
    $subs->execute([$userId]);
    $subscriptions = $subs->fetchAll();

    if (empty($subscriptions)) return;

    try {
        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ];

        $webPush = new \Minishlink\WebPush\WebPush($auth);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'icon' => '/icons/icon-192.png',
            'badge' => '/icons/icon-72.png',
            'timestamp' => time()
        ]);

        foreach ($subscriptions as $sub) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['p256dh'],
                'authToken' => $sub['auth'],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        // Send all queued notifications
        $results = $webPush->flush();

        // Clean up expired subscriptions
        foreach ($results as $result) {
            if ($result->isSubscriptionExpired()) {
                $endpoint = $result->getRequest()->getUri()->__toString();
                $db->prepare("DELETE FROM pilot_push_subscriptions WHERE endpoint = ?")->execute([$endpoint]);
            }
        }
    } catch (Exception $e) {
        error_log('Push notification error: ' . $e->getMessage());
    }
}

/**
 * Send push notification to all users with a specific role in a kitchen
 */
function sendPushToRole(string $role, int $kitchenId, string $title, string $body, string $url = '/app.php') {
    $db = getDB();
    $users = $db->prepare("SELECT id FROM pilot_users WHERE role = ? AND kitchen_id = ? AND is_active = 1");
    $users->execute([$role, $kitchenId]);

    foreach ($users->fetchAll() as $user) {
        sendPushToUser($user['id'], $title, $body, $url);
    }
}
