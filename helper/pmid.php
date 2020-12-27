<?php
/*
description : Dokuwiki PubMed2020 plugin: PubMed2020 Helper for Bureaucracy
author      : Eric Maeker
email       : eric.maeker[at]gmail.com
lastupdate  : 2020-12-27
license     : Public-Domain
*/

/**
 * Class helper_plugin_pubmed2020_pmid
 *
 * A PubMed2020 form field for Bureaucracy plugin
 *
 * Usage:
 * <form>
 * ...
 * pubmed2020_pmid "label"
 * </form>
 *
 * Notes:
 * You MUST only use ONE pubmed2020_pmid field by form.
 *
 * Template usage:
 * @@yourlabel@@  -> replaced with PMID
 * @@yourlabel.dataname@@  -> replaced with paper 'dataname' value where 'dataname' in
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
 */
class helper_plugin_pubmed2020_pmid extends helper_plugin_bureaucracy_fieldtextbox {

    var $paper_refs = array();

    /**
     * Arguments:
     *  - cmd
     *  - label
     *  - ^ (optional)
     *
     * @param array $args The tokenized definition, only split at spaces
     */
    public function initialize($args) {
        parent::initialize($args);
        $this->tpl['class'] .= ' pubmed';
    }

    /**
     * Allow receiving paper attributes by ".". 
     *    Ex. yourlabel.attribute
     * TODO: You can pass an optional argument enclosed in brackets, used as a delimiter
     *    Ex. yourlabel.attributewithdelimiter(, )
     *
     * @return string
     */
    public function getReplacementPattern() {
        $label = $this->opt['label'];
        return '/(@@|##)' . preg_quote($label, '/') .
            '(?:\.(.*?))?' .    //match attribute after "."
            '(?:\((.*?)\))?' .  //match parameter enclosed in "()". Used for grps separator
            '\1/si';
    }

    /**
     * Used as an callback for preg_replace_callback
     *
     * Access PMID references
     * When using a pubmed2020_pmid field:
     *   additional data of the selected paper (PMID) can be used in the template
     *   '@@fieldlabel.EXTRAFIELD@@' replace with the EXTRAFIELD of the publication
     *
     * \see PubMed2020::readMedlineContent() documentation for the EXTRAFIELD list
     *
     * @param $matches
     * @return string
     */
    public function replacementValueCallback($matches) {
//         echo PHP_EOL."<!-------- replacementMultiValueCallback ".PHP_EOL;
//         echo print_r($matches);
//         echo "------>".PHP_EOL;
        
        // Get PMID references

        $value = $this->opt['value'];
        // attr doesn't exists
        if (!isset($matches[2])) {
            return is_null($value) || $value === false ? '' : $value;
        }
        $attr = $matches[2];

        // Check and use delimiter
        // $delimiter = ', ';
        // if (isset($matches[3])) {
        //     $delimiter = $matches[3];
        //  }
        //  return implode($delimiter, array());

        if (!empty($this->paper_refs[$attr]))
          return $this->paper_refs[$attr];

        return $matches[0];
    }

    /**
     * Return the callback for user replacement
     *
     * @return array
     */
    public function getReplacementValue() {
        return array($this, 'replacementValueCallback');
    }

    /**
     * Validate value of field
     * Download from PubMed and 
     * Get the PMID references 
     * Using the PubMed2020 syntax plugin
     *
     * @throws Exception when user not exists
     */
    protected function _validate() {
        parent::_validate();

        $pmid = $this->opt['value'];
        
        // Get the PMID using syntax_plugin_pubmed2020
        // And store the references array in $this->paper_refs var
        if(!plugin_isdisabled('pubmed2020')) {
          $plugin =& plugin_load('syntax', 'pubmed2020');
          if($plugin) {
            // With the PubMed2020 plugin: get raw medline content from pubmed website
            $raw = $plugin->getMedlineContent("pmid", $pmid);

            // With the PubMed2020 plugin: process raw to get the reference array
            if (empty($raw))
              $this->paper_refs = array();
            else
              $this->paper_refs = $plugin->pubmed2020->readMedlineContent($raw, $plugin);
          }
        }
    }
}
