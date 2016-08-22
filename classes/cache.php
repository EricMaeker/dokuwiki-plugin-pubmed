<?php
/*
description : Manage cache system
author      : Ikuo Obataya, Eric Maeker
email       : i.obataya[at]gmail_com, eric[at]maeker.fr
lastupdate  : 2016-08-22
license     : GPL 2 (http://www.gnu.org/licenses/gpl.html)
*/

if(!defined('DOKU_INC')) die();
class plugin_cache{
  var $namespace  ='';
  var $mediaDir   ='';
  var $mediaFormat='';
  var $linkFormat ='';
  var $prefix     ='';
  var $extension  ='';
  var $tmpdir     ='';
  function plugin_cache($_name='plugin_cache',$_prefix='noname',$_ext='txt'){
    global $conf;
    $this->namespace = strtolower($_name);
    $this->prefix    = strtolower($_prefix);
    $this->extension = strtolower($_ext);
    if (empty($_prefix)){
      $this->prefix = $_prefix;
    }else{
      $this->prefix = $_prefix.'_';
    }
    $delimiter = ($conf['useslash'])?'/':':';
    $this->mediaDir    = $conf['mediadir'].'/'.$this->namespace;
    $this->mediaFormat = $this->mediaDir.'/'.$this->prefix.'%s.'.$this->extension;
    $this->linkFormat  = $this->namespace.$delimiter.$this->prefix.'%s.'.$this->extension;

    $this->useTmpDir   = false;
    $this->tmpDir      = '/var/tmp';
    $this->tmpFormat   = $this->tmpDir.'/'.$this->namespace.'_'.$this->prefix.'%s.'.$this->extension;

    $this->CheckDir();
  }

 /**
  * Get media file path
  */
  function GetMediaPath($id){
    $id = strtolower($id);
    if($this->useTmpDir===false){
      return sprintf($this->mediaFormat,$id);
    }else{
      return sprintf($this->tmpFormat,$id);
    }
  }

 /**
  * Get all media file paths array
  * array(ID,filepath)
  */
  function GetAllMediaPaths(){
    $dir = $this->mediaDir;
    $dirhandle = opendir($dir);
    $files = array();

    $patten = array($this->prefix,'.'.$this->extension);
    $replace = array('','');
    while($name = readdir($dirhandle)){
      if (strpos($name,$this->extension)!==false){
        $path = $dir.'/'.$name;
        $id = str_replace($patten,$replace,$name);
        $files[$id] = $path;
      }
    }
    closedir();
    return $files;
  }

 /**
  * Get media link
  */
  function GetMediaLink($id){
    return ml(sprintf($this->linkFormat,$id),'',true,'',true);
  }

 /**
  * Get text from cache. If none, return false
  *
  * Uses gzip if extension is .gz
  * and bz2 if extension is .bz2
  */
  function GetMediaText($id){
    $filepath = $this->GetMediaPath($id);
    if (@file_exists($filepath)){
      @touch($filepath);
      return io_readFile($filepath);
    }
    return false;
  }
 /**
  * Save string to cache with a permission of $conf['fmode'].
  *
  * Uses gzip if extension is .gz
  * and bz2 if extension is .bz2
  */
  function PutMediaText($id,$text){
    global $conf;
    $path = $this->GetMediaPath($id);
    if(io_saveFile($path,$text)){
        @chmod($path,$conf['fmode']);
        return true;
    }
    return false;
  }
 /**
  * Check cache directories
  */
  function CheckDir(){
    global $conf;
    $dummyFN=mediaFN($this->namespace.':_dummy');
    $tmp = dirname($dummyFN);
    if (!@is_dir($tmp)){
      io_makeFileDir($dummyFN);
      @chmod($tmp,$conf['dmode']);
    }
    if (auth_aclcheck($this->namespace.":*","","@ALL")==0){
       global $AUTH_ACL;
       $acl = join("",file(DOKU_CONF.'acl.auth.php'));
       $p_acl = $this->namespace.":*\t@ALL\t1\n";
       $new_acl = $acl.$p_acl;
       io_saveFile(DOKU_CONF.'acl.auth.php', $new_acl);
       $AUTH_ACL = file(DOKU_CONF.'acl.auth.php'); // Reload ACL
    }
  }
 /**
  * Return true if the file exists
  */
  function Exists($id){
    $path = $this->GetMediaPath($id);
    if(@file_exists($path)!==false){
      @touch($path);
      return true;
    }
    return false;
  }

 /**
  * Clear all media files in a plugin's media directory
  */
  function ClearCache(){
    global $conf;
    $handle = @opendir($this->mediaDir);
    if ($handle === false)
      return;
    while (($entry = readdir($handle))){
      $path = $this->mediaDir.'/'.$entry;
      if(is_file($path))
          @unlink($path);
    }
    closedir($handle);
  }
 /**
  * Remove cache and directory
  */
  function RemoveDir(){
    $this->ClearCache();
    @rmdir($this->mediaDir);
  }
 /**
  * save array as tab-text
  */
  function _save_array($id,$ar=''){
    $_st = _microtime();
    if(empty($id)) return false;
    global $conf;
    if (empty($ar)){
    }else{
      $rep_pair = array("\n"=>"","\t"=>"");
      $values = array_values($ar);
      $keys = array_keys($ar);
      $sz = count($values);
      for($i=0;$i<$sz;$i++){
        $k = $keys[$i];
        if(empty($k)) $k=$i;
        $v=strtr($ar[$k],$rep_pair);
        $file.= $k."\t".$v."\n";
      }
    }
    if (empty($file)){$file='#not found';}
    $path = $this->GetMediaPath($id);
    if(io_saveFile($path,$file)){
      @chmod($path,$conf['fmode']);
      _stopwatch('save_array',$_st);
      return true;
    }else{
      _stopwatch('save_array',$_st);
    return false;
    }
  }

 /**
  * read array from tab-text
  */
  function _read_array($id){
    $_st = _microtime();
    if (empty($id) || !$this->Exists($id)) return NULL;
    $path = $this->GetMediaPath($id);
    $lines = split("\n",io_readFile($path));
    $a = array();
    $sz = count($lines);
    for($i=0;$i<$sz;$i++){
      if ($line=='#not found') return null;
      $line = chop($lines[$i]);
      if (empty($line)) continue;
      $items = explode("\t",$line);
      if (count($items)!=2)continue;
      $a[$items[0]] = $items[1];
    }
      _stopwatch('read_array',$_st);
    @touch($path);
    return $a;
  }

}
