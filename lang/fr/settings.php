<?php
/*
description : Dokuwiki Eric Maeker Pubmed plugin
author      : Eric Maeker
email       : eric.maeker[at]gmail.com
lastupdate  : 2019-11-01
license     : Public-Domain
*/
// for the configuration manager
$lang['default_command'] = 'Choose the default command. This allow you to write {{pubmed>234234}} instead of {{pubmed>command:342342}}.';
$lang['user_defined_output'] = 'Tokened user output. Eg: %authors%. %title%. %iso%';
$lang['limit_authors_vancouver'] = 'Limit the number of printed authors with the Vancouver output and add "et al" (see below) at the end of authors listing.';
$lang['et_al_vancouver'] = 'Add this to the end of authors listing according to the author limitation defined. Eg: Author1 KL, Author2 ER et al.';