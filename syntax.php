<?php
/*
description : Dokuwiki PubMed2020 plugin
author      : Eric Maeker
email       : eric.maeker[at]gmail.com
lastupdate  : 2020-12-27
license     : Public-Domain
*/

/**
 * Add crossref eg: https://api.crossref.org/works/10.1016/j.npg.2020.12.002
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_pubmed2020 extends DokuWiki_Syntax_Plugin {
  var $documentFormat;
  var $useDocumentFormat;
  var $pubmed2020;
  var $pubmedCache;
  var $doiUrl = 'http://dx.doi.org/'; //+doi
  var $pmcUrl = 'https://www.ncbi.nlm.nih.gov/pmc/articles/%s/pdf'; //+pmc
  var $twitterUrl = 'https://twitter.com/intent/tweet?%s';
  var $outputTpl = array(
      "short" => '%first_author%. %iso%.<br/>%pmid_url% %pmcid_url% %doi_url%',
      "long" => '%authors%. %title%. %iso%.<br/>%pmid_url% %pmcid_url% %doi_url%',
      "long_tt" => '%authors%. %title_tt%. %iso%.<br/>%pmid_url% %pmcid_url% %doi_url%',
      "long_pdf" => '%authors%. %title%. %iso%.<br/>%pmid_url% %pmcid_url% %doi_url% %localpdf% %tweet%',
      "long_tt_pdf" => '%authors%. %title_tt%. %iso%.<br/>%pmid_url% %pmcid_url% %doi_url% %localpdf% %tweet%',
      "long_abstract" => '%authors%. %title%. %iso%.<br/>%pmid_url% %pmcid_url% %doi_url% %abstract% %abstractFr% %tweet%',
      "long_tt_abstract" => '%authors%. %title_tt%. %iso%.<br/>%pmid_url% %pmcid_url% %doi_url% %abstract% %abstractFr% %tweet%',
      "long_abstract_pdf" => '%authors%. %title%. %iso%.<br/>%pmid_url% %pmcid_url% %doi_url% %abstract% %abstractFr% %localpdf%',
      "long_tt_abstract_pdf" => '%authors%. %title_tt%. %iso%.<br/>%pmid_url% %pmcid_url% %doi_url% %abstract% %abstractFr% %localpdf%',
      "vancouver" => '%vancouver%',
      "vancouver_links" => '%vancouver%<br/>%pmid_url% %pmcid_url%',
      "npg" => '%authorsLimit3% %title_tt%. %npg_iso%.',
      "npg_full" => '%npg_full%',
      "npg_full_links" => '%npg_full% %pmid_url% %pmcid_url%',
      "gpnv_full" => '%gpnv_full%',
      // Add item one by one
      "authors" => '%authors%',
      "title" => '%title%',
      "year" => '%year%',
      "date" => '%month% %year%',
      "journal" => '%journal_title%',
      "journaliso" => '%journal_iso%',
      "doi_link" => '%doi% %journal_url%',
      "abstract_wiki" => '%abstract_wiki%',
      "abstract_html" => '%abstract_html%',
      "listgroup" => '%listgroup%'
      );
  var $commands = Array(
    'addtt',
    'addhash_fr',
    'convertid',
    'test',
    'raw_medline',
    'clear_raw_medline',
    'remove_dir',
    'search',
    'recreate_cross_refs',
    'full_pdf_list',
  );

  // Constructor
  public function __construct(){
    if (!class_exists('pubmed2020_cache'))
      @require_once(DOKU_PLUGIN.'pubmed2020/classes/cache.php');
    if (!class_exists('PubMed2020'))
      @require_once(DOKU_PLUGIN.'pubmed2020/classes/pubmed2020.php');
    $this->pubmed2020  = new PubMed2020();
    $this->pubmedCache = new pubmed2020_cache("pubmed","pubmed","nbib");
    $this->documentFormat = $this->getConf('default_command');
    $this->useDocumentFormat = false;
  }

  function getType() { return 'substition'; }
  function getSort() { return 306; }

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


  function _span($refs, $class, $id) {
    // No data
    if (empty($refs[$id]))
      return "";
    // Data = array
    if (is_array($refs[$id]))
      return "<span class=\"$class\">".hsc(implode(", ",$refs[$id]))."</span>";
    // Default
    return "<span class=\"$class\">".hsc($refs[$id])."</span>";
  }

  function _a($refs, $class, $href, $id, $text) {
    // No data
    if (empty($refs[$id]))
      return "";
    // Default
    return "[<a class=\"$class\" href=\"$href\" ".
           "rel=\"noopener\" target=\"_blank\" ".
           "title=\"$text\" ".
           ">$text</a>]";
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
      // $r = replacement key/value: key=tag to replace in string, value=replacement string
      $r = array(
         // IDs
        "pmid"          => "",
        "pmcid"         => "",
        "doi"           => "",
         
        // AUTHORS
        "authors"       => $this->_span($refs, "authors", "authors"),
        "authorsLimit3" => $this->_span($refs, "authors", "authorsLimit3"),
        "first_author"  => $this->_span($refs, "authors", "first_author"),
        "authorsVancouver" => $this->_span($refs, "vancouver authors", "authorsVancouver"),
        "collectif"     => $this->_span($refs, "authors", "collectif"),
        "corporate_author"=> $this->_span($refs, "authors", "corporate_author"),

        // CITATION
        "vancouver"     => "",

        "npg_iso"       => $this->_span($refs, "iso", "npg_iso"),
        "npg_full"      => $this->_span($refs, "npg", "npg_full"),

        "gpnv_full"      => $this->_span($refs, "gpnv_authors", "gpnv_full_authors").
                            $this->_span($refs, "gpnv_title", "gpnv_full_title").
                            $this->_span($refs, "gpnv_journal", "gpnv_full_journal").
                            $this->_span($refs, "gpnv_iso", "gpnv_full_iso")
                            ,

        // URLS
        "pmid_url"      => $this->_a($refs, "pmid", $refs["url"], 
                                      "url", "PMID: ".$refs["pmid"]),
        "pmcid_url"     => $this->_a($refs, "pmcid", $refs["pmcurl"], 
                                     "pmcid", "PMCID: ".$refs["pmcid"]),
        "pmc_url"       => $this->_a($refs, "pmcid", $refs["pmcurl"], 
                                     "pmcid", "PMCID: ".$refs["pmcid"]),
        "doi_url"       => $this->_a($refs, "pmcid", $this->doiUrl.$refs["doi"],
                                      "doi", "DOI: ".$refs["doi"]),
        "journal_url"   => $this->_a($refs, "pmid", $this->doiUrl.$refs["doi"], 
                                      "pmid", $refs["iso"]),

        "tweet_current" => "<a href='".$this->_createTwitterUrl($refs, true).
                             "' rel='noopener' target='_blank''>Twitter cet article ".
                             "(lien vers ce site)</a>",
        "tweet_pmid"    => "<a href='".$this->_createTwitterUrl($refs).
                             "' rel='noopener' target='_blank''>".
                             "Twitter cet article (lien vers l'article)</a>",
        
        // TITLE
        "title"         => "",
        "booktitle"     => $this->_span($refs, "title", "booktitle"),
        "title_low"     => "",
        "translated_title" => $this->_span($refs, "title", "translated_title"),
        "translated_title_low" => $this->_span($refs, "title", "translated_title_low"),
        "title_tt"      => $this->_span($refs, "title", "translated_title"),

        // JOURNAL
        "journal_iso"   => "",
        "journal_title" => "",
        "journal_iso"   => "",

        // OTHERS
        "lang"          => "",
        "iso"           => "",
        "vol"           => "",
        "issue"         => "",
        "year"          => "",
        "month"         => "",
        "pages"         => "",
        "abstract"      => '<br/><span class="abstract">'.$refs["abstract_html"].'</span>',
        "abstract_wiki" => $refs["abstract_wiki"],
        "abstract_html" => $refs["abstract_html"],
        "type"          => "",
        "country"       => "",
        "copyright"     => "",
        "collection_title" => "",
        "publisher"     => "",
    );
    $r["tweet"] = "<div class='pubmed tweetme'>".
                  $r["tweet_pmid"]."<br/>".
                  $r["tweet_current"].
                  "</div>";
    // Check if we have the local PDF of the paper
    $localPdf = $this->pubmedCache->GetLocalPdfPath($refs["pmid"], $refs["doi"]);
    if (empty($localPdf)) {
      $r["localpdf"] = $this->_span($refs, "nopdf", "No PDF");
    } else {
      $r["localpdf"] = $this->_a($refs, "localPdf", $localPdf, "pmid", "PDF");
    }

    foreach($r as $key => $value) {
      $v = $value;
      if (empty($v))
        $v = $this->_span($refs, $key, $key);
      $outputString = str_replace("%".$key."%", $v, $outputString);
    }
      
      // note tt -> if empty = title
      // note doi & journal_url -> if empty add nothing
      //echo print_r($r);
      
      $refs["abstractFr"] = $this->pubmedCache->GetTranslatedAbstract($refs["pmid"]);
      if (empty($refs["abstractFr"])) {
        $outputString = str_replace("%abstractFr%", '<div class="abstractFr"><a class="abstractFr" href="'.$refs["googletranslate_abstract"].'" rel="noopener" target="_blank">Traduction automatique en Français sur Google Translate</a></div>', $outputString);
      } else {
        // TODO: Create a form to send french abstrat to this class
        // TODO: Allow to store it in a separate file abstractfr_{pmid}.txt
          $outputString = str_replace("%abstractFr%", '<span class="abstract">'.$refs["abstractFr"].'</span>', $outputString);
      }

    // Bootstrap listgroup
    if (strpos($outputString, "%listgroup%") !== false) {
      if (empty($refs["translated_title"])) {
      $lg = "<div class='bs-wrap bs-wrap-list-group list-group pubmed'>";
      $lg .= "<ul class='list-group pubmed'>";
      $lg .= "<li class='level1 list-group-item list-group-item-warning pubmed'>";
      $lg .=   "<strong>".$refs["title"]."</strong></li>";
      } else {
      $lg = "<div class='bs-wrap bs-wrap-list-group list-group'>";
      $lg .= "<ul class='list-group'>";
      $lg .= "<li class='level1 list-group-item list-group-item-warning pubmed'>";
      $lg .=   "<strong>".$refs["translated_title"]."</strong></li>";

      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=   " <i class='dw-icons fa fa-file-o fa-fw' style='font-size:16px'></i> ";
      $lg .=   $refs["title"]."</li>";
      }

      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=   " <i class='dw-icons fa fa-users fa-fw' style='font-size:16px'></i>";
      $lg .=   " <span class='pubmed'><span class='authors'>";
      $lg .=   implode(', ',$refs["authors"]);
      $lg .=   "</span></span></li>";

      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=   " <i class='dw-icons fa fa-newspaper-o fa-fw' style='font-size:16px'></i>";
      $lg .=   " <span class='pubmed'><span class='journal'><span class='journal_title'>".$refs["journal_title"]."</span></span></span></li>";

      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=   " <i class='dw-icons fa fa-calendar-check-o fa-fw' style='font-size:16px'></i> ";
      $lg .=   "<span class='pubmed'><span class='date'>".$refs["year"]." ".$refs["month"]."</span></li>";

      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=   " <i class='dw-icons fa fa-code fa-fw' style='font-size:16px'></i> ";
      $lg .=   "<span class='pubmed'><span class='iso'>".$refs["iso"]."</span></li>";

      // Keywords
      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=   " <i class='dw-icons fa fa-tags fa-fw' style='font-size:16px'></i> ";
      if (!empty($refs["mesh"])) {
        $lg .=   "<span class='mesh'>".implode(', ',$refs["mesh"])."</span> ";
      } else if (!empty($refs["keywords"])) {
        $lg .=   "<span class='keywords'>".implode(', ',$refs["keywords"])."</span>";
      } else {
        $lg .=   "<span class='keywords'>Aucun mots clés</span>";
      }
      $lg .=   "</li>";

      // User added HASHTAGS
      if (!empty($refs["hashtags"])) {
      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=   " <i class='dw-icons fa fa-hashtag fa-fw' style='font-size:16px'></i> ";
      $lg .=   "<span class='pubmed'><span class='hashtags'>".$refs["hashtags"]."</span></li>";
      }

      // Links
      $lg .= "<li class='level1 list-group-item list-group-item-warning pubmed'>";
      $lg .=   "<strong>Liens</strong></li>";

      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=  " <i class='dw-icons fa fa-external-link fa-fw' style='font-size:16px'></i>";
      $lg .=  " <a href='".$refs["googletranslate_abstract"]."' class='list-group-item pubmed' rel='noopener' target='_blank'>Traduction automatique en Français sur Google Translate</a></li>";

      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=  " <i class='dw-icons fa fa-external-link fa-fw' style='font-size:16px'></i>";
      $lg .=  " <a href='http://dx.doi.org/".$refs["doi"]."' class='list-group-item pubmed' rel='noopener' target='_blank' title='".$refs["doi"]."'>DOI: ".$refs["doi"]."</a></li>";
      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=  " <i class='dw-icons fa fa-external-link fa-fw' style='font-size:16px'></i>";
      $lg .=  " <a href='".$refs["url"]."' class='list-group-item pubmed' rel='noopener' target='_blank' title='PMID: ".$refs["pmid"]."'>PMID: ".$refs["pmid"]."</a></li>";

      if (!empty($refs["similarurl"])) { 
      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=  " <i class='dw-icons fa fa-external-link fa-fw' style='font-size:16px'></i>";
      $lg .=  " <a href='".$refs["similarurl"]."' class='list-group-item pubmed' rel='noopener' target='_blank''>Articles similaires</a></li>";
      }

      if (!empty($refs["citedbyurl"])) { 
      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=  " <i class='dw-icons fa fa-external-link fa-fw' style='font-size:16px'></i>";
      $lg .=  " <a href='".$refs["citedbyurl"]."' class='list-group-item pubmed' rel='noopener' target='_blank''>Cité par</a></li>";
      }

      if (!empty($refs["referencesurl"])) { 
      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=  " <i class='dw-icons fa fa-external-link fa-fw' style='font-size:16px'></i>";
      $lg .=  " <a href='".$refs["referencesurl"]."' class='list-group-item pubmed' rel='noopener' target='_blank''>Références</a></li>";
      }

      if (!empty($refs["pmcid"])) { 
      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=  " <i class='dw-icons fa fa-external-link fa-fw' style='font-size:16px'></i>";
      $lg .=  " <a href='".$refs["pmcurl"]."' class='list-group-item pubmed' rel='noopener' target='_blank''>Texte complet gratuit</a></li>";
      }

      // Twitter
      $lg .= "<li class='level1 list-group-item list-group-item-warning pubmed'>";
      $lg .=   "<strong>Twitter</strong></li>";

      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=  " <i class='dw-icons fa fa-twitter fa-fw' style='font-size:16px'></i>";
      $lg .=  " <a href='".$this->_createTwitterUrl($refs)."' class='list-group-item pubmed' rel='noopener' target='_blank''>Twitter cet article (lien vers l'article)</a></li>";
      $lg .= "<li class='level1 list-group-item pubmed'>";
      $lg .=  " <i class='dw-icons fa fa-twitter fa-fw' style='font-size:16px'></i>";
      $lg .=  " <a href='".$this->_createTwitterUrl($refs, true)."' class='list-group-item pubmed' rel='noopener' target='_blank''>Twitter cet article (lien vers cette page)</a></li>";

      $lg .= "</ul>";
      $lg .= "</div>";
      $outputString = str_replace("%listgroup%", $lg, hsc($outputString));
    }

    // Remove double points separated with a span tag
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
    if (str_contains(":", $req)) {
      list($cmd, $id) = explode(':', $req, 2);
    } else {
      $cmd = $req;
      $id = "";
    }
    $cmd = strtolower($cmd);

    // If command is empty (in this case, command is the numeric pmids)
    // Catch default command in plugin's preferences
    $regex = '/^[0-9,]+$/';
    // if (preg_match($regex, $cmd) === 1) {
    if (empty($id)) {
      $id = $cmd;
      $cmd = $this->documentFormat;
    }

    // Manage the article reference commands in :
    //   short, long, long_abstract, vancouver,
    //   or user
    $this->outputTpl["user"] = $this->getConf('user_defined_output');
    
    // Allow user to define a document format
    if ($cmd === "doc_format") {
      $this->documentFormat = $id;
      $this->useDocumentFormat = true;
      return true;
    } else if ($this->useDocumentFormat && (!in_array($cmd, $this->commands))) {
       $cmd = $this->documentFormat;
    }
    
    //echo $cmd.PHP_EOL;

    if (array_key_exists($cmd, $this->outputTpl)) {
      // Check and open multiple PMIDs
      $multipleIds = strpos($id, ",");
      if ($multipleIds) {
        $renderer->doc .= "<ul>";
      }        
      $id = explode(",", $id);
      
      // With multiple PMIDs, the first one can be a word
      if ($id[0] === "sort") {
        // Remove [0]
        array_shift($id);
        // Sort ids
        sort($id);
        $id = array_reverse($id);
      }
      
      // Remove duplicates
      $id = array_unique($id, SORT_REGULAR);
      
      // Add each PMID to the renderer
      foreach ($id as $curId) {
        $renderer->doc .= $this->getIdOutput($cmd, $base, $curId, $multipleIds);
      }
      
      // Close multiple PMIDs
      if ($multipleIds) {
        $renderer->doc .= "</ul>";
      }
    } else {
      // Manage all other commands
      switch($cmd) {
        case 'addtt': // Ok PubMed2020
            // $id = pmid|translatedTitle
            list($id, $tt) = explode('|', $id, 2);
            $raw = $this->getMedlineContent($base, $id);
            if (strpos($raw, "TT  - ") === false) {
              $raw .= "\nTT  - ".$tt."\n";
            } else {
              // Check raw value
              $pattern = "/TT  - ".$tt."/";
              if (!preg_match($pattern, $raw)) {
                $pattern = "/TT  - .*/";
                $raw = preg_replace($pattern, "\nTT  - ".$tt."\n", $raw);
              }
            }
            $this->pubmedCache->saveRawMedlineContent($base, $raw);
            return true;
        case 'addhash_fr': // Ok PubMed2020
            // $id = pmid|hash1,hash2,hash3
            list($id, $hash) = explode('|', $id, 2);
            $raw = $this->getMedlineContent($base, $id);
            if (strpos($raw, "HASH- ") === false) {
              $raw .= "\nHASH- ".$hash."\n";
            } else {
              // Check raw value
              $pattern = "/HASH- ".$hash."/";
              if (!preg_match($pattern, $raw)) {
                $pattern = "/HASH- .*/";
                $raw = preg_replace($pattern, "\nHASH- ".$hash."\n", $raw);
              }
            }
            $this->pubmedCache->saveRawMedlineContent($base, $raw);
            return true;
        case 'convertid': // Ok PubMed2020
            $r = $this->pubmed2020->convertId($id);
            if ($r) {
              $renderer->doc .= "PMID: ".$r->pmid." ; DOI: ".$r->doi." ; PMC: ".$r->pmcid;
            } else {
              $renderer->doc .= "Id not found: ".$id;
            }
            return true;
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
          $link = $this->pubmed2020->getPubmedSearchURL($id);
          $renderer->doc .='<span class="pubmed">';
          $renderer->doc .= '<a class="pmid" rel="noopener" target="_blank" href="';
          $renderer->doc .= $link[1];
          $renderer->doc .= '">'.$link[0].'</a>';
          $renderer->doc .='</span>';
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
   * Create a link to Tweet the paper
   * - $refs is the full paper references (use pubmed2020 class to get it)
   * - $currentUrl if true tweet with current website URL, if false use the $refs["url"]
   */
  function _createTwitterUrl($refs, $currentUrl = false) {
    // https://developer.twitter.com/en/docs/twitter-for-websites/tweet-button/guides/web-intent
    // 280 characters when text is combined with any passed hashtags, via, or url parameters.

    // HASHTAGS
    if (!empty($refs["hashtags"])) {
      $list = explode(",", $refs["hashtags"]);
      foreach ($list as &$value) {
        $value = trim($value);
        $value = str_replace(" ", "_", $value);
        $value = str_replace("-", "ー", $value);
      }
      $hash = "&hashtags=".implode(",", $list); // Comma separated without #
    } else {
      $hash = "";
    }

    // TEXT
    if (!empty($refs["translated_title"])) {
        $txt  = $refs["translated_title"]."\n\n";
    } else {
        $txt  = $refs["title"]."\n\n";
    }
    $txt .= $refs["journal_iso"]." ".$refs["year"]."\n";
    $txt  = "&text=".rawurlencode($txt);
    
    // URL
    $url = "";
    // Get current page URL
    if ($currentUrl) {
      // Use Twitter URL shorteners
      // See 
      // $conf['twitter_url_shortener_format_pmid'] = "";
      // $conf['twitter_url_shortener_format_pmcid'] = "";
      if (!empty($this->getConf('twitter_url_shortener_format_pmid')) 
          && !empty($refs["pmid"])) {
        $url = $this->getConf('twitter_url_shortener_format_pmid');
        $url = str_replace("%PMID%", $refs["pmid"], $url);
      } else if (!empty($this->getConf('twitter_url_shortener_format_pcmid'))
                 && !empty($refs["pmid"])) {
        $url = $this->getConf('twitter_url_shortener_format_pmcid');
        $url = str_replace("%PMCID%", $refs["pmcid"], $url);
      } else {
        global $ID;
        $url = wl($ID,'',true);
      }
    } else {
      $url = $refs["url"];
    }
    $url = "&url=".rawurlencode($url);

    // VIA
    if (!empty($this->getConf('twitter_via_user_name'))) {
      $via  = "&via=".$this->getConf('twitter_via_user_name');
    }
    //$related = "&related=";

    // Create full link
    $tweet  = "";    
    $tweet .= str_replace(array("-", "#"), "", $hash);
    $tweet .= $txt;
    $tweet .= $url;
    $tweet .= $via;
    $tweet  = substr($tweet, 1);
    $tweet  = sprintf($this->twitterUrl, $tweet);

    return $tweet;
  }

  /**
   * Only for dev usage
   *
   * Tests: 25617070 for author "de la Cruz M"
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