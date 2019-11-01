# PubMed plugin for DokuWiki

Retrieves information from NCBI [PubMed]

See http://www.dokuwiki.org/plugin:pubmed

## Authors and licence

- Plugin based on the Ikuo Obataya pubmed code (2007-2016)
- Eric Maeker, MD (Fr)
  - [WebSite]
  - [Email]
  - Mostly completely rewrote the code
  - from 2016 to 2019
- Licence : Public-Domain
- Version : 01 November 2019

## How does it work

See [in action]

## Prerequisites

This plugins is tested with PHP5.6, PHP7.3. You need to install the `php-xml` extension.

### Getting informations

This plugin retrieves the XML description of articles from NCBI [pubmed] and allow users to easily include article citation into their DokuWiki pages.
XML content is cached in the media directory of your wiki.

This plugin is perfectly adapted to dokuwiki farms.

### Including article citation into your pages

The syntax is quite easy:
- `{{pubmed>pmid}}`
- or `{{pubmed>command:pmid}}`

- Using the default options:
  - `{{pubmed>24073682}}` where 24073682 is the PMID of the article as notified by pubmed.

- Using specific formula:
  - `{{pubmed>long:24073682}}` where 24073682 is the PMID of the article as notified by pubmed and *long* is the selected article citation formula.

- You can require multiple citations at once (creating a nice HTML list):
  - For example this list uses the citations used as examples in the Vancouver referencing paper
  - `{{pubmed>vancouver:19171717,12142303,12028325,12084862,12166575,15857727}}`


### Including links to pubmed search page

You can also use this plugin to create [pubmed] search URL.
- `{{pubmed>search:"Inappropriate Prescribing"[Mesh]}}`
- `{{pubmed>search:"Drug-Related Side Effects and Adverse Reactions"[Mesh] AND (Review[ptyp] AND "loattrfree full text"[sb])}}`


## Options

### Citation formula

The article citation can be automatically included using pre-formatted outputs:
- *vancouver* : Full Vancouver citation see [Vancouver].
- *short* : ISO citation in a short way.
- *long* : full ISO citation including all authors, article title, journal title, volume, year, month, pages.
- *long_pdf* : full ISO citation including all authors, article title, journal title, volume, year, month, pages. If you own the PDF file a link will show.
- *long_abstract* : append the full abstract to the *long* citation. The abstract can toggled and is hidden by default.
- *long_abstract_pdf* : append the full abstract to the *long* citation. The abstract can toggled and is hidden by default. If you own the PDF file a link will show.
- or *user* defined : you can define you own citation formula (see below).

### Plugin parameters

This plugin comes with some configuration parameters:
- *Default citation formula*.
- *Default user defined formula*.
- *Default authors limitation in Vancouver citation*
- *Default string replacement of authors over the Vancouver author limitation*

## Specific commands

Some more commands are available:
- *summaryxml* show the retrieved XML code.
`{{pubmed>summaryxml:24073682}}`
- *clear_summary* : clear all cached files
- *remove_dir* : remove the cache directory (by default `/data/media/ncbi_esummary`)
- *recreate_cross_refs* : recreate the crossref (DOI <-> PMID)
- *full_pdf_list* : show all available PDF (see specific doc)

## User defined citation

You can use a simple string to define your own citation formula. In first, you need to define it in the plugin params `user_defined_output`, then you can call it using the `user` command : `{{pubmed>user:20413750}}`.

The following tokens are available.

Token    | Content
-------- | ---
%pmid% | PMID with a link to [pubmed] citation
%type% | Type of the citation ("article", "book")
%authors% | All authors (complete lastname)
%authorsVancouver% | All authors (initials lastname)
%first_author% | Only first author +/- "*et al*"
%collectif% | Author collective
%title% | Title of the article
%lang% | Language of the article
%journal_iso% | ISO Journal title (abbrev)
%journal_title% | Full Journal title
%iso% | ISO formula
%vol% | Volume
%issue% | Issue
%year% | Year
%month% | Month
%pages% | Pages
%abstract% | Abstract (togglable)
%doi% | DOI of the publication
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

## Styling

You can change the style of your citation. Please take a look at the style.css file for further information.

## Using abstract translations

In your media dir (by default `media/ncbi_esummary`), add a textual file with the translation. Name it : `pubmed_PMID_fr.txt` where PMID is the PMID of the citation. That's all.

## Using local PDF

You get a direct link to your media PDF files of publications. You have to save the PDF files in the media directory: `media/ncbi_esummary/pmid_pdf` or `media/ncbi_esummary/doi_pdf`. Just use the PMID or DOI as file name. It is recommanded to use the PMID mode.

## Problems, wishes

Please use [github] repository to adress any comments, issues or wishes.

## To Do

- Add multiple language abstract translations
- Add a `related` to list the related articles of one PMID
- Use the new NCBI API

[WebSite]: https://www.maeker.fr
[Email]: eric.maeker@gmail.com
[pubmed]: https://www.ncbi.nlm.nih.gov/pubmed
[github]: https://github.com/EricMaeker/dokuwiki-plugin-pubmed/tree/dokuwiki-web-site
[in action]: https://www.maeker.fr/eric/wiki/fr/humaniste/soins/centre_personne_biblio
[Vancouver]: https://www.nlm.nih.gov/bsd/uniform_requirements.html