<?php
/**
 * Options for the ghissues plugin
 *
 * @author Zach Smith <zsmith12@umd.edu>
 */


$meta['ghissueuser']    = array('string');                 // Github Username, used for User-Agent and Authentication
$meta['ghissuerefresh'] = array('numeric', '_min' => -1 ); // Min time between API calls (per unique request URL)
$meta['ghissueoauth']   = array('string');                 // OAuth token to use in API calls
