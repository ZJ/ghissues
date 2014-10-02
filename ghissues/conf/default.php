<?php
/**
 * Default settings for the ghissues plugin
 *
 * @author Zach Smith <zsmith12@umd.edu>
 */

$conf['ghissueuser']    = '';         // Github Username, used for User-Agent and Authentication
$conf['ghissuerefresh'] = 10*60;      // Min time between API calls (per unique request URL)
