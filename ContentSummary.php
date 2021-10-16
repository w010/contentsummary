<?php
/**
 * WTP TYPO3 Content Summary
 * v0.8
 * WTP / wolo.pl '.' studio 2021
 * 
 * - Prepare a clear table of found content types, plugins, FCEs, frames.
 * - What is used where and how many of them (...you'll have to repair, if you broke it.)
 * - Get direct links to each type examples - find them all quickly to control if they still work after update.
 * - Whether that cave-era-accordion, which uses that old problematic js lib, is really still needed.
 * - Analyze chart of rootline parents of all pages found containing selected contenttype/plugin to check if
 *   maybe all instances on pages that are already deleted or unavailable publicly for years  
 *
 *   Q: How to use?
 *   A: It's typo3-independent, so run from anywhere. Temporary include somewhere in project's global scope php, ie.
 *      in AdditionalConfiguration_host.php. That way you have database configuration. Or put the db credentials 
 *      here (in 8.x format) and call file directly.
 * 
 * 			- NOTE - set $GLOBALS['ContentSummaryConfig']['versionCompat'] below, if needed!
 * 
 *   Q: Which TYPO3 versions/branches does it support?
 *   A: These which have fields list_type and Ctype in tt_content table, so basically all since 4.x to 10.x should work.
 *		You only must set the database credentials in the format like below / 8.x form.  
 */

// Uncomment when needed
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = 'project_app';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] = 'mysql';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] = 'www_devel';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = 'www_devel';

error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);


// Config
$GLOBALS['ContentSummaryConfig'] = [

	// typo3 major version - to auto handle db structure differences, like frame class or templavoila naming
	'versionCompat' => 10,

    // for handy links feature. set own by hand, if bad url in subdir-projects
    'baseDomain' => 'https://' . $_SERVER['HTTP_HOST'],

    // dump output on every run. you will come back here anyway to check something you forgot, so maybe just keep this on 
    'autosaveHTML' => 0,
    'autosaveCSV' => 1,
    
    // where to save, if not to current dir
    'fileDumpPath' => '', 
];





/**
 * Where the whole magic happens
 */
class ContentSummary	{
	
	// version compatibility values
	public $TV_PREFIX = 'tx_templavoilaplus';
	public $TV_CTYPE = 'templavoilaplus_pi1';
	public $TT_FRAME = 'frame_class';


	
	/** @var PDO */
	protected $db;

	/**
	 * @var array of arrays (items)
	 */
	protected $data = [];

	/**
	 * @var array of strings (every section html output)
	 */
	protected $outputContent = [];

	/**
	 * @var array of messages, notices or errors
	 */
	protected $messages = [];



	public function __construct() {
        // connect to db
		$this->databaseConnect();
		defined('LF') ?: define('LF', chr(10));
		
		if ($GLOBALS['ContentSummaryConfig']['versionCompat'] == 6)	{
			$this->TV_PREFIX = 'tx_templavoila';
			$this->TV_CTYPE = 'templavoila_pi1';
			$this->TT_FRAME = 'section_frame';
		}
	}
	

	protected function makeSummary()   {
		
		// SECTION: list_type

		try {
			$query = $this->db->prepare("
				SELECT t.list_type, COUNT(t.uid) AS count_use, 
					GROUP_CONCAT( DISTINCT t.pid SEPARATOR ', ') AS pids
					# just make this in code
					# GROUP_CONCAT( DISTINCT CONCAT('https://example.de.local/?id=', t.pid) SEPARATOR ' ' ) AS urls
				FROM `tt_content` AS t
					JOIN `pages` AS p  ON p.uid = t.pid
				WHERE t.list_type != ''
					AND NOT t.deleted 		# AND NOT t.hidden
					AND NOT p.deleted
				GROUP BY t.list_type
			");
			$query->execute();
			$query->setFetchMode(PDO::FETCH_ASSOC);
			$this->data['list_type'] = $query->fetchAll();
		} catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		

		if (count($this->data['list_type']))  {
			$sectionContent = '';

			// generate html output
			$sectionContent .= '<table class="item-types-summary">'.LF;
			$sectionContent .=   '<tr>'.LF;
			$sectionContent .=      '<th>' . 'list_type:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'count:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'pids:' . '</th>'.LF;
			$sectionContent .=   '</tr>'.LF;
			
			
			foreach($this->data['list_type'] as $item) {
				$sectionContent .= '<tr>'.LF;
				$sectionContent .=   '<td><a href="?action=analyseRootline&contentGroupKey=list_type&value='.$item['list_type'].'" target="_blank">' . $item['list_type'] . '</a></td>'.LF;
				$sectionContent .=   '<td>' . $item['count_use'] . '</td>'.LF;
				$pidsLinked = [];
				foreach (explode(', ', $item['pids']) as $i => $pid)  {
				    $pidsLinked[] =     '<a href="' . preg_replace('{/$}', '', $GLOBALS['baseDomain']) . '/?id=' . $pid . '" target="_blank">' . $pid . '</a>'.LF;
                }
				$sectionContent .=   '<td>' . implode(', ', $pidsLinked) . '</td>'.LF;
				$sectionContent .= '</tr>'.LF;
			}
			
			$sectionContent .= '</table>'.LF;
			$this->outputContent['summary__list_type'] = $sectionContent;
		}
		
		
		
		
		
		// SECTION: Ctype

		try {
			$query = $this->db->prepare("
                SELECT t.Ctype, COUNT(t.uid) AS count_use,
                   GROUP_CONCAT( DISTINCT t.pid SEPARATOR ', ') AS pids
                FROM `tt_content` AS t
                    JOIN `pages` AS p  ON p.uid = t.pid
                WHERE t.Ctype != ''	
                    AND NOT t.deleted 		# AND NOT t.hidden
                    AND NOT p.deleted
                GROUP BY t.Ctype
			");
			$query->execute();
			$query->setFetchMode(PDO::FETCH_ASSOC);
			$this->data['Ctype'] = $query->fetchAll();
		} catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		

		if (count($this->data['Ctype']))  {
			$sectionContent = '';
			
			// generate html output
			$sectionContent .= '<table class="item-types-summary">'.LF;
			$sectionContent .=   '<tr>'.LF;
			$sectionContent .=      '<th>' . 'Ctype:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'count:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'pids:' . '</th>'.LF;
			$sectionContent .=   '</tr>'.LF;
			
			
			foreach($this->data['Ctype'] as $item) {
				$sectionContent .= '<tr>'.LF;
				$sectionContent .=   '<td><a href="?action=analyseRootline&contentGroupKey=Ctype&value='.$item['Ctype'].'" target="_blank">' . $item['Ctype'] . '</a></td>'.LF;
				$sectionContent .=   '<td>' . $item['count_use'] . '</td>'.LF;
				$pidsLinked = [];
				foreach (explode(', ', $item['pids']) as $i => $pid)  {
				    $pidsLinked[] =     '<a href="' . preg_replace('{/$}', '', $GLOBALS['baseDomain']) . '/?id=' . $pid . '" target="_blank">' . $pid . '</a>'.LF;
                }
				$sectionContent .=   '<td>' . implode(', ', $pidsLinked) . '</td>'.LF;
				$sectionContent .= '</tr>'.LF;
			}
			
			$sectionContent .= '</table>'.LF;
			$this->outputContent['summary__Ctype'] = $sectionContent;
		}
		
		
		
		
		
		// SECTION: TemplaVoila FCEs

		try {
		    if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE '{$this->TV_PREFIX}_ds'")->fetchAll())) {
		    
                $query = $this->db->prepare("
                    SELECT t.{$this->TV_PREFIX}_ds, COUNT(t.uid) AS count_use, 
                        GROUP_CONCAT( DISTINCT t.pid SEPARATOR ', ') AS pids
                    FROM `tt_content` AS t
                        JOIN `pages` AS p ON p.uid = t.pid
                    WHERE t.CType = '{$this->TV_CTYPE}'
                        AND NOT t.deleted 		# AND NOT t.hidden 
                        AND NOT p.deleted
                    GROUP BY t.{$this->TV_PREFIX}_ds
                ");
                $query->execute();
                $query->setFetchMode(PDO::FETCH_ASSOC);
                $this->data[$this->TV_PREFIX.'_ds'] = $query->fetchAll();
		    }
		} catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		

		if (is_array($this->data[$this->TV_PREFIX.'_ds']) && count($this->data[$this->TV_PREFIX.'_ds']))  {
			$sectionContent = '';
			
			// generate html output
			$sectionContent .= '<table class="item-types-summary">'.LF;
			$sectionContent .=   '<tr>'.LF;
			$sectionContent .=      '<th>' . $this->TV_PREFIX.'_ds:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'count:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'pids:' . '</th>'.LF;
			$sectionContent .=   '</tr>'.LF;
			
			
			foreach($this->data[$this->TV_PREFIX.'_ds'] as $item) {
				$sectionContent .= '<tr>'.LF;
				$sectionContent .=   '<td><a href="?action=analyseRootline&contentGroupKey='.$this->TV_PREFIX.'_ds&value='.$item[$this->TV_PREFIX.'_ds'].'" target="_blank">' . $item[$this->TV_PREFIX.'_ds'] . '</a></td>'.LF;
				$sectionContent .=   '<td>' . $item['count_use'] . '</td>'.LF;
				$pidsLinked = [];
				foreach (explode(', ', $item['pids']) as $i => $pid)  {
				    $pidsLinked[] =     '<a href="' . preg_replace('{/$}', '', $GLOBALS['baseDomain']) . '/?id=' . $pid . '" target="_blank">' . $pid . '</a>'.LF;
                }
				$sectionContent .=   '<td>' . implode(', ', $pidsLinked) . '</td>'.LF;
				$sectionContent .= '</tr>'.LF;
			}
			
			$sectionContent .= '</table>'.LF;
			$this->outputContent['summary__'.$this->TV_PREFIX.'_ds'] = $sectionContent;
		}
		
		
		
		
		// SECTION: Frames

		try {
		    if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE 'section_frame'")->fetchAll())) {
                $query = $this->db->prepare("
                    SELECT t.section_frame, COUNT(t.uid) AS count_use, GROUP_CONCAT( DISTINCT t.pid SEPARATOR ', ') AS pids
                    FROM `tt_content` AS t
                        JOIN `pages` AS p  ON p.uid = t.pid
                    WHERE t.section_frame != ''	
                    AND NOT t.deleted 		# AND NOT t.hidden
                        AND NOT p.deleted
                    GROUP BY t.section_frame
                ");
                $query->execute();
                $query->setFetchMode(PDO::FETCH_ASSOC);
                $this->data['section_frame'] = $query->fetchAll();
            }
		} catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		

		if (is_array($this->data['section_frame']) && count($this->data['section_frame']))  {
			$sectionContent = '';
			
			// generate html output
			$sectionContent .= '<table class="item-types-summary">'.LF;
			$sectionContent .=   '<tr>'.LF;
			$sectionContent .=      '<th>' . 'section_frame:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'count:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'pids:' . '</th>'.LF;
			$sectionContent .=   '</tr>'.LF;
			
			
			foreach($this->data['section_frame'] as $item) {
				$sectionContent .= '<tr>'.LF;
				$sectionContent .=   '<td><a href="?action=analyseRootline&contentGroupKey=section_frame&value='.$item['section_frame'].'" target="_blank">' . $item['section_frame'] . '</a></td>'.LF;
				$sectionContent .=   '<td>' . $item['count_use'] . '</td>'.LF;
				$pidsLinked = [];
				foreach (explode(', ', $item['pids']) as $i => $pid)  {
				    $pidsLinked[] =     '<a href="' . preg_replace('{/$}', '', $GLOBALS['baseDomain']) . '/?id=' . $pid . '" target="_blank">' . $pid . '</a>'.LF;
                }
				$sectionContent .=   '<td>' . implode(', ', $pidsLinked) . '</td>'.LF;
				$sectionContent .= '</tr>'.LF;
			}
			
			$sectionContent .= '</table>'.LF;
			$this->outputContent['summary__section_frame'] = $sectionContent;
		}
		
		
		
		// SECTION: Image orient

		try {
		    if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE 'imageorient'")->fetchAll())) {
                $query = $this->db->prepare("
                    SELECT t.imageorient, COUNT(t.uid) AS count_use, GROUP_CONCAT( DISTINCT t.pid SEPARATOR ', ') AS pids
                    FROM `tt_content` AS t
                        JOIN `pages` AS p  ON p.uid = t.pid
                    WHERE t.imageorient != ''
                        AND CType = 'textpic'
                        AND NOT t.deleted 		# AND NOT t.hidden
                        AND NOT p.deleted
                    GROUP BY t.imageorient
                ");
                $query->execute();
                $query->setFetchMode(PDO::FETCH_ASSOC);
                $this->data['imageorient'] = $query->fetchAll();
            }
		} catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		

		if (is_array($this->data['imageorient']) && count($this->data['imageorient']))  {
			$sectionContent = '';
			
			// generate html output
			$sectionContent .= '<table class="item-types-summary">'.LF;
			$sectionContent .=   '<tr>'.LF;
			$sectionContent .=      '<th>' . 'imageorient:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'count:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'pids:' . '</th>'.LF;
			$sectionContent .=   '</tr>'.LF;
			
			
			foreach($this->data['imageorient'] as $item) {
				$sectionContent .= '<tr>'.LF;
				$sectionContent .=   '<td><a href="?action=analyseRootline&contentGroupKey=imageorient&value='.$item['imageorient'].'" target="_blank">' . $item['imageorient'] . '</a></td>'.LF;
				$sectionContent .=   '<td>' . $item['count_use'] . '</td>'.LF;
				$pidsLinked = [];
				foreach (explode(', ', $item['pids']) as $i => $pid)  {
				    $pidsLinked[] =     '<a href="' . preg_replace('{/$}', '', $GLOBALS['baseDomain']) . '/?id=' . $pid . '" target="_blank">' . $pid . '</a>'.LF;
                }
				$sectionContent .=   '<td>' . implode(', ', $pidsLinked) . '</td>'.LF;
				$sectionContent .= '</tr>'.LF;
			}
			
			$sectionContent .= '</table>'.LF;
			$this->outputContent['summary__imageorient'] = $sectionContent;
		}
		
		
		
		// SECTION: Header layout

		try {
		    if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE 'header_layout'")->fetchAll())) {
                $query = $this->db->prepare("
                    SELECT t.header_layout, COUNT(t.uid) AS count_use, GROUP_CONCAT( DISTINCT t.pid SEPARATOR ', ') AS pids
                    FROM `tt_content` AS t
                        JOIN `pages` AS p  ON p.uid = t.pid
                    WHERE t.header_layout != ''	
                    AND NOT t.deleted 		# AND NOT t.hidden
                        AND NOT p.deleted
                    GROUP BY t.header_layout
                ");
                $query->execute();
                $query->setFetchMode(PDO::FETCH_ASSOC);
                $this->data['header_layout'] = $query->fetchAll();
            }
		} catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		

		if (is_array($this->data['header_layout']) && count($this->data['header_layout']))  {
			$sectionContent = '';
			
			// generate html output
			$sectionContent .= '<table class="item-types-summary">'.LF;
			$sectionContent .=   '<tr>'.LF;
			$sectionContent .=      '<th>' . 'header_layout:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'count:' . '</th>'.LF;
			$sectionContent .=      '<th>' . 'pids:' . '</th>'.LF;
			$sectionContent .=   '</tr>'.LF;


			$param_additionalWhere = '&additionalWhere=' . urlencode('AND header != ""');
			
			foreach($this->data['header_layout'] as $item) {
				$sectionContent .= '<tr>'.LF;
				$sectionContent .=   '<td><a href="?action=analyseRootline&contentGroupKey=header_layout&value='.$item['header_layout'].$param_additionalWhere.'" target="_blank">' . $item['header_layout'] . '</a></td>'.LF;
				$sectionContent .=   '<td>' . $item['count_use'] . '</td>'.LF;
				$pidsLinked = [];
				foreach (explode(', ', $item['pids']) as $i => $pid)  {
				    $pidsLinked[] =     '<a href="' . preg_replace('{/$}', '', $GLOBALS['baseDomain']) . '/?id=' . $pid . '" target="_blank">' . $pid . '</a>'.LF;
                }
				$sectionContent .=   '<td>' . implode(', ', $pidsLinked) . '</td>'.LF;
				$sectionContent .= '</tr>'.LF;
			}
			
			$sectionContent .= '</table>'.LF;
			$this->outputContent['summary__header_layout'] = $sectionContent;
		}
	}
	
	
	protected function analyseRootline()   {
	    // init args
        $availablecontentGroupKeys = ['Ctype', 'list_type', $this->TV_PREFIX.'_ds', 'section_frame', 'imageorient', 'header_layout'];
	    $tableField = in_array($_GET['contentGroupKey'], $availablecontentGroupKeys) ? $_GET['contentGroupKey'] : 'INVALID_GROUP_KEY';
	    $additionalWhere_custom = $_GET['additionalWhere'];
	    $additionalWhere = '';

	    $sectionContent = '';
	    $sectionHeader = '<h4>Look up the tree for visibility of grandparents of pages containing these items: </h4>
            <h2><i>' . htmlspecialchars($_GET['contentGroupKey']) . ' = ' . htmlspecialchars($_GET['value']) . '</i></h2>';
	    $sectionHeader .= $additionalWhere_custom ? '<h2><i>' . $additionalWhere_custom . '</i></h2>' : '';
	    $sectionHeader .= '<p class="small"><i>If all of these rootlines contains unavailable pages on any level, it probably means this content type is not available to public anymore.<br>
            Remember they could still be referenced somewhere in typoscript, fluid templates or other extensions.</i></p>';

	    
	    // collect all pids with such items

	    $pidsContainingSuchItems = [];
        try {
            if ($tableField == $this->TV_PREFIX.'_ds') {
                // filter contents with stored ds but are edited and not anymore of type fce
                $additionalWhere = ' AND CType = "'.$this->TV_CTYPE.'"';
            }
            if ($tableField == 'imageorient') {
                // filter contents with stored ds but are edited and not anymore of type fce
                $additionalWhere = ' AND CType = "textpic"';
            }
			$query = $this->db->prepare("
				SELECT t.pid,
				    GROUP_CONCAT( DISTINCT t.uid SEPARATOR ', ') AS uids
				FROM `tt_content` AS t
				WHERE t.{$tableField} = {$this->db->quote($_GET['value'])}
				    $additionalWhere
				  	$additionalWhere_custom
					AND NOT t.deleted 		# AND NOT t.hidden
				GROUP BY t.pid
			");
			$query->execute();
			$query->setFetchMode(PDO::FETCH_ASSOC);
			$pidsContainingSuchItems = $query->fetchAll();
		} catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		
		$typeIsVisibleAtLeastOnce = false;
		
        // iterate these pids and build their rootlines
        foreach ($pidsContainingSuchItems as $i => $item)  {
		    $typeIsAvailableThroughThisRootline = true;
		    
            $rootline = [];
            $this->buildUpRootline($rootline, $item['pid']);
            $pidsContainingSuchItems[$i]['rootline'] = array_reverse($rootline);
            $rootlineBreadcrumb = '';
            // draw rootline breadcrumbs
            foreach ($pidsContainingSuchItems[$i]['rootline'] as $page) {
                $rootlineBreadcrumb .= '<div class="rootline_item' 
                    . ($page['hidden'] ? ' hidden' : '')
                    . ($page['deleted'] ? ' deleted' : '')
                    . '">'
                        . '<a href="' . preg_replace('{/$}', '', $GLOBALS['baseDomain']) . '/?id=' . $page['uid'] . '" target="_blank">'
                        . $page['title']
                        . '</a>'
                        . '<br><span class="page-uid">[ '.$page['uid'].' ]</span>'
                            . ($page['hidden'] ? '<span class="smaller warn">hidden</span>' : '')
                            . ($page['deleted'] ? '<span class="smaller warn">deleted</span>' : '')
                . '</div>';
                
                if ($page['hidden'] || $page['deleted'])    {
                    $typeIsAvailableThroughThisRootline = false;
                }
            }
            // collect this info
            if (!$typeIsVisibleAtLeastOnce && $typeIsAvailableThroughThisRootline)    {
                $typeIsVisibleAtLeastOnce = true;
            }
            
            $sectionContent .= '<div class="rootline_breadcrumb' .($typeIsAvailableThroughThisRootline ? '' : ' inactive'). '">';
            $sectionContent .= $rootlineBreadcrumb ? $rootlineBreadcrumb : '<div class="rootline_item warning">No pagetree rootline found. No such pages in database.<br> Was removed manually? Looking for pid / page uid = ' . $item['pid'] . '</div>';
            $itemUids = [];
            foreach (explode(',', $item['uids']) as $ttcontentUid)   {
                $itemUids[] = '<a href="?action=ttcontentDetails&ttcontentUid=' . intval($ttcontentUid) . '">' . intval($ttcontentUid) . '</a>';
            }
            $sectionContent .= '<div class="these-items">tt_content uids: '.implode(', ', $itemUids).'</div>';
            $sectionContent .= '<br class="clear"> </div>';
        }
        
	    
	    $this->outputContent['analyseRootline'] = $sectionHeader
            . ($typeIsVisibleAtLeastOnce ? '' : '<p>- <b>It seems, that this content type is not present in any visible pagetree. You can consider disabling/removing this functionality</b></p>')
            . $sectionContent;
    }

    
    protected function ttcontentDetails()   {
	    // init args
        $uid = intval($_GET['ttcontentUid']);

	    $sectionContent = '';
	    $sectionHeader = '<h4>Record view (* some fields may be preparsed for readability)</h4>';

	    $row = [];
        try {
			$query = $this->db->prepare("
				SELECT t.*
				FROM `tt_content` AS t
				WHERE t.uid = {$uid}
			");
			$query->execute();
			$query->setFetchMode(PDO::FETCH_ASSOC);
			$row = $query->fetchAll()[0] ?: [];
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		
        $sectionContent .= '<table class="mono">';
		foreach ($row as $fieldname => $value)    {
            $fieldMarked = false;
            $fieldProcessed = false;
            $finalValue = $value;

		    switch ($fieldname) {
                case 'uid':
                case 'pid':
                case 'CType':
                case 'list_type':
                case 'header':
                case 'sys_language_uid':
                case 'section_frame':
                case 'imageorient':
                case 'header_layout':
                    $fieldMarked = true;
                    break;
                case $this->TV_PREFIX.'_ds':
                case $this->TV_PREFIX.'_to':
                    $fieldMarked = $value ? true : false;
                    break;
                case 'tstamp':
                case 'crdate':
		            $fieldProcessed = true;
		            $finalValue = date('Y.m.d  H:i:s', $value);
		            break;
                case 'deleted':
                case 'hidden':
                    if ($value) {
                        $fieldProcessed = true;
		                $finalValue = $value ? '<b>' . strtoupper($fieldname) . '!!</b>' : $value;
                    }
		            break;
                case 'pi_flexform':
                case $this->TV_PREFIX.'_flex':
                    if ($value) {
                        $fieldProcessed = true;
                        $dom = new DOMDocument;
                        $dom->preserveWhiteSpace = true;
                        $dom->formatOutput = true;
                        $dom->loadXML($value);
		                $finalValue = "<pre>" . htmlentities($dom->saveXML()) . "</pre>";
		                // $finalValue = "<pre>" . var_export(simplexml_load_string($value), true) . "</pre>";
                    }
                    break;
                default:
                    $finalValue = htmlspecialchars($value);
            }
            
            $finalValue = $fieldProcessed ? '<div title="original value:' . "\n" . htmlspecialchars($value) . '">' . $finalValue . '</div>' : $finalValue;

            $finalFieldname = $fieldProcessed ? "$fieldname  *" : $fieldname;
		    $sectionContent .= "<tr" . ($fieldMarked ? ' class="field_marked"' : '') . "><td>$finalFieldname</td><td>" . $finalValue . "</td>";
        }
        $sectionContent .= '</table>';
        
	    
	    $this->outputContent['ttcontentDetails'] = $sectionHeader
            . $sectionContent;
    }

	/**
     * Build rootline array up to page top root
	 * @param array $rootline
	 * @param int $uid
	 * @return array
	 */
    protected function buildUpRootline(&$rootline, $uid)    {

	    try {
			$query = $this->db->prepare("
				SELECT p.uid, p.pid, p.deleted, p.hidden, p.title
				FROM `pages` AS p
				WHERE p.uid = {intval($uid)}
			    LIMIT 1
			");
			$query->execute();
			$query->setFetchMode(PDO::FETCH_ASSOC);
			$pageRow = $query->fetch();
			
			// if valid row - collect
            if (is_array($pageRow)  &&  count($pageRow))    {
                $rootline[] = $pageRow;
            }

            // if has parent, go for him
            if ($pageRow['pid'])    {
                $this->buildUpRootline($rootline, $pageRow['pid']);
            }

		} catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		return $rootline;
    }


	/**
	 * Build final page content from prepared parts
     * @return string
	 */
	public function handleRequestsAndCompileContent()   {
        
        $pageContent = '';

        switch ($_GET['action'])    {
        
            case 'analyseRootline':
                $this->analyseRootline();
        
                $pageContent .= '<h1>Analyse rootline</h1>'.LF;
                $pageContent .= $this->getContent('analyseRootline');
                break;
                
            case 'ttcontentDetails':
                $this->ttcontentDetails();
                $pageContent .= '<h1>Tt_content details</h1>'.LF;
                $pageContent .= $this->getContent('ttcontentDetails');
                break;
        
            default:
                $this->makeSummary();
                
                $pageContent .= '<h1>Project item types summary</h1>'.LF; 
                $pageContent .= '###PLACEHOLDER_MESSAGE###'; 
        
                $pageContent .= '<h2>tt_content - Plugin types:</h2>'.LF;
                $pageContent .= $this->getContent('summary__list_type');
                
                $pageContent .= '<h2>tt_content - Ctypes:</h2>'.LF;
                $pageContent .= $this->getContent('summary__Ctype');
                
                if ($this->data[$this->TV_PREFIX.'_ds'])   {
                    $pageContent .= '<h2>tt_content - Templavoila FCE DS:</h2>'.LF;
                    $pageContent .= $this->getContent('summary__'.$this->TV_PREFIX.'_ds');
                }
                
                if ($this->data['section_frame'])   {
                    $pageContent .= '<h2>tt_content - Frames:</h2>'.LF;
                    $pageContent .= $this->getContent('summary__section_frame');
                }
                
                if ($this->data['imageorient'])   {
                    $pageContent .= '<h2>tt_content - Image orient:</h2>'.LF;
                    $pageContent .= $this->getContent('summary__imageorient');
                }
                
                if ($this->data['header_layout'])   {
                    $pageContent .= '<h2>tt_content - Header layout:</h2>'.LF;
                    $pageContent .= $this->getContent('summary__header_layout');
                }
        }
        
        return $pageContent;
    }


	protected function getContent($section)  {

		return $this->outputContent[$section];
	}

	
	protected function databaseConnect()    {
		try {
		    $this->db = new PDO("mysql:host={$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host']};dbname={$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname']}",
			    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'],
			    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password']);
			// set the PDO error mode to exception
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch(PDOException $e) {
		    die("ContentSummary: Database connection failed: " . $e->getMessage());
		}
	}


	/**
     * Replace placeholders with values (to output content to browser)
	 * @param string $html
	 * @return string
	 */
	public function replacePlaceholders($html) {

	    $placeholders = [
	        '###PLACEHOLDER_MESSAGE###' => '<p class="message">' . implode('<br>', $this->messages) . '</p>',
        ];

	    return str_replace(array_keys($placeholders), array_values($placeholders), $html);
	}

	/**
     * Remove placeholders from output html (to dump clean version)
	 * @param string $html
	 * @return string
	 */
	public function cleanupPlaceholders($html) {
	    
	    return str_replace([
            '###PLACEHOLDER_MESSAGE###',
        ], '', $html);
	}


	/**
     * Dump cleaned output html to a file 
	 * @param string $html
	 */
	public function saveOutput($html) {
        if ($GLOBALS['ContentSummaryConfig']['autosaveHTML'] == 1)  {
            $this->messages[] = '<span class="small"><i>(autosave active)</i></span>';
            $html = $this->cleanupPlaceholders($html);
            $savePath = $GLOBALS['ContentSummaryConfig']['fileDumpPath'] . 'content_summary-'.date('Ymd-His').'.html';
            if (false === file_put_contents($savePath, $html)) {
                $this->messages[] = 'Autosave HTML failed! Can\'t write file to path: ' . $savePath;
            }
        }
	}

	/**
	 * Dump the whole summary data to Csv 
	 */
	public function saveCsv() {
	    if ($GLOBALS['ContentSummaryConfig']['autosaveCSV'] == 1)  {
	        
            // open buffer
            $csvHandle = fopen('php://temp/maxmemory:'. (5*1024*1024), 'r+');
	        // prepare data array be in proper shape

            
            // list_type
            if (is_array($this->data['list_type'])  &&  count($this->data['list_type']))    {
                // header line
                fputcsv($csvHandle, ['List types / plugins:', 'count_use:', 'pids:']);
                // must reparse the array, order of columns is mixed/ indexes alphabetically, so
                foreach ($this->data['list_type'] as $row)  {
                    fputcsv($csvHandle, [$row['list_type'], $row['count_use'], $row['pids']]);
                }
            }


            // Ctype
            if (is_array($this->data['Ctype'])  &&  count($this->data['Ctype']))    {
                
                for ($i=0; $i<=3; $i++)  {  // leave 4 empty rows
                    fputcsv($csvHandle, []);
                }
            
                fputcsv($csvHandle, ['Ctypes:', 'count_use:', 'pids:']);
                foreach ($this->data['Ctype'] as $row)  {
                    fputcsv($csvHandle, [$row['Ctype'], $row['count_use'], $row['pids']]);
                }
            }
            
            
            // TV FCE
            if (is_array($this->data[$this->TV_PREFIX.'_ds'])  &&  count($this->data[$this->TV_PREFIX.'_ds']))    {
                
                for ($i=0; $i<=3; $i++)  {  // leave 4 empty rows before
                    fputcsv($csvHandle, []);
                }
                
                fputcsv($csvHandle, ['Templavoila FCEs:', 'count_use:', 'pids:']);
                foreach ($this->data[$this->TV_PREFIX.'_ds'] as $row)  {
                    fputcsv($csvHandle, [$row[$this->TV_PREFIX.'_ds'], $row['count_use'], $row['pids']]);
                }
            }
            
            
            // Frames
            if (is_array($this->data['section_frame'])  &&  count($this->data['section_frame']))    {
                
                for ($i=0; $i<=3; $i++)  {  // leave 4 empty rows
                    fputcsv($csvHandle, []);
                }

                fputcsv($csvHandle, ['CSC/FSC Frames', 'count_use:', 'pids:']);
                foreach ($this->data['section_frame'] as $row)  {
                    fputcsv($csvHandle, [$row['section_frame'], $row['count_use'], $row['pids']]);
                }
            }
            
            
            // Image orient
            if (is_array($this->data['imageorient'])  &&  count($this->data['imageorient']))    {
                
                for ($i=0; $i<=3; $i++)  {  // leave 4 empty rows
                    fputcsv($csvHandle, []);
                }

                fputcsv($csvHandle, ['tt_content: Image orient', 'count_use:', 'pids:']);
                foreach ($this->data['imageorient'] as $row)  {
                    fputcsv($csvHandle, [$row['imageorient'], $row['count_use'], $row['pids']]);
                }
            }
            
            
            // Header layout
            if (is_array($this->data['header_layout'])  &&  count($this->data['header_layout']))    {
                
                for ($i=0; $i<=3; $i++)  {  // leave 4 empty rows
                    fputcsv($csvHandle, []);
                }

                fputcsv($csvHandle, ['tt_content: Header layout', 'count_use:', 'pids:']);
                foreach ($this->data['header_layout'] as $row)  {
                    fputcsv($csvHandle, [$row['header_layout'], $row['count_use'], $row['pids']]);
                }
            }
            

            // catch streamed output from buffer and save it by ourselves at once
            rewind($csvHandle);
            $csv = stream_get_contents($csvHandle);

            
            $savePath = $GLOBALS['ContentSummaryConfig']['fileDumpPath'] . 'content_summary-'.date('Ymd-His').'.csv';
            if (false === file_put_contents($savePath, $csv)) {
                $this->messages[] = 'Autosave CSV failed! Can\'t write file to path: ' . $savePath;
            }
        }
	}
}





// GO


$WorkObject = new ContentSummary();
$pageContent = $WorkObject->handleRequestsAndCompileContent();

ob_start();



?><html lang="en">
<head>
    <title>Content Summary</title>
    <style>
html, body {
    height: 100%;
}
*,
*:before,
*:after {
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
}

html,
body {
    font-size: 100%;
}

body {
    background: #fff;
    color: #222;
    cursor: auto;
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    font-style: normal;
    font-weight: normal;
    line-height: 1.5;
}
table {
	border-collapse: collapse;
	border-spacing: 0;
    margin-bottom: 20px;
}
td  {
    border: 1px solid #ddd;
    padding: 3px 6px;
    vertical-align: top;
    font-size: 0.8em;
}
th  {
    text-align: left;
    padding: 4px 6px;
    vertical-align: top;
}
table.item-types-summary    {
    margin-left: 30px; 
    margin-bottom: 60px; 
}
a   {
    text-decoration: none;
}
a:hover   {
    text-decoration: underline;
}
.mono   {
    font-family: "Lucida Console", monospace, sans-serif;
    line-height: 1.2em;
}
tr.field_marked td {
    font-weight: bold;
}
.rootline_breadcrumb    {
    border: 1px dotted gray;
    padding: 4px 0;
    margin-bottom: 10px;
    background: #f3f2f2;
}
.rootline_breadcrumb > *    {
    float: left;
}
.rootline_breadcrumb.inactive   {
    opacity: 0.7;
    font-style: italic;
    border: 1px dotted lightgray
}
.rootline_item  {
    margin: 0 20px;
    padding: 0 20px;
    border: 1px solid #8da6ce;
    background: #fff;
    position: relative;
}
.rootline_item:after  {
    content: ' ➤ ';
    font-style: normal;
    color: #a1a0ab;
    position: absolute;
    top: 5px;
    right: -20px;
    width: 10px;
    height: 10px;
    font-size: 1.5em;
}
.rootline_item.hidden {
    background: #d6dce0;
    color: #557b86;
}
.rootline_item.deleted {
    background: #eebdbd;
    color: #ec7171;
}
.rootline_item.warning {
    background: #ffe8b3;
    color: #d08914;
}
.rootline_item a    {
    color: inherit;
}
.rootline_item a:hover    {
    color: #000;
    text-decoration: underline;
}
.these-items    {
    margin: 0 14px;
    padding: 0 20px;
    font-size: 0.9em;
}
.page-uid   {
    font-size: 0.9em;
}
.small  { 
    font-size: 0.8em;
}
.smaller  { 
    font-size: 0.7em;
}
.warn   {
    margin-left: 10px;
}
.clear  {
    clear: both;
}
    </style>
</head>
<body>
    <?php print $pageContent; ?>
</body>
</html><?php

// catch output (with placeholders to insert ie. messages or errors)
$html = ob_get_contents();
ob_clean();

// save that output to file, but with removed placeholders (not used there, in static file) 
$WorkObject->saveOutput($WorkObject->cleanupPlaceholders($html));
$WorkObject->saveCsv();

// but the original var still has them, so replace with values now, maybe we got some errors from saving these files 
$html = $WorkObject->replacePlaceholders($html);

// send to browser final content
print $html;
