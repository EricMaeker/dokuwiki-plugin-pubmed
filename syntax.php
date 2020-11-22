<?php
/*
description : Dokuwiki PubMed2020 plugin
author      : Eric Maeker
email       : eric.maeker[at]gmail.com
lastupdate  : 2020-06-05
license     : Public-Domain
*/

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_pubmed2020 extends DokuWiki_Syntax_Plugin {
  var $pubmed2020;
  var $pubmedCache;
  var $doiUrl = 'http://dx.doi.org/'; //+doi
  var $pmcUrl = 'https://www.ncbi.nlm.nih.gov/pmc/articles/%s/pdf'; //+pmc
  var $outputTpl = array(
      "short" => '%first_author%. %iso%. %pmid% %pmcid% %journal_url% %pmc_url%',
      "long" => '%authors%. %title%. %iso%. %pmid% %pmcid% %journal_url% %pmc_url%',
      "long_tt" => '%authors%. %title_tt%. %iso%. %pmid% %pmcid% %journal_url% %pmc_url%',
      "long_pdf" => '%authors%. %title%. %iso%. %pmid% %pmcid% %journal_url% %pmc_url% %localpdf%',
      "long_tt_pdf" => '%authors%. %title_tt%. %iso%. %pmid% %pmcid% %journal_url% %pmc_url% %localpdf%',
      "long_abstract" => '%authors%. %title%. %iso%. %pmid% %pmcid% %journal_url% %pmc_url% %abstract% %abstractFr% %pmid% %doi%',
      "long_tt_abstract" => '%authors%. %title_tt%. %iso%. %pmid% %pmcid% %journal_url% %pmc_url% %abstract% %abstractFr% %pmid% %doi%',
      "long_abstract_pdf" => '%authors%. %title%. %iso%. %pmid% %pmcid% %journal_url% %pmc_url% %abstract% %abstractFr% %pmid% %doi% %localpdf%',
      "long_tt_abstract_pdf" => '%authors%. %title_tt%. %iso%. %pmid% %pmcid% %journal_url% %pmc_url% %abstract% %abstractFr% %pmid% %doi% %localpdf%',
      "vancouver" => '%vancouver%',
      "vancouver_links" => '%vancouver% %pmid% %pmcid% %pmc_url%',
      "npg" => '%authorsLimit3% %title_tt%. %npg_iso%.',
      );

  // Constructor
  public function __construct(){
    if (!class_exists('pubmed2020_cache'))
      @require_once(DOKU_PLUGIN.'pubmed2020/classes/cache.php');
    if (!class_exists('PubMed2020'))
      @require_once(DOKU_PLUGIN.'pubmed2020/classes/pubmed2020.php');
    $this->pubmed2020  = new PubMed2020();
    $this->pubmedCache = new pubmed2020_cache("pubmed","pubmed","nbib");
  }

  function getType(){ return 'substition'; }
  function getSort(){ return 158; }

  /**
   * Plugin tag format: {{pmid>command:arg}}
   */
  function connectTo($mode) {
    $this->Lexer->addSpecialPattern('\{\{(?:pmid|pmcid)>[^}]*\}\}',
                                    $mode,'plugin_pubmed2020');
  }

 /**
  * Handle the match.
  */
  function handle($match, $state, $pos, Doku_Handler $handler){
    $match = str_replace("{{", "", $match);
    $match = str_replace("}}", "", $match);
    return array($state, explode('>', $match, 2));
  }

  /**
   * Replace tokens in the string \e $outputString using the array $refs.
   * \returns Replaced string content.
   */
  function replaceTokens($outputString, $refs) {
      // Empty array -> exit
      if (count($refs) < 2) { // PMID is always included
        return sprintf($this->getLang('pubmed_not_found'), $refs["pmid"]);
      }
      $outputString = str_replace("%authors%", '<span class="authors">'.implode(', ',$refs["authors"]).'</span>', $outputString);

      $outputString = str_replace("%authorsLimit3%", '<span class="authors">'.$refs["authorsLimit3"].'</span>', $outputString);
      $outputString = str_replace("%npg_iso%", '<span class="iso">'.$refs["npg_iso"].'</span>', $outputString);

      $outputString = str_replace("%first_author%", '<span class="authors">'.$refs["first_author"].'</span>', $outputString);

      if (count($refs["authorsVancouver"]) > 0)
        $outputString = str_replace("%authorsVancouver%", '<span class="vancouver authors">'.implode(', ',$refs["authorsVancouver"]).'</span>', $outputString);
      $outputString = str_replace("%collectif%", '<span class="authors">'.$refs["collectif"].'</span>', $outputString);
      
      if (!empty($refs["pmid"])) 
          $outputString = str_replace("%pmid%", '<a href="'.$refs["url"].'" class="pmid" rel="noopener" target="_blank" title="PMID: '.$refs["pmid"].'">PMID: '.$refs["pmid"].'</a>', $outputString);
      else
          $outputString = str_replace("%pmid%", "", $outputString);

      if (!empty($refs["pmcid"])) 
          $outputString = str_replace("%pmcid%", '<a href="'.$refs["pmcurl"].'" class="pmcid" rel="noopener" target="_blank" title="PMCID: '.$refs["pmcid"].'">PMCID: '.$refs["pmcid"].'</a>', $outputString);
      else
          $outputString = str_replace("%pmcid%", "", $outputString);

      $outputString = str_replace("%type%", '<span class="type">'.$refs["type"].'</span>', $outputString);

      $outputString = str_replace("%title%", '<span class="title">'.$refs["title"].'</span>', $outputString);
      if ($refs["translated_title"])
          $outputString = str_replace("%title_tt%", '<span class="title">'.$refs["translated_title"].'</span>', $outputString);
      else
          $outputString = str_replace("%title_tt%", '<span class="title">'.$refs["title"].'</span>', $outputString);

      $outputString = str_replace("%lang%", '<span class="lang">'.$refs["lang"].'</span>', $outputString);
      $outputString = str_replace("%journal_iso%", '<span class="journal_iso">'.$refs["journal_iso"].'</span>', $outputString);
      $outputString = str_replace("%journal_title%", '<span class="journal_title">'.$refs["journal_title"].'</span>', $outputString);
      $outputString = str_replace("%iso%", '<span class="iso">'.$refs["iso"].'</span>', $outputString);
      $outputString = str_replace("%vol%", '<span class="vol">'.$refs["vol"].'</span>', $outputString);
      $outputString = str_replace("%issue%", '<span class="issue">'.$refs["issue"].'</span>', $outputString);
      $outputString = str_replace("%year%", '<span class="year">'.$refs["year"].'</span>', $outputString);
      $outputString = str_replace("%month%", '<span class="month">'.$refs["month"].'</span>', $outputString);
      $outputString = str_replace("%pages%", '<span class="pages">'.$refs["pages"].'</span>', $outputString);
      $outputString = str_replace("%abstract%", '<br/><span class="abstract">'.$refs["abstract"].'</span>', $outputString);

      $refs["abstractFr"] = $this->pubmedCache->GetTranslatedAbstract($refs["pmid"]);
      if (empty($refs["abstractFr"])) {
        $gg =  "https://translate.google.com/#view=home";
        $gg .= "&op=translate&sl=auto&tl=fr&text=";
        $gg .= urlencode($refs["abstract"]);
        $outputString = str_replace("%abstractFr%", '<a class="abstractFr" href="'.$gg.'" rel="noopener" target="_blank">FR</a>', $outputString);
      } else {
        // TODO: Create a form to send french abstrat to this class
        // TODO: Allow to store it in a separate file abstractfr_{pmid}.txt
          $outputString = str_replace("%abstractFr%", '<span class="abstract">'.$refs["abstractFr"].'</span>', $outputString);
      }
      
      if (empty($refs["doi"])) {
        $outputString = str_replace("%doi%", "", $outputString);
        $outputString = str_replace("%journal_url%", "", $outputString);
      } else {
        $outputString = str_replace("%doi%", '<span class="doi">'.$refs["doi"].'</span>', $outputString);
        $outputString = str_replace("%journal_url%", '<a href="'.$this->doiUrl.$refs["doi"].'" class="journal_url" rel="noopener" target="_blank" title="'.$refs["iso"].'"></a>', $outputString);
      }
      if (empty($refs["pmc"]))
        $outputString = str_replace("%pmc_url%", "", $outputString);
      else
        $outputString = str_replace("%pmc_url%", '<a href="'.sprintf($this->pmcUrl, $refs["pmc"]).'" class="pmc_url" rel="noopener" target="_blank" title="'.$refs["pmc"].'"></a>', $outputString);

    // Check local PDF using cache
    $localPdf = $this->pubmedCache->GetLocalPdfPath($refs["pmid"], $refs["doi"]);
    if (empty($localPdf)) {
        $outputString = str_replace("%localpdf%", 'No PDF', $outputString);
    } else {
        $outputString = str_replace("%localpdf%", ' <a href="'.$localPdf.'" class="localPdf" rel="noopener" target="_blank" title="'.$localPdf.'">PDF</a>', $outputString);
    }

      $outputString = str_replace("%vancouver%",  '<span class="vancouver">'.$refs["vancouver"].'</span>', $outputString);

      // Remove ..
      $outputString = str_replace(".</span>.",  '.</span>', $outputString);
      return $outputString;
  }

  /**
   * Create output
   * We have different database to extract data
   * "pmid" = pubmed
   * "pmcid" = pmc
   */
  function render($mode, Doku_Renderer $renderer, $data) {
    if ($mode != 'xhtml')
      return false;
    // Get the command and its arg(s) 
    list($state, $query) = $data;
    list($base, $req) = $query;
    list($cmd, $id) = explode(':', $req, 2);
    $cmd = strtolower($cmd);

    // If command is empty (in this case, command is the numeric pmids)
    // Catch default command in plugin's preferences
    $regex = '/^[0-9,]+$/';
    if (preg_match($regex, $cmd) === 1) {
      $id = $cmd;
      $cmd = $this->getConf('default_command');
    }

    // Manage the article reference commands in :
    //   short, long, long_abstract, vancouver,
    //   or user
    $this->outputTpl["user"] = $this->getConf('user_defined_output');

    if (array_key_exists($cmd, $this->outputTpl)) {
      $multipleIds = strpos($id, ",");
      if ($multipleIds) {
        $renderer->doc .= "<ul>";
      }        
      $id = explode(",", $id);
      foreach ($id as $curId) {
        $renderer->doc .= $this->getIdOutput($cmd, $base, $curId, $multipleIds);
      }
      if ($multipleIds) {
        $renderer->doc .= "</ul>";
      }
    } else {
      // Manage all other commands
      switch($cmd) {
        case 'test': // Ok PubMed2020
            $this->runTests();
            return true;
        case 'raw_medline': // Ok PubMed2020
          // Check multiple PMIDs (PMIDs can be passed in a coma separated list)
          $multipleIds = strpos($id, ",");
          $id = explode(",", $id);
          foreach ($id as $curId) {
            if (!is_numeric($curId)){
              $renderer->doc .= sprintf($this->getLang('pubmed_wrong_format'));
              return false;
            }
            $raw = $this->getMedlineContent($base, $curId);
            if (empty($raw)) {
              $renderer->doc .= sprintf($this->getLang('pubmed_not_found'), $curId);
              return false;
            }
            $renderer->doc .= "<pre>".htmlspecialchars($raw, ENT_QUOTES)."</pre>";
          }  // Foreach PMIDs
          return true;
        case 'clear_raw_medline':
          $this->pubmedCache->clearCache();
          $renderer->doc .= 'Cleared.';
          return true;
        case 'remove_dir':
          $this->pubmedCache->removeDir();
          $renderer->doc .= 'Directory cleared.';
          return true;
        case 'search':
          $renderer->doc .='<div class="pubmed">';
          $renderer->doc .= '<a class="pmid" rel="noopener" target="_blank" href="';
          $renderer->doc .= $this->pubmed2020->getPubmedSearchURL($id);
          $renderer->doc .= '">'.$id.'</a>';
          $renderer->doc .='</div>';
          return true;
        case 'recreate_cross_refs':
          $this->pubmedCache->recreateCrossRefFile();
          return true;
        case 'full_pdf_list':
          // Get all PMID from cache
          $mediaList = array_keys($this->pubmedCache->getAllMediaPaths());
          // Get all PMID using the local PDF filename
          $pdfPmids = $this->pubmedCache->GetAllAvailableLocalPdfByPMIDs();
          // Remove all local PDF PMIDs already in the media list
          $pdfPmids = array_diff($pdfPmids, $mediaList); 
          // Remove all pdfPmid if present in the mediaList
          $pdfDois = $this->pubmedCache->GetAllAvailableLocalPdfByDOIs();
          // Get PMIDs from DOIs
          $pmids = $this->pubmedCache->PmidFromDoi($pdfDois);

//           $i = 0;
          foreach($pdfDois as $doi) {
//             if (++$i == 5)
//                break;
            $raw = $this->pubmed2020->getDataFromCtxp($base, "", $doi);
            if (!empty($raw)) {
              $this->pubmedCache->saveRawMedlineContent($base, $raw);
            }
          }

          // Create a complete list of PMIDs to show
          //$fullPmids = array_merge($pdfPmids, $pmids, $mediaList);
          $fullPmids = array_merge($pdfPmids, $pmids);
          // Check multiple PMIDs (PMIDs can be passed in a coma separated list)
          $renderer->doc .= "<ul>";
          foreach($fullPmids as $currentPmid) {
            $renderer->doc .= $this->getIdOutput("long_abstract", $base, $currentPmid, true);
          }  // Foreach PMIDs
          foreach($pdfDois as $doi) {
            $renderer->doc .= 
                "<a href='".$this->pubmedCache->GetDoiPdfUrl($doi).
                "' title='".$doi.
                "'><img src='".$this->pubmedCache->GetDoiPdfThumbnailUrl($doi).
                "' alt='".$doi.
                "'/></a>";
          }  // Foreach PMIDs
          $renderer->doc .= "</ul>";
          return true;

        default: // Ok PubMed2020
          // Command was not found..
          $renderer->doc .= '<div class="pdb_plugin">';
          $renderer->doc .= sprintf($this->getLang('plugin_cmd_not_found'),$cmd);
          $renderer->doc .= '</div>';
          $renderer->doc .= '<div class="pdb_plugin_text">';
          $renderer->doc .= $this->getLang('pubmed_available_cmd');
          $renderer->doc .= '</div>';
          return true;
      }
    }
  }


  /**
  * Get Medline raw data from cache or get it from NCBI
  */
  function getMedlineContent($base, $id) {
    global $conf;
    $cached = $this->pubmedCache->getMedlineContent($base, $id);
    if ($cached !== false) { 
      return $cached; 
    }
    // Get content from PubMed website
    $raw = $this->pubmed2020->getDataFromCtxp($base, $id);
    // Save to cache
    $this->pubmedCache->saveRawMedlineContent($base, $raw);
    return $raw;
  }
  
  /**
   * Check PMID format
   */
  function checkIdFormat($base, $id) {
    // Check PMID/PMCID format (numeric, 7 or 8 length)
    if (!is_numeric($id) || (strlen($id) < 6 || strlen($id) > 8)) {
      return false;
    }
    return true;
  } // Ok pubmed2020

  /**
   * Get pubmed string output according to the given unique 
   * ID code passed and the command.
   * $multipleIds : boolean, use it if the output in inside a multiple ids request
   */
  function getIdOutput($cmd, $base, $id, $multipleIds) {
     if (!$this->checkIdFormat($base, $id)) {
        return sprintf($this->getLang('pubmed_wrong_format'));
      }

      // Get article content (from cache or web)
      $raw = $this->getMedlineContent($base, $id);
      if (empty($raw)) {
        return sprintf($this->getLang('pubmed_not_found'), $id);
        return false;
      }

      // Get the abstract of the article
      $refs = $this->pubmed2020->readMedlineContent($raw, $this);

      // Catch updated user output template
      $outputTpl['user'] = $this->getConf('user_defined_output');

      // Construct reference to article (author.title.rev.year..) according to command
      $output = "";
      if ($multipleIds)
        $output .= "<li>";
        
      if (empty($this->outputTpl[$cmd]))
          $cmd = "long_abstract";

      // $cmd contains abstract -> use div instead of span
      $block = "span";
      if (strpos($cmd, 'abstract') !== false) {
        $block = "div";
      }

      $output .= "<{$block} class=\"pubmed\"><{$block} class=\"{$cmd}\"";
      if ($multipleIds)
        $output .= ' style="margin-bottom:1em"';
      $output .= ">";

      $output .= $this->replaceTokens($this->outputTpl[$cmd], $refs);
      $output .= "</{$block}></{$block}>";
      if ($multipleIds)
        $output .= "</li>";
      
      return $output;
  } // Ok pubmed2020

  /**
   * Only for dev usage
   */
  function runTests() {
    echo "Starting PubMed2020 Tests<br>";
    // Test CTXP URLs
    $retrieved = $this->pubmed2020->getDataFromCtxp("pmid", "15924077", "doi");

    // Test MedLine Format Reader
    $myfile = fopen(DOKU_PLUGIN.'pubmed/tests/PM15924077.nbib', "r") or die("Unable to open file!");
    $s = fread($myfile, filesize(DOKU_PLUGIN.'pubmed/tests/PM15924077.nbib'));
    fclose($myfile);
    
    // Check retrieved files
    if ($retrieved === $s)
      echo "File Content: Ok".PHP_EOL;
    else
      echo "File Content: NOT Ok".PHP_EOL;

    $this->pubmed2020->readMedlineContent($s, "PMID", $this);
  }
  
}

?>