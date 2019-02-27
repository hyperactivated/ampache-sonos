# ampache-sonos
Enables the configuration of an ampache instance as a SONOS music service.

Note: This is highly experimental, not guaranteed to work and may change at any time

## Requirements
- A configured ampache (https://github.com/ampache/ampache) installation with HTTPS enabled (and possibly needs to be accessible to the internet)
- A user account with streaming and API access permissions
- The PHP SOAP package: https://secure.php.net/manual/en/book.soap.php
- The PHP GetText package: https://secure.php.net/manual/en/book.gettext.php

## Installation
1. Open ampache base directory e.g. cd /srv/http/ampache
2. Clone ampache-sonos e.g. git clone https://github.com/hyperactivated/ampache-sonos.git

You should now have a sub-directory under your ampache directory called ampache-sonos

## Configuration

Follow the instructions at http://musicpartners.sonos.com/node/134 to add your ampache instance as a sonos music service, you will need to use the following values:

Endpoint URL: 
http://&lt;your.server&gt;/&lt;ampache-path&gt;/ampache-sonos/SonosAPI.php

Secure Endpoint URL: 
https://&lt;your.server&gt;/&lt;ampache-path&gt;/ampache-sonos/SonosAPI.php

Authentication SOAP header policy: 
Session ID

Presentation map (optional): 
Version: 1 Type: http://&lt;your.server&gt;/&lt;ampache-path&gt;/ampache-sonos/pMap.xml

Capabilities:
Search
