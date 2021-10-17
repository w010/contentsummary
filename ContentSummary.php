<?php
/**
 * WTP TYPO3 Content Summary
 * v0.9
 * WTP / wolo.pl '.' studio 2021
 */

define ('CONTENT_SUMMARY_VERSION', '0.9.0');

/** - Prepare a clear table of found content types, plugins, FCEs, frames.
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
 *   A: These which have fields list_type and CType in tt_content table, so basically all since 4.x to 10.x should work.
 *		You only must set the database credentials in the format like below / 8.x form.  
 */


// By default, this script operates standalone and prints output, but it may also be included externally, to use its calculated data.
// To do this, before inclusion set config value 'mode_include' => 1
// and then instantiate passing config array
if (! $GLOBALS['ContentSummaryConfig']['mode_include'])	{

	// Uncomment when needed
	$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = 'project_app';
	$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] = 'mysql';
	$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] = 'www_devel';
	$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = 'www_devel';



	// Local Config
	$GLOBALS['ContentSummaryConfig'] = [
		'autosaveCSV' => 0,
		// 'versionCompat' => 6,
	];
	

	error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
}







// Default Config
$GLOBALS['ContentSummaryConfigDefault'] = [

	// typo3 major version - to auto handle db structure differences, like frame class or templavoila naming
	'versionCompat' => 10,

    // for handy links feature. set own by hand, if bad url in subdir-projects
    'baseDomain' => 'https://' . $_SERVER['HTTP_HOST'],

    // dump output on every run. you will come back here anyway to check something you forgot, so maybe just keep this on 
    'autosaveHTML' => 0,
    'autosaveCSV' => 1,
    
    // where to save, if not to current dir
    'fileDumpPath' => '',
	
	'showSummaryFor' => [
		ContentSummary::TYPE_PLUGIN,
		ContentSummary::TYPE_CE,
		ContentSummary::TYPE_FRAME,
		ContentSummary::TYPE_HEADER,
		ContentSummary::TYPE_IMAGEORIENT,
		ContentSummary::TYPE_TEMPLAVOILA_DS,
		ContentSummary::TYPE_TEMPLAVOILA_TO
	],
];



$GLOBALS['ContentSummaryConfig'] = array_merge($GLOBALS['ContentSummaryConfigDefault'], $GLOBALS['ContentSummaryConfig'] ?? []);



/**
 * Where the whole magic happens
 */
class ContentSummary	{
	
	// version compatibility values
	public $TV_PREFIX = 'tx_templavoilaplus';
	public $TV_CTYPE = 'templavoilaplus_pi1';
	public $TT_FRAME = 'frame_class';


	// these are internal section keys, not database fields!
	const TYPE_PLUGIN = 'plugin';
	const TYPE_CE = 'CType';
	const TYPE_FRAME = 'frame';
	const TYPE_HEADER = 'header_layout';
	const TYPE_IMAGEORIENT = 'imageorient';
	const TYPE_TEMPLAVOILA_DS = 'tv_ds';
	const TYPE_TEMPLAVOILA_TO = 'tv_to';


	/** @var PDO */
	protected $db;

	/**
	 * @var array configuration
	 */
	protected $config = [];

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



	public function __construct(array $config) {
		$this->config = $config;
		if (!$config['mode_include'])	{
			// connect to db
			$this->databaseConnect();
		}
		defined('LF') ?: define('LF', chr(10));
		
		if ($this->config['versionCompat'] == 6)	{
			$this->TV_PREFIX = 'tx_templavoila';
			$this->TV_CTYPE = 'templavoila_pi1';
			$this->TT_FRAME = 'section_frame';
		}
	}
	

	protected function makeSummary()   {
		
		// SECTION: list_type

		if (in_array(self::TYPE_PLUGIN, $this->config['showSummaryFor']))	{

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
				$this->outputContent['summary__'.self::TYPE_PLUGIN] = $sectionContent;
			}
		}
		
		
		
		
		// SECTION: CType

		if (in_array(self::TYPE_CE, $this->config['showSummaryFor']))	{
			
			try {
				$query = $this->db->prepare("
					SELECT t.CType, COUNT(t.uid) AS count_use,
					   GROUP_CONCAT( DISTINCT t.pid SEPARATOR ', ') AS pids
					FROM `tt_content` AS t
						JOIN `pages` AS p  ON p.uid = t.pid
					WHERE t.CType != ''	
						AND NOT t.deleted 		# AND NOT t.hidden
						AND NOT p.deleted
					GROUP BY t.CType
				");
				$query->execute();
				$query->setFetchMode(PDO::FETCH_ASSOC);
				$this->data['CType'] = $query->fetchAll();
			} catch(PDOException $e) {
				echo "Error: " . $e->getMessage();
			}
			

			if (count($this->data['CType']))  {
				$sectionContent = '';
				
				// generate html output
				$sectionContent .= '<table class="item-types-summary">'.LF;
				$sectionContent .=   '<tr>'.LF;
				$sectionContent .=      '<th>' . 'CType:' . '</th>'.LF;
				$sectionContent .=      '<th>' . 'count:' . '</th>'.LF;
				$sectionContent .=      '<th>' . 'pids:' . '</th>'.LF;
				$sectionContent .=   '</tr>'.LF;
				
				
				foreach($this->data['CType'] as $item) {
					$sectionContent .= '<tr>'.LF;
					$sectionContent .=   '<td><a href="?action=analyseRootline&contentGroupKey=CType&value='.$item['CType'].'" target="_blank">' . $item['CType'] . '</a></td>'.LF;
					$sectionContent .=   '<td>' . $item['count_use'] . '</td>'.LF;
					$pidsLinked = [];
					foreach (explode(', ', $item['pids']) as $i => $pid)  {
						$pidsLinked[] =     '<a href="' . preg_replace('{/$}', '', $GLOBALS['baseDomain']) . '/?id=' . $pid . '" target="_blank">' . $pid . '</a>'.LF;
					}
					$sectionContent .=   '<td>' . implode(', ', $pidsLinked) . '</td>'.LF;
					$sectionContent .= '</tr>'.LF;
				}
				
				$sectionContent .= '</table>'.LF;
				$this->outputContent['summary__'.self::TYPE_CE] = $sectionContent;
			}
		}




		// SECTION: Frames

		if (in_array(self::TYPE_FRAME, $this->config['showSummaryFor']))	{

			try {
				if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE '{$this->TT_FRAME}'")->fetchAll())) {
					$query = $this->db->prepare("
						SELECT ce.{$this->TT_FRAME}, COUNT(ce.uid) AS count_use, GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids
						FROM `tt_content` AS ce
							JOIN `pages` AS p  ON p.uid = ce.pid
						WHERE ce.{$this->TT_FRAME} != ''	
						AND NOT ce.deleted 		# AND NOT ce.hidden
							AND NOT p.deleted
						GROUP BY ce.{$this->TT_FRAME}
					");
					$query->execute();
					$query->setFetchMode(PDO::FETCH_ASSOC);
					$this->data[$this->TT_FRAME] = $query->fetchAll();
				}
			} catch(PDOException $e) {
				echo "Error: " . $e->getMessage();
			}
			
	
			if (is_array($this->data[$this->TT_FRAME]) && count($this->data[$this->TT_FRAME]))  {
				$sectionContent = '';
				
				// generate html output
				$sectionContent .= '<table class="item-types-summary">'.LF;
				$sectionContent .=   '<tr>'.LF;
				$sectionContent .=      '<th>' . $this->TT_FRAME.':' . '</th>'.LF;
				$sectionContent .=      '<th>' . 'count:' . '</th>'.LF;
				$sectionContent .=      '<th>' . 'pids:' . '</th>'.LF;
				$sectionContent .=   '</tr>'.LF;
				
				
				foreach($this->data[$this->TT_FRAME] as $item) {
					$sectionContent .= '<tr>'.LF;
					$sectionContent .=   '<td><a href="?action=analyseRootline&contentGroupKey='.$this->TT_FRAME.'&value='.$item[$this->TT_FRAME].'" target="_blank">' . $item[$this->TT_FRAME] . '</a></td>'.LF;
					$sectionContent .=   '<td>' . $item['count_use'] . '</td>'.LF;
					$pidsLinked = [];
					foreach (explode(', ', $item['pids']) as $i => $pid)  {
						$pidsLinked[] =     '<a href="' . preg_replace('{/$}', '', $GLOBALS['baseDomain']) . '/?id=' . $pid . '" target="_blank">' . $pid . '</a>'.LF;
					}
					$sectionContent .=   '<td>' . implode(', ', $pidsLinked) . '</td>'.LF;
					$sectionContent .= '</tr>'.LF;
				}
				
				$sectionContent .= '</table>'.LF;
				$this->outputContent['summary__'.self::TYPE_FRAME] = $sectionContent;
			}
		}
	
	
	
		
		// SECTION: Image orient

		if (in_array(self::TYPE_IMAGEORIENT, $this->config['showSummaryFor']))	{

			try {
				if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE 'imageorient'")->fetchAll())) {
					$query = $this->db->prepare("
						SELECT ce.imageorient, COUNT(ce.uid) AS count_use, GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids
						FROM `tt_content` AS ce
							JOIN `pages` AS p  ON p.uid = ce.pid
						WHERE ce.imageorient != ''
							AND CType = 'textpic'
							AND NOT ce.deleted 		# AND NOT ce.hidden
							AND NOT p.deleted
						GROUP BY ce.imageorient
						"/* cast to integer workaround: */."
						ORDER BY (ce.imageorient * 1)
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
				$this->outputContent['summary__'.self::TYPE_IMAGEORIENT] = $sectionContent;
			}
		}
		
		
		
		
		// SECTION: Header layout

		if (in_array(self::TYPE_HEADER, $this->config['showSummaryFor']))	{

			try {
				if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE 'header_layout'")->fetchAll())) {
					$query = $this->db->prepare("
						SELECT ce.header_layout, COUNT(ce.uid) AS count_use, GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids
						FROM `tt_content` AS ce
							JOIN `pages` AS p  ON p.uid = ce.pid
						WHERE ce.header_layout != ''	
						AND NOT ce.deleted 		# AND NOT t.hidden
							AND NOT p.deleted
						GROUP BY ce.header_layout
						"/* cast to integer workaround: */."
						ORDER BY (ce.header_layout * 1)
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
				$this->outputContent['summary__'.self::TYPE_HEADER] = $sectionContent;
			}
		}
		
		
		
		
		// SECTION: TemplaVoila FCE DS

		if (in_array(self::TYPE_TEMPLAVOILA_DS, $this->config['showSummaryFor']))	{

			try {
				if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE '{$this->TV_PREFIX}_ds'")->fetchAll())) {
				
					$query = $this->db->prepare("
						SELECT ce.{$this->TV_PREFIX}_ds, COUNT(ce.uid) AS count_use, 
							GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids
						FROM `tt_content` AS ce
							JOIN `pages` AS p ON p.uid = ce.pid
						WHERE ce.CType = '{$this->TV_CTYPE}'
							AND NOT ce.deleted 		# AND NOT ce.hidden 
							AND NOT ce.deleted
						GROUP BY ce.{$this->TV_PREFIX}_ds
						ORDER BY ce.{$this->TV_PREFIX}_to
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
				$this->outputContent['summary__'.self::TYPE_TEMPLAVOILA_DS] = $sectionContent;
			
			}
		}
		
		
		
		
		// SECTION: TemplaVoila FCE TO

		if (in_array(self::TYPE_TEMPLAVOILA_TO, $this->config['showSummaryFor']))	{

			try {
				if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE '{$this->TV_PREFIX}_to'")->fetchAll())) {
				
					$query = $this->db->prepare("
						SELECT ce.{$this->TV_PREFIX}_to, COUNT(ce.uid) AS count_use, 
							GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids,
						   	tvto.title AS to_title
						FROM `tt_content` AS ce
							JOIN `pages` AS p  ON p.uid = ce.pid
							JOIN `{$this->TV_PREFIX}_tmplobj` AS tvto  ON tvto.uid = ce.{$this->TV_PREFIX}_to
						WHERE ce.CType = '{$this->TV_CTYPE}'
							AND NOT ce.deleted 		# AND NOT t.hidden 
							AND NOT p.deleted
						GROUP BY ce.{$this->TV_PREFIX}_to
						ORDER BY ce.{$this->TV_PREFIX}_to
					");
					$query->execute();
					$query->setFetchMode(PDO::FETCH_ASSOC);
					$this->data[$this->TV_PREFIX.'_to'] = $query->fetchAll();
				}
			} catch(PDOException $e) {
				echo "Error: " . $e->getMessage();
			}
			

			if (is_array($this->data[$this->TV_PREFIX.'_to']) && count($this->data[$this->TV_PREFIX.'_to']))  {
				$sectionContent = '';
				
				// generate html output
				$sectionContent .= '<table class="item-types-summary">'.LF;
				$sectionContent .=   '<tr>'.LF;
				$sectionContent .=      '<th>' . $this->TV_PREFIX.'_to:' . '</th>'.LF;
				$sectionContent .=      '<th>' . 'count:' . '</th>'.LF;
				$sectionContent .=      '<th>' . 'pids:' . '</th>'.LF;
				$sectionContent .=   '</tr>'.LF;
				
				
				foreach($this->data[$this->TV_PREFIX.'_to'] as $item) {
					$sectionContent .= '<tr>'.LF;
					$sectionContent .=   '<td><a href="?action=analyseRootline&contentGroupKey='.$this->TV_PREFIX.'_to&value='.$item[$this->TV_PREFIX.'_to'].'" target="_blank">' . $item[$this->TV_PREFIX.'_to'] .' / '. $item['to_title'] .'</a></td>'.LF;
					$sectionContent .=   '<td>' . $item['count_use'] . '</td>'.LF;
					$pidsLinked = [];
					foreach (explode(', ', $item['pids']) as $i => $pid)  {
						$pidsLinked[] =     '<a href="' . preg_replace('{/$}', '', $GLOBALS['baseDomain']) . '/?id=' . $pid . '" target="_blank">' . $pid . '</a>'.LF;
					}
					$sectionContent .=   '<td>' . implode(', ', $pidsLinked) . '</td>'.LF;
					$sectionContent .= '</tr>'.LF;
				}
				
				$sectionContent .= '</table>'.LF;
				$this->outputContent['summary__'.self::TYPE_TEMPLAVOILA_TO] = $sectionContent;
			}
		}
	}
	
	
	protected function analyseRootline()   {
	    // init args
        $availablecontentGroupKeys = ['CType', 'list_type', $this->TV_PREFIX.'_ds', $this->TV_PREFIX.'_to', $this->TT_FRAME, 'imageorient', 'header_layout'];
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
                case $this->TT_FRAME:
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

                if (in_array(self::TYPE_PLUGIN, $this->config['showSummaryFor']))   {
					$pageContent .= '<h2>tt_content - Plugin types:</h2>'.LF;
					$pageContent .= $this->getContent('summary__'.self::TYPE_PLUGIN);
                }
                
				if (in_array(self::TYPE_CE, $this->config['showSummaryFor']))   {
					$pageContent .= '<h2>tt_content - CTypes:</h2>'.LF;
					$pageContent .= $this->getContent('summary__'.self::TYPE_CE);
				}
                
                if (in_array(self::TYPE_FRAME, $this->config['showSummaryFor']))   {
                    $pageContent .= '<h2>tt_content - Frames:</h2>'.LF;
                    $pageContent .= $this->getContent('summary__'.self::TYPE_FRAME);
                }
                
                if (in_array(self::TYPE_IMAGEORIENT, $this->config['showSummaryFor']))   {
                    $pageContent .= '<h2>tt_content - Image orient:</h2>'.LF;
                    $pageContent .= $this->getContent('summary__'.self::TYPE_IMAGEORIENT);
                }
                
                if (in_array(self::TYPE_HEADER, $this->config['showSummaryFor']))	{
                    $pageContent .= '<h2>tt_content - Header layout:</h2>'.LF;
                    $pageContent .= $this->getContent('summary__'.self::TYPE_HEADER);
                }
                
                if (in_array(self::TYPE_TEMPLAVOILA_DS, $this->config['showSummaryFor']))   {
                    $pageContent .= '<h2>tt_content - Templavoila FCE DS:</h2>'.LF;
                    $pageContent .= $this->getContent('summary__'.self::TYPE_TEMPLAVOILA_DS);
                }
				
                if (in_array(self::TYPE_TEMPLAVOILA_TO, $this->config['showSummaryFor']))   {
                    $pageContent .= '<h2>tt_content - Templavoila FCE TO:</h2>'.LF;
                    $pageContent .= $this->getContent('summary__'.self::TYPE_TEMPLAVOILA_TO);
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
        if ($this->config['autosaveHTML'] == 1)  {
            $this->messages[] = '<span class="small"><i>(autosave active)</i></span>';
            $html = $this->cleanupPlaceholders($html);
            $savePath = $this->config['fileDumpPath'] . 'content_summary-'.date('Ymd-His').'.html';
            if (false === file_put_contents($savePath, $html)) {
                $this->messages[] = 'Autosave HTML failed! Can\'t write file to path: ' . $savePath;
            }
        }
	}

	/**
	 * Dump the whole summary data to Csv 
	 */
	public function saveCsv() {
	    if ($this->config['autosaveCSV'] == 1)  {
	        
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


            // CType
            if (is_array($this->data['CType'])  &&  count($this->data['CType']))    {
                
                for ($i=0; $i<=3; $i++)  {  // leave 4 empty rows
                    fputcsv($csvHandle, []);
                }

                fputcsv($csvHandle, ['CTypes:', 'count_use:', 'pids:']);
                foreach ($this->data['CType'] as $row)  {
                    fputcsv($csvHandle, [$row['CType'], $row['count_use'], $row['pids']]);
                }
            }
            
            
            // Frames
            if (is_array($this->data[$this->TT_FRAME])  &&  count($this->data[$this->TT_FRAME]))    {
                
                for ($i=0; $i<=3; $i++)  {  // leave 4 empty rows
                    fputcsv($csvHandle, []);
                }

                fputcsv($csvHandle, ['CSC/FSC Frames', 'count_use:', 'pids:']);
                foreach ($this->data[$this->TT_FRAME] as $row)  {
                    fputcsv($csvHandle, [$row[$this->TT_FRAME], $row['count_use'], $row['pids']]);
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
            
            
            // TV FCE DS
            if (is_array($this->data[$this->TV_PREFIX.'_ds'])  &&  count($this->data[$this->TV_PREFIX.'_ds']))    {
                
                for ($i=0; $i<=3; $i++)  {  // leave 4 empty rows before
                    fputcsv($csvHandle, []);
                }
                
                fputcsv($csvHandle, ['tt_content: TV FCE DSs:', 'count_use:', 'pids:']);
                foreach ($this->data[$this->TV_PREFIX.'_ds'] as $row)  {
                    fputcsv($csvHandle, [$row[$this->TV_PREFIX.'_ds'], $row['count_use'], $row['pids']]);
                }
            }
			
            
            // TV FCE TO
            if (is_array($this->data[$this->TV_PREFIX.'_to'])  &&  count($this->data[$this->TV_PREFIX.'_to']))    {
                
                for ($i=0; $i<=3; $i++)  {  // leave 4 empty rows before
                    fputcsv($csvHandle, []);
                }
                
                fputcsv($csvHandle, ['tt_content: TV FCE TOs:', 'count_use:', 'pids:']);
                foreach ($this->data[$this->TV_PREFIX.'_to'] as $row)  {
                    fputcsv($csvHandle, [$row[$this->TV_PREFIX.'_to'] .' / '. $row['to_title'], $row['count_use'], $row['pids']]);
                }
            }


            // catch streamed output from buffer and save it by ourselves at once
            rewind($csvHandle);
            $csv = stream_get_contents($csvHandle);

            
            $savePath = $this->config['fileDumpPath'] . 'content_summary-'.date('Ymd-His').'.csv';
            if (false === file_put_contents($savePath, $csv)) {
                $this->messages[] = 'Autosave CSV failed! Can\'t write file to path: ' . $savePath;
            }
        }
	}
}



if (! $GLOBALS['ContentSummaryConfig']['mode_include'])	{

	// GO
	
	
	$WorkObject = new ContentSummary($GLOBALS['ContentSummaryConfig']);
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

	<p>v<?=CONTENT_SUMMARY_VERSION?></p>
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

}
