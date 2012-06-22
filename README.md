Novutec Domain Parser
====================

Novutec Domain Parser lets you parse every given string to return you
the domain name out of this string.

Copyright (c) 2007 - 2012 Novutec Inc. (http://www.novutec.com)
Licensed under the Apache License, Version 2.0 (the "License").

Installation
------------

Installing from source: `git clone git://github.com/novutec/domainparser.git`

Move the source code to your preferred project folder.

Usage
-----

* include Parser.php
`require_once 'DomainParser/Parser.php';`

* create parser object
`$Parser = new Novutec\DomainParser\Parser()`

* call parse function
`$result = $Parser->parse($string);`

* please note if the given string doesn't contain a domain name the default tld
".com" will be added to the query. You may change this by adding a tld to the
parse method call
`$result = $Parser->parse($string, $yourPreferredDefaultTld);`

3rd Party Libraries
-------------------

Thanks to developers of following used libraries:

* phlyLabs: http://phlylabs.de
* mozilla: http://www.mozilla.org 

Issues
------

Please report any issues via https://github.com/novutec/domainparser/issues

LICENSE and COPYRIGHT
-----------------------

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.