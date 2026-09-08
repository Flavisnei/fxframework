<?php
declare(strict_types=1);
namespace Fx\Framework\Admin;

use Fx\Framework\Auth\Authenticatable;

final class AdminUser implements Authenticatable
{
    public function __construct(public readonly array $data) {}
    public function getAuthIdentifier(): int { return (int) $this->data['id']; }
    public function publicData(): array
    {
        return array_intersect_key($this->data, array_flip(['id', 'name', 'email', 'role_id', 'active']));
    }
}
