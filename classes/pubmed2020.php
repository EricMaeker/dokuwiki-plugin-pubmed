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
  
  // Set this to true to get debugging page output
  //     when retrieving and processing pubmed URL
  var $debugUsingEchoing = false; 

  public function __construct() {
    $this->HttpClient   = new DokuHTTPClient();
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
        case "PG": $ret["pages"] = trim($value); break;
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
        case "SO": $ret["so"] = $value; break; //SO  - Rev Neurol (Paris). 2005 Apr;161(4):419-26. doi: 10.1016/s0035-3787(05)85071-4.
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

    // Get authors
    if ($ret["corporate_author"]) {
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
        $vancouver .= " ".$pluginObject->getConf('et_al_vancouver');
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


    $ret["similarurl"] = sprintf($this->similarURL, $ret["pmid"]);
    $ret["citedbyurl"] = sprintf($this->citedByURL, $ret["pmid"]);
    $ret["referencesurl"] = sprintf($this->referencesURL, $ret["pmid"]);

    // Construct Vancouver citation of this article
    // See https://www.nlm.nih.gov/bsd/uniform_requirements.html
    if ($ret["book_title"]) {
      // Author. <i>BookTitle</i>. country:PB;year.
      $ret["vancouver"] = $vancouver;
      $ret["vancouver"] .= "<i>".$ret["book_title"].".</i> ";
      $ret["iso"] = $ret["country"]." : ";
      $ret["iso"] .= $ret["year"].".";
      $ret["vancouver"] .= $ret["iso"];
      return $ret;
    } 
    $vancouver .= $ret["title"];
    $vancouver .= " ".$ret["so"];
//     $vancouver .= " ".$ret["journal_iso"]."";
//     $vancouver .= " ".$pubDate;
//     $vancouver .= ";".$ret["vol"];
//     if (!empty($ret["issue"]))
//       $vancouver .= "(".$ret["issue"].")";
//     $vancouver .= ":".$ret["pages"];
    $ret["vancouver"] = $vancouver;

    $gg  =  "https://translate.google.com/";
    $gg .= "?sl=auto&tl=fr&text=";
    $gg .= rawurlencode($ret["abstract"]);
    $gg .= "&op=translate";
    $ret["googletranslate_abstract"] = $gg;
    //echo print_r($ret);
    return $ret;
  } // Ok pubmed2020



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
      if (!empty($ret["bookaccession"])) {
        $ret["npg_full"] .= " https://www.ncbi.nlm.nih.gov/books/".$ret["bookaccession"];
      }      
      return $ret;
    }
    // JOURNALS
    // Journal
    if (!empty($ret["journal_iso"])) {
       $npg = str_replace(".", "", $ret["journal_iso"])." ";
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
      } else if (!empty($ret["bookaccession"])) {
        $npg .= ", https://www.ncbi.nlm.nih.gov/books/".$ret["bookaccession"];
      }
    } else if (!empty($ret["doi"])) {
      $npg .= ", doi : ".$ret["doi"];
    } else if (!empty($ret["bookaccession"])) {
      $npg .= ", https://www.ncbi.nlm.nih.gov/books/".$ret["bookaccession"];
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
    if (substr_compare(".", $t, -strlen($t)) === 0) {
      mb_substr($t, 0, -1);
    }
    $ret["npg_full"] .= $t." ";
    $ret["npg_full"] .= $ret["npg_iso"];

    return $ret;
  }
  
  /*
   * Normalize case of the author's name
   */
  function _normalizeNameCase($name) {
    // Only change fully uppered names
    if (ctype_upper($name)) {
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
      "Authors' conclusions:",
      "Authors conclusions:",
      "Background \& aims:",
      "Background and objectives:",
      "Background:",
      "Background\/objectives:",
      "Clinical rehabilitation impact:",
      "Clinical relevance:",
      "Clinical significance:",
      "Clinical trial registration:",
      "Comparison:",
      "Conclusion:",
      "Conclusions and implications:",
      "Conclusions and relevance:",
      "Conclusions:",
      "Data collection and analysis:",
      "Data extraction:",
      "Data sources and review methods:",
      "Data synthesis:",
      "Design, study, and participants:",
      "Design:",
      "Diagnosis of interest:",
      "Eligibility criteria:",
      "Experimental design:",
      "Exposures:",
      "Findings:",
      "Funding:",
      "Implications:",
      "Inclusion criteria population:",
      "Index test:",
      "Information sources:",
      "Interpretation:",
      "Intervention:",
      "Introduction:",
      "Keywords:",
      "Main outcome measures:",
      "Main outcomes and measures:",
      "Main outcomes:",
      "Main results:",
      "Material and methods:",
      "Materials \& methods:",
      "Measurements:",
      "Methodological quality:",
      "Method:",
      "Methods:",
      "Objective:",
      "Objectives:",
      "Outcomes:",
      "Participants:",
      "Participants\/setting:",
      "Patients and methods:",
      "Population:",
      "Primary and secondary outcome measures:",
      "Purpose and objective:",
      "Purpose:",
      "Reference test:",
      "Research question:",
      "Results:",
      "Search methods:",
      "Search strategy:",
      "Selection criteria:",
      "Setting:",
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
      "Trial registration:",
      "Types of studies:",
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
       return (substr_count($a, " ") < substr_count($b, " ")); 
    });
    // Replace in abstract
    foreach($chapters as $c) {
      $pattern = "/\s*".$c."\s+/i";
      $abstract = preg_replace($pattern, "$lf$boldS$c$boldE ", $abstract);
    }
//     $info = array();
//     $abstract = p_render('xhtml', p_get_instructions($abstract), $info);
//     echo '<pre>'.$abstract.'</pre>';
    return $abstract;
  }



} // class PubMed2020

?>