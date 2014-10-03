<?php
/**
 * DokuWiki Plugin ghissues (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Zach Smith <zsmith12@umd.edu>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_ghissues_api_cache_interface extends DokuWiki_Plugin {
	var $_GH_API_limit;
	
    /**
     * Return info about supported methods in this Helper Plugin
     *
     * @return array of public methods
     */
    public function getMethods() {
        return array(
            array(
                'name'   => 'checkIssuesCache',
                'desc'   => 'returns true if page is clear to use the current cache',
                'params' => array(
                    'apiUrl' => 'string',
                    'page'   => 'string'
                ),
                'return' => array('useCache' => 'boolean')
            )
        );
    }
	// Master cache checker.  First checks if the cache expired, then checks if the page is older than the cache
	public function checkIssuesCache( $apiUrl, $page ) {
		return true;
	}
	
	// return true if still fresh.  Otherwise you'll confuse the bears
	public function checkCacheFreshness($apiURL, &$cache=NULL) {
		if ( !isset($cache) ) {
			$cache = new cache_ghissues_api($apiURL);
		}
		
		// Check if we've done a cached API call recently.  If you have, the cache is plenty fresh
		if ( $cache->sniffETag($this->getConf['ghissuerefresh']) ) return true;
		
		// Old cache, time to check in with GH.
		return $this->callGithubAPI($apiURL, $cache);
	}
	
	// return true if no update since the last time we asked.
	public function callGithubAPI($apiURL, &$cache=NULL) {
		if ( !isset($cache) ) {
			$cache = new cache_ghissues_api($apiURL);
		}
		
		$http = new DokuHTTPClient();
		
		$http->agent = substr($http->agent,-1).' via ghissue plugin from user '.$this->getConf('ghissueuser').')';
		$http->headers['Accept'] = 'application/vnd.github.v3.text+json';
		
		$lastETag = $cache->retrieveETag();
		if ( !empty($lastETag) ) {
			$http->headers['If-None-Match'] = $lastETag;
		}
		
		$apiResp = $http->get($data['url']);
		$apiHead = array();
		$apiHead = $http->resp_headers;
		
		$this->_GH_API_limit = intval($apiHead['x-ratelimit-remaining']);
		
		$apiStatus = substr($apiHead['status'],0,3);
		
		if ( $apiStatus == '304' ) { // No modification
			$cache->storeETag($apiHead['etag']); // Update the last time we checked
			return true;
		} else if ( $apiStatus == '200' ) { // Updated content!  But will the table change?
			$cache->storeETag($apiHead['etag']); // Update the last time we checked
			// Need a call to handle multi-page results
			
			// Need a call to build the table from the response
			$newTable = formatApiResponse($apiResp);
			
			if ( $newTable != $cache->retrieveCache() ) {
				if (!$cache->storeCache($newTable)) {				
					msg('Unable to save cache file. Hint: disk full; file permissions; safe_mode setting.',-1);
					return true; // Couldn't save the update, can't reread from new cache
				}
				return false;
			}
			
			return true; // All that for a table that looks the same...
		} else { // Some other HTTP status, we're not handling. Save old table plus error message
			// Don't update when we checked in case it was a temporary thing (it probably wasn't though)
			// $cache->storeETag($apiHead['etag']); // Update the last time we checked
			$errorTable  = '<div class=ghissues_plugin_api_err">';
			$errorTable .= sprintf($this->getLang('badhttpstatus'),htmlentities($apiHead['status']));
			$errorTable .= '</div>'."\n".$cache->retrieveCache();
			
			if (!$cache->storeCache($errorTable)) {				
				msg('Unable to save cache file. Hint: disk full; file permissions; safe_mode setting.',-1);
				return true; // Couldn't save the update, can't reread from new cache
			}
			return false;
		}
		return true;
	}
	
	public function formatApiResponse($rawJSON) {
		$json = new JSON();
    	$response = $json->decode($rawJSON);
		
		// Assume the top is already there, as that can be built by without the API request.
		$outputXML = "<ul>\n";
		foreach($response as $issueIdx => $issue) {
			$outputXML .= '<li class="ghissues_plugin_issue_line">'."\n";
			$outputXML .= '<div>'."\n";
			$outputXML .= $this->external_link($issue->html_url, htmlentities('#'.$issue->number.': '.$issue->title), '"ghissues_plugin_issue_title"', '_blank');
			$outputXML .= "\n".'<span class="ghissues_plugin_issue_labels">';
			foreach($issue->labels as $label) {
				$outputXML .= '<span style="background-color:#'.$label->color.'"';
    			$outputXML .= ' class='.$this->_getSpanClassFromBG($label->color).'>';
				$outputXML .= htmlentities($label->name);
    			$outputXML .= '</span>'."\n"; 				
    		}
			$outputXML .= '</span></div>'."\n";
			$outputXML .= '<div class="ghissues_plugin_issue_report">'."\n";
			$outputXML .= sprintf($this->getLang('reporter'),htmlentities($issue->user->login));
			$outputXML .= htmlentities(strftime($conf['dformat'],strtotime($issue->created_at)));
			$outputXML .= '</div></ul>'."\n";
		}
		
		return $outputXML;
	}
	
	private function _getSpanClassFromBG($htmlbg) {
    	$colorval = hexdec($htmlbg);

    	$red = 0xFF & ($colorval >> 0x10);
    	$green = 0xFF & ($colorval >> 0x08);
    	$blue = 0xFF & $colorval;
    	
    	$lum = 1.0 - ( 0.299 * $red + 0.587 * $green + 0.114 * $blue)/255.0;
    	
    	if( $lum < 0.5 ) {
    		return '"ghissues_light"';
    	} else {
    		return '"ghissues_dark"';
    	}
    }
}

class cache_ghissues_api extends cache {
	public $etag = '';
	var    $_etag_time;
	
	public function cache_ghissues_api($requestURL) {
		parent::cache($requestURL,'.ghissues');
		$this->$etag = substr($this->cache, 0, -9).'.etag';
	}
	
	public function retrieveETag($clean=true) {
		return io_readFile($this->etag, $clean);
	}
	
	public function storeETag($etagValue) {
		if ( $this->_nocache ) return false;
		
		return io_savefile($this->etag, $etagValue);
	}
	
	// Sniff to see if it is rotten (expired). <0 means always OK, 0 is never ok.
	public function sniffETag($expireInterval) {
		if ( $expireInterval <  0 ) return true;
		if ( $expireInterval == 0 ) return false;
		
		if (!($this->_etag_time = @filemtime($this->cache))) return false;  // Check if cache is there
		if ( (time() - $this->_etag_time) > $expireInterval ) return false; // Past Sell-By
		return true;
	}
}
// vim:ts=4:sw=4:et:
