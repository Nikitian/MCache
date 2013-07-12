<pre><?php
ini_set('display_errors','on');
ini_set('error_reporting',8191);
include_once(dirname(__FILE__).'/MCache.class.php');
function rnd(){
    return mt_rand(-time(),time());
}
try{
    $time=microtime(true);
    $m = MCache::getInstance();
    $m->tagSet('mytag2')->set('name1',rnd())->set('name2',rnd());
    echo 'Random value1:'.$m->get('name1').PHP_EOL;
    echo 'Random value2:'.$m->get('name2').PHP_EOL;
    $m->setByTag(rnd());
    echo 'Random by tag value1: '.$m->get('name1').PHP_EOL;
    echo 'Random by tag value2: '.$m->get('name2').PHP_EOL.PHP_EOL;
    $m->tagSet('qq')->set('somevar',rnd());
    var_dump($m->get('somevar'));
    var_dump($m->getByTag());
    vaR_dump($m->deleteByTag()->getByTag());
    $m->tagUnset();
    echo PHP_EOL.'Stats:';
    var_dump($m->stats);
    echo PHP_EOL.'time elapsed: '.round(microtime(true)-$time,6).' sec';
}catch(Exception $e){
    echo$e->getMessage();
}
?></pre>