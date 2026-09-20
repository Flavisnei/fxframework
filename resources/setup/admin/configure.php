<?php
declare(strict_types=1);
require __DIR__.'/vendor/autoload.php';
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Question\Question;
$app=new Fx\Framework\Console\Artisan(__DIR__);$app->setAutoExit(false);
$options=getopt('',['email:','password-env:','no-interaction']);
$input=new ArrayInput([]);$input->setInteractive(!isset($options['no-interaction']));$output=new ConsoleOutput();
$email=$options['email']??null;
if($email===null && $input->isInteractive())$email=$app->getHelperSet()->get('question')->ask($input,$output,new Question('Email do administrador: '));
if(!is_string($email) || !filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Informe um email valido.\n");exit(1);}
$args=['command'=>'admin:init','--email'=>$email];
if(isset($options['password-env']))$args['--password-env']=$options['password-env'];
$input=new ArrayInput($args);$input->setInteractive(!isset($options['no-interaction']));
$status=$app->run($input,$output);
if($status===0){(new Fx\Framework\Modules\ModuleManager(__DIR__))->enable('fx-admin');$output->writeln('Painel configurado e modulo ativado. Consulte LEIA-ME.txt para iniciar.');}
exit($status);
