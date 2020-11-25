<?php
/*
description : Dokuwiki PubMed2020 plugin
author      : Eric Maeker
email       : eric.maeker[at]gmail.com
lastupdate  : 2020-06-05
license     : Public-Domain
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
   */
  function getPubmedSearchURL($searchTerms) {
    // Split using | to get URL options: size, format, filter, sort
    $options = explode("|", $searchTerms);
    if (count($options) < 1)
      return "ERROR"; // TODO
    $url = sprintf($this->pubmedSearchURL, urlencode($options[0]));
    if (count($options) > 1)
      $url .= "&".implode("&", array_slice($options, 1));
    return $url;
  } // ok, V2020

  /**
   * Get full abstract of the article stored in an Array where
   *      "pmid"          -> PMID 
   *      "url"           -> URL to PubMed site
   *      "authors"       -> Array of authors
   *      "first_author"  -> First author + "et al." if other authors are listed
   *      "authorsLimit3" -> Three first authors + "et al." if other authors are listed
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
        case "AB": $ret["abstract"] = $value; break;
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
            $name = $n[0];
          } else {
            $n = explode(" ", trim($value));
              $name = $n[0];
              $sn = $n[1];
          }

          // Keep only first letter of each surname and lower it
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
          break;
        //case "PST": $ret[""] = $value; break; // SB  - IM
        case "SO": $ret["so"] = $value; break; //SO  - Rev Neurol (Paris). 2005 Apr;161(4):419-26. doi: 10.1016/s0035-3787(05)85071-4.
        case "CI" : $ret["copyright"] = $value; break;
        case "CN" : $ret["corporate_author"] = $value; break;
        case "CTI" : $ret["collection_title"] = $value; break;
        case "BTI" : 
          $ret["book_title"] = $value; 
          $ret["title"] = $value; 
          break;
        case "PB" : // Possible publisher? count as author?
          $ret["publisher"] = $value;
          break;
        
      }  // Switch
    } // Foreach

    // Get authors
    if ($ret["corporate_author"]) {
      array_push($authors, $ret["corporate_author"]);
    } else if ($ret["publisher"]) {
      array_push($authors, $ret["publisher"]);
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
        $authors3 .= " ".$pluginObject->getConf('et_al_vancouver');
      $authors3 .= ". ";
    } else {
      // Less than three authors
      $authors3 = implode(', ',$authorsToUse).". ";
    }
    $ret["authorsLimit3"] = $authors3;

    // no authors -> nothing to add  Eg: pmid 12142303

    // Get Mesh terms & keywords
    $ret["mesh"] = $mesh;
    $ret["keywords"] = $keywords;

    if ($ret["book_title"]) {
      // Author. <i>BookTitle</i>. country:PB;year.
      $ret["vancouver"] = $vancouver;
      $ret["vancouver"] .= "<i>".$ret["book_title"].".</i> ";
      $ret["iso"] = $ret["country"]." : ";
      $ret["iso"] .= $ret["year"].".";
      $ret["vancouver"] .= $ret["iso"];
      //echo print_r($ret);
      return $ret;
    }
    // Remove points from the journal_iso string
    if ($pluginObject->getConf('remove_dot_from_journal_iso') === true)
       $ret["journal_iso"] = str_replace(".", "", $ret["journal_iso"]);

    // Construct iso citation of this article
    // Use SO from the raw medline content
    $ret["iso"] = $ret["so"];

    // Construct NPG ISO citation of this article
    //%npg_iso% %year% ; %vol% (%issue%) : %pages%
    if (!empty($ret["journal_iso"])) {
       $npg = str_replace(".", "", $ret["journal_iso"])." ";
    }
    if (!empty($ret["year"])) {
      $npg .= $ret["year"];
      if (!empty($ret["vol"])) {
          $npg .= " ; ".$ret["vol"];
        if (!empty($ret["issue"])) {
          $npg .= " (".$ret["issue"].")";
        }
        if (!empty($ret["pages"])) {
          $npg .= " : ".$ret["pages"];
        }
      } else {
        $npg .= ", doi : ".$ret["doi"];
      }
    } else {
        $npg .= ", doi : ".$ret["doi"];
    }
    $npg = trim(str_replace("  ", " ", $npg));
    $ret["npg_iso"] = $npg;

/*
    $ret["iso"] = $ret["journal_iso"].' ';
    $ret["iso"] .= $pubDate.";";
    if (!empty($ret["vol"]))
      $ret["iso"] .= $ret["vol"];
    if (!empty($ret["issue"]))
      $ret["iso"] .= '('.$ret["issue"].')';
    $ret["iso"] .= ':'.$ret["pages"];
*/
    // Construct Vancouver citation of this article
    // See https://www.nlm.nih.gov/bsd/uniform_requirements.html
    $vancouver .= $ret["title"];
    $vancouver .= " ".$ret["so"];
//     $vancouver .= " ".$ret["journal_iso"]."";
//     $vancouver .= " ".$pubDate;
//     $vancouver .= ";".$ret["vol"];
//     if (!empty($ret["issue"]))
//       $vancouver .= "(".$ret["issue"].")";
//     $vancouver .= ":".$ret["pages"];
    $ret["vancouver"] = $vancouver;

    //echo print_r($ret);
    return $ret;
  } // Ok pubmed2020



} // class PubMed2020

?>