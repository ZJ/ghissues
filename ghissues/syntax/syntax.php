<?php
/**
 * DokuWiki Plugin ghissues (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Zach Smith <zsmith12@umd.edu>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_ghissues_syntax extends DokuWiki_Syntax_Plugin {
    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }
    /**
     * @return string Paragraph type
     */
    public function getPType() {
        return 'block';
    }
    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 305;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{ghissue\b.*?}}',$mode,'plugin_ghissues_syntax');
    }

    /**
     * Handle matches of the ghissues syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler &$handler){
        $dropwrapper = trim(substr(substr($match,10),0,-2));
        
        $exploded = explode(' ',$dropwrapper);
        
        $repoPath = htmlentities($exploded[0]);
        
        unset($exploded[0]);
        $theRest = '';
        foreach($exploded as $item) {
        	$theRest .= $item.' ';
        }
        
        $filters='';
        
        $wholeHeader = 'Open Issues in /'.htmlentities($repoPath.' Plus:'.$theRest);
        $url = 'https://api.github.com/repos/'.$repoPath.'/issues'.$filters;
        $urlHash = hash('sha1',$url);
        $data = array( 'header' => $wholeHeader, 'url' => $url, 'hash' => $urlHash );

        return $data;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer &$renderer, $data) {
        if($mode == 'metadata') {
        	
        	$hashpair = array( $data['url'] => hash('md5', $data['url']) );
        	
        	if (!isset($renderer->meta['plugin_ghissues_apicalls'])) $renderer->meta['plugin_ghissues_apicalls'] = array();
        	//$renderer->meta['plugin_ghissues_apicalls'] = array();
        	$renderer->meta['plugin_ghissues_apicalls'] = array_unique(array_merge($renderer->meta['plugin_ghissues_apicalls'], $hashpair));

        	return true;
        }
		
		if($mode != 'xhtml') return false;
		global $conf;
		
		$http = new DokuHTTPClient();
		
		$http->agent = substr($http->agent,-1).' via ghissue plugin from user '.$this->getConf('ghissueuser').')';
		$http->headers['Accept'] = 'application/vnd.github.v3.text+json';
		
		//$renderOutput = '<p>Meta: '.p_get_metadata($renderer, 'ghissues').'</p>';
		
		$apicall = $http->get($data['url']); //GETurl
		$renderOutput .= '<p>'.htmlentities(var_export($http->resp_headers, TRUE)).'</p>';
		$renderOutput .= '<p>ETag: '.htmlentities($http->resp_headers['etag']).'</p>';
		$renderOutput .= '<p>Response: '.htmlentities($http->resp_headers['status']).'</p>';
		$renderOutput .= '<p>Rate Limit: '.htmlentities($http->resp_headers['x-ratelimit-remaining']).'</p>';
		$renderOutput .= '<p>URL: '.htmlentities($data['url']).'</p>';
		$renderOutput .= '<p>URL Hash: '.htmlentities($data['hash']).'</p>';

		$renderOutput .= '<p>'.htmlentities($apicall).'</p>';


		if($apicall !== false){
    		$json = new JSON();
    		$resp = $json->decode($apicall);
			$renderOutput .= '<div class="ghissues_plugin_box etag-'.substr($http->resp_headers['etag'],1,-2).'">';
    		$renderOutput .= '<div class="ghissues_plugin_box_header">'.htmlentities($data['header']).'</div>';
    		foreach($resp as $listind => $issue) {
				$renderOutput .= '<div class="ghissues_plugin_issue_line">';
				$renderOutput .= '<div class="ghissues_plugin_issue_title">';
				$renderOutput .= htmlentities('#'.$issue->number.': ');
    			$renderOutput .= htmlentities($issue->title);
    			foreach($issue->labels as $label) {
					$renderOutput .= '<span style="background-color:#'.$label->color.'"';
    				$renderOutput .= ' class='.$this->getSpanClassFromBG($label->color).'>';
    				$renderOutput .= htmlentities($label->name);
    				$renderOutput .= '</span>'."\n"; 				
    			}
    			$renderOutput .= '</div><div class="ghissues_plugin_issue_report">';
    			$renderOutput .= sprintf($this->getLang('reporter'),htmlentities($issue->user->login));
    			//$renderOutput .= ' <img src="'.htmlentities($issue->user->avatar_url).'" alt="'.htmlentities($issue->user->login);
    			//$renderOutput .= '" width="15" height="15">';
    			//$renderOutput .= ' on ';
    			$renderOutput .= htmlentities(strftime($conf['dformat'],strtotime($issue->created_at)));
    			$renderOutput .= '</div></div>'."\n";
			}
			$renderOutput .= '</div>';
		} else {
			$renderOutput .= "<p>Else case</p>";
			$renderOutput .= '<p>'.var_export($http->$resp_headers, TRUE).'</p>';
		}
		
		$renderer->doc .= $renderOutput;
		
        return true;
    }
        
    private function getSpanClassFromBG($htmlbg) {
    	$colorval = hexdec($htmlbg);

    	$red = 0xFF & ($colorval >> 0x10);
    	$green = 0xFF & ($colorval >> 0x08);
    	$blue = 0xFF & $colorval;
    	
    	$lum = 1.0 - ( 0.299 * $red + 0.587 * $green + 0.114 * $blue)/255.0;
    	
    	if( $lum < 0.5 ) {
    		return '"ghissues_plugin_label_light"';
    	} else {
    		return '"ghissues_plugin_label_dark"';
    	}
    }

}

// vim:ts=4:sw=4:et:
