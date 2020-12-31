<?php
/*
description : Dokuwiki Eric Maeker Pubmed plugin
author      : Eric Maeker
email       : eric.maeker[at]gmail.com
lastupdate  : 2019-11-01
license     : Public-Domain
*/
// for the configuration manager
$lang['default_command'] = 'Choose the default command. This allow you to write {{pmid>234234}} instead of {{pmid>command:342342}}.';
$lang['user_defined_output'] = 'Tokened user output. Eg: %authors%. %title%. %iso%';
$lang['limit_authors_vancouver'] = 'Limit the number of printed authors with the Vancouver output and add "et al" (see below) at the end of authors listing.';
$lang['et_al_vancouver'] = 'Add this to the end of authors listing according to the author limitation defined. Eg: Author1 KL, Author2 ER et al.';
$lang['twitter_url_shortener_format_pmid'] = 'You can use URL shortener for PMID inside the tweet links. You can use %PMID% tags in your url format, they will be replaced with correct datas. Eg: \"https://agpr.fr/p/%PMID%"';
$lang['twitter_url_shortener_format_pmcid'] = 'You can use URL shortener for PMCID inside the tweet links. You can use %PMCID% tags in your url format, they will be replaced with correct datas. Eg: \"https://agpr.fr/pm/%PMCID%"';