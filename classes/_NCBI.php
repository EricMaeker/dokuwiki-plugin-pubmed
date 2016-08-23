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
    // Prepare pubmed URL using the XML text abstract
    $url = sprintf($this->pubmedXmlURL, urlencode($id));
    if ($this->debugUsingEchoing)
      echo PHP_EOL.">> PUBMED: getting URL: ".$url.PHP_EOL;

    // Get it
    $summary = $this->HttpClient->get($url);
    // Check error
    if (preg_match("/<pre>\s+<\/pre>/i",$summary)) {
      if ($this->debugUsingEchoing)
        echo PHP_EOL.">> PUBMED: Error while retrieving URL: ".$url.PHP_EOL;
      return NULL; 
    }

    // Extract everything inside the PubmedArticle tagname
    if ($this->debugUsingEchoing)
      echo PHP_EOL.">> PUBMED: retrieved from the URL: ".PHP_EOL.$summary.PHP_EOL;
      
    // Now extract the requiered XML code from what we got from the pubmed website
    // The code lies inside the unique <pre></pre> HTML block of this page
    $tagname = "pre";
    $pattern = "#<\s*?$tagname\b[^>]*>(.*?)</$tagname\b[^>]*>#s";
    preg_match($pattern, $summary, $matches);
    if ($this->debugUsingEchoing)
      echo PHP_EOL.">> PUBMED: processed: ".PHP_EOL.htmlspecialchars_decode($matches[1]).PHP_EOL;
    return '<?xml version="1.0" standalone="yes"?>'.htmlspecialchars_decode($matches[1]);
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
   * Get full abstract of the article stored in an Array where
   *      "pmid"          -> PMID 
   *      "url"           -> URL to PubMed site
   *      "authors"       -> Array of authors
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
   */
  function getAbstract($xml) {
    // Use DOM php reader
    $dom = new DOMDocument;
    $dom->loadXML($xml);
    if (!$dom) {
      echo '<p>Erreur lors de l\'analyse du document</p>'; // TODO translate this
      return array();
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
      "journal_title" => $content->MedlineCitation[0]->Article[0]->Journal[0]->Title,
      "lang" => $content->MedlineCitation[0]->Article[0]->Language,
      "vol" => $content->MedlineCitation[0]->Article[0]->Journal[0]->JournalIssue[0]->Volume[0],
      "issue" => $content->MedlineCitation[0]->Article[0]->Journal[0]->JournalIssue[0]->Issue[0],
      "year" => $content->MedlineCitation[0]->Article[0]->Journal[0]->JournalIssue[0]->PubDate[0]->Year[0],
      "month" => $content->MedlineCitation[0]->Article[0]->Journal[0]->JournalIssue[0]->PubDate[0]->Month[0],
      "pages" => $content->MedlineCitation[0]->Article[0]->Pagination[0]->MedlinePgn[0],
      "abstract" => $abstract,
      "doi" => $doi
      );

    // Remove points from the journal_iso string (as we now it is an abbrev
    $ret["journal_iso"] = str_replace(".", "", $ret["journal_iso"]);

    // Construct iso citation of this article
    $ym = "";
    $ret["iso"] = $ret["journal_iso"].'. ';
    if (!empty($ret["year"]) && !empty(rel["month"]))
      $ym = $ret["year"]." ".$ret["month"];
    else
      $ym = $ret["year"].$ret["month"];
    if (!empty($ym))
      $ret["iso"] .= $ym.';';
    if (!empty($ret["vol"]))
      $ret["iso"] .= $ret["vol"];
    if (!empty($ret["issue"]))
      $ret["iso"] .= '('.$ret["issue"].')';
    $ret["iso"] .= ':'.$ret["pages"];
    if (!empty($ret["doi"]))
      $ret["iso"] .= ". doi: ".$ret["doi"];
    return $ret;
  }

}
?>
