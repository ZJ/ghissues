<?php
/**
 * DokuWiki Plugin ghissues (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Zach Smith <zsmith12@umd.edu>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_ghissues_cacheHandler extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handle_parser_cache_use');
   
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_parser_cache_use(Doku_Event &$event, $param) {
		$pageCache =& $event->data;
		
		//dbglog("ghissues: In handle_parser_cache_use");
		
		// We only do anything if it is rendering xhtml
		if( !isset($pageCache->page) ) return;
		if( !isset($pageCache->mode) || $pageCache->mode != 'xhtml' ) return;
		
		$apiRequests = p_get_metadata($pageCache->page, 'plugin_ghissues_apicalls');
		if ( !is_array($apiRequests) ) return; // No ghissues api calls
		
		$loadFromCache = $this->loadHelper('ghissues_apiCacheInterface');
		//dbglog('ghissues: handleParser '.var_export($apiRequests, TRUE));
		foreach($apiRequests as $apiURL => $apiHash) {
			$pageCache->depends['files'][]= $loadFromCache->checkIssuesCache($apiURL);
			//dbglog('ghissues: '.$loadFromCache->checkIssuesCache($apiURL));
		}
    	return;
    }

}

// vim:ts=4:sw=4:et:
