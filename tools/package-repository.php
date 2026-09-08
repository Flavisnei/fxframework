<?php
declare(strict_types=1);

// Prepara snapshots independentes; publicação dos refs é uma etapa explícita.
$root = dirname(__DIR__);
function packageGit(array $args): string {
    global $root;
    $p = proc_open(['git', ...$args], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root, null, ['bypass_shell'=>true]);
    if (!is_resource($p)) { throw new RuntimeException('Git indisponivel.'); }
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); fclose($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($p)!==0) { throw new RuntimeException('Git falhou: '.trim($err)); }
    return $out;
}
try {
    if ($argc!==3 || !preg_match('~\Ahttps://github.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?\z~', $argv[2], $url)) { throw new InvalidArgumentException('Uso: php tools/package-repository.php DIRETORIO_NOVO https://github.com/conta/repositorio'); }
    $parent=realpath(dirname($argv[1])); $name=basename($argv[1]);
    if ($parent===false || in_array($name,['','.','..'],true) || file_exists($argv[1])) { throw new InvalidArgumentException('Destino deve ser novo, com pai existente.'); }
    $destination=$parent.DIRECTORY_SEPARATOR.$name;
    if (str_starts_with(strtolower(str_replace('\\','/',$destination)).'/',strtolower(str_replace('\\','/',realpath($root))).'/')) { throw new InvalidArgumentException('Destino deve ficar fora do checkout.'); }
    $base='https://github.com/'.$url[1].'/'.$url[2];
    $source=trim(packageGit(['rev-parse','HEAD^{commit}']));
    $rows=explode("\n",trim(packageGit(['ls-tree','-d','--name-only',$source.':packages'])));
    $parents=[];
    foreach (explode("\n",trim(packageGit(['for-each-ref','--format=%(refname:strip=4) %(objectname)','refs/remotes/origin/packages/']))) as $row) {
        $parts=explode(' ',$row); if (count($parts)===2) { $parents[$parts[0]]=$parts[1]; }
    }
    $repository=['packages'=>[]]; $plan=['schema'=>1,'source_commit'=>$source,'repository'=>$base,'refs'=>[]];
    foreach ($rows as $component) {
        if (!preg_match('/\A[a-z][a-z0-9-]*\z/',$component)) { throw new RuntimeException('Diretorio de pacote invalido.'); }
        $tree=trim(packageGit(['rev-parse',$source.':packages/'.$component]));
        $entries=explode("\0",trim(packageGit(['ls-tree','-rz',$tree]),"\0"));
        foreach ($entries as $entry) {
            if (!preg_match('/^(100644|100755) blob [a-f0-9]+\t(.+)$/sD',$entry,$match)) { throw new RuntimeException('Pacote contem link ou submodulo.'); }
            if (preg_match('~(^|/)(vendor|storage|node_modules|\.git)(/|$)|(^|/)\.env(?:$|\.)|\.local\.|\.(sqlite3?|db|log|pem|key)$~i',$match[2])) { throw new RuntimeException('Pacote contem caminho privado ou gerado.'); }
        }
        $manifest=json_decode(packageGit(['show',$source.':packages/'.$component.'/composer.json']),true,32,JSON_THROW_ON_ERROR);
        if (($manifest['name']??null)!=='fxfavalessa/fx-'.$component || !isset($manifest['autoload'])) { throw new RuntimeException('Manifesto nao corresponde ao pacote.'); }
        packageGit(['cat-file','-e',$source.':packages/'.$component.'/docs/index.html']);
        $args=['commit-tree',$tree]; if (isset($parents[$component])) { array_push($args,'-p',$parents[$component]); }
        array_push($args,'-m','Snapshot '.$manifest['name'].' de '.$source);
        $commit=trim(packageGit($args));
        unset($manifest['require-dev'],$manifest['autoload-dev'],$manifest['repositories'],$manifest['scripts']);
        $manifest['version']='dev-main';
        $manifest['source']=['type'=>'git','url'=>$base.'.git','reference'=>$commit];
        $manifest['dist']=['type'=>'zip','url'=>$base.'/archive/'.$commit.'.zip','reference'=>$commit];
        $repository['packages'][$manifest['name']]['dev-main']=$manifest;
        $plan['refs'][]=['package'=>$manifest['name'],'commit'=>$commit,'ref'=>'refs/heads/packages/'.$component];
    }
    if (!$plan['refs']) { throw new RuntimeException('Nenhum pacote encontrado.'); }
    if (!mkdir($destination,0700)) { throw new RuntimeException('Falha ao criar destino.'); }
    foreach (['packages.json'=>$repository,'publish-plan.json'=>$plan] as $file=>$data) {
        if (file_put_contents($destination.'/'.$file,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n")===false) { throw new RuntimeException('Falha ao gravar artefatos.'); }
    }
    echo json_encode(['directory'=>$destination,'packages'=>count($plan['refs']),'source_commit'=>$source,'published'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,$error->getMessage().PHP_EOL); exit(1); }
