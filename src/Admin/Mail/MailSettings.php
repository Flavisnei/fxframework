<?php
declare(strict_types=1);
namespace Fx\Framework\Admin\Mail;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Configuracao opt-in do Admin; nao exporta segredos para o ambiente global do PHP. */
final class MailSettings
{
    private const FIELDS = ['enabled'=>'FX_MAIL_ENABLED','host'=>'FX_MAIL_HOST','port'=>'FX_MAIL_PORT','username'=>'FX_MAIL_USERNAME','password'=>'FX_MAIL_PASSWORD','from'=>'FX_MAIL_FROM','from_name'=>'FX_MAIL_FROM_NAME','admin_url'=>'FX_ADMIN_URL','key'=>'FX_MAIL_KEY'];
    private string $path;
    public function __construct(string $root, private readonly ?array $legacy = null, private readonly ?\Closure $prepare = null)
    {
        $this->path = rtrim($root,'/\\').'/.env';
    }
    private function source(): string
    {
        if (is_link($this->path)) { throw new \RuntimeException('O arquivo .env nao pode ser um link.'); }
        if (!is_file($this->path)) { return ''; }
        $text=file_get_contents($this->path);
        if ($text===false || strlen($text)>262144) { throw new \RuntimeException('Nao foi possivel ler .env (limite 256 KiB).'); }
        return $text;
    }
    private function environment(string $text): array
    {
        try { $values=(new Dotenv())->parse($text,$this->path); }
        catch (\Throwable) { throw new \RuntimeException('Sintaxe invalida no .env; revise o arquivo sem divulgar seu conteudo.'); }
        foreach ([...array_values(self::FIELDS),'FX_MAIL_DSN'] as $key) {
            $value=getenv($key); if ($value!==false) { $values[$key]=$value; }
        }
        return $values;
    }
    private function values(string $text): array
    {
        $env=$this->environment($text);
        $dsn=$env['FX_MAIL_DSN'] ?? $this->legacy['dsn'] ?? '';
        $parts=parse_url($dsn) ?: [];
        $values=['enabled'=>$this->legacy!==null ? '1':'0','host'=>$parts['host']??'','port'=>(string)($parts['port']??465),'username'=>rawurldecode($parts['user']??''),'password'=>rawurldecode($parts['pass']??''),'from'=>$this->legacy['from']??'','from_name'=>$this->legacy['from_name']??'FX Admin','admin_url'=>$this->legacy['admin_url']??'','key'=>$this->legacy['key']??''];
        foreach(self::FIELDS as $field=>$key) { if(array_key_exists($key,$env))$values[$field]=$env[$key]; }
        return $values;
    }
    public function locked(): bool
    {
        foreach([...array_values(self::FIELDS),'FX_MAIL_DSN'] as $key) { if(getenv($key)!==false)return true; }
        return false;
    }
    public function snapshot(): array
    {
        $text=$this->source();$values=$this->values($text);
        $values['password_set']=$values['password']!=='';
        unset($values['password'],$values['key']);
        $values['enabled']=$values['enabled']==='1';
        $values['revision']=hash('sha256',$text);$values['locked']=$this->locked();
        return $values;
    }
    /** null preserva a configuracao legada; enabled=0 desabilita explicitamente. */
    public function override(): bool { return array_key_exists('FX_MAIL_ENABLED',$this->environment($this->source())); }
    public function mail(): ?array
    {
        $v=$this->values($this->source());
        if($v['enabled']!=='1')return null;
        return self::mailValues($v);
    }
    private static function mailValues(array $v): array
    {
        return ['dsn'=>'smtps://'.rawurlencode($v['username']).':'.rawurlencode($v['password']).'@'.$v['host'].':'.$v['port'],'from'=>$v['from'],'from_name'=>$v['from_name'],'admin_url'=>$v['admin_url'],'key'=>$v['key']];
    }
    /** Preserva variaveis alheias e recusa formulario obsoleto. Uma senha vazia mantem a atual. */
    public function save(array $input): array
    {
        if($this->locked())throw new HttpException(409,'Configuracao definida pelo ambiente do servidor; altere-a no servidor.');
        $lock=fopen($this->path.'.lock','c');
        if(!$lock || !flock($lock,LOCK_EX))throw new \RuntimeException('Nao foi possivel bloquear .env.');
        $temp=null;
        try {
            $text=$this->source();
            if(($input['revision']??null)!==hash('sha256',$text))throw new HttpException(409,'A configuracao mudou. Reabra a tela antes de salvar.');
            $v=$this->values($text);
            if(!is_bool($input['enabled']??null))throw new HttpException(422,'Informe se o email esta habilitado.');
            foreach(['host','port','username','password','from','from_name','admin_url'] as $field) {
                $value=$input[$field]??null;
                if(!is_string($value) || strlen($value)>2048 || preg_match('/[\x00-\x1f\x7f]/',$value))throw new HttpException(422,'Campo de email invalido: '.$field);
                if($field!=='password' || $value!=='')$v[$field]=$field==='password'?$value:trim($value);
            }
            $v['enabled']=$input['enabled']?'1':'0';
            if(!preg_match('/\A[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?\z/',$v['host']) || !ctype_digit($v['port']) || (int)$v['port']<1 || (int)$v['port']>65535)throw new HttpException(422,'Informe servidor SMTP e porta validos. Criptografia: SSL/TLS implicito.');
            if($v['username']==='' || $v['password']==='' || !filter_var($v['from'],FILTER_VALIDATE_EMAIL))throw new HttpException(422,'Informe autenticacao e remetente validos.');
            $url=parse_url($v['admin_url']);
            if(!$url || !filter_var($v['admin_url'],FILTER_VALIDATE_URL) || ($url['scheme']??'')!=='https' || !\Fx\Framework\Admin\AdminUrl::recoveryPath($url['path']??'') || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']))throw new HttpException(422,'Use endereco HTTPS do painel terminado em /admin.');
            if($v['key']==='')$v['key']=bin2hex(random_bytes(32));
            if(!preg_match('/\A[a-fA-F0-9]{64}\z/',$v['key']))throw new HttpException(422,'Chave da fila invalida; corrija FX_MAIL_KEY no ambiente.');
            if($this->prepare!==null)($this->prepare)(self::mailValues($v));
            $managed=[...array_values(self::FIELDS),'FX_MAIL_DSN'];
            $parsed=$this->environment($text);
            foreach($managed as $key) { if(isset($parsed[$key]) && preg_match('/[\r\n]/',$parsed[$key]))throw new HttpException(409,'Converta as variaveis de email existentes para uma linha antes de editar pelo painel.'); }
            $lines=preg_split('/\r?\n/',$text);
            $lines=array_filter($lines,static fn($line)=>!preg_match('/^\s*(?:export\s+)?('.implode('|',$managed).')\s*=/',$line));
            $output=rtrim(implode("\n",$lines))."\n";
            foreach(self::FIELDS as $field=>$key) {
                $value=str_replace(['\\','"','$'],['\\\\','\\"','\\$'],$v[$field]);
                $output.=$key.'="'.$value.'"'."\n";
            }
            $temp=tempnam(dirname($this->path),'.fx-mail-');
            if($temp===false)throw new \RuntimeException('Falha ao preparar .env.');
            chmod($temp,0600);
            if(file_put_contents($temp,$output)!==strlen($output) || !rename($temp,$this->path))throw new \RuntimeException('Falha ao salvar .env.');
            $temp=null;
        } finally { if($temp!==null && is_file($temp))unlink($temp);flock($lock,LOCK_UN);fclose($lock); }
        return $this->snapshot();
    }
}
