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

	public function checkIssuesCache( $apiUrl, $page ) {
		return true;
	}
}

class helper_plugin_ghissues_api_cache extends cache {
     public function cache_parser($id, $file, $mode) {
 192          if ($id) $this->page = $id;
 193          $this->file = $file;
 194          $this->mode = $mode;
 195  
 196          parent::cache($file.$_SERVER['HTTP_HOST'].$_SERVER['SERVER_PORT'],'.'.$mode);
 197      }
}
// vim:ts=4:sw=4:et:
