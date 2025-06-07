<?php
/*
description : Dokuwiki PubMed2020 plugin
author      : Eric Maeker
email       : eric.maeker[at]gmail.com
lastupdate  : 2021-02-09
license     : Public-Domain

Data are stored is RIS format: https://en.wikipedia.org/wiki/RIS_(file_format)
See also: https://citation.crosscite.org/docs.html

convertIds -> https://www.ncbi.nlm.nih.gov/pmc/tools/id-converter-api/
*/

if(!defined('DOKU_INC')) die();

class PubMed2020 {
  var $HttpClient;
  // New PubMed interface. See https://api.ncbi.nlm.nih.gov/lit/ctxp
  var $ctxpBaseURL = "https://api.ncbi.nlm.nih.gov/lit/ctxp/v1/";
  var $ctxpURLs = array(
        "pmid" => "pubmed/?format=medline&id=%s",
        "pmcid" => "pmc/?format=medline&id=%s",
      );

  var $pubmedURL       = 'https://pubmed.ncbi.nlm.nih.gov/%s';
  var $pmcURL          = 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC%s';
  var $pubmedSearchURL = 'https://pubmed.ncbi.nlm.nih.gov/?term=%s';
  var $similarURL      = 'https://pubmed.ncbi.nlm.nih.gov/?linkname=pubmed_pubmed&from_uid=%s';
  var $citedByURL      = 'https://pubmed.ncbi.nlm.nih.gov/?linkname=pubmed_pubmed_citedin&from_uid=%s';
  var $convertId       = 'https://www.ncbi.nlm.nih.gov/pmc/utils/idconv/v1.0/?ids=%s&format=json&versions=no&showaiid=no';
  var $referencesURL   = 'https://pubmed.ncbi.nlm.nih.gov/%s/#references';
  var $scienceDirectURL= "https://www.sciencedirect.com/search?qs=%s"; // %s = doi
  var $scienceDirectPIIURL= "https://www.sciencedirect.com/science/article/pii/%s"; // %s = pii
  
  // Set this to true to get debugging page output
  //     when retrieving and processing pubmed URL
  var $debugUsingEchoing = false; 

  public function __construct() {
    $this->HttpClient   = new \dokuwiki\HTTP\DokuHTTPClient();
    $this->ctxpURLs["pmid"] = $this->ctxpBaseURL.$this->ctxpURLs["pmid"];
    $this->ctxpURLs["pmcid"] = $this->ctxpBaseURL.$this->ctxpURLs["pmcid"];
  } // Ok, V2020


  function startsWith($string, $startString) { 
    $len = strlen($startString); 
    return (substr($string, 0, $len) === $startString); 
  } // ok, V2020

  function convertId($id){
    if (empty($id))
      return NULL;
    $url = sprintf($this->convertId, $id);
    if ($this->debugUsingEchoing)
      echo PHP_EOL.">> CONVERT ID: getting URL: ".$url.PHP_EOL;
    $json = $this->HttpClient->get($url);
    if ($this->debugUsingEchoing)
      echo PHP_EOL.">> CONVERT ID: returned: ".$json.PHP_EOL;
    $r = json_decode($json);
    if ($r->records[0]->status === "error") {
      if ($this->debugUsingEchoing)
        echo PHP_EOL.">> CONVERT ID: ERROR: ".$r->records[0]->errmsg.PHP_EOL;
      return NULL;
    }
    echo print_r($r->records[0]);
    return $r->records[0];
  }

  function getPmidFromDoi($doi){
    if (empty($doi))
      return NULL;
    $search = "\"$doi\"&sort=date&size=100&format=pubmed";
    $url = sprintf($this->pubmedSearchURL, $search);
    if ($this->debugUsingEchoing)
      echo PHP_EOL.">> getPmidFromDoi: getting URL: ".$url.PHP_EOL;
    $r = $this->HttpClient->get($url);
    if ($this->debugUsingEchoing)
      echo PHP_EOL.">> getPmidFromDoi: returned: ".$r.PHP_EOL;
    // <pre class="search-results-chunk">33543243</pre>

    $pattern = "/PMID- (\d+)/";
    if (preg_match($pattern, $r, $m)){
      if ($this->debugUsingEchoing)
        echo PHP_EOL.">> getPmidFromDoi: OK: ".$m[1].PHP_EOL;
      return $m[1];
    }
    return NULL;
  }

  /**
   * Returns all PMIDs corresponding to the search query
   * Do not query format, sort order or size
   * These data are automatically added to the search query
   * Returns the array of PMIDs
   */
  function getAllSearchResult($search) {
    $url = sprintf($this->pubmedSearchURL, urlencode($search));
    $url .= "&format=pmid&sort=date&size=200";

    //<pre class="search-results-chunk">.*<\/pre>
    $content = $this->HttpClient->get($url);
    
    $pattern = "/<pre class=\"search-results-chunk\">((?:.*?\r?\n?)*)<\/pre>/";
    if (preg_match($pattern, $content, $m, PREG_UNMATCHED_AS_NULL)) {
      $pmids = explode("\n", $m[1]);
    }
    return $pmids;
  }

  /*
   * Get RIS, MEDLINE and CITATION from CTXP website
  */
  function getDataFromCtxp($base, $id, $doi="") {
    $url = "";
    if (empty($id))
      return NULL;
    if (empty($this->ctxpURLs[$base]))
      return NULL;

    $url = sprintf($this->ctxpURLs[$base], urlencode($id));

    if ($this->debugUsingEchoing)
      echo PHP_EOL.">> PUBMED: getting URL: ".$url.PHP_EOL;

    // Retrieve URL
    $medlineContent = $this->HttpClient->get($url);
    // Check length of the returned HTTP content, make a second try if necessary
    if (strlen($medlineContent) < 10) {
      $medlineContent = $this->HttpClient->get($url);
      if ($this->debugUsingEchoing)
        echo PHP_EOL.">> PUBMED: Second try: ".strlen($medlineContent)." ".$url."<BR>".PHP_EOL;
    }

    // Check error in the content
    if (strlen($medlineContent) < 10) {
      if ($this->debugUsingEchoing)
        echo PHP_EOL.">> PUBMED: Error while retrieving URL: ".$url."<10".PHP_EOL;
      return NULL; 
    }
    if ($this->debugUsingEchoing)
      echo PHP_EOL.">> PUBMED: retrieved from the URL: ".PHP_EOL.$medlineContent.PHP_EOL;

    return $medlineContent;
  } // ok, V2020

  /*
   * Create a pubmed query, return the URL of the query
   * {{pmid>search:#title|terms|size|filter|...}}
   * return array(title, searchUrl)
   */
  function getPubmedSearchURL($searchTerms) {
    // Split using | to get URL options: size, format, filter, sort
    $options = explode("|", $searchTerms);
    if (count($options) < 1)
      return "ERROR"; // TODO
    // Find title
    $title = "";
    if (substr($options[0], 0, 1) === "#") {
      $title = substr($options[0], 1);
      array_shift($options);
    } else {
      $title = $searchTerms; // Title === search terms
    }
    $url = sprintf($this->pubmedSearchURL, urlencode($options[0]));
    if (count($options) > 1)
      $url .= "&".implode("&", array_slice($options, 1));
    return array($title, $url);
  } // ok, V2020

  /**
   * Get full abstract of the article stored in an Array where
   * Ids:
   *   "pmid"          -> PMID 
   *   "pmcid"         -> if available PMCID
   *   "doi"           -> DOI references when available
   *   "pii"           -> PII references when available
   *   "bookaccession"
   *
   * Authors:
   *   "authors"       -> Array of authors
   *   "first_author"  -> First author + "et al." if other authors are listed
   *   "authorsLimit3" -> Three first authors + "et al." if other authors are listed
   *   "authorsVancouver" -> according to the configuration of the plugin
   *   "corporate_author" -> If author is corporate 
   *                        (in this case also included in authors and first_author)
   *   "collectif"     -> If author is a collective 
   *
   * Titles:
   *   "title"         -> Full title
   *   "title_low"     -> Lowered full title
   *   "translated_title" -> Translated title (TODO: improve this)
   *   "translated_title_low" -> Lowered translated title (TODO: improve this)
   *   "book_title"
   *
   * Journal:
   *   "journal_iso"   -> Journal ISO Abbreviation
   *   "journal_title" -> Journal full title
   *   "journal_id"
   *
   * Other references:
   *   "lang"          -> language of the article
   *   "iso"
   *   "vol"           -> Journal Volume
   *   "issue"         -> Journal Issue
   *   "year"          -> Journal Year of publication
   *   "month"         -> Journal Month of publication
   *   "pages"         -> Journal pagination
   *   "abstract"      -> Complete abstract
   *   "abstract_wiki" -> Wikified abstract
   *   "abstract_html" -> HTML'd abstract
   *   "type"          -> Type of paper
   *   "country"
   *   "copyright"
   *   "collection_title"
   *   "publisher"
   *
   * Keywords, Mesh and Hastags:
   *   "keywords"     -> Non-mesh keywords of the paper
   *   "mesh"         -> Mesh terms associated with the paper
   *   "hashtags"     -> Added hastag with command 'addhash'
   *
   * Hard coded citations:
   *   "iso"           -> ISO citation of the article
   *   "npg_full"      -> Citation for: Neurologie Psychiatrie Geriatrie journal
   *   "npg_iso"       -> Same with authors and title
   *
   * Links:
   *   "url"           -> URL to PubMed site
   *   "pmcurl"        -> if available URL to PMC site
   *   "googletranslate_abstract"   -> Link to google translate prepopulated with abstract
   *   "sciencedirecturl" -> Link to ScienceDirect web site (using DOI)
   *
   * \note $pluginObject must be accessible for translations ($this->getLang())
   */
  function readMedlineContent($string, $pluginObject) {
    // No data return empty array
    if (empty($string))
      return array("pmid" => "0");
    $content = $string;
    $authors = array();
    $authorsVancouver = array();
    $val = "";
    $key = "";
    $array = array();
    $id = 0;
    foreach(preg_split("/((\r?\n)|(\r\n?))/", $content) as $line) {
      //echo print_r($line).PHP_EOL;
      if ($this->startsWith($line,"  ")) {
        // Append multiline value
        $array[$key] .= ' '.trim($line);
        continue;
      } else if (strlen($line) > 4) {
        // Get new key
        $key = trim(substr($line, 0, 4));
        if ($id<9)
          $key .= '0';
        $key .= $id;
        $val = trim(substr($line, 6));
        $id++;
        $array[$key] = $val;
      }
    }

    // Now process datas
    // TODO: Catch book references. Eg: 28876803
    $ret = array();
    // Create all keys with empty values to avoid php warnings
    $ret["pmid"] = null;
    $ret["pmcid"] = null;
    $ret["doi"] = null;
    $ret["pii"] = null;
    $ret["bookaccession"] = null;
    $ret["authors"] = null;
    $ret["first_author"] = null;
    $ret["authorsLimit3"] = null;
    $ret["authorsVancouver"] = null;
    $ret["corporate_author"] = null;
    $ret["collectif"] = null;
    $ret["title"] = null;
    $ret["title_low"] = null;
    $ret["translated_title"] = null;
    $ret["translated_title_low"] = null;
    $ret["book_title"] = null;
    $ret["journal_iso"] = null;
    $ret["journal_title"] = null;
    $ret["journal_id"] = null;
    $ret["lang"] = null;
    $ret["iso"] = null;
    $ret["vol"] = null;
    $ret["issue"] = null;
    $ret["year"] = null;
    $ret["month"] = null;
    $ret["pages"] = null;
    $ret["abstract"] = null;
    $ret["abstract_wiki"] = null;
    $ret["abstract_html"] = null;
    $ret["type"] = null;
    $ret["country"] = null;
    $ret["copyright"] = null;
    $ret["collection_title"] = null;
    $ret["publisher"] = null;
    $ret["keywords"] = null;
    $ret["mesh"] = null;
    $ret["hashtags"] = null;
    $ret["so"] = null;
    $ret["npg_full"] = null;
    $ret["npg_iso"] = null;
    $ret["url"] = null;
    $ret["pmcurl"] = null;
    $ret["googletranslate_abstract"] = null;
    $ret["sciencedirecturl"] = null;
    $ret["scihuburl"] = null;

    $mesh = array();
    $keywords = array();
    foreach($array as $key => $value) {
      $k = preg_replace('/[0-9]+/', '', $key);

      switch ($k) {  // See https://www.nlm.nih.gov/bsd/mms/medlineelements.html
//AD  - Médecin gériatre, psychogériatre, Court séjour gériatrique, Unité COVID, Centre 
//      Hospitalier de Calais, 1601 Boulevard des Justes, 62100, Calais, France      Hospitalier de Calais, 1601 Boulevard des Justes, 62100, Calais, France

        case "PMID": 
          $ret["pmid"] = $value;  //PMID - 15924077
          $ret["url"] = sprintf($this->pubmedURL, urlencode($value));
          break;
        case "PMC":
          $ret["pmcid"] = str_replace("PMC", "", $value);
          $ret["pmcurl"] = sprintf($this->pmcURL, urlencode($ret["pmcid"]));
          break;
        case "DCOM": break; //DCOM- 20050929
        case "LR": break;  //LR  - 20191109
        case "IS": break;  //IS  - 0035-3787 (Print)  //IS  - 0035-3787 (Linking)
        case "VI": $ret["vol"] = $value; break;  //VI  - 161
        case "IP": $ret["issue"] = $value; break; //IP  - 4
        case "DP": 
          $ret["year"] = substr($value,0,4);
          break; //DP  - 2005 Apr
        case "TI": 
          // TODO: Keep case of title correctly -> How?
          $ret["title"] = $value; 
          break; // TI title english
        case "PG":
          $ret["pages"] = trim($value);
          // Error with PMID 5042912 (remove last ending '-' char)
          $ret["pages"] = rtrim($ret["pages"], "-");
          break;
        case "AB": 
          $ret["abstract"] = $value; 
          $ret["abstract_wiki"] = $this->_normalizeAbstract($value);
          $ret["abstract_html"] = $this->_normalizeAbstract($value, "html");
          break;
/*
        case "AU": 
          // Keep case of names correctly
          // NAME SN -> Name SN (first letter uppercase only)
          $n = explode(" ", trim($value));
          if (count($n) >= 2) {
              // $n[0] = ucfirst(strtolower($n[0]));
              // Correctly manages Name1-Name2
              $n[0] = ucwords(strtolower($n[0]), "-");
              $value = $n[0]." ".$n[1];
          }
          //array_push($authors, $value);
          break;
*/
        case "FAU": 
          $sn = "";
          $surname = "";
          if (strpos($value, ',') !== false) {
            $n = explode(",", trim($value));
            $sn = $n[1];
            $name = $this->_normalizeNameCase($n[0]);
          } else {
            $n = explode(" ", trim($value));
            $name = $this->_normalizeNameCase($n[0]);
            $sn = $n[1];
          }
          // Keep only first letter of each surname
          foreach (explode(' ', $sn) as $w) {
            $surname .=  mb_substr($w,0,1,'UTF-8');
          }
          $value = $name." ".$surname;
          array_push($authors, $value);
          break;
        case "LA": $ret["lang"] = $value; break; //LA  - fre
        case "PT": $ret["type"] = $value; break; //PT  - English Abstract  //PT  - Journal Article
        case "TT": $ret["translated_title"] = $value; break;
        case "PL": $ret["country"] = $value; break;  //PL  - France
        case "TA": $ret["journal_iso"] = $value; break; // TA  - Rev Neurol (Paris)
        case "JT": $ret["journal_title"] = $value; break; // JT  - Revue neurologique
        case "JID": $ret["journal_id"] = $value; break; // JID - 2984779R
//         case "SB": $ret[""] = $value; break; // SB  - IM
        case "MH": array_push($mesh, $value); break;
        case "OT": array_push($keywords, $value); break;
//         case "EDAT": $ret[""] = $value; break; // SB  - IM
//         case "MHDA": $ret[""] = $value; break; // SB  - IM
//         case "CRDT": $ret[""] = $value; break; // SB  - IM
//         case "PHST": $ret[""] = $value; break; // SB  - IM
        case "AID": 
          if (strpos($value, "[doi]") > 0)
            $ret["doi"] = str_replace(" [doi]", "", $value); 
          if (strpos($value, "[pii]") > 0)
            $ret["pii"] = str_replace(" [pii]", "", $value);
          if (strpos($value, "[bookaccession]") > 0)
            $ret["bookaccession"] = str_replace(" [bookaccession]", "", $value);
          break;
        //case "PST": $ret[""] = $value; break; // SB  - IM
        case "SO": 
          // Error with 5042912 (pages) => replace "-." by "."
          $ret["so"] = str_replace("-.", ".", $value);          
          break;
        case "CI" : $ret["copyright"] = $value; break;
        case "CN" : $ret["corporate_author"] = $value; break;
        case "CTI" : $ret["collection_title"] = $value; break;
        case "BTI" : 
          $ret["book_title"] = $value;
          if (empty($ret["title"]))
            $ret["title"] = $value; 
          break;
        case "PB" : // Possible publisher? count as author?
          $ret["publisher"] = $value;
          break;
        case "LID": // possible page? see 32947851
          if (strpos($value, "[doi]") > 0) {
            $ret["doi"] = str_replace(" [doi]", "", $value); 
          } else if (strpos($value, "[pii]") > 0) {
            $ret["pii"] = str_replace(" [pii]", "", $value); 
          } else {
            $ret["pages"] = $value;
          }
          break;
        case "HASH": $ret["hashtags"] = $value; break;
      }  // Switch
    } // Foreach

    // Create lowered case titles
    if (!empty($ret["translated_title"])) {
        $ret["translated_title_low"] = ucfirst(strtolower($ret["translated_title"])); //mb_convert_case($ret["translated_title"], MB_CASE_TITLE);
    }
    if (!empty($ret["title"])) {
        $ret["title_low"] = ucfirst(strtolower($ret["title"])); //mb_convert_case($ret["title"], MB_CASE_TITLE);
    }

    // Manage unavailable title with a translated title
    if (strpos($ret["title"], "[Not Available]") !== false) {
        $ret["title"] = $ret["translated_title"];
    }

    // Get authors
    if (isset($ret["corporate_author"])) {
      array_push($authors, $ret["corporate_author"]);
    } 

    $ret["authors"] = $authors;
    $ret["authorsVancouver"] = $authors;
    if (count($authors) == 0) {
        array_push($authors, $pluginObject->getLang('no_author_listed'));
    }

    //"collectif" => $collectif,
    // Create first author for short output
    if (count($authors) > 1) {
      $ret['first_author'] = $authors[0].", ".$pluginObject->getConf('et_al_vancouver');
    } else {
      $ret['first_author'] = $authors[0];
    }
    
    // Create Vancouver Authors.
    // Manage limitation in number of authors
    $limit = $pluginObject->getConf('limit_authors_vancouver');
    $authorsToUse = $ret["authorsVancouver"];
    $addAndAl = false;
    if ($limit >= 1) {
      if (count($authorsToUse) > $limit) {
        $addAndAl = true;
        $authorsToUse = array_slice($authorsToUse, 0, $limit);
      }
    }

    $vancouver = "";
    if (count($authorsToUse) > 0) {
      $vancouver = implode(', ',$authorsToUse);
      if ($addAndAl)
        $vancouver .= ", ".$pluginObject->getConf('et_al_vancouver');
      $vancouver .= ". ";
    }

    // Create 3 authors only
    $limit = 3;
    $authorsToUse = $ret["authorsVancouver"];
    $addAndAl = false;
    if ($limit >= 1) {
      if (count($authorsToUse) > $limit) {
        $addAndAl = true;
        $authorsToUse = array_slice($authorsToUse, 0, $limit);
      }
    }
    if (count($authorsToUse) > 0) {
      $authors3 = implode(', ',$authorsToUse);
      if ($addAndAl)
        $authors3 .= ", ".$pluginObject->getConf('et_al_vancouver');
      $authors3 .= ". ";
    } else {
      // Less than three authors
      $authors3 = implode(', ',$authorsToUse).". ";
    }
    $ret["authorsLimit3"] = $authors3;
    $ret["authorsVancouver"] = $vancouver;

    // no authors -> nothing to add  Eg: pmid 12142303
    
    // Book -> See https://pubmed.ncbi.nlm.nih.gov/30475568/?format=pubmed

    // Get Mesh terms & keywords
    $ret["mesh"] = $mesh;
    $ret["keywords"] = $keywords;

    // Remove points from the journal_iso string
    if ($pluginObject->getConf('remove_dot_from_journal_iso') === true)
       $ret["journal_iso"] = str_replace(".", "", $ret["journal_iso"]);

    // Construct iso citation of this article
    // Use SO from the raw medline content
    $ret["iso"] = $ret["so"];
    $ret = $this->createNpgCitation($ret);
    $ret = $this->createGpnvCitation($ret);


    $ret["similarurl"] = sprintf($this->similarURL, $ret["pmid"]);
    $ret["citedbyurl"] = sprintf($this->citedByURL, $ret["pmid"]);
    $ret["referencesurl"] = sprintf($this->referencesURL, $ret["pmid"]);

    // Construct Vancouver citation of this article
    // See https://www.nlm.nih.gov/bsd/uniform_requirements.html
    if (isset($ret["book_title"])) {
      // Author. <i>BookTitle</i>. country:PB;year.
      $ret["vancouver"] = $vancouver;
      $ret["vancouver"] .= $ret["title"]." ";
      $ret["vancouver"] .= $ret["book_title"].". ";
      $ret["iso"] = $ret["country"]." : ";
      $ret["iso"] .= $ret["year"].".";
      $ret["vancouver"] .= $ret["iso"];
      $ret["sciencedirecturl"] = sprintf($this->scienceDirectURL, $ret["doi"]);
      return $ret;
    } 
    $vancouver .= $ret["title"];
    $vancouver .= " ".$ret["so"];
    // $vancouver .= " ".$ret["journal_iso"]."";
    // $vancouver .= " ".$pubDate;
    // $vancouver .= ";".$ret["vol"];
    // if (!empty($ret["issue"]))
    //   $vancouver .= "(".$ret["issue"].")";
    // $vancouver .= ":".$ret["pages"];
    $ret["vancouver"] = $vancouver;

    $gg  =  "https://translate.google.com/";
    $gg .= "?sl=auto&tl=fr&text=";
    $gg .= rawurlencode($ret["abstract"]);
    $gg .= "&op=translate";
    $ret["googletranslate_abstract"] = $gg;
    //echo print_r($ret);
    $ret["sciencedirecturl"] = sprintf($this->scienceDirectURL, $ret["doi"]);
    return $ret;
  } // Ok pubmed2020



  /** NPG: See https://www.em-consulte.com/revue/NPG/presentation/npg */
  function createNpgCitation($ret) {
    // Construct NPG ISO citation of this article
    //%npg_iso% %year% ; %vol% (%issue%) : %pages%
    // BOOKS
    if (!empty($ret["book_title"])) {
      // Trivalle C. Gérontologie préventive. Éléments de prévention du vieillissement pathologique. Paris : Masson, 2002.
      // https://pubmed.ncbi.nlm.nih.gov/30475568/?format=pubmed
      // Authors
      $ret["npg_full"] = $ret["authorsLimit3"];
      // Title
      if (!empty($ret["translated_title"])) {
        $t = $ret["translated_title"];
      } else if (!empty($ret["title"])) {
        $t = $ret["title"];
      } else if (!empty($ret["book_title"])) {
        $t = $ret["book_title"];
      }

      // Normalize title case
      $t = $this->_normalizeTitleCase($t);

      $ret["npg_full"] .= $t.". ";

      // Town
      if (!empty($ret["country"])) {
        $ret["npg_full"] .= $ret["country"];
      }
      // Editor
      if (!empty($ret["publisher"])) {
        $ret["npg_full"] .= " : ".$ret["publisher"];
      }
      // Year
      if (!empty($ret["year"])) {
        $ret["npg_full"] .= ", ".$ret["year"].".";
      }
//       if (!empty($ret["bookaccession"])) {
//         $ret["npg_full"] .= " https://www.ncbi.nlm.nih.gov/books/".$ret["bookaccession"];
//       }      
      return $ret;
    }
    // JOURNALS
    // Journal
    $npg = "";
    if (!empty($ret["journal_iso"])) {
       $npg = str_replace(".", "", $ret["journal_iso"])." ";
    }
    // Year
    if (!empty($ret["year"])) {
      $npg .= $ret["year"];
      // Vol/Issue
      if (!empty($ret["vol"]) || !empty($ret["issue"]))
          $npg .= " ; ";
      // Vol
      if (!empty($ret["vol"]))
          $npg .= $ret["vol"];
      // Issue
      if (!empty($ret["issue"]))
          $npg .= "(".$ret["issue"].")";
      // Pages or DOI (no pages -> add DOI)
      if (!empty($ret["pages"])) {
          $npg .= " : ".$this->_normalizePages($ret["pages"]).".";
      } else if (!empty($ret["doi"])) {
        $npg .= ", doi : ".$ret["doi"];
//       } else if (!empty($ret["bookaccession"])) {
//         $npg .= ", https://www.ncbi.nlm.nih.gov/books/".$ret["bookaccession"];
      }
    } else if (!empty($ret["doi"])) {
      $npg .= ", doi : ".$ret["doi"];
//     } else if (!empty($ret["bookaccession"])) {
//       $npg .= ", https://www.ncbi.nlm.nih.gov/books/".$ret["bookaccession"];
    }
    $npg = trim(str_replace("  ", " ", $npg));
    $ret["npg_iso"] = $npg;
    $ret["npg_full"] = $ret["authorsLimit3"];
    $t = "";
    if (!empty($ret["translated_title"])) {
      $t = $ret["translated_title"];
    } else if (!empty($ret["title"])) {
      $t = $ret["title"];
    } else if (!empty($ret["book_title"])) {
      $t = $ret["book_title"];
    }
    
    // Normalize title case
    $t = $this->_normalizeTitleCase($t);

    if (substr_compare(".", $t, -strlen($t)) === 0) {
      mb_substr($t, 0, -1);
    }

    $ret["npg_full"] .= $t." ";
    $ret["npg_full"] .= $ret["npg_iso"];

    return $ret;
  }
  
  /** 
   * GPNV: See https://www.jle.com/fr/revues/gpn/espace_auteur
   * vancouver with style mention & spaces 
   */
  function createGpnvCitation($ret) {
    // Construct GPNV ISO citation of this article
    $npg = "";
    $ret["gpnv_full"] = "";
    //%npg_iso% %year% ; %vol% (%issue%) : %pages%
    // BOOKS
    if (!empty($ret["book_title"])) {
      // Trivalle C. Gérontologie préventive. Éléments de prévention du vieillissement pathologique. Paris : Masson, 2002.
      // https://pubmed.ncbi.nlm.nih.gov/30475568/?format=pubmed
      // Authors
      $ret["gpnv_full_authors"] = $ret["authorsVancouver"];
      // Title
      if (!empty($ret["translated_title"])) {
        $t = $ret["translated_title"];
      } else if (!empty($ret["title"])) {
        $t = $ret["title"];
      } else if (!empty($ret["book_title"])) {
        $t = $ret["book_title"];
      }
      $ret["gpnv_full"] .= $t.". ";
      // Town
      if (!empty($ret["country"])) {
        $ret["gpnv_full"] .= $ret["country"];
      }
      // Editor
      if (!empty($ret["publisher"])) {
        $ret["gpnv_full"] .= " : ".$ret["publisher"];
      }
      // Year
      if (!empty($ret["year"])) {
        $ret["gpnv_full"] .= ", ".$ret["year"].".";
      }
      // TODO: this is wrong
      $ret["gpnv_full_title"] = $ret["gpnv_full"];
      return $ret;
    }
    // JOURNALS
    // Journal
    if (!empty($ret["journal_iso"])) {
      $ret["gpnv_full_journal"] = str_replace(".", "", $ret["journal_iso"])." ";
    }
    // Year
    if (!empty($ret["year"])) {
      $npg .= $ret["year"];
      // Vol
      if (!empty($ret["vol"])) {
          $npg .= " ; ".$ret["vol"];
        // Issue
        if (!empty($ret["issue"])) {
          $npg .= " (".$ret["issue"].")";
        }
        // Pages
        if (!empty($ret["pages"])) {
          $npg .= " : ".$this->_normalizePages($ret["pages"]).".";
        }
      } else if (!empty($ret["doi"])) {
        $npg .= ", doi : ".$ret["doi"];
//       } else if (!empty($ret["bookaccession"])) {
//         $npg .= ", https://www.ncbi.nlm.nih.gov/books/".$ret["bookaccession"];
      }
//     } else if (!empty($ret["doi"])) {
//       $npg .= ", doi : ".$ret["doi"];
//     } else if (!empty($ret["bookaccession"])) {
//       $npg .= ", https://www.ncbi.nlm.nih.gov/books/".$ret["bookaccession"];
    }
    $npg = trim(str_replace("  ", " ", $npg));
    $ret["gpnv_full_iso"] = $npg;
    $ret["gpnv_full_authors"] = $ret["authorsVancouver"];
    $t = "";
    if (!empty($ret["translated_title"])) {
      $t = $ret["translated_title"];
    } else if (!empty($ret["title"])) {
      $t = $ret["title"];
    } else if (!empty($ret["book_title"])) {
      $t = $ret["book_title"];
    }
    if (substr_compare(".", $t, -strlen($t)) === 0) {
      mb_substr($t, 0, -1);
    }
    $ret["gpnv_full_title"] = $t." ";
    //$ret["gpnv_full"] .= $ret["gpnv_iso"];
    return $ret;
  }


  /**
   * Lowering title (with exceptions)
   */
  function _normalizeTitleCase($t) {
    // Is title is full uppercase?
    $low_t = ucfirst(strtolower(ucwords($t)));
    if (mb_strtoupper($t, 'utf-8') !== $t) {
      $words = preg_split('/[\s\-\[\]\(\)\/\'\’\"\“\”\.]+/', $t);
      foreach ($words as $word) {
        //echo $word.PHP_EOL;
        if (strlen($word) > 1 && ctype_upper(str_replace("-", "", $word))) {
          //echo $word."  ".strtolower($word)."\n";
          //$low_t = str_replace(strtolower($word), $word, $low_t);
          $low_t = preg_replace('/([\s\-\(\[\.\/\'])'.strtolower($word).'([\s\-\)\]\.\:\?\/\'])/i', "$1$word$2", $low_t);
        }
      }
    }

    // Case exceptions
    $exceptions = Array(
      // Countries
      "Afghanistan", "Aland Islands", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua", "Argentina", "Armenia", "Aruba", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Barbuda", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Trty.", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burundi", "Caicos Islands", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (Keeling) Islands", "Colombia", "Comoros", "Congo", "Congo, Democratic Republic of the", "Cook Islands", "Costa Rica", "Cote d'Ivoire", "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "Falkland Islands (Malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "French Guiana", "French Polynesia", "French Southern Territories", "Futuna Islands", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guernsey", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard", "Herzegovina", "Holy See", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran (Islamic Republic of)", "Iraq", "Ireland", "Isle of Man", "Israel", "Italy", "Jamaica", "Jan Mayen Islands", "Japan", "Jersey", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea", "Korea (Democratic)", "Kuwait", "Kyrgyzstan", "Lao", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libyan Arab Jamahiriya", "Liechtenstein", "Lithuania", "Luxembourg", "Macao", "Macedonia", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "McDonald Islands", "Mexico", "Micronesia", "Miquelon", "Moldova", "Monaco", "Mongolia", "Montenegro", "Montserrat", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "Nevis", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "Northern Mariana Islands", "Norway", "Oman", "Pakistan", "Palau", "Palestinian Territory, Occupied", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Principe", "Puerto Rico", "Qatar", "Reunion", "Romania", "Russian Federation", "Rwanda", "Saint Barthelemy", "Saint Helena", "Saint Kitts", "Saint Lucia", "Saint Martin (French part)", "Saint Pierre", "Saint Vincent", "Samoa", "San Marino", "Sao Tome", "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia", "South Sandwich Islands", "Spain", "Sri Lanka", "Sudan", "Suriname", "Svalbard", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan", "Tajikistan", "Tanzania", "Thailand", "The Grenadines", "Timor-Leste", "Tobago", "Togo", "Tokelau", "Tonga", "Trinidad", "Tunisia", "Turkey", "Turkmenistan", "Turks Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "US Minor Outlying Islands", "Uzbekistan", "Vanuatu", "Vatican City State", "Venezuela", "Vietnam", "Virgin Islands (British)", "Virgin Islands (US)", "Wallis", "Western Sahara", "Yemen", "Zambia", "Zimbabwe",
      // Cities
      "Jerusalem",
      // Continent
      "Europe", "Africa",
      // Associations / Societies
      "American Geriatrics Society",
      "American Psychiatric Association",
      "American College of Physicians",
      "American Academy of Family Physicians",
      "American College of Cardiology",
      "American Heart Association Task Force",
      "ACC/AHA/AAPA/ABC/ACPM/AGS/APhA/ASH/ASPC/NMA/PCNA",
      "ESC/ESH",
      "European Society of Hypertension",
      "European Union Geriatric Medicine Society Working Group",
      "European Society of Anaesthesiology",
      "American Association for Emergency Psychiatry",
      // Diseases
      "Parkinson",
      "Alzheimer",
      "Lewy",
      "Sydenham",
      "Asperger",
      // Others
      "AI",
      "Syst-Eur",
      "UKU-SERS-Pat",
      "Largactil",
      "ADRs",
      "U.S.",
      "Hg",
      "SARS",
      "CoV",
      "COVID",
      "I",
    );
    foreach ($exceptions as $word) {
      //echo $word.PHP_EOL;
      $p = strtolower($word);
      $p = str_replace("/", "\/", $p); 
      $p = '/([\s\-\(\.\/\'\`])'.$p.'([\s\-\)\.\:\?\/\'\`])/';
      // String exists in full lowercase
      //echo $p." ".print_r(preg_match($p, $low_t, $matches)).PHP_EOL;
      // Find exception in full lowercase
      if (preg_match($p, $low_t, $matches, PREG_OFFSET_CAPTURE)) {
        // String in full lowercase
        //echo "*** low".PHP_EOL;
        $low_t = preg_replace($p, "$1$word$2", $low_t);
      } else {
        // Find exception in full lowercase but first letter
        $p = ucfirst(strtolower($word));
        $p = str_replace("/", "\/", $p);
        $p = '/([\s\-\(\.\/\'\`])'.$p.'([\s\-\)\.\:\?\/\'\`])/';
        //echo "---> ".$low_t."  //  ".$p." ".print_r(preg_match($p, $low_t, $matches)).PHP_EOL;
        if  (preg_match($p, $low_t, $matches)) {
          //echo "*** Ucf".PHP_EOL;
          $low_t = preg_replace($p, "$1$word$2", $low_t);
        } else {
          // Find exception in full lowercase but first letter at the start of the title
          $p = ucfirst(strtolower($word));
          $p = str_replace("/", "\/", $p);
          $p = '/^'.$p.'([\s\-\)\.\:\?\/\'\`])/m';
          //echo "---> ".$low_t."  //  ".$p." ".print_r(preg_match($p, $low_t, $matches)).PHP_EOL;
          if  (preg_match($p, $low_t, $matches)) {
            //echo "*** Ucf".PHP_EOL;
            $low_t = preg_replace($p, "$word$1", $low_t);
          }
        }
      }
    } // End exception checking
    
    // Check all sentences -> Uppercase first letter of each sentence
    if (strpos($low_t, ". ")) {
      // Split each sentences
      //echo "GOT A DOT".PHP_EOL;
      $sentences = preg_split('/\. /', $low_t);
      $low_t = "";
      foreach ($sentences as $sentence) {
        //echo $sentence.PHP_EOL;
        $low_t .= rtrim(ucfirst($sentence), '.').". ";
      }
      $sentences = ucfirst(strtolower(ucwords($t)));
    }

    // Check all sentences -> Uppercase first letter of each sentence
    if (strpos($low_t, "? ")) {
      // Split each sentences
      //echo "GOT A DOT".PHP_EOL;
      $sentences = preg_split('/\? /', $low_t);
      $low_t = "";
      foreach ($sentences as $sentence) {
        //echo $sentence.PHP_EOL;
        $low_t .= ucfirst($sentence);
        if (substr($sentence, -1) !== '.')
          $low_t .= "? ";
      }
      $sentences = ucfirst(strtolower(ucwords($t)));
    }

    //echo $t.PHP_EOL.PHP_EOL;
    $t = $low_t;
    //echo $t.PHP_EOL.PHP_EOL;
    return $t;
  }
  /*
   * Normalize case of the author's name
   */
  function _normalizeNameCase($name) {
    // Only change fully uppered names (take care to spaces)
    if (ctype_upper(str_replace(" ", "", $name))) {
       return ucwords(mb_strtolower($name), " \t\r\n\f\v-'");
    }
    return $name;
  }

  /*
   * Normamize pages number
   */
  function _normalizePages($pages) {
    // Test -
    if (strpos($pages, "-") === false) {
      return $pages;
    }
    // Split pages
    $p = mb_split("-", $pages);
    if (count($p) !== 2) {
      return $pages;
    }
    // Count number of num and compare
    if (strlen($p[0]) !== strlen($p[1])) {
      return $pages;
    }

    // Compare num by num
    $length = mb_strlen($p[0]);
    for($i = 0 ; $i < $length ; $i++) {
       if ($p[0][$i] !== $p[1][$i]) {
         return $p[0]."-".mb_substr($p[1], $i);
       }
    }
    return $pages;
  }

  /**
   * Correct raw abstract into $format ("html" or "wiki")
   */
  function _normalizeAbstract($abstract, $format = "wiki"){  // Pb: 33397541
    $chapters = Array(
      "Aim:",
      "Aims:",
      "Aims and objectives:", 
      "Authors' conclusions:",
      "Authors conclusions:",
      "Background \& aims:",
      "Background and objectives:",
      "Background:",
      "Background\/objectives:",
      "Background\/aims:", 
      "Clinical implications:", //
      "Clinical rehabilitation impact:",
      "Clinical relevance:",
      "Clinical significance:",
      "Clinical trial registration:",
      "Clinical trials registration:", 
      "Comparison:",
      "Conclusion:",
      "Conclusions\/implications:",
      "Conclusions and implications:",
      "Conclusions and relevance:",
      "Conclusions\/relevance:",
      "Conclusions:",
      "Context:",
      "Data analysis:", 
      "Data collection and analysis:",
      "Data extraction:",
      "Data extraction and synthesis:", 
      "Data sources:", 
      "Data sources and review methods:",
      "Data sources and study selection:", 
      "Data synthesis:",
      "Design, study, and participants:",
      "Design:",
      "Development:", 
      "Diagnosis of interest:",
      "Discussion:",
      "Discussion and conclusions:", 
      "Discussion and conclusion:", 
      "Discussion and implications:", 
      "Discussion\/Conclusion:",  
      "Eligibility criteria:",
      "Experimental design:",
      "Exposures:",
      "Findings:",
      "Funding:",
      "Impact:", //
      "Implications:",
      "Implications for nursing management:",
      "Implications for clinical management:",
      "Importance:", //
      "Inclusion criteria population:",
      "Index test:",
      "Information sources:",
      "Interpretation:",
      "Intervention:",
      "Introduction:",
      "Keywords:",
      "Limitation:", //
      "Limitations:", //
      "Main outcome measures:",
      "Main outcomes and measures:",
      "Main outcomes:",
      "Main results:",
      "Material and methods:",
      "Materials \& methods:",
      "Measurements:",
      "Mesures:",
      "Methodological quality:",
      "Methodology:", 
      "Method:",
      "Methods:",
      "Methods and results:", 
      "Objective:",
      "Objectives:",
      "Outcomes:",
      "Participants:",
      "Participants\/setting:",
      "Patients and methods:",
      "Patient or public contribution:", //
      "Population:",
      "Primary and secondary outcome measures:",
      "Prospero registration:", //
      "Purpose and objective:",
      "Purpose:",
      "Purpose of the study:", 
      "Rationale:",
      "Recent developments:", 
      "Recommendations for screening and assessment:", 
      "Recommendations for management:", 
      "Reference test:",
      "Relevance to clinical practice:", 
      "Research design and methods:", 
      "Research question:",
      "Results:",
      "Result:", 
      "Scope:", 
      "Search methods:",
      "Search strategy:",
      "Selection criteria:",
      "Setting:",
      "Setting and subjects:", 
      "Setting and participants:",
      "Settings:",
      "Significance of results:",
      "Statistical analysis performed:",
      "Study design and methods:",
      "Study design:",
      "Study selection:",
      "Subjects:",
      "Subjects\/methods:",
      "Subjects \& methods:",
      "Subjects and methods:",
      "Summary:", //
      "Systematic review registration:", //
      "Trial registration:",
      "Types of studies:",
      "Tweetable abstract:", //
      "Where next\?:",
    );
    // Prepare output tags
    $lf = PHP_EOL.PHP_EOL;
    $boldS = "**";
    $boldE = "**";
    switch ($format) {
      case "html": case "xhtml":
        $boldS = "<b>"; $boldE = "</b>"; $lf = "<br><br>";
      default: break;
    }
    // Sort array
    usort($chapters, function ($a, $b) {
        $countA = substr_count($a, " ");
        $countB = substr_count($b, " ");

        if ($countA < $countB) {
            return 1;
        } elseif ($countA > $countB) {
            return -1;
        } else {
            return 0;
        }
    });
    // Correct some typos in abstract
    $abstract = str_replace("ABSTRACTObjectives:", "Objectives: ", $abstract);
    //echo print_r($chapters);
    // Replace in abstract
    foreach($chapters as $c) {
      $pattern = "/\s*".$c."\s+/i";
      $c = str_replace("\\", "", $c);
      $abstract = preg_replace($pattern, "$lf$boldS$c$boldE ", $abstract);
    }
    // Remove first $lf of abstract
    if (substr($abstract, 0, strlen($lf)) === $lf) {
      $abstract = substr($abstract, strlen($lf));
    }
//     $info = array();
//     $abstract = p_render('xhtml', p_get_instructions($abstract), $info);
//     echo '<pre>'.$abstract.'</pre>';
    return $abstract;
  }



} // class PubMed2020

?>