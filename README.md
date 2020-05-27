# PubMed plugin for DokuWiki

Retrieves information from NCBI [PubMed]

See http://www.dokuwiki.org/plugin:pubmed2020

## Authors and licence

- Ikuo Obataya wrote the pubmed plugin in 2007-2016
- Eric Maeker improved this first plugins (without integrating new Ikuo code) from 2016 to 2019
- Code was rewritten in 2020 due to PubMed new API (see [updateCtx])
- License : Public Domain
- Version : 2020-05-27

## How does it work

See [in action]

### Getting informations

This plugin retrieves the MedLine description of articles and books recorded in the NCBI [pubmed] database and allow users to easily include article citation into their DokuWiki pages. The MedLine content is cached in the media directory of your wiki.

This plugin is perfectly adapted to dokuwiki farms.

### Including article citation into your pages

The syntax is quite easy:
- `{{pmid>pmid}}`
- or `{{pmid>command:pmid}}`

- Using the default options:
  - `{{pmid>24073682}}` where 24073682 is the PMID of the article as notified by pubmed.
  - `{{pmid>user:24073682}}` where 24073682 is the PMID of the article as notified by pubmed and the default *user* parameter will be used to create the article citation.

- Using specific formula:
  - `{{pmid>long:24073682}}` where 24073682 is the PMID of the article as notified by pubmed and *long* is the selected article citation formula.

- You can require multiple citations at once (creating a nice HTML list):
  - For example this list uses the citations used as examples in the Vancouver referencing paper
  - `{{pmid>vancouver:19171717,12142303,12028325,12084862,12166575,15857727}}`


### Including links to pubmed search page

You can also use this plugin to create [pubmed] search URL.
- `{{pmid>search:"Inappropriate Prescribing"[Mesh]}}`
- `{{pmid>search:"Drug-Related Side Effects and Adverse Reactions"[Mesh] AND (Review[ptyp] AND "loattrfree full text"[sb])}}`


## Options

### Citation formula

The article citation can be automatically included using pre-formatted outputs:
- *vancouver* : Full Vancouver citation see [Vancouver].
- *short* : ISO citation in a short way.
- *long* : full ISO citation including all authors, article title, journal title, volume, year, month, pages.
- *long_tt* : same as *long* but with translated title (if exists)
- *long_pdf* : full ISO citation including all authors, article title, journal title, volume, year, month, pages. If you own the PDF file a link will show.
- *long_tt_pdf* : same as *long_pdf* but with translated title (if exists)
- *long_abstract* : append the full abstract to the *long* citation. The abstract can toggled and is hidden by default.
- *long_tt_abstract* : same as *long_tt_abstract* but with translated title (if exists)
- *long_abstract_pdf* : append the full abstract to the *long* citation. The abstract can toggled and is hidden by default. If you own the PDF file a link will show.
- *long_tt_abstract_pdf* : same as *long_tt_abstract_pdf* but with translated title (if exists)
- or *user* defined : you can define you own citation formula (see below).

Provides by default a link to the PubMed page and to the free full text in PMC if exists.

### Plugin parameters

This plugin comes with some configuration parameters:
- *Default citation formula*.
- *Default user defined formula*.
- *Default authors limitation in Vancouver citation*
- *Default string replacement of authors over the Vancouver author limitation*

## Specific commands

Some more commands are available:
- *test* only for devs
- *raw_medline* show the retrieved MedLine code.
`{{pmid>summaryxml:24073682}}`
- *clear_raw_medline* : clear all cached Medline files
- *remove_dir* : remove the cache directory (by default */data/media/pubmed*)
- *recreate_cross_refs* : recreate the crossref (DOI <-> PMID)
- *full_pdf_list* : show all available PDF (see specific doc)

## User defined citation

You can use a simple string to define your own citation formula. The following tokens are available.

Token    | Content
-------- | ---
%pmid% | PMID with a link to [pubmed] citation
%type% | Type of the citation ("article", "book")
%authors% | All authors (complete lastname)
%authorsVancouver% | All authors (initials lastname)
%first_author% | Only first author +/- "*et al*"
%corporate_author% | Author collective
%title% | Title of the article
%title_tt% | Translated title in the original language of the publication
%book_title% | Title of the Book
%collection_title% | Title of the collection
%copyright% | Copyright
%country% | Country
%lang% | Language of the article
%journal_iso% | ISO Journal title (abbrev)
%journal_title% | Full Journal title
%journal_id% | Journal ID
%iso% | Self computed ISO citation
%so% | Medline ISO citation
%vol% | Volume
%issue% | Issue
%year% | Year
%month% | Month
%pages% | Pages
%abstract% | Abstract (togglable)
%doi% | DOI of the publication
%pii% | PII of the publication
%journal_url% | Link to Journal web site using the DOI
%pmc_url% | If available, link to free PDF of the article.
%abstractFr% | Show french translated abstract (see specific doc)
%localpdf% | Add link to local PDF file (see specific doc)

Hard coded formula    |  Content
--------------------- | ----------
*short*               | `%first_author%. %iso%. %pmid%. %journal_url% %pmc_url%`
*long*                | `%authors%. %title%. %iso%. %pmid%. %journal_url% %pmc_url%`
*long_pdf*            | `%authors%. %title%. %iso%. %pmid%. %journal_url% %pmc_url% %localpdf%`
*long_abstract*       | `%authors%. %title%. %iso%. %pmid%. %journal_url% %pmc_url% %abstract% %abstractFr%`
*long_abstract_pdf*   | `%authors%. %title%. %iso%. %pmid%. %journal_url% %pmc_url% %abstract% %abstractFr% %localpdf%`
*long_tt*                | `%authors%. %title_tt%. %iso%. %pmid%. %journal_url% %pmc_url%`
*long_tt_pdf*            | `%authors%. %title_tt%. %iso%. %pmid%. %journal_url% %pmc_url% %localpdf%`
*long_tt_abstract*       | `%authors%. %title_tt%. %iso%. %pmid%. %journal_url% %pmc_url% %abstract% %abstractFr%`
*long_tt_abstract_pdf*   | `%authors%. %title_tt%. %iso%. %pmid%. %journal_url% %pmc_url% %abstract% %abstractFr% %localpdf%`


## Styling

You can change the style of your citation. Please take a look at the style.css file for further information.

## Using local PDF

You get a direct link to your media PDF files of publications. You have to save the PDF files in the media directory: media/pubmed/pmid_pdf or media/pubmed/doi_pdf. Just use the PMID or DOI as file name. It is recommanded to use the PMID mode.

## Problems, wishes

Please use [github] repository to adress any comments, issues or wishes.

[pubmed]: https://pubmed.ncbi.nlm.nih.gov/
[updateCtx]: https://api.ncbi.nlm.nih.gov/lit/ctxp
[github]: https://github.com/EricMaeker/dokuwiki-plugin-pubmed/tree/dokuwiki-web-site
[in action]: https://www.maeker.fr/eric/wiki/fr:medical:cours:part:iatrogenese:references
[Vancouver]: https://www.nlm.nih.gov/bsd/uniform_requirements.html