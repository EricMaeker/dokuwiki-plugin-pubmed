<?php
/*
description : Dokuwiki Eric Maeker Pubmed plugin
author      : Eric Maeker
email       : eric.maeker[at]gmail.com
lastupdate  : 2019-11-01
license     : Public-Domain
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
  var $crossRefId =''; // Crossreference file PMID <-> DOI
  var $pdfDoiNS   =''; // Saving PDF using DOI.pdf
                       // All files in this path must be named
                       // {DOI}.pdf with a replacement of any '/' using '_'
  var $pdfPmidNS  =''; // Saving PDF using PMID.pdf
                       // All files in this path must be named
                       // {PMID}.pdf
  var $abstractTrFormat =''; // Files containing the translated abstract

  /**
   * Initialization
   */
  public function __construct($_name='plugin_cache',$_prefix='noname',$_ext='txt'){
    global $conf;
    $this->namespace = strtolower($_name);
    $this->pdfDoiNS  = strtolower($_name."/doi_pdf");
    $this->pdfPmidNS = strtolower($_name."/pmid_pdf");
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
    $this->abstractTrFormat = $this->mediaDir.'/'.$this->prefix.'%s_fr.txt';

    $this->useTmpDir   = false;
    $this->tmpDir      = '/var/tmp';
    $this->tmpFormat   = $this->tmpDir.'/'.$this->namespace.'_'.$this->prefix.'%s.'.$this->extension;
    $this->crossRefId = 'cross'; 
//     echo "<br/><br/><br/><pre>".
//         "NS: ". $this->namespace.PHP_EOL.
//         "pdfDoiNS: ". $this->pdfDoiNS.PHP_EOL.
//         "pdfPmidNS: ". $this->pdfPmidNS.PHP_EOL.
//         "Prefix: ".$this->prefix.PHP_EOL.
//         "extension: ".$this->extension.PHP_EOL.
//         "delimiter: ".$delimiter.PHP_EOL.
//         "mediaDir: ".$this->mediaDir.PHP_EOL.
//         "mediaFormat: ".$this->mediaFormat.PHP_EOL.
//         "linkFormat: ".$this->linkFormat.PHP_EOL.
//         "useTmpDir: ".print_r($this->useTmpDir).PHP_EOL.
//         "tmpFormat: ".$this->tmpFormat.PHP_EOL.
//         "crossRefId: ".$this->crossRefId.PHP_EOL.
//         "abstractTrFormat: ".$this->abstractTrFormat.PHP_EOL.
//         "</pre><br/>";
    $this->CheckDir();
  }

  /**
   * Get local pdf file path if exists (checking PMID and DOI dirs)
   */
  function GetLocalPdfPath($pmid, $doi){
    global $conf;
    $delimiter = ($conf['useslash'])?'/':':';
    // Check with PMID
    $ml = $this->pdfPmidNS.$delimiter.$pmid.".pdf";
    $filename = mediaFN($ml);
    //echo "<br/><pre>".$ml." ".$filename."</pre></br>";
    if (!file_exists($filename)) {
        // Test DOI
        $ml = $this->pdfDoiNS.$delimiter.str_replace("/","_",$doi).".pdf";
        $filename = mediaFN($ml);
        //echo "<br/><pre>".$ml." ".$filename."</pre></br>";
        if (!file_exists($filename)) {
            return ""; // Not found
        }
    }
    return ml($ml,'',true,'',true);
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
        if (!empty($id))
            $files[$id] = $path;
      }
    }
    closedir();
    return $files;
  }

  /**
   */
  function RecreateCrossRefFile(){
    $files = $this->GetAllMediaPaths();
    $cross = Array();
    foreach ($files as $id => $path) {
        // Read file $path
        if (@file_exists($path)){
          $content = io_readFile($path);
          $doi = $this->_catchDoiFromRawPubmedXml($content);
          // What to do if doi not found ?
          if (!empty($doi))
              $cross[$id] = $doi;
        }
    }
    // Save cross data
    $this->_save_array($this->crossRefId, $cross);
    return true;
  }

  function PmidFromDoi(&$pdfDois){
    $cross = $this->_read_array("cross");
    if (empty($cross))
        return NULL;
//     echo "<br><br>".print_r($cross)."<br><br>";
    $pmids = Array();
    $removeDoi = Array();
    foreach ($pdfDois as $doi) {
        $pmid = array_search($doi, $cross);
//         echo "<br>PMID:{$pmid}.............DOI:{$doi}<br>";
        if (!empty($pmid)) {
            $pmids[] = $pmid;
            $removeDoi[] = $doi;
        }
    }
    $pdfDois = array_diff($pdfDois, $removeDoi); 
    return $pmids;
  }

  /**
   * Get all local PDF file PMIDs
   */
  function GetAllAvailableLocalPdfByPMIDs(){
    //$this->pdfDoiNS  = strtolower($_name."/doi_pdf");
    //$this->pdfPmidNS = strtolower($_name."/pmid_pdf");
    // cache all PDF in PMID dir
    $dir = mediaFN($this->pdfPmidNS);
    $dirhandle = opendir($dir);
    $files = array();
    while($name = readdir($dirhandle)){
      if (strpos($name,".pdf")!==false){
        $id = str_replace(".pdf","",$name);
        $files[] = $id;
      }
    }
    closedir();
    //echo print_r($files);
    return $files;
  }

  /**
   * Get all local PDF file DOIs
   */
  function GetAllAvailableLocalPdfByDOIs(){
    // cache all PDF in DOI dir
    $dir = mediaFN($this->pdfDoiNS);
//     echo "*********** ".$dir."<br/>";
    $dirhandle = opendir($dir);
    $files = array();
    while($name = readdir($dirhandle)){
      if (strpos($name,".pdf")!==false){
        $id = str_replace(".pdf","",$name);
        $id = str_replace("_","/",$id);
        $files[] = $id;
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
  
  function GetDoiPdfUrl($doi){
    global $conf;
    $delimiter = ($conf['useslash'])?'/':':';
    $doi = str_replace("/","_",$doi);
    $ml = $this->pdfDoiNS.$delimiter.$doi.".pdf";
//     $filename = mediaFN($ml);
//     $file = mediaFN($this->pdfDoiNS.$this->delimiter.$doi.'.pdf');
    return ml($ml,'',true,'',true);
  }
  function GetDoiPdfThumbnailUrl($doi){
    global $conf;
    $delimiter = ($conf['useslash'])?'/':':';
    $doi = str_replace("/","_",$doi);
    $ml = $this->pdfDoiNS.$delimiter.$doi.'.jpg';
    return ml($ml,'',true,'',true);
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
      //@touch($filepath);
      return io_readFile($filepath);
    }
    return false;
  }
  
  /**
   * Return the content of the translated abstract of the PMID
   */
  function GetTranslatedAbstract($pmid, $lang='fr'){
    $filepath = sprintf($this->abstractTrFormat,$pmid);
    if (@file_exists($filepath)){
      //@touch($filepath);
      return io_readFile($filepath);
    }
    return "";
  }
  
  /**
   * Save string to cache with a permission of $conf['fmode'].
   *
   * Uses gzip if extension is .gz
   * and bz2 if extension is .bz2
   */
  function SavePubMedXmlSummaryText($xml){
    global $conf;
    $pmid = $this->_catchPmidFromRawPubmedXml($xml);
    $doi = $this->_catchDoiFromRawPubmedXml($xml);
//     echo "********* SAVING <br>PMID=".$pmid."  DOI=".$doi."<br>";
    $path = $this->GetMediaPath($pmid);
    if (io_saveFile($path,$xml)){
        @chmod($path,$conf['fmode']);
        $crossrefs = $this->_read_array($this->crossRefId);
        $crossrefs[$pmid] = $doi;
        $this->_save_array($this->crossRefId, $crossrefs);
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
    //echo "dummyFN: ".$dummyFN;
    $tmp = dirname($dummyFN);
    if (!@is_dir($tmp)){
      io_makeFileDir($dummyFN);
      @chmod($tmp,$conf['dmode']);
    }

    $dummyFN=mediaFN($this->pdfDoiNS.':_dummy');
    $tmp = dirname($dummyFN);
    //echo "dummyFN: ".$dummyFN." ".print_r(@is_dir($tmp))."<br>";
    if (!@is_dir($tmp)){
      io_makeFileDir($dummyFN);
      @chmod($tmp,$conf['dmode']);
    }

    $dummyFN=mediaFN($this->pdfPmidNS.':_dummy');
    $tmp = dirname($dummyFN);
    //echo "dummyFN: ".$dummyFN." ".print_r(@is_dir($tmp))."<br>";
    if (!@is_dir($tmp)){
      io_makeFileDir($dummyFN);
      @chmod($tmp,$conf['dmode']);
    }

    if (auth_aclcheck($this->namespace.":*","","@ALL")==0){
       global $AUTH_ACL;
       $acl = join("",file(DOKU_CONF.'acl.auth.php'));
       $p_acl = $this->namespace.":*\t@ALL\t1\n";
       $p_acl .= $this->pdfDoiNS.":*\t@admin\t16\n";
       $p_acl .= $this->pdfPmidNS.":*\t@admin\t16\n";
       $p_acl .= $this->pdfDoiNS.":*\t@ALL\t0\n";
       $p_acl .= $this->pdfPmidNS.":*\t@ALL\t0\n";
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
   * save key/value array as tab-text
   */
  function _save_array($id,$array){
    if (empty($id)) 
      return false;
    if (empty($array))
        return false;
    global $conf;
    $path = $this->GetMediaPath($id);
    if (io_saveFile($path,json_encode($array))) {
      @chmod($path,$conf['fmode']);
      return true;
    }
    return false;
  }

  /**
   * read array from tab-text
   */
  function _read_array($id){
    if (empty($id) || !$this->Exists($id)) 
        return NULL;
    $path = $this->GetMediaPath($id);
    $array = json_decode(io_readFile($path), true);
    return $array;
  }

  function _catchDoiFromRawPubmedXml($xml){
    $pattern = '~"doi">(.*)</~';
    $matches = '';
    $r = preg_match($pattern,$xml,$matches);
    return $matches[1];
  }

  function _catchPmidFromRawPubmedXml($xml){
    $pattern = '~"pubmed">(.*)</~';
    $matches = '';
    $r = preg_match($pattern,$xml,$matches);
    return $matches[1];
  }
}
?>