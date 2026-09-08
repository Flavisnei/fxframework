<?php
declare(strict_types=1);
namespace Fx\Framework\Admin\Mail;

final class MailConfig
{
    public static function queue(array $admin): ResetMailQueue
    {
        if (!is_array($admin['mail'] ?? null) || !is_file($admin['database'])) { throw new \RuntimeException('Configure mail em config/admin.php e inicialize o banco Admin.'); }
        return new ResetMailQueue(new \PDO('sqlite:' . $admin['database']), $admin['mail']['key'] ?? '', $admin['mail']['admin_url'] ?? '');
    }
    public static function sender(array $admin): SmtpSender
    {
        return new SmtpSender($admin['mail']['dsn'] ?? '', $admin['mail']['from'] ?? '', $admin['mail']['local_test'] ?? false);
    }
}
