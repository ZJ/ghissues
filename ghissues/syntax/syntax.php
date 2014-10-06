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
        $theRest = implode(" ",$exploded);
        
        $filters='';
		
		// Check if we're filtering based on issue state
        $headerState = $this->getLang('open');
        $matches = array();
        if ( preg_match("/\bstate:(open|closed|all)\b/", $theRest, $matches) ) {
        	switch($matches[1]) {
        		case "open":
        			$filters="?state=open";
        			break;
        		case "closed":
        			$filters="?state=closed";
        			$headerState = $this->getLang('closed');
        			break;
        		case "all":
        			$filters="?state=all";
        			$headerState = $this->getLang('all');
        			break;
        	}
        }
        
        // Now check for label processing, matches "label:" through end of line
        $headerLabel = '';
        $matches = array();
        $codedLabels = array();
        if ( preg_match("/\blabels?:(.*\z)/", $theRest, $matches) ) {
        	if( $filters == '' ) {
        		$filters = '?labels=';
        	} else {
        		$filters .= '&labels=';
        	}
        	$rawLabels = array();
        	$rawLabels = preg_split('~\\\\.(*SKIP)(*FAIL)|,~s', trim($matches[1]));
        	if ( count($rawLabels) > 1 ) {
        		$headerLabel = $this->getLang('withLabels');
        	} else {
        		$headerLabel = $this->getLang('withLabel');        	
        	}
        	foreach ($rawLabels as $thisIndex => $thisLabel) {
        		$codedLabels[$thisIndex] = urlencode($thisLabel);
        	}
        	$headerLabel .= htmlentities(implode($this->getLang('and'),$rawLabels));
        	$filters .= implode(',',$codedLabels);
        }
        
        $url = 'https://api.github.com/repos/'.$repoPath.'/issues'.$filters;
		$httpUrl = 'http://www.github.com/'.$repoPath.'/issues'.$filters;
        
        $buildHeader = $headerState.$this->getLang('issuesIn');
        
        $wholeHeader = $buildHeader.htmlentities($repoPath).$headerLabel;
        $wholeFooter = $this->external_link($httpUrl, $this->getLang('viewOnGH'), NULL, '_blank');
        $data = array( 'header' => $wholeHeader, 'url' => $url, 'footer' => $wholeFooter );

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
        	$renderer->meta['plugin_ghissues_apicalls'] = array_unique(array_merge($renderer->meta['plugin_ghissues_apicalls'], $hashpair));

        	return true;
        }
		
		if ($mode != 'xhtml') return false;
		global $conf;
		
		$renderOutput  = '<div class="ghissues_plugin_box">'."\n".'<div class="ghissues_plugin_box_header">';
		$renderOutput .= $data['header']."</div>\n";
		// If we don't load the helper, we're doomed.
		if ( !($loadFromCache = $this->loadHelper('ghissues_apiCacheInterface')) ) {
			$renderer->doc .= '<p>ghissues helper failed to load</p>';
			return false;
		}
		
		$renderOutput .= $loadFromCache->getRenderedRequest($data['url']);
		$renderOutput .= '<div class="ghissues_plugin_box_footer">'.$data['footer'].'</div>';
		$renderOutput .= '</div>';
		$renderer->doc .= $renderOutput;
		
        return true;
    }
        
}

// vim:ts=4:sw=4:et:
