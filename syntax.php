<?php
/*
description : Syntax plugin, PubMed article references integrator
author      : Ikuo Obataya, Eric Maeker
email       : i.obataya[at]gmail_com, eric[at]maeker.fr
lastupdate  : 2017-10-07
license     : GPL 2 (http://www.gnu.org/licenses/gpl.html)
*/

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_pubmed extends DokuWiki_Syntax_Plugin {
  var $ncbi;
  var $xmlCache;
  // Constructor

  function syntax_plugin_pubmed(){
    if (!class_exists('plugin_cache'))
      @require_once(DOKU_PLUGIN.'pubmed/classes/cache.php');
    if (!class_exists('ncbi'))
      @require_once(DOKU_PLUGIN.'pubmed/classes/_NCBI.php');
    $this->ncbi     = new ncbi();
    $this->xmlCache = new plugin_cache("ncbi_esummary","pubmed","xml.gz");
  }

  function getType(){ return 'substition'; }
  function getSort(){ return 158; }

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
  */
  function handle($match, $state, $pos, &$handler){
    $match = substr($match,9,-2);
    return array($state,explode(':', $match, 2));
  }

 /**
  * Create output
  */
  function render($mode, &$renderer, $data) {
    if ($mode!='xhtml')
      return false;

    // Get the command and its arg(s) 
    list($state, $query) = $data;
    list($cmd,$pmid) = $query;

    // Lowering command string
    $cmd = strtolower($cmd);

    // If command is empty (in this case, command is the numeric pmid), catch prefs of the plugin
    if (is_numeric($cmd)) {
      $pmid = $cmd;
      $cmd = $this->getConf('default_command');
    }

    // Manage the article reference commands (short, long, long_abstract)
    if ($cmd=='long' || $cmd=='short' || $cmd=='long_abstract') {
      // Check PMID format
      if (!is_numeric($pmid)) {
        $renderer->doc.=sprintf($this->getLang('pubmed_wrong_format'));
        return false;
      }

      // Get article summary (from cache or web)
      $xml = $this->getSummaryXML($pmid);
      if (empty($xml)) {
        $renderer->doc .= sprintf($this->getLang('pubmed_not_found'),$pmid);
        return false;
      }

      // Get the abstract of the article
      $refs = $this->ncbi->getAbstract($xml, $this);

      // Create output template
      $out = array();
      $out['short'] = '%first_author%. %iso%. %pmid%';
      $out['long'] = '%authors%. %title%. %iso%. %pmid%';
      $out['long_abstract'] = '%authors%. %title%. %iso%. %pmid%. abstract';

      // Construct reference to article (author.title.rev.year..) according to command
      foreach($out as &$outputString) {
        $outputString = str_replace("%authors%",      '<span class="authors">'.implode(', ',$refs["authors"]).'</span>', $outputString);
        $outputString = str_replace("%first_author%", '<span class="authors">'.$refs["first_author"].'</span>', $outputString);
        $outputString = str_replace("%pmid%",         '<a href="'.$refs["url"].'" class="pmid" target="_blank" title="PMID: '.$refs["pmid"].'"></a>', $outputString);
        $outputString = str_replace("%title%",        '<span class="title">'.$refs["title"].'</span>', $outputString);
        $outputString = str_replace("%lang%",         '<span class="lang">'.$refs["lang"].'</span>', $outputString);
        $outputString = str_replace("%journal_iso%",  '<span class="journal_iso">'.$refs["journal_iso"].'</span>', $outputString);
        $outputString = str_replace("%journal_title%", '<span class="journal_title">'.$refs["journal_title"].'</span>', $outputString);
        $outputString = str_replace("%iso%",          '<span class="iso">'.$refs["iso"].'</span>', $outputString);
        $outputString = str_replace("%vol%",          '<span class="vol">'.$refs["vol"].'</span>', $outputString);
        $outputString = str_replace("%issue%",        '<span class="issue">'.$refs["issue"].'</span>', $outputString);
        $outputString = str_replace("%year%",         '<span class="year">'.$refs["year"].'</span>', $outputString);
        $outputString = str_replace("%month%",        '<span class="month">'.$refs["month"].'</span>', $outputString);
        $outputString = str_replace("%pages%",        '<span class="pages">'.$refs["pages"].'</span>', $outputString);
        $outputString = str_replace("%abstract%",     '<br/><span class="abstract">'.$refs["abstract"].'</span>', $outputString);
        $outputString = str_replace("%doi%",          '<span class="doi">'.$refs["doi"].'</span>', $outputString);
        // Remove ..
        $outputString = str_replace(".</span>.",  '.', $outputString);
      }
      $renderer->doc .= '<div class="pubmed"><div class="'.$cmd.'">';
      $renderer->doc .= $out[$cmd];
      $renderer->doc .= "</div></div>";
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
          $renderer->doc .= "<pre>".$xml."</pre>";
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
          $renderer->doc.= '<a href="'.$this->ncbi->getPubmedSearchURL($pmid).'">'.$pmid.'</a>';
          $renderer->doc.='</div>';
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
  function getSummaryXml($pmid){
    global $conf;
    $cachedXml = $this->xmlCache->GetMediaText($pmid);
    if ($cachedXml!==false){ return $cachedXml; }

    // Get summary XML
    $summary = $this->ncbi->SummaryXml('pubmed',$pmid);
    $cachePath = $this->xmlCache->GetMediaPath($pmid);
    if (!empty($summary)){
      if(io_saveFile($cachePath,$summary)){
        chmod($cachePath,$conf['fmode']);
      }
    }
    return $summary;
  }
}

?>
