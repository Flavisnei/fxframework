<?php
declare(strict_types=1);
namespace Fx\Framework\Tests;

use Fx\Framework\Admin\Mail\MailSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MailSettingsTest extends TestCase
{
    private string $root;
    protected function setUp():void { $this->root=sys_get_temp_dir().'/fx-mail-settings-'.bin2hex(random_bytes(8));mkdir($this->root,0700); }
    protected function tearDown():void { foreach(glob($this->root.'/{*,.*}',GLOB_BRACE) as $file)if(is_file($file))unlink($file);rmdir($this->root); }
    private function input(MailSettings $settings):array
    {
        return ['revision'=>$settings->snapshot()['revision'],'enabled'=>true,'host'=>'smtp.example.test','port'=>'465','username'=>'suporte=example.test','password'=>'secret $HOME ${OTHER} " \\ # = literal','from'=>'sender@example.test','from_name'=>'Equipe FX','admin_url'=>'https://example.test/admin'];
    }
    public function testEnvRoundTripPreservesUnrelatedValuesSecretsAndEncryptionKey():void
    {
        file_put_contents($this->root.'/.env',"# preserve\nAPP_NAME=Example\n");
        $settings=new MailSettings($this->root);$input=$this->input($settings);$saved=$settings->save($input);
        self::assertArrayNotHasKey('password',$saved);self::assertArrayNotHasKey('key',$saved);self::assertTrue($saved['password_set']);
        $raw=file_get_contents($this->root.'/.env');self::assertStringContainsString('APP_NAME=Example',$raw);
        $parsed=(new Dotenv())->parse($raw);self::assertSame($input['password'],$parsed['FX_MAIL_PASSWORD']);
        self::assertSame($input['password'],rawurldecode(parse_url((new MailSettings($this->root))->mail()['dsn'],PHP_URL_PASS)));
        $input['revision']=$saved['revision'];$input['password']='';$input['from_name']='Outro nome';$settings->save($input);
        $again=(new Dotenv())->parse(file_get_contents($this->root.'/.env'));
        self::assertSame($parsed['FX_MAIL_KEY'],$again['FX_MAIL_KEY']);self::assertSame($parsed['FX_MAIL_PASSWORD'],$again['FX_MAIL_PASSWORD']);
        self::assertFalse(getenv('FX_MAIL_PASSWORD')); // Sem exportacao global pelo leitor.
    }
    public function testLegacyMigrationPreservesKeyAndPassword():void
    {
        $key=bin2hex(random_bytes(32));$settings=new MailSettings($this->root,['dsn'=>'smtps://user:old-password@smtp.example.test:465','from'=>'sender@example.test','admin_url'=>'https://example.test/admin','key'=>$key]);
        $input=$this->input($settings);$input['password']='';$settings->save($input);
        self::assertSame($key,$settings->mail()['key']);self::assertStringContainsString(':old-password@',$settings->mail()['dsn']);
    }
    public function testStaleFormAndInvalidFieldsDoNotOverwriteFile():void
    {
        $settings=new MailSettings($this->root);$input=$this->input($settings);$settings->save($input);$before=file_get_contents($this->root.'/.env');
        try{$settings->save($input);self::fail('Formulario obsoleto aceito.');}catch(HttpException $error){self::assertSame(409,$error->getStatusCode());}
        foreach(['host'=>"smtp.example.test\nEVIL=1",'port'=>'0','admin_url'=>'http://example.test/admin','from'=>'invalid'] as $field=>$value){
            $bad=$this->input($settings);$bad[$field]=$value;
            try{$settings->save($bad);self::fail($field);}catch(HttpException $error){self::assertSame(422,$error->getStatusCode());}
            self::assertSame($before,file_get_contents($this->root.'/.env'));
        }
    }
    public function testEnvironmentOverrideLocksEditorAndDoesNotLeakPassword():void
    {
        $previous=getenv('FX_MAIL_PASSWORD');putenv('FX_MAIL_PASSWORD=override-secret');
        try {
            $settings=new MailSettings($this->root);self::assertTrue($settings->snapshot()['locked']);
            self::assertStringNotContainsString('override-secret',json_encode($settings->snapshot()));
            $this->expectException(HttpException::class);$settings->save($this->input($settings));
        } finally { $previous===false?putenv('FX_MAIL_PASSWORD'):putenv('FX_MAIL_PASSWORD='.$previous); }
    }
    public function testPrepareFailureDoesNotEnableIncompleteConfiguration():void
    {
        $settings=new MailSettings($this->root,null,static function(){throw new \RuntimeException('fixture');});
        try{$settings->save($this->input($settings));self::fail('Preparacao deveria falhar.');}catch(\RuntimeException){self::assertFileDoesNotExist($this->root.'/.env');}
    }
}
