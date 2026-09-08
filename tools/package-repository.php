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
    if (!in_array($argc,[3,4],true) || !preg_match('~\Ahttps://github.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?\z~', $argv[2], $url)) { throw new InvalidArgumentException('Uso: php tools/package-repository.php DIRETORIO_NOVO https://github.com/conta/repositorio [1.0.0-rc.1]'); }
    $version=$argv[3] ?? 'dev-main';
    if ($version !== 'dev-main' && !preg_match('/\A(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-rc\.[1-9][0-9]*)?\z/',$version)) { throw new InvalidArgumentException('Versao deve ser SemVer exata, opcionalmente -rc.N.'); }
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
    // Mantém versões anteriores do índice comprometido; uma versão publicada nunca é regravada.
    $index='docs/composer/packages.json';
    if (trim(packageGit(['ls-tree','--name-only',$source,'--',$index])) !== '') {
        $repository=json_decode(packageGit(['show',$source.':'.$index]),true,64,JSON_THROW_ON_ERROR);
        if (!isset($repository['packages']) || !is_array($repository['packages'])) { throw new RuntimeException('Indice anterior invalido.'); }
    }
    if ($version!=='dev-main') {
        foreach ($repository['packages'] as $versions) {
            if (isset($versions[$version])) { throw new RuntimeException('Versao ja existe; escolha uma nova versao.'); }
        }
    }
    $plan['version']=$version;
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
        $manifest['version']=$version;
        if ($version!=='dev-main') {
            foreach (($manifest['require'] ?? []) as $dependency=>$constraint) {
                if (str_starts_with($dependency,'fxfavalessa/fx-')) { $manifest['require'][$dependency]=$version; }
            }
        }
        $manifest['source']=['type'=>'git','url'=>$base.'.git','reference'=>$commit];
        $manifest['dist']=['type'=>'zip','url'=>$base.'/archive/'.$commit.'.zip','reference'=>$commit];
        $repository['packages'][$manifest['name']][$version]=$manifest;
        if ($version!=='dev-main') { $plan['tags'][]=['commit'=>$commit,'ref'=>'refs/tags/packages/'.$component.'/v'.$version]; }
        $plan['refs'][]=['package'=>$manifest['name'],'commit'=>$commit,'ref'=>'refs/heads/packages/'.$component];
    }
    if (!$plan['refs']) { throw new RuntimeException('Nenhum pacote encontrado.'); }
    if (!mkdir($destination,0700)) { throw new RuntimeException('Falha ao criar destino.'); }
    foreach (['packages.json'=>$repository,'publish-plan.json'=>$plan] as $file=>$data) {
        if (file_put_contents($destination.'/'.$file,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n")===false) { throw new RuntimeException('Falha ao gravar artefatos.'); }
    }
    echo json_encode(['directory'=>$destination,'packages'=>count($plan['refs']),'source_commit'=>$source,'published'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,$error->getMessage().PHP_EOL); exit(1); }
