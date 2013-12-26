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

### Configuring the plug-in

### Using the plug-in in wiki pages



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
