Wikipedia divisions
=========

Parser to get list of country divisions and subdivisions with ISO-3166-2 codes in CSV format from https://en.wikipedia.org/wiki/ISO_3166-2

To execute run the command in shell: 

~~~
php parse.php
~~~

The results are saved in files:

~~~
result/divisions.csv
result/subdivisions.csv
~~~

The first column is a country ISO-3166-1 code

**Example of parsed urls:**

* https://en.wikipedia.org/wiki/ISO_3166-2:FR
* https://en.wikipedia.org/wiki/ISO_3166-2:GB
* https://en.wikipedia.org/wiki/ISO_3166-2:US

Additional
----------
Parser for country divisions from Geoname:  
https://github.com/tigrov/geoname-divisions


License
-------

[MIT](LICENSE)
