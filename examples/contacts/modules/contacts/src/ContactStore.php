<?php
declare(strict_types=1);
namespace Example\Contacts;

use Fx\Framework\Admin\FieldErrors;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ContactStore
{
    public function __construct(private readonly \PDO $db)
    {
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $db->exec('PRAGMA busy_timeout = 5000');
    }

    /** Instalacao explicita; nenhuma criacao de tabelas durante requisicoes. */
    public function install(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS contacts (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, phone TEXT NOT NULL, notes TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1)');
    }

    private function query(string $sql, array $values = []): \PDOStatement
    {
        $statement = $this->db->prepare($sql); $statement->execute($values); return $statement;
    }

    public function listing(array $query): array
    {
        $page = $query['page'] ?? '1'; $search = $query['q'] ?? '';
        if ((!is_int($page) && !is_string($page)) || !ctype_digit((string) $page) || (int) $page < 1 || (int) $page > 100000 || !is_string($search) || strlen($search) > 100) { throw new FieldErrors(['q' => 'Use uma pesquisa de ate 100 bytes e pagina entre 1 e 100000.']); }
        $page = (int) $page;
        $filter = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
        $where = " WHERE name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!'";
        $total = (int) $this->query('SELECT COUNT(*) FROM contacts' . $where, [$filter, $filter])->fetchColumn();
        $rows = $this->query('SELECT id,name,email,phone,version FROM contacts' . $where . ' ORDER BY id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20), [$filter, $filter])->fetchAll();
        return ['data' => $rows, 'page' => $page, 'per_page' => 20, 'total' => $total];
    }

    public function get(int $id): array
    {
        $row = $this->query('SELECT * FROM contacts WHERE id=?', [$id])->fetch();
        if (!$row) { throw new HttpException(404, 'Contato nao encontrado. Atualize a lista.'); }
        return $row;
    }

    public static function positiveId(mixed $value, string $field = 'id'): int
    {
        if (!is_int($value) || $value < 1) { throw new FieldErrors([$field => 'Informe um inteiro positivo.']); }
        return $value;
    }

    public function save(?int $id, array $data): array
    {
        $errors = []; $values = [];
        foreach (['name' => [2,120], 'email' => [3,254], 'phone' => [0,40], 'notes' => [0,2000]] as $field => [$min,$max]) {
            $value = $data[$field] ?? '';
            if (!is_string($value) || str_contains($value, "\0") || strlen(trim($value)) < $min || strlen(trim($value)) > $max) { $errors[$field] = "Informe entre $min e $max bytes de texto."; }
            else { $values[$field] = trim($value); }
        }
        if (isset($values['email'])) { $values['email'] = strtolower($values['email']); if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Informe um email valido.'; } }
        if ($errors) { throw new FieldErrors($errors); }
        $version = $id === null ? null : self::positiveId($data['version'] ?? null, 'version');
        try {
            if ($id === null) {
                $this->query('INSERT INTO contacts(name,email,phone,notes) VALUES (?,?,?,?)', array_values($values));
                return $this->get((int) $this->db->lastInsertId());
            }
            $updated = $this->query('UPDATE contacts SET name=?,email=?,phone=?,notes=?,version=version+1 WHERE id=? AND version=?', [...array_values($values), $id, $version]);
            if (!$updated->rowCount()) { $this->conflict($id); }
            return $this->get($id);
        } catch (\PDOException $error) {
            if ($error->getCode() === '23000') { throw new FieldErrors(['email' => 'Este email ja esta cadastrado.'], 409); }
            throw $error;
        }
    }

    public function delete(int $id, int $version): array
    {
        if (!$this->query('DELETE FROM contacts WHERE id=? AND version=?', [$id, $version])->rowCount()) { $this->conflict($id); }
        return ['message' => 'Contato excluido.'];
    }

    private function conflict(int $id): never
    {
        $this->get($id);
        throw new HttpException(409, 'Outra pessoa alterou este contato. Reabra o cadastro antes de tentar novamente.');
    }
}
