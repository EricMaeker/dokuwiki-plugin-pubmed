<?php
/**
 * DokuWiki Plugin pubmed
 *
 * @license BSD-3 Clause http://www.gnu.org/licenses/bsd.html
 * @author  Eric Maeker, MD (fr) <eric.maeker@gmail.com>
 */

$meta['default_command'] = array('multichoice', '_choices' => array('short','long','vancouver','long_abstract','summaryxml','search','user'));
$meta['user_defined_output'] = array('string');
$meta['limit_authors_vancouver'] = array('numericopt', '_min' => '1');
$meta['et_al_vancouver'] = array('string');
$meta['remove_dot_from_journal_iso'] = array('onoff');
$meta['twitter_via_user_name'] = array('string');
$meta['twitter_url_shortener_format_pmid'] = array('string');
$meta['twitter_url_shortener_format_pmcid'] = array('string');

//Setup VIM: ex: et ts=2 enc=utf-8 :

