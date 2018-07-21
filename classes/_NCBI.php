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
  var $xmlStartPattern = '<?xml version="1.0" standalone="yes"?>';
  
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
    // Check length of the returned HTTP content, make a second try if necessary
    if (strlen($summary) < 500) {
      $summary = $this->HttpClient->get($url);
      if ($this->debugUsingEchoing)
        echo PHP_EOL.">> PUBMED: Second try: ".strlen($summary)." ".$url."<BR>".PHP_EOL;
    }
    
    // Check error in the content (no <pre></pre>)
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
    return $this->xmlStartPattern.htmlspecialchars_decode($matches[1]);
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

  /**
   * Get full abstract of the article stored in an Array where
   *      "pmid"          -> PMID 
   *      "url"           -> URL to PubMed site
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
  function getAbstract($xml, $pmid, $pluginObject) {
    // No XML return empty array
    if (empty($xml) || $xml === $this->xmlStartPattern)
      return array("pmid" => $pmid);

    // Use DOM php reader
    $dom = new DOMDocument;

    // Load XML document
    $dom->loadXML($xml);
    if (!$dom) {
      echo '<p>Erreur lors de l\'analyse du document</p>'; // TODO translate this
      return array();
    }
    $content = simplexml_import_dom($dom);
    $authors = array();
    $authorsVancouver = array();
    $abstract = "";
    $part = array();

    // Manage Book references
    if (!empty($content->BookDocument)) {
      $content = $content->BookDocument[0];
      $book = $content->Book[0];
    
      // Catch book references
			/*
			<PubmedBookArticle>
				<BookDocument>
					<PMID Version="1">28876803</PMID>
					<ArticleIdList>
						<ArticleId IdType="bookaccession">...</ArticleId>
					</ArticleIdList>
					<Book>
						<Publisher>
							<PublisherName>...</PublisherName>
							<PublisherLocation>...</PublisherLocation>
						</Publisher>
						<BookTitle book="sbu0010">...</BookTitle>
						<PubDate>
							<Year>...</Year>
							<Month>...</Month>
						</PubDate>
						<AuthorList Type="authors">
							<Author>
								<CollectiveName>...</CollectiveName>
							</Author>
						</AuthorList>
						<CollectionTitle book="sbumr">...</CollectionTitle>
						<Medium>...</Medium>
						<ReportNumber>...</ReportNumber>
					</Book>
					<Language>...</Language>
					<Abstract>
						<AbstractText>...</AbstractText>
						<CopyrightInformation...</CopyrightInformation>
					</Abstract>
				</BookDocument>
				<PubmedBookData>
					<History>
						<PubMedPubDate PubStatus="pubmed">
							<Year>2017</Year>
							<Month>9</Month>
							<Day>7</Day>
							<Hour>6</Hour>
							<Minute>1</Minute>
						</PubMedPubDate>
						<PubMedPubDate PubStatus="medline">
							<Year>2017</Year>
							<Month>9</Month>
							<Day>7</Day>
							<Hour>6</Hour>
							<Minute>1</Minute>
						</PubMedPubDate>
						<PubMedPubDate PubStatus="entrez">
							<Year>2017</Year>
							<Month>9</Month>
							<Day>7</Day>
							<Hour>6</Hour>
							<Minute>1</Minute>
						</PubMedPubDate>
					</History>
					<PublicationStatus>ppublish</PublicationStatus>
					<ArticleIdList>
						<ArticleId IdType="pubmed">28876803</ArticleId>
					</ArticleIdList>
				</PubmedBookData>
			</PubmedBookArticle>
			*/

      // Catch authors
      if (!empty($book->AuthorList)) {
        if (!empty($book->AuthorList->Author[0]->CollectiveName)) {
          array_push($authors, $book->AuthorList->Author[0]->CollectiveName);
        } else if (!empty($book->AuthorList)) {
          foreach($book->AuthorList[0]->Author as $author) {
            if (!empty($author->LastName) || !empty($author->ForeName))
              array_push($authors, $author->LastName.' '.$author->ForeName);
          } // foreach
        } else {
          array_push($authors, $pluginObject->getLang('no_author_listed'));
        }
      }

	  $ret = array(
		  "type" => "book",
		  "pmid" => $content->PMID[0],
		  "url" => sprintf($this->pubmedURL, urlencode($content->PMID[0])),
		  "authors" => $authors,
		  "title" => $book->BookTitle[0],
		  "lang" => $content->Language,
		  "year" => $book->PubDate[0]->Year[0],
		  "month" => $book->PubDate[0]->Month[0],
		  "abstract" => $content->Abstract[0]->AbstractText[0],
		  "publisherName" => $book->Publisher[0]->PublisherName[0],
		  "publisherLocation" => $cbook->Publisher[0]->PublisherLocation[0],
		  "collectionTitle" => $book->CollectionTitle[0],
		  "copyright" => $content->Abstract[0]->CopyrightInformation[0],
		  );
		  
		// Construct iso citation of this book
		$ym = "";
		$ret["iso"] = "";
		  
		if (!empty($ret["publisherName"]))
		  $ret["iso"] .= " ".$ret["publisherName"].". ";
		if (!empty($ret["publisherLocation"]))
		  $ret["iso"] .= ' '.$ret["publisherLocation"].'. ';

        if (!empty($ret["year"]) && !empty($ret["month"]))
		  $ym = $ret["year"].", ".$ret["month"];
		else
		  $ym = $ret["year"].$ret["month"];
		if (!empty($ym))
		  $ret["iso"] .= $ym.'. ';

		$ret["iso"] .= $ret["copyright"];
        return $ret;
        
        // TODO : Manage VANCOUVER CITATION
    }

	// Manage Article references
    $content = $content->MedlineCitation[0];
    $article = $content->Article[0];
    $journal = $article->Journal[0];
    $collectif = "";
    
    // Catch authors
    if (!empty($article->AuthorList)) {
      foreach($article->AuthorList[0]->Author as $author) {
        if (!empty($author->LastName) || !empty($author->ForeName)) {
          array_push($authors, $author->LastName.' '.$author->ForeName);
        }
        if (!empty($author->LastName) || !empty($author->Initials)) {
          array_push($authorsVancouver, $author->LastName.' '.$author->Initials);
        }
        if (!empty($author->CollectiveName))
          $collectif = $author->CollectiveName;
      } // foreach authors
    } else {
      array_push($authors, $pluginObject->getLang('no_author_listed'));
    }

    // If article has an Abstract catch it
    if (!empty($article->Abstract[0]->AbstractText)) {
      foreach($article->Abstract[0]->AbstractText as $part) {
        if (!empty($part["Label"]))
          $abstract .= $part["Label"].": ";
        $abstract .= $part.'<br>';
      }
    } else {
        $abstract = $pluginObject->getLang('no_abstract_available').'<br>';
    }

    // Catch doi, pmc
    $doi = "";
    if (!empty($content->PubmedData[0]->ArticleIdList[0]->ArticleId)) {
      foreach($content->PubmedData[0]->ArticleIdList[0]->ArticleId as $part) {
        if ($part["IdType"]=="doi") $doi = $part;
        if ($part["IdType"]=="pmc") $pmc = $part;
      }
    }

    // Create the object to return
    $ret = array(
	  "type" => "article",
      "pmid" => $content->PMID[0],
      "url" => sprintf($this->pubmedURL, urlencode($content->PMID[0])),
      "authors" => $authors,
      "authorsVancouver" => $authorsVancouver,
      "collectif" => $collectif,
      "title" => $article->ArticleTitle[0],
      "journal_iso" => $journal->ISOAbbreviation,
      "journal_title" => $journal->Title,
      "lang" => $article->Language,
      "vol" => $journal->JournalIssue[0]->Volume[0],
      "issue" => $journal->JournalIssue[0]->Issue[0],
      "year" => $journal->JournalIssue[0]->PubDate[0]->Year[0],
      "month" => $journal->JournalIssue[0]->PubDate[0]->Month[0], // Month English  Abbrev
      "day" => $journal->JournalIssue[0]->PubDate[0]->Day[0],
      "pages" => $article->Pagination[0]->MedlinePgn[0],
      "abstract" => $abstract,
      "doi" => $doi,
      "pmc" => $pmc
    );

    // Create first author for short output
    if (count($authors) > 1) {
        $ret['first_author'] = $authors[0].' <span class="etal">et al</span>';
    } else {
        $ret['first_author'] = $authors[0];
    }

    // Remove points from the journal_iso string (as we now it is an abbrev)
    $ret["journal_iso"] = str_replace(".", "", $ret["journal_iso"]);

    // Construct iso citation of this article
    $pubDate = $ret["year"]." ".$ret["month"]." ".$ret["day"];
    $pubDate = trim(str_replace("  ", " ", $pubDate));

    $ret["iso"] = $ret["journal_iso"].'. ';
    $ret["iso"] .= $pubDate.";";
    if (!empty($ret["vol"]))
      $ret["iso"] .= $ret["vol"];
    if (!empty($ret["issue"]))
      $ret["iso"] .= '('.$ret["issue"].')';
    $ret["iso"] .= ':'.$ret["pages"];

    // Construct Vancouver citation of this article
    // See https://www.nlm.nih.gov/bsd/uniform_requirements.html
    $vancouver = "";

    // Collectif d'auteurs eg: pmid 19171717
    // <Author ValidYN="Y">
    //   <CollectiveName>Diabetes Prevention Program Research Group</CollectiveName>
    // </Author>
    if (!empty($collectif)) {
        $vancouver = $collectif.". ";
    } else {
    
      // Manage limitation in number of authors
      $limit = $pluginObject->getConf('limit_authors_vancouver');
//       $limit = 4;
      $authorsToUse = $ret["authorsVancouver"];
      $addAndAl = false;
      if ($limit >= 1) {
        if (count($authorsToUse) > $limit) {
          $addAndAl = true;
          $authorsToUse = array_slice($authorsToUse, 0, $limit);
        }
      }

      if (count($authorsToUse) > 0) {
        $vancouver = implode(', ',$authorsToUse);
        if ($addAndAl)
          $vancouver .= " ".$pluginObject->getConf('et_al_vancouver');
        $vancouver .= ". ";
      } 
      // no authors -> nothing to add  Eg: pmid 12142303
    }
    
    // Supplément de périodique
    //    eg : pmid 12028325  nothing to do Suppl et S sont inclus dans le XML
    // Numéro à supplément
    //    eg : pmid 12084862
    // TODO : Références de nature particulière
    //    eg : pmid 12166575 15857727

    $vancouver .= $ret["title"];
    $vancouver .= " ".$ret["journal_iso"].".";
    $vancouver .= " ".$pubDate;
    $vancouver .= ";".$ret["vol"];
    if (!empty($ret["issue"]))
      $vancouver .= "(".$ret["issue"].")";
    $vancouver .= ":".$ret["pages"];

    $ret["vancouver"] = $vancouver;
    return $ret;
  }

}
?>
