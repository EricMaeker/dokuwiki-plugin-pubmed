<?php
/*
description : Dokuwiki PubMed2020 plugin
author      : Eric Maeker
email       : eric.maeker[at]gmail.com
lastupdate  : 2020-05-26
license     : Public-Domain
*/

if(!defined('DOKU_INC')) die();

class PubMed2020 {
  var $HttpClient;
  // New PubMed interface. See https://api.ncbi.nlm.nih.gov/lit/ctxp
  var $ctxpURL_RIS = "https://api.ncbi.nlm.nih.gov/lit/ctxp/v1/pubmed/?format=ris&id=%s";
  var $ctxpURL_MEDLINE = "https://api.ncbi.nlm.nih.gov/lit/ctxp/v1/pubmed/?format=medline&id=%s";
  var $ctxpURL_CSL = "https://api.ncbi.nlm.nih.gov/lit/ctxp/v1/pubmed/?format=csl&id=%s";

  var $pubmedURL       = 'https://pubmed.ncbi.nlm.nih.gov/%s';
  var $pubmedSearchURL = 'https://pubmed.ncbi.nlm.nih.gov/?term=%s';
  var $scihubURL = "https://sci-hub.tw/%s";
  
  // Set this to true to get debugging page output when retrieving and processing pubmed URL
  var $debugUsingEchoing = false; 

  public function __construct()
  {
    $this->HttpClient   = new DokuHTTPClient();
  } // Ok, V2020


  function startsWith($string, $startString) 
  { 
    $len = strlen($startString); 
    return (substr($string, 0, $len) === $startString); 
  }

  /*
   * Get RIS, MEDLINE and CITATION from CTXP website
  */
  function getDataFromCtxp($pmid, $doi="") {
    $url = "";
    if (!empty($pmid))
        $url = sprintf($this->ctxpURL_MEDLINE, urlencode($pmid));
//     else if (!empty($doi))
//         $url = sprintf($this->pubmedXmlURLFromDOI, urlencode($doi));
    else
        return "";
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
   */
  function getPubmedSearchURL($searchTerms) {
    return sprintf($this->pubmedSearchURL, urlencode($searchTerms));
  } // ok, V2020

  /**
   * Get full abstract of the article stored in an Array where
   *      "pmid"          -> PMID 
   *      "url"           -> URL to PubMed site
   *      "scihuburl"     -> URL to sci-hub site
   *      "authors"       -> Array of authors
   *      "first_author"  -> First author + "et al." if other authors are listed
   *      "title"         -> Full title
   *      "lang"          -> language of the article
   *      "journal_iso"   -> Journal ISO Abbreviation
   *      "journal_title" -> Journal full title
   *      "iso"           -> ISO citation of the article
   *      "vol"           -> Journal Volume
   *      "issue"         -> Journal Issue
   *      "year"          -> Journal Year of publication
   *      "month"         -> Journal Month of publication
   *      "pages"         -> Journal pagination
   *      "abstract"      -> Complete abstract
   *      "doi"           -> doi references when available
   * $pluginObject must be accessible for translations ($this->getLang())
   * $pmid is used for error message
   */
  function readMedlineContent($string, $pmid, $pluginObject) {
    // No data return empty array
    if (empty($string))
      return array("pmid" => $pmid);
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
        //echo PHP_EOL."k: ".$key." ; val: ".$val.PHP_EOL;
        $id++;
        $array[$key] = $val;
      }
    }
    //echo print_r($array);

    // Now process datas
    // TODO: Catch book references. Eg: 28876803
    $ret = array();
    $mesh = array();
    foreach($array as $key => $value) {
      $k = preg_replace('/[0-9]+/', '', $key);

      switch ($k) {  // See https://www.nlm.nih.gov/bsd/mms/medlineelements.html
        case "PMID": 
          $ret["pmid"] = $value;  //PMID - 15924077
          $ret["url"] = sprintf($this->pubmedURL, urlencode($value));
          break;
        case "DCOM": break; //DCOM- 20050929
        case "LR": break;  //LR  - 20191109
        case "IS": break;  //IS  - 0035-3787 (Print)  //IS  - 0035-3787 (Linking)
        case "VI": $ret["vol"] = $value; break;  //VI  - 161
        case "IP": $ret["issue"] = $value; break; //IP  - 4
        case "DP": 
          $ret["year"] = substr($value,0,4);
          break; //DP  - 2005 Apr
        case "TI": $ret["title"] = $value; break; // TI title english
        case "PG": $ret["pages"] = $value; break;
        case "AB": $ret["abstract"] = $value; break;
        case "AU": array_push($authors, $value); break;
        case "LA": $ret["lang"] = $value; break; //LA  - fre
        case "PT": $ret["type"] = $value; break; //PT  - English Abstract  //PT  - Journal Article
        case "TT": $ret["translated_title"] = $value; break;
        case "PL": $ret["country"] = $value; break;  //PL  - France
        case "TA": $ret["journal_iso"] = $value; break; // TA  - Rev Neurol (Paris)
        case "JT": $ret["journal_title"] = $value; break; // JT  - Revue neurologique
        case "JID": $ret["journal_id"] = $value; break; // JID - 2984779R
//         case "SB": $ret[""] = $value; break; // SB  - IM
        case "MH": array_push($mesh, $value); break; // MH  - *Accidental Falls
//         case "EDAT": $ret[""] = $value; break; // SB  - IM
//         case "MHDA": $ret[""] = $value; break; // SB  - IM
//         case "CRDT": $ret[""] = $value; break; // SB  - IM
//         case "PHST": $ret[""] = $value; break; // SB  - IM
        case "AID": 
          if (strpos($value, "[doi]") > 0)
            $ret["doi"] = str_replace(" [doi]", "", $value); 
          if (strpos($value, "[pii]") > 0)
            $ret["pii"] = str_replace(" [pii]", "", $value);
          break;
        //case "PST": $ret[""] = $value; break; // SB  - IM
        case "SO": $ret["so"] = $value; break; //SO  - Rev Neurol (Paris). 2005 Apr;161(4):419-26. doi: 10.1016/s0035-3787(05)85071-4.
        case "PMC": $ret["pmc"] = $value; break;//PMC - PMC6549299
        case "CI" : $ret["copyright"] = $value; break;
        case "CN" : $ret["corporate_author"] = $value; break;
        case "CTI" : $ret["collection_title"] = $value; break;
        case "BTI" : 
          $ret["book_title"] = $value; 
          $ret["title"] = $value; 
          break;
        
      }  // Switch
    } // Foreach

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
      $ret['first_author'] = $authors[0].' <span class="etal">et al</span>';
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
    // no authors -> nothing to add  Eg: pmid 12142303

    // Get Mesh terms
    $ret["mesh"] = $mesh;

    if ($ret["book_title"]) {
      // Author. <i>BookTitle</i>. country:PB;year.
      $ret["vancouver"] = $vancouver;
      $ret["vancouver"] .= "<i>".$ret["book_title"].".</i> ";
      $ret["iso"] = $ret["country"]." : ";
      $ret["iso"] .= $ret["year"].".";
      $ret["vancouver"] .= $ret["iso"];
      $ret["scihuburl"] = sprintf($this->scihubURL, urlencode($ret["doi"]));
      //echo print_r($ret);
      return $ret;
    }
    // Remove points from the journal_iso string
    if ($pluginObject->getConf('remove_dot_from_journal_iso') === true)
       $ret["journal_iso"] = str_replace(".", "", $ret["journal_iso"]);

    // Construct iso citation of this article
    $pubDate = $ret["year"]." ".$ret["month"]." ".$ret["day"];
    $pubDate = trim(str_replace("  ", " ", $pubDate));

    $ret["iso"] = $ret["journal_iso"].' ';
    $ret["iso"] .= $pubDate.";";
    if (!empty($ret["vol"]))
      $ret["iso"] .= $ret["vol"];
    if (!empty($ret["issue"]))
      $ret["iso"] .= '('.$ret["issue"].')';
    $ret["iso"] .= ':'.$ret["pages"];

    // Construct Vancouver citation of this article
    // See https://www.nlm.nih.gov/bsd/uniform_requirements.html
    $vancouver .= $ret["title"];
    $vancouver .= " ".$ret["journal_iso"]."";
    $vancouver .= " ".$pubDate;
    $vancouver .= ";".$ret["vol"];
    if (!empty($ret["issue"]))
      $vancouver .= "(".$ret["issue"].")";
    $vancouver .= ":".$ret["pages"];
    $ret["vancouver"] = $vancouver;

    $ret["scihuburl"] = sprintf($this->scihubURL, urlencode($ret["doi"]));
    //echo print_r($ret);
    return $ret;
  } // Ok pubmed2020



} // class PubMed2020

?>