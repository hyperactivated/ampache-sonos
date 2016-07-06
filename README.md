# ampache-sonos
Enables the configuration of an ampache instance as a SONOS music service.

Note: This is highly experimental, not guaranteed to work and may change at any time

## Requirements
A configured ampache installation with HTTPS enabled (and possibly needs to be accessible to the internet)
A user account with streaming permissions

## Installation
1. Open ampache base directory e.g. cd /srv/http/ampache
2. Clone ampache-sonos e.g. git clone https://github.com/hyperactivated/ampache-sonos.git

You should now have a sub-directory under your ampache directory called ampache-sonos

## Configuration

Follow the instructions at http://musicpartners.sonos.com/node/134 to add your ampache instance as a sonos music service, you will need to use the following values:

Endpoint URL: 
http://<your.server>/<ampache-path>/ampache-sonos/SonosAPI.php

Secure Endpoint URL: 
https://<your.server>/<ampache-path>/ampache-sonos/SonosAPI.php

Authentication SOAP header policy: 
Session ID

Presentation map (optional): 
Version: 1 Type: http://<your.server>/<ampache-path>/ampache-sonos/presentationMap.xml

Capabilities:
Search