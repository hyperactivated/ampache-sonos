<?php

define('NO_SESSION','1');
require_once '../lib/init.php';

// Prevent anything other than POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo "<br>" . T_('This service is for use by Sonos products only.')."</br>\n";
    exit;
}

// Sonos API, see documentation at http://musicpartners.sonos.com/?q=docs

class SonosAPI
{
    private $sessionid;

    function __construct()
    {

    }

    function credentials($parameters)
    {
    	$this->sessionid = $parameters->sessionId;
    }

    function checkSession()
    {
    	if (!Session::exists('api', $this->sessionid)) {
    		throw new SoapFault('Client.SessionIdInvalid',
                                T_('Session ID Invalid.')); 
    	}

    	Session::extend($this->sessionid, 'api');

    	$user = Session::username($this->sessionid);
    	$GLOBALS['user'] = User::get_from_username($user);
        Preference::init();
    }

    // getSessionId see http://musicpartners.sonos.com/node/82
    function getSessionId($args) 
    {
    	//TODO: Check we're throwing the write faults according to documentation.
    	$user = $args->username;
    	$password = $args->password;
        $auth = Auth::login($user, $password, true);

        // Authenticate the user
        if (!$auth['success']) {
        	throw new SoapFault('Client.LoginInvalid',
                                T_('Unauthorized.')); 
        }

        //Check the user has the rights to use the API
        if (!Access::check_network('init-api', $user, 5)) {
        	throw new SoapFault('Client.LoginUnsupported',
                                T_('Unauthorized.')); 
        }

        // Create an Ampache session
        $sessionid = Session::create(array(
                    'type' => 'api',
                    'username' => $user,
                ));

        // If we didn't end up with a valid session error out
        if (!$sessionid) {
            throw new SoapFault('Client.LoginUnauthorized',
                                T_('Unauthorized.'));            
        }

        $GLOBALS['user'] = User::get_from_username($user);
        Preference::init();

        // Return the session id
        return array( 'getSessionIdResult' => $sessionid);
    }

    // getMetaData see http://musicpartners.sonos.com/node/83
    function getMetadata($args) 
    {
    	$this->checkSession();

    	// $fh = fopen('calls.txt', 'a');
    	// fwrite($fh, 'getMetadata:' . var_export($args, true) . PHP_EOL);
    	// fclose($fh);

    	$result = array();

    	if ($args->id == 'root') {
    		$result['index'] = $args->index;
    		$result['mediaCollection'] = array();
    		
    		$containers = array();
    		
    		$container = array();
    		$container['id'] = 'browse:Artists';
    		$container['itemType'] = 'container';
    		$container['displayType'] = 'genericGrid';
    		$container['title'] = 'Artists';
    		$container['canPlay'] = false;
    		$container['containsFavorite'] = false;
    		$container['albumArtURI'] = null;
    		$containers[] = $container;

    		unset($container);
    		$container = array();
    		$container['id'] = 'browse:Albums';
    		$container['itemType'] = 'container';
    		$container['displayType'] = 'genericGrid';
    		$container['title'] = 'Albums';
    		$container['canPlay'] = false;
    		$container['containsFavorite'] = false;
    		$container['albumArtURI'] = null;
    		$containers[] = $container;

    		unset($container);
    		$container = array();
    		$container['id'] = 'browse:Songs';
    		$container['itemType'] = 'container';
    		$container['displayType'] = 'genericGrid';
    		$container['title'] = 'Songs';
    		$container['canPlay'] = false;
    		$container['containsFavorite'] = false;
    		$container['albumArtURI'] = null;
    		$containers[] = $container;

    		$result['total'] = count($containers);

    		if ($args->index >= $result['total']) {
    			$result['count'] = 0;
    		} else {
    			$result['mediaCollection'] = array_slice($containers, $args->index, $args->count);
    			$result['count'] = count($result['mediaCollection']);
    		}
    	} elseif ($args->id == 'search') {
    		$result['index'] = $args->index;
    		$result['mediaCollection'] = array();
    		
    		$containers = array();
    		
    		$container = array();
    		$container['id'] = 'artist';
    		$container['itemType'] = 'search';
    		$container['title'] = 'Artist';
    		$containers[] = $container;

    		unset($container);
    		$container = array();
    		$container['id'] = 'album';
    		$container['itemType'] = 'search';
    		$container['title'] = 'Album';
    		$containers[] = $container;

    		unset($container);
    		$container = array();
    		$container['id'] = 'track';
    		$container['itemType'] = 'search';
    		$container['title'] = 'Track';
    		$containers[] = $container;

    		$result['total'] = count($containers);

    		if ($args->index >= $result['total']) {
    			$result['count'] = 0;
    		} else {
    			$result['mediaCollection'] = array_slice($containers, $args->index, $args->count);
    			$result['count'] = count($result['mediaCollection']);
    		}
    	} elseif (strpos($args->id, ':') !== false) {
    		list($type, $id) = explode(':', $args->id, 2);
    		if ($type == 'browse') {
    			if ($id == 'Artists') {
		    		$result = $this->artistSearch($args);
		    	} elseif ($id == 'Albums') {
		    		$result = $this->albumSearch($args);
		    	} elseif ($id == 'Songs') {
		    		$result = $this->songSearch($args);
		    	}
    		} elseif ($type == 'artist') {
    			$artist = new Artist($id);
    			$artistData = $this->getArtistMetadata($id);
    			$albums = $artist->get_albums();
    			$result['index'] = $args->index;
    			$result['total'] = count($albums);
    			foreach ($albums as $album_id) {
    				$albumData[] = $this->getAlbumMetadata($album_id);
    			}
    			if ($args->index > count($albumData)) {
    				$result['count'] = 0;
    			} else {
    				$result['mediaCollection'] = array_slice($albumData, $args->index, $args->count);
    			}
    			$result['count'] = count($result['mediaCollection']);
    		} elseif ($type == 'album') {
    			$album = new Album($id);
    			$albumData = $this->getAlbumMetadata($id);
    			$songs = $album->get_songs();
    			$result['index'] = $args->index;
    			$result['total'] = count($songs);
    			foreach ($songs as $song_id) {
    				$songData[] = $this->getSongMetadata($song_id);
    			}
    			if ($args->index > count($songData)) {
    				$result['count'] = 0;
    			} else {
    				$result['mediaMetadata'] = array_slice($songData, $args->index, $args->count);
    			}
    			$result['count'] = count($result['mediaMetadata']);
    		}
    	}

    	return array('getMetadataResult' => $result);
    }

    // getMediaMetaData see http://musicpartners.sonos.com/node/84
    function getMediaMetadata($args) 
    {
    	$this->checkSession();

    	// $fh = fopen('calls.txt', 'a');
    	// fwrite($fh, 'getMediaMetadata:' . var_export($args, true) . PHP_EOL);
    	// fclose($fh);

    	list($type, $id) = explode(':', $args->id, 2);
		if ($type == 'song') {
			$result = $this->getSongMetadata($id);
		}

		return array('getMediaMetadataResult' => $result);
    }

    // getMediaURI see http://musicpartners.sonos.com/node/85
    function getMediaURI($args)
    {
    	$this->checkSession();

    	$mediaID = explode(':', $args->id);

    	if ($mediaID[0] != 'song') {
    		throw new SoapFault('Client.InvalidMediaType',
                                T_('Requested media is of invalid type, must be track.'));
    	}
    	$mediaURI = Song::play_url($mediaID[1]);

    	// ToDo: Work out how to do resumption

    	return array('getMediaURIResult' => $mediaURI);
    }

    // search see http://musicpartners.sonos.com/node/86
    function search($args) 
    {
    	$this->checkSession();

    	$result = array();
    	switch ($args->id) {
    		case 'artist':
    			$result = $this->artistSearch($args);
    			break;
    		case 'album':
    			$result = $this->albumSearch($args);
    			break;
    		case 'track':
    		default:
    			$result = $this->songSearch($args);
    			break;
    	}

    	return array('searchResult' => $result);
    }

    function artistSearch($args)
    {
    	$query = new Query();
    	$query->set_type('artist');
    	if (!empty($args->term)){
    		$query->set_filter('alpha_match', $args->term);
    	}
    	$query->set_sort('name', 'ASC');

    	$result = array();

    	$result['index'] = $args->index;
    	$artists = $query->get_objects();
    	$result['total'] = count($artists);

        if ($result['total'] > $args->index) {
        	$artists = array_slice($artists, $args->index, $args->count);
	    	$result['mediaCollection'] = array();
	        foreach ($artists as $artist_id) {
	            $item = $this->getArtistMetadata($artist_id);
	            $result['mediaCollection'][] = $item;
	        }

	        $result['count'] = count($result['mediaCollection']);
	    }
	    else
	    {
	    	$result['count'] = 0;
	    }

        return $result;
    }

    function getArtistMetadata($artist_id)
    {
    	$artist = new Artist($artist_id);
        $artist->format();

        $item = array();

        $item['title'] = $artist->f_full_name;
        $item['itemType'] = 'artist';
        $item['displayType'] = 'searchResult';
        $item['id'] = 'artist:'.$artist->id;
        $item['authrequired'] = 1;
        foreach ($artist->tags as $tag) {
        	if ($item['genre'] != '') {
        		$item['genre'] .= ', ';
        	}
        	$item['genre'] .= $tag['name'];
        }
        $item['albumArtURI'] = Art::url($artist_id, 'artist', $this->sessionid);

        return $item;
    }

    function albumSearch($args, $method = 'title')
    {
    	$query = new Query();
    	$query->set_type('album');
    	if (!empty($args->term)){
	    	switch ($method) {
	    		case 'title':
	    		default:
	    			$query->set_filter('alpha_match', $args->term);
	    			break;
	    	}
	    }
    	$query->set_sort('name', 'ASC');

    	$result = array();

    	$result['index'] = $args->index;
    	$albums = $query->get_objects();
    	$result['total'] = count($albums);

        if ($result['total'] > $args->index) {
        	$albums = array_slice($albums, $args->index, $args->count);
	    	$result['mediaCollection'] = array();
	        foreach ($albums as $album_id) {
	        	$item = $this->getAlbumMetadata($album_id);
	            $result['mediaCollection'][] = $item;
	        }

	        $result['count'] = count($result['mediaCollection']);
	    } else {
	    	$result['count'] = 0;
	    }

        return $result;
    }

    function getAlbumMetadata($album_id)
    {
    	$album = new Album($album_id);
        $album->format();

        $item = array();

        $item['title'] = $album->name;
        $item['itemType'] = 'album';
        $item['displayType'] = 'searchResult';
        $item['id'] = 'album:'.$album->id;
        $item['authrequired'] = 1;
        foreach ($album->tags as $tag) {
        	if ($item['genre'] != '') {
        		$item['genre'] .= ', ';
        	}
        	$item['genre'] .= $tag['name'];
        }
        $item['albumArtURI'] = Art::url($album_id, 'album', $this->sessionid);
        $item['canPlay'] = true;

        return $item;
    }

    function getSongMetadata($song_id)
    {
    	$song = new Song($song_id);
        $song->format();

        $item = array();
        $item['id'] = 'song:'.$song->id;
        $item['itemType'] = 'track';
        $item['mimeType'] = $song->type_to_mime();
        $item['title'] = $song->title;
        $item['displayType'] = 'searchResult';

        $item['trackMetadata'] = array();
        $item['trackMetadata']['albumId'] = 'album:'.$song->album;
        $item['trackMetadata']['duration'] = $song->time;
        $item['trackMetadata']['artistId'] = 'artist:'.$song->artist;
        $item['trackMetadata']['albumArtURI'] = Art::url($song->album, 'album', $this->sessionid);
        $item['trackMetadata']['album'] = $song->f_album_full;
        $item['trackMetadata']['artist'] = $song->f_artist_full;
        
        $item['authrequired'] = 1;

        $item['genre'] = '';

        foreach ($song->tags as $tag) {
        	if ($item['genre'] != '') {
        		$item['genre'] .= ', ';
        	}
        	$item['genre'] .= $tag['name'];
        }

        return $item;
    }

    function songSearch($args, $method = 'title')
    {
        $query = new Query();
    	$query->set_type('song');
    	if (!empty($args->term)){
	    	switch ($method) {
	    		case 'album':
	    			$query->set_filter('album', $args->term);
	    			$query->set_sort('track', 'ASC');
	    			break;
	    		case 'title':
	    		default:
	    			$query->set_filter('alpha_match', $args->term);
	    			$query->set_sort('title', 'ASC');
	    			break;
	    	}
	    }

    	$result = array();

    	$result['index'] = $args->index;
    	$songs = $query->get_objects();
    	$result['total'] = count($songs);

        if ($result['total'] > $args->index) {
        	$songs = array_slice($songs, $args->index, $args->count);
	    	$result['mediaMetadata'] = array();
	        foreach ($songs as $song_id) {
	            $result['mediaMetadata'][] = $this->getSongMetadata($song_id);
	        }

	        $result['count'] = count($result['mediaMetadata']);
	    } else {
	    	$result['count'] = 0;
	    }

        return $result;
    }

    // getLastUpdate see http://musicpartners.sonos.com/node/87
    function getLastUpdate($args) 
    {
        $result = new StdClass();
		$result->catalog = Catalog::getLastUpdate();

		// TODO: Implement favorites update correctly
        $favoriteUpdateId = microtime(true); //$this->favorites->getLastUpdate($this->user);
        $ratingsUpdateId = microtime(true); //$this->ratings->getLastUpdate($this->user);
        // Because ratings data is part of the dynamic metadata returned
        // by getMetadata() and getExtendedMetadata(), the "favorites"
        // updateId has to include changes to the ratings DB as well as the
        // favorites DB.
        if ($favoriteUpdateId > $ratingsUpdateId) {
            $result->favorites = $favoriteUpdateId;
        } else {
            $result->favorites = $ratingsUpdateId;
        }
        $result->pollInterval = 60;
        
        return array('getLastUpdateResult' => $result);
    }

    // getExtendedMetaData see http://musicpartners.sonos.com/node/127
    function getExtendedMetaData($args)
    {
    	$this->checkSession();
    }

    // getExtendedMetadataText see http://musicpartners.sonos.com/node/128
    function getExtendedMetadataText($args)
    {
    	$this->checkSession();
    }
}

// Instantiate the SoapServer
$server = new SoapServer('Sonos.wsdl', array('cache_wsdl' => 0));
$server->setClass('SonosAPI');

try{
    $server->handle();
} catch (Exception $e) {

    $errorId2msgId = array (
        'Server.ServiceUnknownError'  => "MSG_SOAPFAULT_SERVICE_UNKNOWN_ERROR",
        'Server.ServiceUnavailable'   => "MSG_SOAPFAULT_SERVICE_UNAVAILABLE",
        'Client.SessionIdInvalid'     => "MSG_SOAPFAULT_SESSION_ID_INVALID",
        'Client.LoginInvalid'         => "MSG_SOAPFAULT_LOGIN_UNAUTHORIZED",
        'Client.LoginDisabled'        => "MSG_SOAPFAULT_LOGIN_DISABLED",
        'Client.LoginUnauthorized'    => "MSG_SOAPFAULT_LOGIN_UNAUTHORIZED",
        'Client.DeviceLimit'          => "MSG_SOAPFAULT_DEVICE_LIMIT",
        'Client.UnsupportedTerritory' => "MSG_SOAPFAULT_UNSUPPORTED_TERRITORY",
        'Client.ItemNotFound'         => "MSG_SOAPFAULT_ITEM_NOT_FOUND",
                            );
    $requestContents = "\n".file_get_contents('php://input')."\n"; // reset this to just the input on any fault
    
    $transittime = number_format(microtime(true)-$start,6);
    
    $server->fault($e->getMessage(), $errorId2msgId[$e->getMessage()] . 
                   ' ('.$e->getCode().': '.$e->getFile().':'.$e->getLine().')');
}
