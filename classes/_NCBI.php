<?php
/*
description : Access to NCBI using eSummary and eSearch
author      : Ikuo Obataya, Eric Maeker
email       : i.obataya[at]gmail_com, eric[at]maeker.fr
lastupdate  : 2016-08-22
license     : GPL 2 (http://www.gnu.org/licenses/gpl.html)
*/

if(!defined('DOKU_INC')) die();

class ncbi {
  var $HttpClient;
  var $pubmedURL   = '';
  var $pubmedXmlURL = '';
  var $pubmedSearchURL  = '';
  
  // Set this to true to get debugging page output when retrieving and processing pubmed URL
  var $debugUsingEchoing = false; 

  function ncbi()
  {
    $this->HttpClient   = new DokuHTTPClient();
    $this->pubmedURL    = 'http://www.ncbi.nlm.nih.gov/pubmed/%s';
    $this->pubmedXmlURL = 'http://www.ncbi.nlm.nih.gov/pubmed/%s?report=xml&format=text';
    $this->pubmedSearchURL = 'http://www.ncbi.nlm.nih.gov/pubmed/?term=%s';
  }

  /*
   * Retrieve Summary XML
   */
  function SummaryXml($db,$id) {
    $url = sprintf($this->pubmedXmlURL, urlencode($id));

    // Get it
    $summary = $this->HttpClient->get($url);
    // Check error
    if (preg_match("/<pre><\/pre>/i",$summary)) {
      if ($this->debugUsingEchoing)
        echo PHP_EOL.">> PUBMED: Error while retrieving URL: ".$url.PHP_EOL;
      return NULL; 
    }

    $pattern = "#<\s*?$tagname\b[^>]*>(.*?)</$tagname\b[^>]*>#s";
    preg_match($pattern, $summary, $matches);
    $summary = '<?xml version="1.0" standalone="yes"?>'.htmlspecialchars_decode($matches[1]);
    return $summary;
  } // Ok, checked
  
  /*
   * Retrieve Search result
   */
  function SearchXml($db,$term){
    $result = $this->HttpClient->get(sprintf($this->eSearchURL,urlencode($db),urlencode($term)));
    if (preg_match("/error/i",$result)){return NULL;}
    return $result;
  }
  
  /*
   * Create a pubmed query, return the URL of the query
   */
  function getPubmedSearchURL($searchTerms) {
    return sprintf($this->pubmedSearchURL, urlencode($searchTerms));
  }

  /*
   * Handle XML elements
   */

  function GetSummaryItem($item,&$xml){
    preg_match('/"'.$item.'"[^>]*>([^<]+)/',$xml,$m);
    return $m[1];
  }

  function GetSummaryItems($item,&$xml){
    preg_match_all('/"'.$item.'"[^>]*>([^<]+)/',$xml,$m);
    return $m[1];
  }

  function GetSearchItem($item,&$xml){
     preg_match("/<".$item.">([^<]+?)</",$xml,$m);
     return $m[1];
  }

  function GetSearchItems($item,&$xml){
     preg_match_all("/<".$item.">([^<]+?)</",$xml,$m);
     return $m[1];
  }

  function getAbstract($xml) {
    $dom = new DOMDocument;
    $dom->loadXML($xml);
    if (!$dom) {
      echo '<p>Erreur lors de l\'analyse du document</p>';
      exit;
    }
    $content = simplexml_import_dom($dom);
    $authors = array();
    foreach ($content->MedlineCitation[0]->Article[0]->AuthorList[0]->Author as $author) {
      array_push($authors, $author->LastName.' '.$author->ForeName);
    }
    $abstract = "";
    foreach ($content->MedlineCitation[0]->Article[0]->Abstract[0]->AbstractText as $part) {
      if (!empty($part["Label"]))
        $abstract .= $part["Label"].": ";
      $abstract .= $part.'<br>';
    }
    $doi = "";
    foreach ($content->PubmedData[0]->ArticleIdList[0]->ArticleId as $part) {
      if($part["IdType"]=="doi") $doi = $part;
    }
        
    $ret = array(
      "pmid" => $content->MedlineCitation[0]->PMID[0],
      "url" => sprintf($this->pubmedURL, urlencode($content->MedlineCitation[0]->PMID[0])),
      "authors" => $authors,
      "title" => $content->MedlineCitation[0]->Article[0]->ArticleTitle[0],
      "journal_iso" => $content->MedlineCitation[0]->Article[0]->Journal[0]->ISOAbbreviation,
      "lang" => $content->MedlineCitation[0]->Article[0]->Language,
      "vol" => $content->MedlineCitation[0]->Article[0]->Journal[0]->JournalIssue[0]->Volume[0],
      "issue" => $content->MedlineCitation[0]->Article[0]->Journal[0]->JournalIssue[0]->Issue[0],
      "year" => $content->MedlineCitation[0]->Article[0]->Journal[0]->JournalIssue[0]->PubDate[0]->Year[0],
      "month" => $content->MedlineCitation[0]->Article[0]->Journal[0]->JournalIssue[0]->PubDate[0]->Month[0],
      "pages" => $content->MedlineCitation[0]->Article[0]->Pagination[0]->MedlinePgn[0],
      "abstract" => $abstract,
      "doi" => $doi
      );
    $ret["iso"] = $ret["journal_iso"].'. '.$ret["year"];
    if (!empty($refs["month"]))
      $ret["iso"] .= ' '.$ret["month"];
    $ret["iso"] .= ';'.$ret["vol"];
    if (!empty($refs["issue"]))
      $ret["iso"] .= '('.$ret["issue"].')';
    $ret["iso"] .= ':'.$ret["pages"];
    if (!empty($ret["doi"]))
      $ret["iso"] .= ". doi: ".$ret["doi"];
    return $ret;
  }

}
?>
