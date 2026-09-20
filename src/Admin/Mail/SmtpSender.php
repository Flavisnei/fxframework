<?php
declare(strict_types=1);
namespace Fx\Framework\Admin\Mail;

use RuntimeException;

final class SmtpSender
{
    private readonly \Symfony\Component\Mailer\Mailer $mailer;
    private readonly \Symfony\Component\Mailer\Transport\TransportInterface $transport;
    public function __construct(string $dsn, private readonly string $from, bool $localTest = false, private readonly string $fromName = '')
    {
        if (!class_exists(\Symfony\Component\Mailer\Transport::class)) { throw new RuntimeException('Instale symfony/mailer ^6.4 no projeto para usar SMTP.'); }
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Remetente SMTP invalido.'); }
        $parts = parse_url($dsn);
        $local = $localTest && in_array($parts['host'] ?? '', ['127.0.0.1', '[::1]'], true) && ($parts['scheme'] ?? '') === 'smtp' && !isset($parts['user']) && !isset($parts['pass']);
        if (!$parts || (!$local && ($parts['scheme'] ?? '') !== 'smtps') || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment']) || !empty($parts['path'])) { throw new RuntimeException('Use smtps:// com TLS e sem opcoes de query. SMTP simples so e aceito em teste loopback sem credenciais.'); }
        try {
            $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
            if ($transport instanceof \Symfony\Component\Mailer\Transport\Smtp\SmtpTransport) { $transport->getStream()->setTimeout(10); }
            $this->transport = $transport;
            $this->mailer = new \Symfony\Component\Mailer\Mailer($transport);
        } catch (\Throwable) { throw new RuntimeException('Configuracao do transporte SMTP invalida.'); }
    }
    public function checkConnection(): void
    {
        try {
            if (!$this->transport instanceof \Symfony\Component\Mailer\Transport\Smtp\SmtpTransport) { throw new RuntimeException('Transporte SMTP necessario.'); }
            $this->transport->start();
        } catch (\Throwable) { throw new RuntimeException('Falha na conexao TLS ou autenticacao. Confira servidor, porta e credenciais.'); }
        finally { if ($this->transport instanceof \Symfony\Component\Mailer\Transport\Smtp\SmtpTransport) { try { $this->transport->stop(); } catch (\Throwable) {} } }
    }
    public function sendTest(string $recipient): void
    {
        try {
            $message=(new \Symfony\Component\Mime\Email())->from(new \Symfony\Component\Mime\Address($this->from,$this->fromName))->to($recipient)->subject('Teste de email - FX Framework')->text('Mensagem de teste solicitada pelo administrador. Nao altera senhas.');
            $this->mailer->send($message);
        } catch (\Throwable) { throw new RuntimeException('Falha no envio. Confira credenciais, destinatario e se o remetente pertence a conta SMTP.'); }
    }
    public function __invoke(string $email, string $url): void
    {
        $message = (new \Symfony\Component\Mime\Email())->from(new \Symfony\Component\Mime\Address($this->from, $this->fromName))->to($email)->subject('Recuperacao de senha - FX Admin')->text("Recebemos uma solicitacao para alterar sua senha.\n\nAbra o link abaixo dentro de 30 minutos. Ele pode ser usado uma unica vez.\n\n{$url}\n\nSe voce nao solicitou, ignore esta mensagem.\n");
        try { $this->mailer->send($message); }
        catch (\Throwable) { throw new RuntimeException('Falha ao enviar via SMTP. Consulte a configuracao e o provedor.'); }
    }
}
