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


//Setup VIM: ex: et ts=2 enc=utf-8 :

