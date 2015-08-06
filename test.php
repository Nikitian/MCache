<pre><?php
ini_set('display_errors','on');
ini_set('error_reporting',8191);
include_once(dirname(__FILE__).'/MCache.class.php');
function rnd(){
    return mt_rand(-time(),time());
}
try{
    $echo = false;
    $time=microtime(true);
    $m = MCache::getInstance();
    $m->saveTypes = true;
    for($iter=0;$iter<1000;$iter++) {
        if($echo)echo 'Iteration #' . $iter . PHP_EOL;
            $last = $m->pick('last');
            if($last !== null) {
                if($echo)echo 'Var from last session ' . $last . PHP_EOL;
            }
            $m->tagSet('mytag2')->set('name1',rnd())->set('name2',rnd());
            if($echo)echo 'Random value1:'.$m->get('name1').PHP_EOL;
            if($echo)echo 'Random value2:'.$m->get('name2').PHP_EOL;
            $m->setByTag(rnd());
            if($echo)echo 'Random by tag value1: '.$m->get('name1').PHP_EOL;
            if($echo)echo 'Random by tag value2: '.$m->get('name2').PHP_EOL.PHP_EOL;
            $somevar = rnd();
            if($echo)echo 'Save $somevar: ' . $somevar . PHP_EOL;
            $m->tagSet('qq')->set('somevar', $somevar);
            if($echo)var_dump($m->get('somevar'));
            if($echo)var_dump($m->getByTag());
            if($echo)vaR_dump($m->deleteByTag()->getByTag());
            $m->tagUnset();
            $last = rnd();
            $m->set('last', $last);
            if($echo)echo 'Store for next ' . $last . PHP_EOL;
    }
    echo PHP_EOL.'Stats:';
    var_dump($m->stats);
    var_dump($m->errors);
    echo PHP_EOL.'time elapsed: '.round(microtime(true)-$time,6).' sec';
}catch(Exception $e){
    echo$e->getMessage();
}
?></pre>