php-websocket-server
====================

Simple reusable websocket-server class for php, supporting as many browsers as possible (hixie-76, hybi-00, and the current finished draft), as well as echange of a flash policy file.

Example of use can be found in example.php

Note: This is for server use only. For building a client that will work on &lt;99% of all browsers, check out:
https://github.com/gimite/web-socket-js/

php-websocket-server replies to requests for the flash policy file, making it compatible with web-socket-js for those pesky archaic browsers that don't support it yet.
