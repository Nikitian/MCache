<?php
/**
 * Wrapper for memcache and memcached
 * Support float timeout (Only in saveTypes = true)
 * Support tag and group operations by tag
 * @uses Memcache or Memcached
 * @author Nikitian
 * @version 1.0.1
 *  1.0.1    Fix self::add() for memcache bug
 */
class MCache{
    /**
     * Instance of this array classes
     * @var object
     */
    static private $_instance = array();
    /**
     * If false, not using methods _pack()/_unpack(). false - it's not correctly with Memcache
     * Before change, use flush() for clean storage from last state data!
     * If do not flush last data:
     * <pre>
     * $m->set('name',false);
     * $m->saveTypes = false;
     * var_dump($m->get('name'));
     * </pre>
     * you cat see this: <code>string(19) "{"v":false,"t":"b","s":0.5}"</pre>
     * @var bool|true
     */
    public $saveTypes = true;
    /**
     * Default API name for use if not specified. If this API not installed, try to use another
     * @var enum(Memcache|Memcached)
     */
    private $_defaultApi = 'Memcache';
    /**
     * Use for connect with API
     * @var resource
     */
    private $_resource = null;
    /**
     * Used API
     * @var enum(Memcache|Memcached)
     */
    public $apitype = null;
    /**
     * Default timeout for $this->set()
     * Precision about 0.0002 sec - it's ~time for operations set/get
     * @var float|3600
     */
    public $timeoutDefault = 3600;
    /**
     * Statistic this session
     * <pre>
     * array(
     *      hits=>{Count of hit data from storage},
     *      sets=>{Count of sets and add data to storage},
     *      loose hits=>{Count hits with no returns},
     * )
     * </pre>
     * @var array
     */
    public $stats = array(
        'hits'=>0,
        'sets'=>0,
        'loose hits'=>0,
    );
    /**
     * Status last get() operation
     * @var bool
     */
    public $getSuccess = true;
    /**
     * Cache for have() and get() requests
     * After geting value, stored in this cache, value removes from cache.
     * @var array 
     */
    private $_cache = array();
    /**
     * Limit for any items in cache for have() requests
     * Set -1 for not use cache
     * @var int
     */
    public $cacheLimitItemSize = 1000;
    /**
     * Convert names for using only with this domain.
     * If true, you may use keys with unlimited length.
     * @var bool|true
     */
    public $domainConnect = true;
    /**
     * Domainname, using for generate new names
     * @var string
     */
    public $domainForConnect = '';
    /**
     * Tag for current operations
     * @var string
     */
    private $_tag = null;
    /**
     * Time for save tags
     * Must be equal or more than timeout all another data in this tag
     * @var float|3600
     */
    public $tagTimeout = 3600;
    /**
     * Config for connect
     * @var array
     */
    private $_config = array();
    /**
     * If false, may will be throwed exceptions on error.
     * If true, on error fill property errors and returns where need this object. Useful for chain.
     * @var bool|true
     */
    public $silentMode = true;
    /**
     * List of errors. Fills only if property silentMode is true
     * @var array
     */
    public $errors = array();
    
    /**
     * If not specified any arguments for connect, try to use data from Config->get('mcache') or use localhost:11211
     * @param array $c      <pre>Array/Object(
     *      host,
     *      port,
     *      apitype (Memcache|Memcached)
     * )</pre>
     * @return \MCache
     */
    public function __construct($c=array()){
        $this->_config = (array)$c;//If specified as object
        return$this;
    }
    /**
     * Connect to API memcache on demand.
     * @return \MCache
     * @throws Exception
     */
    private function _connect(){
        if(array_key_exists('apitype',$this->_config)){
            if(!class_exists($this->_config['apitype'])){
                $err = 'Not installed selected MCache api: ['.$this->_config['apitype'].']';
                if($this->silentMode){
                    $this->errors[]=$err;
                }
                else{
                    throw new Exception($err);
                }
            }
            else{
                $this->apitype = $this->_config['apitype'];
            }
        }
        else{
            $this->apitype = $this->_defaultApi;//'Memcache';
            if(!class_exists($this->apitype)){
                $this->apitype = ($this->_defaultApi=='Memcache'?'Memcached':'Memcache');
                if(!class_exists($this->apitype)){
                    $err='Not installed Memcache and Memcached';
                    if($this->silentMode){
                        $this->errors[]=$err;
                    }
                    else{
                        throw new Exception($err);
                    }
                }
            }
        }
        if(array_key_exists('timeout',$this->_config)){
            $this->timeoutDefault = $this->_config['timeout'];
        }
        $this->_resource = new $this->apitype;
        if(!array_key_exists('host',$this->_config)){
            //Try to load from Config
            if(
                class_exists('Config') && 
                method_exists('Config', 'getInstance') && 
                method_exists('Config', 'have') && 
                method_exists('Config', 'get')
                ){
                $config = Config::getInstance();
                $this->_config = $config->get('mcache');
                if(!array_key_exists('host',$this->_config)){
                    $this->_config['host']='localhost';
                }
            }
            else{
                $this->_config['host']='localhost';
            }
        }
        if(!array_key_exists('port',$this->_config)){
            $this->_config['port']=11211;
        }
        if($this->apitype=='Memcache'){
            $ret = $this->_resource->connect($this->_config['host'],$this->_config['port']);
        }
        else{
            $ret = $this->_resource->addServer($this->_config['host'],$this->_config['port']);
        }
        if($ret===false){
            $err='Wrong host or port for memcache server';
            if($this->silentMode){
                $this->errors[]=$err;
            }
            else{
                throw new Exception($err);
            }
        }
        return $this;
    }
    
    public function __destruct() {
        if(method_exists($this->_resource, 'close')){
            $this->_resource->close();
        }
    }
    
    public function __get($name) {
        if(!property_exists($this, $name)){
            if($this->_resource===null)$this->_connect();
            if(property_exists($this->_resource,$name)){
                return $this->_resource->$name;
            }
        }
        else{
            return$this->$name;
        }
        return null;
    }
    
    public function __set($name,$value) {
        if(!property_exists($this, $name)){
            if($this->_resource===null)$this->_connect();
            if(property_exists($this->_resource,$name)){
                return $this->_resource->$name = $value;
            }
        }
        else{
            return $this->$name = $value;
        }
        return null;
    }
    
    public function __call($name, $arguments) {
        if($this->_resource===null)$this->_connect();
        if(method_exists($this, $name)){
            return call_user_func_array(array($this,$name), $this, $arguments);
        }
        elseif(method_exists($this->_resource, $name)){
            //Clean all local cache.
            if($name == 'flush')$this->_cache = array();
            return call_user_func_array(array($this->_resource,$name), $arguments);
        }
        return null;
    }
    
    /**
     * 
     * @param array $c      <pre>Array/Object(
     *      host,
     *      port,
     *      apitype (Memcache|Memcached)
     * )</pre>
     * @return \MCache
     */
    static function getInstance($c=array()){
        $key = crc32(implode(',',$c));
        if(!array_key_exists($key,self::$_instance)){
            self::$_instance[$key] = new self($c);
        }
        return self::$_instance[$key];
    }
    
    /**
     * Check to need a compress value
     * Used only for work with Memcache API
     * If saveTypes sets in true, always return MEMCACHE_COMPRESSED
     * @param mixed $value
     * @return MEMCACHE_COMPRESSED|false
     */
    private function _checkCompress($value){
        if($this->saveTypes)return MEMCACHE_COMPRESSED;
        return is_bool($value) || is_int($value) || is_float($value) ? false : MEMCACHE_COMPRESSED;
    }
    
    /**
     * Pack saving data to json-string with data type
     * @param mixed $value      Stored value
     * @param float $timeout    Seconds for save
     * @return string
     */
    private function _pack($value,$timeout = 0){
        if(!$this->saveTypes)return$value;
        return json_encode(array(
            'v'=>$value,
            't'=>(
                is_bool($value)?'b':(
                    is_int($value)?'i':(
                        is_object($value)?'o':(
                            is_float($value)?'f':(
                                is_array($value)?'a':
                                's'
                            )
                        )
                    )
                )
            ),
            's'=>($this->apitype!='Memcached')?($timeout+microtime(true)):$timeout,
        ));
    }
    
    /**
     * Unpack saved data
     * Check timeout in microseconds
     * @param string $value
     * @return mixed
     */
    private function _unpack($value){
        if(!$this->saveTypes)return$value;
        $v = json_decode($value, 'true');
        if(
            !is_array($v) ||
            sizeof($v)!=3 ||
            !array_key_exists('v',$v) ||
            !array_key_exists('t',$v) ||
            !array_key_exists('s',$v) ||
            $v['s']<microtime(true)
            ){
            $this->getSuccess = false;
            return null;
        }
        $this->getSuccess = true;
        switch($v['t']){
            case'b':return ($v['v']?true:false);
            case'i':return intval($v['v']);
            case'o':return (object)$v['v'];
            case'f':return floatval($v['v']);
            case'a':return (array)$v['v'];
            default:return$v['v'];
        }
    }
    /**
     * Convert name of storage item to demain connected name
     * @param string $name
     * @return string
     */
    private function _nameWithDomain($name){
        if($this->domainForConnect==''){
            $domain = str_replace('www.','',strtolower($_SERVER['HTTP_HOST']));
        }
        else{
            $domain = $this->domainForConnect;
        }
        $name = md5($domain.'#!#'.$name);
        return$name;
    }
    
    /**
     * Setting data to storage.
     * If $name is array and $value not specified or null, sets $name as array keys=>values
     * @param string|array $name        Storage identificator or array keys=>values
     * @param mixed $value              Saved values
     * @param float $timeout|-1         Time to save in seconds from this time. if not specified or -1, uses $this->timeoutDefault
     * @return \MCache
     * @throws Exception
     */
    public function set($name,$value=null,$timeout=-1,$alwaysfalse=false){
        if($this->_resource===null)$this->_connect();
        if($timeout==-1){
            $timeout = $this->timeoutDefault;
        }
        if($this->apitype=='Memcached'){
            $timeout+=microtime(true);
        }
        
        $ret = true;
        if($value===null && is_array($name)){
            if($this->apitype=='Memcache'){
                //For sets array as it may do in memcached
                foreach($name as $k=>$v){
                    $rawname = $k;
                    if($this->domainConnect){
                        $k = $this->_nameWithDomain($k);
                    }
                    $ret = $this->_resource->set($k,$this->_pack($v,$timeout),$this->_checkCompress($v),ceil($timeout));
                    if($ret!==false && $this->_tag!==null){
                        //Save a tag
                        if(!$alwaysfalse){
                            $this->_tagSet($rawname);
                        }
                    }
                    $this->stats['sets']++;
                }
            }
            elseif($this->apitype=='Memcached'){
                foreach($name as $k=>$v){
                    if($this->domainConnect){
                        $newname = array();
                        $rawname = $name;
                        foreach($name as $k=>$v){
                            $k = $this->_nameWithDomain($k);
                            $newname[$k]=$v;
                        }
                        $name = $newname;
                        unset($newname);
                    }
                    /*
                     * Clean cache for this item
                    */
                    if(array_key_exists($k,$this->_cache))unset($this->_cache[$k]);
                    $name[$k]=$this->_pack($v,$timeout);
                }
                $ret = $this->_resource->setMulti($name,ceil($timeout));
                if($ret!==false && $this->_tag!==null){
                    //Save a tag
                    if(!$alwaysfalse){
                        foreach($rawname as $k=>$v){
                            $this->_tagSet($k);
                        }
                    }
                }
                $this->stats['sets']+=sizeof($name);
                if($ret===false){
                    $ret = $this->_resource->getResultCode();
                }
            }
            else{
                $err='Unknown exception in set() method';
                if($this->silentMode){
                    $this->errors[]=$err;
                }
                else{
                    throw new Exception($err);
                }
            }
        }
        else{
            $rawname = $name;
            if($this->domainConnect){
                $name = $this->_nameWithDomain($name);
            }
            /*
             * Clean cache for this item
             */
            if(array_key_exists($name,$this->_cache))unset($this->_cache[$name]);
            if($this->apitype=='Memcache'){
                $ret = $this->_resource->set($name,$this->_pack($value,$timeout),$this->_checkCompress($value),ceil($timeout));
            }
            else{
                $ret = $this->_resource->set($name,$this->_pack($value,$timeout),ceil($timeout));
            }
            if($ret!==false && $this->_tag!==null){
                //Save a tag
                if(!$alwaysfalse){
                    $this->_tagSet($rawname);
                }
            }
            if($ret===false){
                $ret = 'Some error in Memcache: '.$this->resorce->getServerStatus($this->_config['host'],$this->_config['port']);
            }
            $this->stats['sets']++;
        }
        if($ret!==true){
            $err='Cant\'t set data to storage. Return '.var_export($ret,true);
            if($this->silentMode){
                $this->errors[]=$err;
            }
            else{
                throw new Exception($err);
            }
        }
        return $this;
    }
    /**
     * Generate tagname for internal use only
     * @return array
     */
    private function _tagGenerateName(){
        if(is_array($this->_tag)){
            $ret = array();
            foreach($this->_tag as $t){
                $ret[]=__CLASS__.'#tag#'.$t;
            }
            return$ret;
        }
        return array(__CLASS__.'#tag#'.$this->_tag);
    }
    /**
     * Set selected name to $this->_tag
     * @param string $name
     * @return bool
     */
    private function _tagSet($name){
        $tagsid = $this->_tagGenerateName();
        $ret = true;
        foreach($tagsid as $tagid){
            $tag = $this->get($tagid);
            if($this->getSuccess===false){
                $tag = array();
            }
            $tag[]=$name;
            $ret = $ret && $this->set($tagid, array_unique($tag), $this->tagTimeout, true);
        }
        return$ret;
    }
    /**
     * Returns array of names for current tag
     * @return array
     */
    private function _tag2name(){
        $tagid = $this->_tagGenerateName();
        $tag = $this->get($tagid);
        if($this->getSuccess===false){
            return array();
        }
        return reset($tag);
    }
    
    /**
     * Adding data to storage.
     * If $name is array and $value not specified or null, adds $name as array keys=>values
     * @param string|array $name    Storage identificator or array keys=>values
     * @param mixed $value          Saved values
     * @param int $timeout|-1       Time to save. if not specified or -1, uses $this->timeoutDefault
     * @return \MCache
     * @throws Exception
     */
    public function add($name,$value=null,$timeout=-1){
        if($this->_resource===null)$this->_connect();
        if($timeout==-1){
            $timeout = $this->timeoutDefault;
        }
        if($this->apitype=='Memcached'){
            $timeout+=time();
        }
        $ret = true;
        if($value===null && is_array($name)){
            //For sets array as it may do in memcached
            foreach($name as $k=>$v){
                $rawname = $k;
                if($this->domainConnect){
                    $k = $this->_nameWithDomain($k);
                }
                $ret = $this->_resource->add($k,$this->_pack($v,$timeout),ceil($timeout));
                if($ret!==false && $this->_tag!==null){
                    //Save a tag
                    $this->_tagSet($rawname);
                }
                $this->stats['sets']++;
            }
        }
        else{
            $rawname = $name;
            if($this->domainConnect){
                $name = $this->_nameWithDomain($name);
            }
            if($this->apitype=='Memcache'){
                $ret = $this->_resource->add($name,$this->_pack($value,$timeout),0,ceil($timeout));
            }
            else{
                $ret = $this->_resource->add($name,$this->_pack($value,$timeout),$this->_checkCompress($value),ceil($timeout));
            }
            if($ret!==false && $this->_tag!==null){
                //Save a tag
                $this->_tagSet($rawname);
            }
            $this->stats['sets']++;
        }
        if($ret!==true){
            $err='Cant\'t set data to storage';
            if($this->silentMode){
                $this->errors[]=$err;
            }
            else{
                throw new Exception($err);
            }
        }
        return $this;
    }
    
    /**
     * Getting data from storage.
     * If $name is array, gets $name as array keys
     * @param string|array $name        Storage identificator or array keys
     * @param bool $returnstatus|false  Return status of getting. Not value. If $name is array, return true if all keys exists.
     * @return mixed
     * @throws Exception
     */
    public function get($name,$returnstatus=false){
        if($this->_resource===null)$this->_connect();
        $ret = null;
        $success = true;
        if(is_array($name) && $this->apitype=='Memcache'){
            //For gets array as it may do in memcached
            $ret = array();
            foreach($name as $k){
                $rawname = $k;
                if($this->domainConnect){
                    $k = $this->_nameWithDomain($k);
                }
                if(array_key_exists($k,$this->_cache)){
                    $tmp = $this->_unpack($this->_cache[$k]);
                    if(!$returnstatus)unset($this->_cache[$k]);
                    $this->getSuccess = true;
                }
                else{
                    $tmp = $this->_unpack($this->_resource->get($k));
                }
                $ret[$rawname]=$tmp;
                if($this->getSuccess===false){
                    $this->stats['loose hits']++;
                }
                if($returnstatus && $this->_cacheLimitItemSize>strlen($tmp)){
                    $this->_cache[$k]=$this->_pack($tmp);
                }
                $success=$success&&$this->getSuccess;
                $this->stats['hits']++;
            }
        }
        else{
            if($this->domainConnect){
                $name = $this->_nameWithDomain($name);
            }
            if(array_key_exists($name,$this->_cache)){
                $ret = $this->_unpack($this->_cache[$name]);
                if(!$returnstatus)unset($this->_cache[$name]);
                $this->getSuccess = true;
            }
            else{
                $ret = $this->_unpack($this->_resource->get($name));
            }
            if($this->getSuccess===false){
                if($this->apitype=='Memcached'){
                    if($this->_resource->getResultCode()==$this->_resource->RES_NOTFOUND){
                        $this->stats['loose hits']++;
                    }
                }
                else{
                    $this->stats['loose hits']++;
                }
            }
            if($returnstatus && $this->_cacheLimitItemSize>strlen($ret)){
                $this->_cache[$name]=$ret;
            }
            $this->stats['hits']++;
        }
        if($returnstatus)return($success&&$this->getSuccess)?true:false;
        return $ret;
    }
    /**
     * Check data exists
     * @param string|array $name    Storage identificator or array keys. If $name is array, return true if all keys exists.
     * @return bool
     */
    public function have($name){
        return $this->get($name, true);
    }
    /**
     * Delete key or keys from storage. 
     * @param string|array $name        Key for delete. If array - it's a array of keys.
     * @param bool  $returnstatus|false If true, return have($name) before delete.
     * @return \MCache|bool
     */
    public function delete($name,$returnstatus = false){
        if($this->_resource===null)$this->_connect();
        if($returnstatus){
            $ret = $this->have($name);
        }
        if(!is_array($name) || sizeof($name)==0){
            $name = array($name);
        }
        foreach($name as $k){
            if($this->domainConnect){
                $k = $this->_nameWithDomain($k);
            }
            $this->_resource->delete($k);
            if(array_key_exists($k,$this->_cache))unset($this->_cache[$k]);
        }
        if($returnstatus)return$ret;
        return$this;
    }
    /**
     * Set tag/tags for next operations
     * @param string|array $name    Tag or array of tags
     * @return \MCache
     */
    public function tagSet($name){
        $this->_tag = $name;
        return$this;
    }
    /**
     * Unset tag for next operations
     * @return \MCache
     */
    public function tagUnset(){
        $this->_tag = null;
        return$this;
    }
    /**
     * Get current tag
     * @return string|array
     */
    public function tagGet(){
        return$this->_tag;
    }
    /**
     * Set value for all stored data with selected tag
     * @param mixed $value|false
     * @param float $timeout|-1     If not specified, sets as $this->timeoutDefault
     * @return \MCache
     * @throws Exception
     */
    public function setByTag($value,$timeout = -1){
        if($this->_tag===null){
            $err='Can\'t set by unsetted tag. Tag can\'t equil null';
            if($this->silentMode){
                $this->errors[]=$err;
                return$this;
            }
            else{
                throw new Exception($err);
            }
        }
        $names = $this->_tag2name();
        foreach($names as $name){
            $this->set($name,$value,$timeout);
        }
        return $this;
    }
    /**
     * Deletes all stored data by selected tag
     * @return \MCache
     * @throws Exception
     */
    public function deleteByTag(){
        if($this->_tag===null){
            $err='Can\'t delete by unsetted tag. Tag can\'t equil null';
            if($this->silentMode){
                $this->errors[]=$err;
                return$this;
            }
            else{
                throw new Exception($err);
            }
        }
        $names = $this->_tag2name();
        $this->delete(array_merge(
            $names,
            $this->_tagGenerateName()
        ));
        return $this;
    }
    /**
     * Add value for all stored data by selected tag
     * @param mixed $value
     * @param float $timeout|-1     If not specified, sets as $this->timeoutDefault
     * @return \MCache
     * @throws Exception
     */
    public function addByTag($value,$timeout = -1){
        if($this->_tag===null){
            $err='Can\'t add by unsetted tag. Tag can\'t equil null';
            if($this->silentMode){
                $this->errors[]=$err;
                return$this;
            }
            else{
                throw new Exception($err);
            }
        }
        $names = $this->_tag2name();
        foreach($names as $name){
            $this->add($name,$value,$timeout);
        }
        return $this;
    }
    /**
     * Get all values with selected tag/tags
     * @return mixed
     * @throws Exception
     */
    public function getByTag(){
        if($this->_tag===null){
            $err='Can\'t get by unsetted tag. Tag can\'t equil null';
            if($this->silentMode){
                $this->errors[]=$err;
                return$this;
            }
            else{
                throw new Exception($err);
            }
        }
        $name = $this->_tag2name();
        return $this->get($name);
    }
    /**
     * Increase value by name or array of names on value
     * @param string|array $name        Name o array of names increases data
     * @param float $value|1            Values for increase
     * @param float|int $timeout|-1     Timeout. If not specified, sets by timeoutDefault
     * @return \MCache
     */
    public function inc($name,$value=1,$timeout=-1){
        if($value==0)return$this;
        if($this->_resource===null)$this->_connect();
        if($timeout==-1){
            $timeout = $this->timeoutDefault;
        }
        if($this->apitype=='Memcached'){
            $timeout+=microtime(true);
        }
        $items = $this->get($name);
        if(!$this->getSuccess)return $this;
        if(is_array($name)){
            foreach($items as $k=>$item){
                $this->set($k, $item+$value, $timeout);
            }
        }
        else{
            $this->set($name, $items+$value, $timeout);
        }
        return $this;
    }
    /**
     * Decrease value by name or array of names on value
     * @param string|array $name    Name o array of names decreases data
     * @param float $value|1        Values for decrease
     * @param float|int $timeout|-1 Timeout. If not specified, sets by timeoutDefault
     * @return \MCache
     */
    public function dec($name,$value=1,$timeout=-1){
        return $this->inc($name,-$value,$timeout);
    }
    /**
     * Multiply value by name or array of names on value
     * If value = 1 no any jobs
     * @param string|array $name    Name o array of names multiplys data
     * @param float $value|2        Values for multiply
     * @param float|int $timeout|-1 Timeout. If not specified, sets by timeoutDefault
     * @return \MCache
     */
    public function mult($name,$value=2,$timeout=-1){
        if($value==1)return$this;
        if($this->_resource===null)$this->_connect();
        if($timeout==-1){
            $timeout = $this->timeoutDefault;
        }
        if($this->apitype=='Memcached'){
            $timeout+=microtime(true);
        }
        if($value==0){//All sets to zero
            $this->set($name, 0, $timeout);
        }
        else{
            $items = $this->get($name);
            if(!$this->getSuccess)return $this;
            if(is_array($name)){
                foreach($items as $k=>$item){
                    $this->set($k, $item*$value, $timeout);
                }
            }
            else{
                $this->set($name, $items*$value, $timeout);
            }
        }
        return $this;
    }
    /**
     * Division value by name or array of names on value
     * If value = 0 no jobs or throw exception (if not silentMode)
     * @param string|array $name    Name o array of names divisions data
     * @param float $value|2        Values for division
     * @param float|int $timeout|-1 Timeout. If not specified, sets by timeoutDefault
     * @return \MCache
     * @throws Exception
     */
    public function div($name,$value=2,$timeout=-1){
        if($value==0){
            $err='Division by zero';
            if($this->silentMode){
                $this->errors[]=$err;
                return$this;
            }
            else{
                throw new Exception($err);
            }
        }
        return $this->mult($name, 1/$value,$timeout);
    }
    /**
     * Increase values by tag
     * @param float $value|1
     * @param float|int $timeout|-1
     * @return \MCache
     * @throws Exception
     */
    public function incByTag($value=1,$timeout=-1){
        if($value==0)return$this;
        if($this->_tag===null){
            $err='Can\'t increase by unsetted tag. Tag can\'t equil null';
            if($this->silentMode){
                $this->errors[]=$err;
                return$this;
            }
            else{
                throw new Exception($err);
            }
        }
        $names = $this->_tag2name();
        foreach($names as $name){
            $this->inc($name,$value,$timeout);
        }
        return $this;
    }
    /**
     * Decrease values by tag
     * @param float $value|1
     * @param float|int $timeout|-1
     * @return \MCache
     */
    public function decByTag($value=1,$timeout=-1){
        return $this->incByTag(-$value,$timeout);
    }
    /**
     * Multiply values by tag
     * @param float $value|2
     * @param float|int $timeout|-1
     * @return \MCache
     * @throws Exception
     */
    public function multByTag($value=2,$timeout=-1){
        if($value==1)return$this;
        if($value==0)return$this->setByTag($value, $timeout);
        if($this->_tag===null){
            $err='Can\'t multiply by unsetted tag. Tag can\'t equil null';
            if($this->silentMode){
                $this->errors[]=$err;
                return$this;
            }
            else{
                throw new Exception($err);
            }
        }
        $names = $this->_tag2name();
        foreach($names as $name){
            $this->mult($name,$value,$timeout);
        }
        return $this;
    }
    /**
     * Division values by tag
     * @param float $value|2
     * @param float|int $timeout|-1
     * @return \MCache
     * @throws Exception
     */
    public function divByTag($value=2,$timeout=-1){
        if($value==0){
            $err='Division by zero';
            if($this->silentMode){
                $this->errors[]=$err;
                return$this;
            }
            else{
                throw new Exception($err);
            }
        }
        return $this->multByTag(1/$value,$timeout);
    }
}