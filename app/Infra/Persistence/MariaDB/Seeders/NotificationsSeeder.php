<?php
declare(strict_types=1);

use App\Infra\Persistence\MariaDB\Seeder;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Notifications Seeder
 */
return new class extends Seeder
{
    public function run(Database $db): void
    {
        // Get admin user ID
        $stmt = $db->execute("SELECT id FROM users WHERE email = 'admin@ecigdis.co.nz'");
        $admin = $stmt->fetch();
        
        if (!$admin) {
            echo "Admin user not found, skipping notification seeding\n";
            return;
        }
        
        $notifications = [
            [
                'kind' => 'system',
                'title' => 'Welcome to CIS',
                'body' => 'Welcome to the Central Information System. Your admin account has been created successfully.',
                'severity' => 'success',
                'user_id' => $admin['id'],
                'meta_json' => json_encode(['icon' => 'fas fa-rocket'])
            ],
            [
                'kind' => 'security',
                'title' => 'Change Default Password',
                'body' => 'Please change the default administrator password for security reasons.',
                'severity' => 'warning',
                'user_id' => $admin['id'],
                'meta_json' => json_encode(['icon' => 'fas fa-shield-alt', 'action_url' => '/admin/profile'])
            ],
            [
                'kind' => 'setup',
                'title' => 'Configure Integrations',
                'body' => 'Set up API keys and integrations for Vend, Xero, Deputy, and OpenAI to enable full functionality.',
                'severity' => 'info',
                'user_id' => $admin['id'],
                'meta_json' => json_encode(['icon' => 'fas fa-cogs', 'action_url' => '/admin/config'])
            ],
            [
                'kind' => 'feature',
                'title' => 'Session Replay Available',
                'body' => 'Privacy-compliant session replay is now enabled. Configure consent settings as needed.',
                'severity' => 'info',
                'user_id' => $admin['id'],
                'meta_json' => json_encode(['icon' => 'fas fa-play', 'action_url' => '/admin/replay'])
            ],
            [
                'kind' => 'system',
                'title' => 'System Ready',
                'body' => 'All core CIS modules have been initialized. Check the dashboard for system status.',
                'severity' => 'success',
                'user_id' => null, // System-wide notification
                'meta_json' => json_encode(['icon' => 'fas fa-check-circle'])
            ],
        ];
        
        foreach ($notifications as $notification) {
            $db->execute("
                INSERT INTO notifications (
                    kind, title, body, severity, status, user_id, meta_json
                ) VALUES (?, ?, ?, ?, 'sent', ?, ?)
            ", [
                $notification['kind'],
                $notification['title'],
                $notification['body'],
                $notification['severity'],
                $notification['user_id'],
                $notification['meta_json']
            ]);
        }
        
        echo "Sample notifications created\n";
    }
};
