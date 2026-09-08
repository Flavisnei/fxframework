<?php

declare(strict_types=1);

namespace Fx\Framework\Console\Installation;

use InvalidArgumentException;
use RuntimeException;

/** Dados de instalação somente: não registra repositórios nem executa código. */
final class InstallCatalog
{
    private const MAX_BYTES = 262144;

    private function __construct(public readonly array $components, public readonly array $presets) {}

    public static function load(?string $source = null, ?string $sha256 = null): self
    {
        if ($source === null) {
            if ($sha256 !== null) { throw new InvalidArgumentException('Informe --catalog junto de --catalog-sha256.'); }
            $components = [];
            foreach (PackageCatalog::COMPONENTS as $name => $description) {
                $components[$name] = ['package' => PackageCatalog::package($name), 'description' => $description];
            }
            return new self($components, PackageCatalog::PRESETS);
        }
        if ($sha256 !== null && !preg_match('/\A[a-fA-F0-9]{64}\z/', $sha256)) {
            throw new InvalidArgumentException('SHA-256 do catalogo deve conter 64 caracteres hexadecimais.');
        }
        $remote = str_starts_with($source, 'https://');
        $options = [];
        if ($remote) {
            $url = parse_url($source);
            if ($sha256 === null || !is_array($url) || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || preg_match('/[\x00-\x20\x7f]/', $source)) {
                throw new InvalidArgumentException('Catalogo HTTPS exige SHA-256 e URL sem credenciais, fragmento ou espacos.');
            }
            $options = ['http' => ['timeout' => 15, 'follow_location' => 0, 'ignore_errors' => true, 'header' => "Accept: application/json\r\nUser-Agent: FX-Artisan"],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]];
        } elseif (str_contains($source, '://') || !is_file($source)) {
            throw new InvalidArgumentException('Catalogo deve ser arquivo JSON local ou URL HTTPS.');
        }
        $stream = @fopen($source, 'rb', false, stream_context_create($options));
        if ($stream === false) { throw new RuntimeException('Nao foi possivel ler o catalogo. Confira caminho, TLS e conectividade.'); }
        try {
            $metadata = stream_get_meta_data($stream);
            if ($remote) {
                $status = $metadata['wrapper_data'][0] ?? '';
                if (!preg_match('~^HTTP/\S+ 200(?: |$)~', $status)) {
                    throw new RuntimeException('Catalogo HTTPS deve responder 200 sem redirecionamento.');
                }
            }
            $json = stream_get_contents($stream, self::MAX_BYTES + 1);
            $metadata = stream_get_meta_data($stream);
            if ($json === false || ($metadata['timed_out'] ?? false) || strlen($json) > self::MAX_BYTES) {
                throw new RuntimeException('Catalogo excedeu 256 KiB ou o tempo de leitura.');
            }
        } finally { fclose($stream); }
        if ($sha256 !== null && !hash_equals(strtolower($sha256), hash('sha256', $json))) {
            throw new RuntimeException('SHA-256 do catalogo diverge. Nenhum pacote foi solicitado ao Composer.');
        }
        return self::fromJson($json);
    }

    private static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['schema'] ?? null) !== 1 || !is_array($data['components'] ?? null) || !$data['components'] || count($data['components']) > 200 || !is_array($data['presets'] ?? null) || array_diff(array_keys($data), ['schema', 'components', 'presets'])) {
            throw new InvalidArgumentException('Catalogo invalido: use schema 1, components (1 a 200) e presets.');
        }
        $packages = [];
        foreach ($data['components'] as $name => $entry) {
            if (!self::alias($name) || !is_array($entry) || array_diff(array_keys($entry), ['package', 'description']) || !is_string($entry['package'] ?? null) || !preg_match('~\A[a-z0-9]+(?:[_.-][a-z0-9]+)*/[a-z0-9]+(?:[_.-][a-z0-9]+)*\z~', $entry['package']) || strlen($entry['package']) > 150 || !is_string($entry['description'] ?? null) || strlen($entry['description']) > 300 || preg_match('/[\x00-\x1f\x7f<>]/', $entry['description']) || in_array($entry['package'], $packages, true)) {
                throw new InvalidArgumentException('Componente invalido ou pacote duplicado no catalogo.');
            }
            $packages[] = $entry['package'];
        }
        if (count($data['presets']) > 50) { throw new InvalidArgumentException('Catalogo aceita ate 50 presets.'); }
        foreach ($data['presets'] as $name => $members) {
            if (!self::alias($name) || !is_array($members) || !array_is_list($members) || !$members || count($members) > 200) {
                throw new InvalidArgumentException('Preset invalido no catalogo.');
            }
            foreach ($members as $member) {
                if (!is_string($member) || !isset($data['components'][$member])) { throw new InvalidArgumentException('Preset referencia componente ausente.'); }
            }
            if (count(array_unique($members)) !== count($members)) { throw new InvalidArgumentException('Preset repete componentes.'); }
        }
        // Um alias não pode mascarar o nome de pacote, pois aliases não contêm barras.
        return new self($data['components'], $data['presets']);
    }

    private static function alias(mixed $name): bool
    {
        return is_string($name) && (bool) preg_match('/\A[a-z][a-z0-9-]{0,63}\z/', $name);
    }

    public function package(string $name): string
    {
        if (isset($this->components[$name])) { return $this->components[$name]['package']; }
        foreach ($this->components as $entry) { if ($entry['package'] === $name) { return $name; } }
        throw new InvalidArgumentException('Componente desconhecido. Consulte module:list com o mesmo catalogo.');
    }

    public function preset(string $name): array
    {
        if (!isset($this->presets[$name])) { throw new InvalidArgumentException('Preset indisponivel no catalogo selecionado.'); }
        return array_map($this->package(...), $this->presets[$name]);
    }
}
