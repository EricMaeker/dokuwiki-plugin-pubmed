<?php
/*
description : Dokuwiki Eric Maeker Pubmed plugin
author      : Eric Maeker
email       : eric.maeker[at]gmail.com
lastupdate  : 2019-11-01
license     : Public-Domain
*/
$meta['default_command'] = array('multichoice', '_choices' => array('short','long','vancouver','long_abstract','summaryxml','search','user'));
$meta['user_defined_output'] = array('string');
$meta['limit_authors_vancouver'] = array('numericopt', '_min' => '1');
$meta['et_al_vancouver'] = array('string');


//Setup VIM: ex: et ts=2 enc=utf-8 :

