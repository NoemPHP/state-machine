The web serer at 'machines/webserver/machine.yml' has a bug

Review specs and tests at 'specs/machines/webserver.yaml'

Tests are passing, but the server does not work in real life

Steps to reproduce:

* Start web server: ddev exec php machines/webserver/machine.php
* Send first request:  ddev exec curl -v --max-time 13 http://0.0.0.0:8080/test
* Observe: The request is processed and we see generated HTML
* Send second request:  ddev exec curl -v --max-time 13 http://0.0.0.0:8080/test
* Oberse error: Empty reply from serer
* No further requests are processed any more

Specs have already been added, but please review 