google-spreadsheet-mw-plugin
============================

A [MediaWiki](http://www.mediawiki.org) plug-in for accessing values in a Google Spreadsheet.  It provides a tag you can insert into MediaWiki documents; each use of the tag can reference a cell in a Google Docs spreadsheet and return the value in that cell.

----
*Author*: Michael Hucka (http://www.cds.caltech.edu/~mhucka)

*Copyright*: Copyright (C) 2012-2014 by the California Institute of Technology, Pasadena, USA.

*License*: This code is licensed under the LGPL version 2.1.  Please see the Please see the file [../COPYING.txt](https://raw.github.com/mhucka/google-spreadsheet-mw-plugin/master/COPYING.txt) for details.

*Repository*: https://github.com/mhucka/google-spreadsheet-mw-plugin


Requirements
------------

1. This is designed to work as a plug-in for MediaWiki.  It has so far only been tested and used with MediaWiki 1.11.
2. This relies on a JSON parser.  It was written to use the JSON PECL extension in PHP 5.1.6, but other json parsers would probably work with small modifications.


Background
----------

In one of our projects, we maintain a large spreadsheet in Google Docs for tracking the status of different subprojects.  Most of the other public information about the subprojects, however, is maintained on our website, which is implemented using MediaWiki together with a custom skin and extensions.  We didn't want to manually copy data from that spreadsheet into the wiki pages because it would inevitably fall out of sync.  After searching and failing to find a MediaWiki plug-in to return values from a Google Docs spreadsheet, I implemented this solution.

The *google-spreadsheet-mw-plugin* provides a tag, `<gcscellvalue>`, that can be used in wiki pages.  The tag takes arguments specifying a spreadsheet in Google Docs and a cell within that spreadsheet.  When the page is read, the tag returns the value of the spreadsheet cell, optionally doing some additional manipulations on the value.  The result is that you can write web pages that seamlessly integrate data and text automatically fetched directly from the spreadsheet.


Usage
-----

There are three parts to using this.  First, you need to add the plug-in to your MediaWiki installation.  Second, you need to configure it to be able to access one or more spreadsheets in Google Docs.  Third, you need to write wiki pages that use the tag provided by the plug-in.

### Installing the plug-in

Use the same procedure as you would to install any other MediaWiki plug-in.  For our MediaWiki installation, it was simply as follows:

**1**. Copy the PHP file to the `extensions` subdirectory in your MediaWiki installation.

**2**. Add a line to your LocalSettings.php file to load the PHP file. For example, here is what it looks like for our system:

~~~~~php
require_once( "$IP/extensions/google-spreadsheet-mw-plugin/GoogleSpreadsheetAccess.php");
~~~~~


### Configuring the plug-in

For security reasons, the plug-in does not allow you to reference spreadsheets directly from the tag in a wiki page.  (Doing so would allow anyone with write access to your wiki to insert potentially malicious constructs from spreadsheets they control.)  Instead, there is a level of indirection: on the server you define identifiers that are mapped to actual spreadsheets, and when you use the tag in a wiki page, you use the identifier to name the spreadsheet you want to access.  Thus, the maintainers of the wiki site controls which spreadsheets can be accessed.  (Of course, for this security measure to have any value, the maintainer of the wiki should also control write access to the spreadsheets.  If the maintainer of the wiki do not control write access to the spreadsheets, or worse, the spreadsheets are publicly writable, then this indirection offers no security at all.)

To configure the plug-in, edit the `$sheet_ids` variable near the top of the PHP file.  The format is

~~~~~php
$sheet_ids = array(
   "sheet name" => "Google key for spreadsheet"
);
~~~~~

For each *"sheet name"*, create an identifier that you want to use in the references in your wiki pages.  To find the *"Google key for spreadsheet"* part, look at the URL of your Google Docs spreadsheet and find the part after the `key=` keyword.  It will look something like the following:

~~~~~
https://spreadsheets.google.com/tq?key=045klja34aAKLjasdfLLLJlkasdf04aKL73zz
~~~~~

Suppose you wanted to name the sheet **mysheet** when referencing it from your wiki pages.  If the above was the Google Docs key for the spreadsheet, the following is an example of what the final setting in the PHP file would look like:

~~~~~php
$sheet_ids = array(
   "mysheet" => "045klja34aAKLjasdfLLLJlkasdf04aKL73zz"
);
~~~~~

Then in your wiki pages, references would look like this:

~~~~~
<gscellvalue sheet="mysheet" find="..." in="..." return="..." ...>
~~~~~


### Using the plug-in in wiki pages

When used in a wiki page, `<gscellvalue>` accepts arguments that indicate a row to find in the spreadsheet and the column value within that row to be returned.  The approach is relatively simple and relies on one important assumption about the spreadsheet: that the **first row consists of column labels**.  References to "rows" in this extension are to these row labels and **not** to the spreadsheet's own row identifiers&mdash;in other words, not to the "A", "B", "C", .... "AA", "AB", etc., provided by the spreadsheet, but rather to row labels that the spreadsheet author provides.  This approach provides an important capability: you can reorder the spreadsheet columns without affecting column references in MediaWiki pages.

String matches are performed in a case-*sensitive* manner.  (I.e., "Foo" is not considered to be the same as "foo".)

The wiki page syntax for `gscellvalue` is the following:

~~~~~
<gscellvalue sheet="S" find="X" in="Y" return="Z" 
             prepend="A" append="B" ifempty="C" wikitext bigtable>
~~~~~

where the following arguments are required:

 *S* =  name for the spreadsheet (see $sheet_ids above) <br>
 *X* =  exact string to look for in column "Y", to find a row <br>
 *Y* =  label (not ID) of the column in which to search for content "X" <br>
 *Z* =  label (not ID) of the column whose value is to be returned <br>

and the following arguments are optional:

 *A* =  text to prepend to the value returned <br>
 *B* =  text to append to the value returned <br>
 *C* =  value to return if the cell content is found to be empty <br>
 *wikitext* =  keyword indicating content is to be parsed before returning it <br>
 *bigtable* =  keyword indicating table is large, so don't read it all at once <br>

If a value for the optional argument 'ifempty' is supplied, and the
spreadsheet cell to be returned is empty, only the value of 'ifempty' is
returned alone, without prepending A or appending B.  Conversely, if a
value for 'ifempty' is not supplied, and the spreadsheet cell value is
empty, then A and B *will* still be prepended and appended (which means
you will get the concatenation "AB" as the returned result).  Single-
and double-quote characters will be removed from the resulting string
before it is returned or parsed as wikitext; this is necessary so that
A and B can be strings with leading and trailing spaces (which you can
do by putting quotes around the strings, like this: append="' text'").

If the attribute 'wikitext' is supplied, the entire string to be returned
is first handed to the MediaWiki parser, and the result of that is what is
returned.  The attribute 'wikitext' takes no value.

By default, this plug-in will make a single call to Google to get the
entire table in one read, then do the cell value lookups internally in
this plug-in.  Depending on the size of the spreadsheet, the speed of your
server, and the number of uses of <gscellvalue> in a given MediaWiki page,
this approach may be slower than doing two separate reads together with
using the Google spreadsheets query API.  If the attribute 'bigtable' is
supplied, this plug-in will make two separate calls to Google rather than
read the whole spreadsheet into memory in one call.

Other attributes supplied to gscellvalue are silently ignored.





History and acknowledgments
---------------------------

This code was written for the SBML project website (http://sbml.org) under funding from grant R01GM070923 (Principal Investigator: Dr. Michael Hucka) from the National Institute of General Medical Sciences (USA) to the California Institute of Technology.  The first version was written and deployed in mid-2012.


Contributing
------------

I welcome improvements of all kinds, to the code and to the documentation.
Please feel free to contact me or do the following:

1. Fork this repo.  See the links at the top of the github page.
2. Create your feature branch (`git checkout -b my-new-feature`) and write
your changes to the code or documentation.
3. Commit your changes (`git commit -am 'Describe your changes here'`).
4. Push to the branch (`git push origin my-new-feature`).
5. Create a new pull request to notify me of your suggested changes.


License
-------

Copyright (C) 2012-2014 by the California Institute of Technology, Pasadena, USA.

This library is free software; you can redistribute it and/or modify it under the terms of the GNU Lesser General Public License as published by the Free Software Foundation; either version 2.1 of the License, or any later version.

This software is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY, WITHOUT EVEN THE IMPLIED WARRANTY OF MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE.  The software and documentation provided hereunder is on an "as is" basis, and the California Institute of Technology has no obligations to provide maintenance, support, updates, enhancements or modifications.  In no event shall the California Institute of Technology be liable to any party for direct, indirect, special, incidental or consequential damages, including lost profits, arising out of the use of this software and its documentation, even if the California Institute of Technology has been advised of the possibility of such damage.  See the GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License along with this library in the file named "COPYING.txt" included with the software distribution.
