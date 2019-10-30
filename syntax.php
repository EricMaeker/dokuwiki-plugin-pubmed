<?php
/*
description : Syntax plugin, PubMed article references integrator
author      : Eric Maeker (based on Ikuo Obataya work)
email       : eric[at]maeker.fr
lastupdate  : 2018-07-08
license     : GPL 2 (http://www.gnu.org/licenses/gpl.html)
*/

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_pubmed extends DokuWiki_Syntax_Plugin {
  var $ncbi;
  var $xmlCache;
  var $doiUrl = 'http://dx.doi.org/'; //+doi
  var $pmcUrl = 'https://www.ncbi.nlm.nih.gov/pmc/articles/%s/pdf'; //+pmc
  var $outputTpl = array(
      "short" => '%first_author%. %iso%. %pmid% %journal_url% %pmc_url%',
      "long" => '%authors%. %title%. %iso%. %pmid% %journal_url% %pmc_url%',
      "long_pdf" => '%authors%. %title%. %iso%. %pmid% %journal_url% %pmc_url% %scihub_url% %localpdf%',
      "long_abstract" => '%authors%. %title%. %iso%. %pmid% %journal_url% %pmc_url% %abstract% %abstractFr% %pmid% %doi%',
      "long_abstract_pdf" => '%authors%. %title%. %iso%. %pmid% %journal_url% %pmc_url% %abstract% %abstractFr% %pmid% %doi% %localpdf%',
      "vancouver" => '%vancouver%',
      );

  // Constructor
  public function __construct() {
    if (!class_exists('plugin_cache'))
      @require_once(DOKU_PLUGIN.'pubmed/classes/cache.php');
    if (!class_exists('ncbi'))
      @require_once(DOKU_PLUGIN.'pubmed/classes/_NCBI.php');
    $this->ncbi     = new ncbi();
    $this->xmlCache = new plugin_cache("ncbi_esummary","pubmed","xml.gz");
  }

  function getType() { return 'substition'; }
  function getSort() { return 158; }

  /**
   * Plugin tag format: {{pubmed>command:arg}}
   */
  function connectTo($mode) {
    $this->Lexer->addSpecialPattern('\{\{pubmed>[^}]*\}\}',$mode,'plugin_pubmed');
  }

 /**
  * Handle the match. Accepted commands:
  * - long: integrate the full article reference. Arg must be a valid PMID number
  * - short: integrate a simplified article reference. Arg must be a valid PMID number
  * - long_abstract: integrate the full article reference and its abstract (if available). Arg must be a valid PMID number
  * - summaryxml: Arg must be a valid PMID number
  * - clear_summary: clear all cached summary of the selected PMID. No args required
  * - remove_dir: clear all cache dir. No args required
  * - search: Create a pubmed search query using the arg. Arg must be a valid pubmed search (including MeSH terms)
  * - user: using plugin configuration, you can define a user specific tokened string output format
  * - full_list
  * - recreate_cross_refs
  */
  function handle($match, $state, $pos, Doku_Handler $handler){
    $match = substr($match,9,-2);
    return array($state, explode(':', $match, 2));
  }

  /**
   * Replace tokens in the string \e $outputString using the array $refs.
   * \returns Replaced string content.
   */
  function replaceTokens($outputString, $refs) {
      // Empty array -> exit
      if (count($refs) < 2) { // PMID is always included
        return sprintf($this->getLang('pubmed_not_found'),$refs["pmid"]);
      }

      /*
		  "type" => "book", "article"
		  "publisherName" (book)
		  "publisherLocation" (book)
		  "collectionTitle" (book)
		  "copyright" (book)
		  "authorsVancouver" (article)
		  "vancouver"
      */

      $outputString = str_replace("%authors%", '<span class="authors">'.implode(', ',$refs["authors"]).'</span>', $outputString);
      $outputString = str_replace("%first_author%", '<span class="authors">'.$refs["first_author"].'</span>', $outputString);
      if (count($refs["authorsVancouver"]) > 0)
        $outputString = str_replace("%authorsVancouver%", '<span class="vancouver authors">'.implode(', ',$refs["authorsVancouver"]).'</span>', $outputString);
      $outputString = str_replace("%collectif%", '<span class="authors">'.$refs["collectif"].'</span>', $outputString);
      
      $outputString = str_replace("%pmid%", '<a href="'.$refs["url"].'" class="pmid" target="_blank" title="PMID: '.$refs["pmid"].'">PMID : '.$refs["pmid"].'</a>', $outputString);
      $outputString = str_replace("%type%", '<span class="type">'.$refs["type"].'</span>', $outputString);

      $outputString = str_replace("%title%", '<span class="title">'.$refs["title"].'</span>', $outputString);
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

      $refs["abstractFr"] = $this->xmlCache->GetTranslatedAbstract($refs["pmid"]);
      if (empty($refs["abstractFr"])) {
        $gg =  "https://translate.google.com/#view=home";
        $gg .= "&op=translate&sl=auto&tl=fr&text=";
        $gg .= urlencode($refs["abstract"]);
        $outputString = str_replace("%abstractFr%", '<a class="abstractFr" href="'.$gg.'" target="_blank">FR</a>', $outputString);
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
        $outputString = str_replace("%journal_url%", '<a href="'.$this->doiUrl.$refs["doi"].'" class="journal_url" target="_blank" title="'.$refs["iso"].'"></a>', $outputString);
      }
      if (empty($refs["pmc"]))
        $outputString = str_replace("%pmc_url%", "", $outputString);
      else
        $outputString = str_replace("%pmc_url%", '<a href="'.sprintf($this->pmcUrl, $refs["pmc"]).'" class="pmc_url" target="_blank" title="'.$refs["pmc"].'"></a>', $outputString);

    // Check local PDF using cache
    $localPdf = $this->xmlCache->GetLocalPdfPath($refs["pmid"], $refs["doi"]);
    if (empty($localPdf)) {
        $outputString = str_replace("%localpdf%", 'No PDF', $outputString);
    } else {
        $outputString = str_replace("%localpdf%", ' <a href="'.$localPdf.'" class="localPdf" target="_blank" title="'.$localPdf.'">PDF</a>', $outputString);
    }

      $outputString = str_replace("%vancouver%",  '<span class="vancouver">'.$refs["vancouver"].'</span>', $outputString);

      // Remove ..
      $outputString = str_replace(".</span>.",  '.</span>', $outputString);
      return $outputString;
  }

  /**
   * Create output
   */
  function render($mode, Doku_Renderer $renderer, $data) {
    if ($mode != 'xhtml')
      return false;

    // Get the command and its arg(s) 
    list($state, $query) = $data;
    list($cmd, $pmid) = $query;

    // Lowering command string
    $cmd = strtolower($cmd);

    // If command is empty (in this case, command is the numeric pmids)
    // Catch default command in plugin's preferences
    $regex = '/^[0-9,]+$/';
    if (preg_match($regex, $cmd) === 1) {
      $pmid = $cmd;
      $cmd = $this->getConf('default_command');
    }

    // Manage the article reference commands in :
    //   short, long, long_abstract, vancouver,
    //   or user
    if (array_key_exists($cmd, $this->outputTpl)) {

      $multiplePmids = false;

      // Check multiple PMIDs (PMIDs can be passed in a coma separated list)
      if (strpos($pmid, ",") !== false) {
        $multiplePmids = true;
        $renderer->doc .= "<ul>";
      }
        
      $pmid = explode(",", $pmid);
      foreach ($pmid as $currentPmid) {
        $renderer->doc .= $this->getPmidOutput($cmd, $currentPmid, $multiplePmids);
      }  // Foreach PMIDs

      if ($multiplePmids) {
        $renderer->doc .= "</ul>";
      }

    } else {
      // Manage all other commands (summaryxml, clear_summary, remove_dir, search)
      switch($cmd) {
        case 'summaryxml':
          if (!is_numeric($pmid)){
            $renderer->doc.=sprintf($this->getLang('pubmed_wrong_format'));
            return false;
          }
          $xml = $this->getSummaryXML($pmid);
          if(empty($xml)){
            $renderer->doc.=sprintf($this->getLang('pubmed_not_found'),$pmid);
            return false;
          }
          $renderer->doc .= "<pre>".htmlspecialchars($xml,ENT_QUOTES)."</pre>";
          return true;

        case 'clear_summary':
          $this->xmlCache->ClearCache();
          $renderer->doc .= 'Cleared.';
          return true;

        case 'remove_dir':
          $this->xmlCache->RemoveDir();
          $renderer->doc .= 'Directory cleared.';
          return true;
          
        case 'search':
          $renderer->doc.='<div class="pubmed">';
          $renderer->doc.= '<a class="pmid" href="'.$this->ncbi->getPubmedSearchURL($pmid).'">'.$pmid.'</a>';
          $renderer->doc.='</div>';
          return true;

        case 'recreate_cross_refs':
          $this->xmlCache->RecreateCrossRefFile();
          return true;

        case 'full_pdf_list':
          // Get all PMID from cache
          $mediaList = array_keys($this->xmlCache->GetAllMediaPaths());
          // Get all PMID using the local PDF filename
          $pdfPmids = $this->xmlCache->GetAllAvailableLocalPdfByPMIDs();
          // Remove all local PDF PMIDs already in the media list
          $pdfPmids = array_diff($pdfPmids, $mediaList); 
          // Remove all pdfPmid if present in the mediaList
          $pdfDois = $this->xmlCache->GetAllAvailableLocalPdfByDOIs();
          // Get PMIDs from DOIs
          $pmids = $this->xmlCache->PmidFromDoi($pdfDois);

//           $i = 0;
          foreach($pdfDois as $doi) {
//             if (++$i == 5)
//                break;
            $xml = $this->ncbi->SummaryXml('pubmed', "", $doi);
            if (!empty($xml)) {
              $this->xmlCache->SavePubMedXmlSummaryText($xml);
            }
          }

          // Create a complete list of PMIDs to show
          //$fullPmids = array_merge($pdfPmids, $pmids, $mediaList);
          $fullPmids = array_merge($pdfPmids, $pmids);
          // Check multiple PMIDs (PMIDs can be passed in a coma separated list)
          $renderer->doc .= "<ul>";
          foreach($fullPmids as $currentPmid) {
            $renderer->doc .= $this->getPmidOutput("long_abstract", $currentPmid, true);
          }  // Foreach PMIDs
          foreach($pdfDois as $doi) {
            $renderer->doc .= 
                "<a href='".$this->xmlCache->GetDoiPdfUrl($doi).
                "' title='".$doi.
                "'><img src='".$this->xmlCache->GetDoiPdfThumbnailUrl($doi).
                "' alt='".$doi.
                "'/></a>";
          }  // Foreach PMIDs
          $renderer->doc .= "</ul>";
          return true;

        default:
          // Command was not found..
          $renderer->doc.='<div class="pdb_plugin">'.sprintf($this->getLang('plugin_cmd_not_found'),$cmd).'</div>';
          $renderer->doc.='<div class="pdb_plugin_text">'.$this->getLang('pubmed_available_cmd').'</div>';
          return true;
          $renderer->doc.=sprintf($this->getLang('pubmed_wrong_format'));
          return true;
      }
    }
  }


  /**
  * Get summary XML from cache or NCBI
  */
  function getSummaryXml($pmid) {
    global $conf;
    $cachedXml = $this->xmlCache->GetMediaText($pmid);
    if ($cachedXml !== false) { 
      return $cachedXml; 
    }

    // Get summary XML from PubMed website
    $summary = $this->ncbi->SummaryXml('pubmed',$pmid);
    // Save to cache
    $this->xmlCache->SavePubMedXmlSummaryText($summary);
//     $cachePath = $this->xmlCache->GetMediaPath($pmid);
//     if (!empty($summary)) {
//       if(io_saveFile($cachePath, $summary)){
//         chmod($cachePath, $conf['fmode']);
//       }
//     }
    return $summary;
  }
  
  /**
   * Check PMID format
   */
  function checkPmidFormat($pmid) {
    // Check PMID format (numeric, 7 or 8 length)
    if (!is_numeric($pmid) || (strlen($pmid) < 6 || strlen($pmid) > 8)) {
      return false;
    }
    return true;
  }

  /**
   * Get pubmed string output according to the given unique PMID code passed and the command
   */
  function getPmidOutput($cmd, $pmid, $multiplePmids) {
     if (!$this->checkPmidFormat($pmid)) {
        return sprintf($this->getLang('pubmed_wrong_format'));
      }

      // Get article summary (from cache or web)
      $xml = $this->getSummaryXML($pmid);
      if (empty($xml)) {
        return sprintf($this->getLang('pubmed_not_found'),$pmid);
        return false;
      }

      // Get the abstract of the article
      $refs = $this->ncbi->getAbstract($xml, $pmid, $this);

      // Catch updated user output template
      $outputTpl['user'] = $this->getConf('user_defined_output');

      // Construct reference to article (author.title.rev.year..) according to command
      $output = "";
      if ($multiplePmids)
        $output .= "<li>";
        
      if (empty($this->outputTpl[$cmd]))
          $cmd = "long_abstract";

      // $cmd contains abstract -> use div instead of span
      $block = "span";
      if (strpos($cmd, 'abstract') !== false) {
        $block = "div";
      }

      $output .= "<{$block} class=\"pubmed\"><{$block} class=\"{$cmd}\"";
      if ($multiplePmids)
        $output .= ' style="margin-bottom:1em"';
      $output .= ">";

      $output .= $this->replaceTokens($this->outputTpl[$cmd], $refs);
      $output .= "</{$block}></{$block}>";
      if ($multiplePmids)
        $output .= "</li>";
      
      return $output;
  }
}

?>
