<?php
/**
 * ContentSummary - record types use cases tracker for TYPO3
 *  
 * v0.13
 * WTP / wolo.pl '.' studio 2021
 * 
 * https://github.com/w010/contentsummary
 */

namespace WTP;

const CONTENT_SUMMARY_VERSION = '0.13.0';

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
//	$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = 'project_app';
//	$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] = 'mysql';
//	$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] = 'www_devel';
//	$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = 'www_devel';



	// Local Config
	$GLOBALS['ContentSummaryConfig'] = [
		'autosaveCSV' => 0,
		// 'versionCompat' => 6,
		/*'makeSummaryFor' => [
			//ContentSummary::CE_TEMPLAVOILA_TO,
			//ContentSummary::PAGE_TEMPLAVOILA_DS,
			//ContentSummary::PAGE_TEMPLAVOILA_TO,
		],*/
		'debug' => 0,
	];


	error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
}







// Default Config
$GLOBALS['ContentSummaryConfigDefault'] = [

	// typo3 major version - to auto handle db structure differences, like frame class or templavoila naming
	'versionCompat' => 10,

    // for handy frontend links feature. set own by hand, if bad url in subdir-projects
    'baseDomain' => 'https://' . $_SERVER['HTTP_HOST'],

	// 'urlActionBase'

    // dump output on every run. you will come back here anyway to check something you forgot, so maybe just keep this on 
    'autosaveHTML' => 0,
    'autosaveCSV' => 0,

    // where to save, if not to current dir
    'fileDumpPath' => '',

	// todo: respect this order making output
	'makeSummaryFor' => [
		ContentSummary::CE_PLUGIN,
		ContentSummary::CE_CTYPE,
		ContentSummary::CE_FRAME,
		ContentSummary::CE_HEADERLAYOUT,
		ContentSummary::CE_IMAGEORIENT,
		ContentSummary::CE_TEMPLAVOILA_DS,
		ContentSummary::CE_TEMPLAVOILA_TO,
		ContentSummary::PAGE_TEMPLAVOILA_DS,
		ContentSummary::PAGE_TEMPLAVOILA_TO,
	],

	// shows tech data, like sql queries
	'debug' => 0,
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
	const CE_PLUGIN = 'ce_plugin';
	const CE_CTYPE = 'ce_CType';
	const CE_FRAME = 'ce_frame';
	const CE_HEADERLAYOUT = 'ce_headerlayout';
	const CE_IMAGEORIENT = 'ce_imageorient';
	const CE_TEMPLAVOILA_DS = 'ce_tv_ds';
	const CE_TEMPLAVOILA_TO = 'ce_tv_to';
	// pages
	const PAGE_TEMPLAVOILA_DS = 'page_tv_ds'; 
	const PAGE_TEMPLAVOILA_TO = 'page_tv_to'; 


	/** @var \PDO */
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
	 * @var array of arrays (for items)
	 */
	protected $debug = [];

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
		//if (!$config['mode_include'])	{	// just connect again... easier, faster, and it really doesn't make any difference
			// connect to db
			$this->databaseConnect();
		//}
		defined('LF') ?: define('LF', chr(10));
		
		if ($this->config['versionCompat'] == 6)	{
			$this->TV_PREFIX = 'tx_templavoila';
			$this->TV_CTYPE = 'templavoila_pi1';
			$this->TT_FRAME = 'section_frame';
		}
	}
	

	protected function collectSummaryData()   {
		
		try {


			// SECTION: list_type

			if (in_array(self::CE_PLUGIN, $this->config['makeSummaryFor']))	{

				$query = $this->db->prepare("
					SELECT ce.list_type,
					   	COUNT(ce.uid) AS count_use, 
						GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids
						# just make this in code
						# GROUP_CONCAT( DISTINCT CONCAT('https://example.de.local/?id=', t.pid) SEPARATOR ' ' ) AS urls
					FROM `tt_content` AS ce
						JOIN `pages` AS p  ON p.uid = ce.pid
					WHERE ce.list_type != ''
						AND NOT ce.deleted 		# AND NOT t.hidden
						AND NOT p.deleted
					GROUP BY ce.list_type
				");
				$query->execute();
				$query->setFetchMode(\PDO::FETCH_ASSOC);
				$data = $query->fetchAll();


				if (is_array($data) && count($data))  {
					$sectionContent = '';
		
					// generate html output
					$sectionContent .= '<table class="item-types-summary  mono">'.LF;
					$sectionContent .=   '<tr>'.LF;
					$sectionContent .=      '<th>'. 'list_type:' .'</th>'.LF;
					$sectionContent .=      '<th>'. 'count:' .'</th>'.LF;
					$sectionContent .=      '<th>'. 'pids:' .'</th>'.LF;
					$sectionContent .=   '</tr>'.LF;
					
					
					foreach ($data as $item) {
						$sectionContent .= '<tr>'.LF;
						$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => 'list_type', 'value' => $item['list_type'], 'itemType' => 'content']) .'" target="_blank">' . $item['list_type'] . '</a></td>'.LF;
						$sectionContent .=   '<td>'. $item['count_use'] .'</td>'.LF;
						$pidsLinked = [];
						foreach (explode(', ', $item['pids']) as $i => $pid)  {
							$pidsLinked[] =     '<a href="'. $this->urlSite($pid) .'" target="_blank">'. $pid .'</a>'.LF;
						}
						$sectionContent .=   '<td class="pids">'. implode(' ', $pidsLinked) .'</td>'.LF;
						$sectionContent .= '</tr>'.LF;
					}
					
					$sectionContent .= '</table>'.LF;
					$this->setOutputContent('summary', self::CE_PLUGIN, $sectionContent);
					$this->data[self::CE_PLUGIN] = $data;
					$this->debug[self::CE_PLUGIN]['sql'] = $query->queryString;
				}
			}




			// SECTION: CType
	
			if (in_array(self::CE_CTYPE, $this->config['makeSummaryFor']))	{
			
				$query = $this->db->prepare("
					SELECT ce.CType,
					   	COUNT(ce.uid) AS count_use,
					   	GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids
					FROM `tt_content` AS ce
						JOIN `pages` AS p  ON p.uid = ce.pid
					WHERE ce.CType != ''	
						AND NOT ce.deleted 		# AND NOT t.hidden
						AND NOT p.deleted
					GROUP BY ce.CType
				");
				$query->execute();
				$query->setFetchMode(\PDO::FETCH_ASSOC);
				$data = $query->fetchAll();


				if (is_array($data) && count($data))  {
					$sectionContent = '';
					
					// generate html output
					$sectionContent .= '<table class="item-types-summary  mono">'.LF;
					$sectionContent .=   '<tr>'.LF;
					$sectionContent .=      '<th>'. 'CType:' .'</th>'.LF;
					$sectionContent .=      '<th>'. 'count:' .'</th>'.LF;
					$sectionContent .=      '<th>'. 'pids:' .'</th>'.LF;
					$sectionContent .=   '</tr>'.LF;
					
					
					foreach ($data as $item) {
						$sectionContent .= '<tr>'.LF;
						$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => 'CType', 'value' => $item['CType'], 'itemType' => 'content']) .'" target="_blank">'. $item['CType'] .'</a></td>'.LF;
						$sectionContent .=   '<td>'. $item['count_use'] .'</td>'.LF;
						$pidsLinked = [];
						foreach (explode(', ', $item['pids']) as $i => $pid)  {
							$pidsLinked[] =     '<a href="'. $this->urlSite($pid) .'" target="_blank">'. $pid .'</a>'.LF;
						}
						$sectionContent .=   '<td class="pids">' . implode(' ', $pidsLinked) . '</td>'.LF;
						$sectionContent .= '</tr>'.LF;
					}
					
					$sectionContent .= '</table>'.LF;
					$this->setOutputContent('summary', self::CE_CTYPE, $sectionContent);
					$this->data[self::CE_CTYPE] = $data;
					$this->debug[self::CE_CTYPE]['sql'] = $query->queryString;
				}
			}



			// SECTION: Frames
	
			if (in_array(self::CE_FRAME, $this->config['makeSummaryFor']))	{

				if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE '{$this->TT_FRAME}'")->fetchAll())) {
					$query = $this->db->prepare("
						SELECT ce.{$this->TT_FRAME},
						   	COUNT(ce.uid) AS count_use,
						   	GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids
						FROM `tt_content` AS ce
							JOIN `pages` AS p  ON p.uid = ce.pid
						WHERE ce.{$this->TT_FRAME} != ''	
						AND NOT ce.deleted 		# AND NOT ce.hidden
							AND NOT p.deleted
						GROUP BY ce.{$this->TT_FRAME}
					");
					$query->execute();
					$query->setFetchMode(\PDO::FETCH_ASSOC);
					$data = $query->fetchAll();


					if (is_array($data) && count($data))  {
						$sectionContent = '';
						
						// generate html output
						$sectionContent .= '<table class="item-types-summary  mono">'.LF;
						$sectionContent .=   '<tr>'.LF;
						$sectionContent .=      '<th>'. $this->TT_FRAME.':' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'count:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'pids:' .'</th>'.LF;
						$sectionContent .=   '</tr>'.LF;
						
						
						foreach ($data as $item) {
							$sectionContent .= '<tr>'.LF;
							$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => $this->TT_FRAME, 'value' => $item[$this->TT_FRAME], 'itemType' => 'content']) .'" target="_blank">'. $item[$this->TT_FRAME] .'</a></td>'.LF;
							$sectionContent .=   '<td>'. $item['count_use'] .'</td>'.LF;
							$pidsLinked = [];
							foreach (explode(', ', $item['pids']) as $i => $pid)  {
								$pidsLinked[] =     '<a href="'. $this->urlSite($pid) .'" target="_blank">'. $pid .'</a>'.LF;
							}
							$sectionContent .=   '<td class="pids">'. implode(' ', $pidsLinked) .'</td>'.LF;
							$sectionContent .= '</tr>'.LF;
						}
						
						$sectionContent .= '</table>'.LF;
						$this->setOutputContent('summary', self::CE_FRAME, $sectionContent);
						$this->data[self::CE_FRAME] = $data;
						$this->debug[self::CE_FRAME]['sql'] = $query->queryString;
					}
				}
			}



			// SECTION: Image orient

			if (in_array(self::CE_IMAGEORIENT, $this->config['makeSummaryFor']))	{

				if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE 'imageorient'")->fetchAll())) {
					$query = $this->db->prepare("
						SELECT ce.imageorient,
						   	COUNT(ce.uid) AS count_use,
						   	GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids
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
					$query->setFetchMode(\PDO::FETCH_ASSOC);
					$data = $query->fetchAll();


					if (is_array($data) && count($data))  {
						$sectionContent = '';
						
						// generate html output
						$sectionContent .= '<table class="item-types-summary  mono">'.LF;
						$sectionContent .=   '<tr>'.LF;
						$sectionContent .=      '<th>'. 'imageorient:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'count:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'pids:' .'</th>'.LF;
						$sectionContent .=   '</tr>'.LF;
						
						
						foreach ($data as $item) {
							$sectionContent .= '<tr>'.LF;
							$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => 'imageorient', 'value' => $item['imageorient'], 'itemType' => 'content']) .'" target="_blank">'. $item['imageorient'] .'</a></td>'.LF;
							$sectionContent .=   '<td>'. $item['count_use'] .'</td>'.LF;
							$pidsLinked = [];
							foreach (explode(', ', $item['pids']) as $i => $pid)  {
								$pidsLinked[] =     '<a href="'. $this->urlSite($pid) .'" target="_blank">'. $pid .'</a>'.LF;
							}
							$sectionContent .=   '<td class="pids">'. implode(' ', $pidsLinked) .'</td>'.LF;
							$sectionContent .= '</tr>'.LF;
						}
		
						$sectionContent .= '</table>'.LF;
						$this->setOutputContent('summary', self::CE_IMAGEORIENT, $sectionContent);
						$this->data[self::CE_IMAGEORIENT] = $data;
						$this->debug[self::CE_IMAGEORIENT]['sql'] = $query->queryString;
					}
				}
			}



			// SECTION: Header layout

			if (in_array(self::CE_HEADERLAYOUT, $this->config['makeSummaryFor']))	{

				$query = $this->db->prepare("
					SELECT ce.header_layout,
						COUNT(ce.uid) AS count_use,
						GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids
					FROM `tt_content` AS ce
						JOIN `pages` AS p  ON p.uid = ce.pid
					WHERE ce.header_layout != ''	
					AND NOT ce.deleted 		# AND NOT t.hidden
						AND NOT p.deleted
					GROUP BY ce.header_layout
					"/* cast to integer workaround: */."
					ORDER BY (ce.header_layout * 1), ce.header_layout
				");
				$query->execute();
				$query->setFetchMode(\PDO::FETCH_ASSOC);
				$data = $query->fetchAll();
		

				if (is_array($data) && count($data))  {
					$sectionContent = '';
					
					// generate html output
					$sectionContent .= '<table class="item-types-summary  mono">'.LF;
					$sectionContent .=   '<tr>'.LF;
					$sectionContent .=      '<th>'. 'header_layout:' .'</th>'.LF;
					$sectionContent .=      '<th>'. 'count:' .'</th>'.LF;
					$sectionContent .=      '<th>'. 'pids:' .'</th>'.LF;
					$sectionContent .=   '</tr>'.LF;
	


					foreach ($data as $item) {
						$sectionContent .= '<tr>'.LF;
						$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => 'header_layout', 'value' => $item['header_layout'], 'itemType' => 'content', 'additionalWhere' => 'AND header != ""']) .'" target="_blank">'. $item['header_layout'] .'</a></td>'.LF;
						$sectionContent .=   '<td>'. $item['count_use'] .'</td>'.LF;
						$pidsLinked = [];
						foreach (explode(', ', $item['pids']) as $i => $pid)  {
							$pidsLinked[] =     '<a href="'. $this->urlSite($pid) .'" target="_blank">'. $pid .'</a>'.LF;
						}
						$sectionContent .=   '<td class="pids">'. implode(' ', $pidsLinked) .'</td>'.LF;
						$sectionContent .= '</tr>'.LF;
					}
					
					$sectionContent .= '</table>'.LF;
					$this->setOutputContent('summary', self::CE_HEADERLAYOUT, $sectionContent);
					$this->data[self::CE_HEADERLAYOUT] = $data;
					$this->debug[self::CE_HEADERLAYOUT]['sql'] = $query->queryString;
				}
			}



			// SECTION: TemplaVoila FCE DS

			if (in_array(self::CE_TEMPLAVOILA_DS, $this->config['makeSummaryFor']))	{

				if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE '{$this->TV_PREFIX}_ds'")->fetchAll())) {
				
					$query = $this->db->prepare("
						SELECT ce.{$this->TV_PREFIX}_ds,
							COUNT(ce.uid) AS count_use, 
							GROUP_CONCAT( DISTINCT ce.pid SEPARATOR ', ') AS pids
						FROM `tt_content` AS ce
							JOIN `pages` AS p ON p.uid = ce.pid
						WHERE ce.CType = '{$this->TV_CTYPE}'
							AND NOT ce.deleted 		# AND NOT ce.hidden 
							AND NOT p.deleted
						GROUP BY ce.{$this->TV_PREFIX}_ds
						ORDER BY ce.{$this->TV_PREFIX}_ds
					");
					$query->execute();
					$query->setFetchMode(\PDO::FETCH_ASSOC);
					$data = $query->fetchAll();


					if (is_array($data) && count($data))  {
						$sectionContent = '';
						
						// generate html output
						$sectionContent .= '<table class="item-types-summary  mono">'.LF;
						$sectionContent .=   '<tr>'.LF;
						$sectionContent .=      '<th>'. $this->TV_PREFIX.'_ds:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'count:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'pids:' .'</th>'.LF;
						$sectionContent .=   '</tr>'.LF;
						
						
						foreach ($data as $item) {
							$sectionContent .= '<tr>'.LF;
							$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => $this->TV_PREFIX.'_ds', 'value' => $item[$this->TV_PREFIX.'_ds'], 'itemType' => 'content']) .'" target="_blank">'. $item[$this->TV_PREFIX.'_ds'] .'</a></td>'.LF;
							$sectionContent .=   '<td>'. $item['count_use'] .'</td>'.LF;
							$pidsLinked = [];
							foreach (explode(', ', $item['pids']) as $i => $pid)  {
								$pidsLinked[] =     '<a href="'. $this->urlSite($pid) .'" target="_blank">'. $pid .'</a>'.LF;
							}
							$sectionContent .=   '<td class="pids">'. implode(' ', $pidsLinked) .'</td>'.LF;
							$sectionContent .= '</tr>'.LF;
						}
						
						$sectionContent .= '</table>'.LF;
						$this->setOutputContent('summary', self::CE_TEMPLAVOILA_DS, $sectionContent);
						$this->data[self::CE_TEMPLAVOILA_DS] = $data;
						$this->debug[self::CE_TEMPLAVOILA_DS]['sql'] = $query->queryString;
					}
				}
			}



			// SECTION: TemplaVoila FCE TO

			if (in_array(self::CE_TEMPLAVOILA_TO, $this->config['makeSummaryFor']))	{

				if (count($this->db->query("SHOW COLUMNS FROM `tt_content` LIKE '{$this->TV_PREFIX}_to'")->fetchAll())) {
				
					$query = $this->db->prepare("
						SELECT ce.{$this->TV_PREFIX}_to,
						   	COUNT(ce.uid) AS count_use, 
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
					$query->setFetchMode(\PDO::FETCH_ASSOC);
					$data = $query->fetchAll();


					if (is_array($data) && count($data))  {
						$sectionContent = '';
						
						// generate html output
						$sectionContent .= '<table class="item-types-summary  mono">'.LF;
						$sectionContent .=   '<tr>'.LF;
						$sectionContent .=      '<th>'. $this->TV_PREFIX.'_to:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'count:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'pids:' .'</th>'.LF;
						$sectionContent .=   '</tr>'.LF;
						
						
						foreach ($data as $item) {
							$sectionContent .= '<tr>'.LF;
							$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => $this->TV_PREFIX.'_to', 'value' => $item[$this->TV_PREFIX.'_to'], 'itemType' => 'content']) .'" target="_blank">'. $item[$this->TV_PREFIX.'_to'] .' <br> '. $item['to_title'] .'</a></td>'.LF;
							$sectionContent .=   '<td>'. $item['count_use'] .'</td>'.LF;
							$pidsLinked = [];
							foreach (explode(', ', $item['pids']) as $i => $pid)  {
								$pidsLinked[] =     '<a href="'. $this->urlSite($pid) .'" target="_blank">'. $pid .'</a>'.LF;
							}
							$sectionContent .=   '<td class="pids">'. implode(' ', $pidsLinked) .'</td>'.LF;
							$sectionContent .= '</tr>'.LF;
						}
						
						$sectionContent .= '</table>'.LF;
						$this->setOutputContent('summary', self::CE_TEMPLAVOILA_TO, $sectionContent);
						$this->data[self::CE_TEMPLAVOILA_TO] = $data;
						$this->debug[self::CE_TEMPLAVOILA_TO]['sql'] = $query->queryString;
					}
				}
			}






			// SECTION: PAGE - TemplaVoila DS

			if (in_array(self::PAGE_TEMPLAVOILA_DS, $this->config['makeSummaryFor']))	{

				if (count($this->db->query("SHOW COLUMNS FROM `pages` LIKE '{$this->TV_PREFIX}_ds'")->fetchAll())) {

					$query = $this->db->prepare("
						SELECT p.{$this->TV_PREFIX}_ds, p.{$this->TV_PREFIX}_next_ds,
						   	COUNT(p.uid) AS count_use,
							GROUP_CONCAT( DISTINCT p.uid SEPARATOR ', ') AS pids
						FROM `pages` p
						WHERE ( p.{$this->TV_PREFIX}_ds != ''  ||  p.{$this->TV_PREFIX}_next_ds != '' )  
							AND NOT p.deleted
						GROUP BY p.{$this->TV_PREFIX}_ds, p.{$this->TV_PREFIX}_next_ds
						ORDER BY p.{$this->TV_PREFIX}_ds, p.{$this->TV_PREFIX}_next_ds
					");
					$query->execute();
					$query->setFetchMode(\PDO::FETCH_ASSOC);
					$data = $query->fetchAll();


					if (is_array($data) && count($data))  {
						$sectionContent = '';
						
						// generate html output
						$sectionContent .= '<table class="item-types-summary  mono">'.LF;
						$sectionContent .=   '<tr>'.LF;
						$sectionContent .=      '<th>'. $this->TV_PREFIX.'_ds:' .'</th>'.LF;
						$sectionContent .=      '<th>'. $this->TV_PREFIX.'_next_ds:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'count:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'page records: <i>(click = details)</i>' .'</th>'.LF;
						$sectionContent .=   '</tr>'.LF;
						
						
						foreach ($data as $item) {
							$sectionContent .= '<tr>'.LF;
							$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => $this->TV_PREFIX.'_ds', 'value' => $item[$this->TV_PREFIX.'_ds'], 'itemType' => 'page', 'additionalWhere' => 'OR '.$this->TV_PREFIX.'_next_ds = '.$item[$this->TV_PREFIX.'_ds']]) .'" target="_blank">'. $item[$this->TV_PREFIX.'_ds'] .'</a></td>'.LF;
							$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => $this->TV_PREFIX.'_ds', 'value' => $item[$this->TV_PREFIX.'_ds'], 'itemType' => 'page', 'additionalWhere' => 'OR '.$this->TV_PREFIX.'_next_ds = '.$item[$this->TV_PREFIX.'_ds']]) .'" target="_blank">'. $item[$this->TV_PREFIX.'_next_ds'] .'</a></td>'.LF;
							$sectionContent .=   '<td>'. $item['count_use'] .'</td>'.LF;
							$pidsLinked = [];
							foreach (explode(', ', $item['pids']) as $i => $pid)  {
								$pidsLinked[] =     '<a href="'. $this->urlAction('pageDetails', ['recordUid' => $pid]) .'" target="_blank">'. $pid .'</a>'.LF;
							}
							$sectionContent .=   '<td class="pids">'. implode(' ', $pidsLinked) .'</td>'.LF;
							$sectionContent .= '</tr>'.LF;
						}
						
						$sectionContent .= '</table>'.LF;
						$this->setOutputContent('summary', self::PAGE_TEMPLAVOILA_DS, $sectionContent);
						$this->data[self::PAGE_TEMPLAVOILA_DS] = $data;
						$this->debug[self::PAGE_TEMPLAVOILA_DS]['sql'] = $query->queryString;
					}
				}
			}



			// SECTION: PAGE - TemplaVoila TO

			if (in_array(self::PAGE_TEMPLAVOILA_TO, $this->config['makeSummaryFor']))	{

				if (count($this->db->query("SHOW COLUMNS FROM `pages` LIKE '{$this->TV_PREFIX}_to'")->fetchAll())) {
				
					$query = $this->db->prepare("
						SELECT p.{$this->TV_PREFIX}_to, p.{$this->TV_PREFIX}_next_to,
						   	COUNT(p.uid) AS count_use, 
							GROUP_CONCAT( DISTINCT p.uid SEPARATOR ', ') AS pids,
						   	tvto.title AS to_title
						FROM `pages` p
							JOIN `{$this->TV_PREFIX}_tmplobj`  tvto  ON tvto.uid = p.{$this->TV_PREFIX}_to
						WHERE ( p.{$this->TV_PREFIX}_to != ''  ||  p.{$this->TV_PREFIX}_next_to != '' )  
							AND NOT p.deleted 		# AND NOT t.hidden 
							AND NOT tvto.deleted
						GROUP BY p.{$this->TV_PREFIX}_to, p.{$this->TV_PREFIX}_next_to
						ORDER BY p.{$this->TV_PREFIX}_to, p.{$this->TV_PREFIX}_next_to
					");
					$query->execute();
					$query->setFetchMode(\PDO::FETCH_ASSOC);
					$data = $query->fetchAll();


					if (is_array($data) && count($data))  {
						$sectionContent = '';
						
						// generate html output
						$sectionContent .= '<table class="item-types-summary  mono">'.LF;
						$sectionContent .=   '<tr>'.LF;
						$sectionContent .=      '<th>'. $this->TV_PREFIX.'_to:' .'</th>'.LF;
						$sectionContent .=      '<th>'. $this->TV_PREFIX.'_next_to:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'count:' .'</th>'.LF;
						$sectionContent .=      '<th>'. 'page records: <i>(click = details)</i>' .'</th>'.LF;
						$sectionContent .=   '</tr>'.LF;
						
						
						foreach ($data as $item) {
							$sectionContent .= '<tr>'.LF;
							$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => $this->TV_PREFIX.'_to', 'value' => $item[$this->TV_PREFIX.'_to'], 'itemType' => 'page', 'additionalWhere' => 'OR '.$this->TV_PREFIX.'_next_to = '.$item[$this->TV_PREFIX.'_to']]) .'" target="_blank">'. $item[$this->TV_PREFIX.'_to'] .'<br>'. $item['to_title'] .'</a></td>'.LF;
							$sectionContent .=   '<td><a href="'. $this->urlAction('analyseRootline', ['groupKey' => $this->TV_PREFIX.'_to', 'value' => $item[$this->TV_PREFIX.'_to'], 'itemType' => 'page', 'additionalWhere' => 'OR '.$this->TV_PREFIX.'_next_to = '.$item[$this->TV_PREFIX.'_to']]) .'" target="_blank">'. $item[$this->TV_PREFIX.'_next_to'] .'</a></td>'.LF;
							$sectionContent .=   '<td>'. $item['count_use'] .'</td>'.LF;
							$pidsLinked = [];
							foreach (explode(', ', $item['pids']) as $i => $pid)  {
								$pidsLinked[] =     '<a href="'. $this->urlAction('pageDetails', ['recordUid' => $pid]) .'" target="_blank">'. $pid .'</a>'.LF;
							}
							$sectionContent .=   '<td class="pids">'. implode(' ', $pidsLinked) .'</td>'.LF;
							$sectionContent .= '</tr>'.LF;
						}
						
						$sectionContent .= '</table>'.LF;
						$this->setOutputContent('summary', self::PAGE_TEMPLAVOILA_TO, $sectionContent);
						$this->data[self::PAGE_TEMPLAVOILA_TO] = $data;
						$this->debug[self::PAGE_TEMPLAVOILA_TO]['sql'] = $query->queryString;
					}
				}
			}
		} catch(\PDOException $e) {
			$this->messages[] = "SQL/PDO Error: " . $e->getMessage();
		}
	}


	protected function urlSite($pageUid)	{
		return rtrim($this->config['baseDomain'], '/') . '/?id=' . $pageUid;
	}


	protected function urlAction($action, $params = [])	{
		// if run in a context, from some other code, probably before our params we need that run context's url
		$urlActionBase = $this->config['urlActionBase'];
		$_l = parse_url($urlActionBase);
		$url = $urlActionBase . ($_l['query']?'&':'?');

		$params = ['action' => $action] + $params;

		$paramPairs = [];
		foreach ($params as $param => $value)	{
			$paramPairs[] = $param .'='. urlencode($value);
		}
		return $url . implode('&', $paramPairs);
	}


	protected function urlStartScreen()	{
		return $this->config['urlLinkBase'] ?? $_SERVER['SCRIPT_NAME'];
	}


	protected function action_analyseRootline()   {
	    // init args
        $availableItemTypes = ['page', 'content'];
        $itemType = in_array($_GET['itemType'], $availableItemTypes) ? $_GET['itemType'] : '';
	    if (!$itemType)	{
	    	$this->setOutputContent('analyse', 'rootline', '<h4>No Item Type passed! (must be either `content` or `page`)</h4>');
	    	return;
		}

        $availableGroupKeys = [
        	'page' => [$this->TV_PREFIX.'_ds', $this->TV_PREFIX.'_to', 'tx_fed_page_controller_action', 'tx_fed_page_controller_action_sub'],
        	'content' => ['CType', 'list_type', $this->TV_PREFIX.'_ds', $this->TV_PREFIX.'_to', $this->TT_FRAME, 'imageorient', 'header_layout'],
		];
	    $tableFieldGroupKey = in_array($_GET['groupKey'], $availableGroupKeys[$itemType]) ? $_GET['groupKey'] : 'INVALID_GROUP_FIELD_KEY';		// will cause sql error, and ok
	    $additionalWhere_custom = $_GET['additionalWhere'];
	    $additionalWhere = '';

	    // no input cleanup, no need to secure script against yourself
	    $value = $_GET['value'];




		$sectionHeader = '<h3>Item type: <b>'.strtoupper($itemType).'</b></h3>';
	    $sectionContent = '';
	    $pidsContainingSuchItems = [];
	    // additional fields to read from database when building rootline items
	    $addFieldsSelect = [];



	    switch ($itemType)	{
			case 'content':
				$sectionHeader .= '<h2><i>' . htmlspecialchars($tableFieldGroupKey) . ' = ' . htmlspecialchars($value) . '</i>'
									. ($additionalWhere_custom ? '<br><i>' . $additionalWhere_custom . '</i>' : '')
									. '</h2>';
				$sectionHeader .= '<h4><i>Look up the tree for visibility of grandparents of pages containing these items</i></h4>';
				$sectionHeader .= '<p class="small"><i>If all of these rootlines contains unavailable pages on any level, it may mean that this content type is not available to public anymore.<br>'
									. 'Remember they could still be referenced somewhere in typoscript, fluid templates or other extensions.</i></p>';

	    		// collect all pids with records with such values
				try {
					if ($tableFieldGroupKey == $this->TV_PREFIX.'_ds') {
						// filter contents with stored ds but are edited and not anymore of type fce
						$additionalWhere = ' AND CType = "'.$this->TV_CTYPE.'"';
					}
					if ($tableFieldGroupKey == 'imageorient') {
						// filter contents with stored ds but are edited and not anymore of type fce
						$additionalWhere = ' AND CType = "textpic"';
					}
					$query = $this->db->prepare("
						SELECT ce.pid,
							GROUP_CONCAT( DISTINCT ce.uid SEPARATOR ', ') AS uids
						FROM `tt_content`  ce
						WHERE ( ce.{$tableFieldGroupKey} = {$this->db->quote($value)}
								$additionalWhere
								$additionalWhere_custom
							)  AND NOT ce.deleted 		# AND NOT ce.hidden
						GROUP BY ce.pid
					");
					$this->debug['rootlineAnalyse']['sql'] = $query->queryString;
					$query->execute();
					$query->setFetchMode(\PDO::FETCH_ASSOC);
					$pidsContainingSuchItems = $query->fetchAll();
				} catch(\PDOException $e) {
					$this->messages[] = "Error: " . $e->getMessage();
				}
				break;



			case 'page':
				$sectionHeader .= '<h2><i>' . htmlspecialchars($tableFieldGroupKey) . ' = ' . htmlspecialchars($value) . '</i>'
									. ($additionalWhere_custom ? '<br><i>' . $additionalWhere_custom . '</i>' : '')
									. '</h2>';
				$sectionHeader .= '<h4><i>Look up the tree for visibility of grandparents of pages with these values</i></h4>';
				$sectionHeader .= '<p class="small"><i>If all of these rootlines contains unavailable pages on any level, it means that probably these values are not available to public anymore.</i></p>';

	    		// collect all pages with such values
				try {
					$query = $this->db->prepare("
						SELECT p.uid  AS pid
						FROM `pages`  p
						WHERE ( p.{$tableFieldGroupKey} = {$this->db->quote($value)}
								$additionalWhere
								$additionalWhere_custom
							) AND NOT p.deleted
					");
					$this->debug['rootlineAnalyse']['sql'] = $query->queryString;
					$query->execute();
					$query->setFetchMode(\PDO::FETCH_ASSOC);
					$pidsContainingSuchItems = $query->fetchAll();
				} catch(\PDOException $e) {
					$this->messages[] = "Error: " . $e->getMessage();
				}


				if ($tableFieldGroupKey === $this->TV_PREFIX.'_ds') {
					$addFieldsSelect[] = $this->TV_PREFIX.'_ds';
					$addFieldsSelect[] = $this->TV_PREFIX.'_next_ds';
				}
				if ($tableFieldGroupKey === $this->TV_PREFIX.'_to') {
					$addFieldsSelect[] = $this->TV_PREFIX.'_to';
					$addFieldsSelect[] = $this->TV_PREFIX.'_next_to';
				}
				
				break;
		}



        
		
		$typeIsVisibleAtLeastOnce = false;
		
        // iterate these pids and build their rootlines
        foreach ($pidsContainingSuchItems as $i => $item)  {
		    $typeIsAvailableThroughThisRootline = true;
		    
            $rootline = [];
            $this->buildUpRootline($rootline, $item['pid'], $addFieldsSelect);
            $pidsContainingSuchItems[$i]['rootline'] = array_reverse($rootline);
            $rootlineBreadcrumb = '';
            // draw rootline breadcrumbs
            foreach ($pidsContainingSuchItems[$i]['rootline'] as $_r => $page) {
            	$rootline_item_class = '';
            	// check if previous item on the same level was the same page
				if ($page['uid']  &&  is_array($pidsContainingSuchItems[$i - 1])  &&  $page['uid'] === $pidsContainingSuchItems[$i - 1]['rootline'][$_r]['uid'])	{
					// the same page - mark with class to dim it for readability
					$rootline_item_class = ' repeats-previous-row-item';
				}
                $rootlineBreadcrumb .= '<div class="rootline_item' 
                    . ($page['hidden'] ? ' hidden' : '')
                    . ($page['deleted'] ? ' deleted' : '')
					. $rootline_item_class
                    . '">'
                        . '<a href="' . $this->urlSite($page['uid']) . '" class="page-title" target="_blank">'
                        	. $page['title']
                        . '</a>'
                        . '<span class="page-uid">
									[ <a href="'.$this->urlAction('pageDetails', ['recordUid' => intval($page['uid'])]) . '" target="_blank">'. $page['uid'] .'</a> ]
								</span>'
							. '<span class="page-properties">'

								. (function($pageRow) use ($itemType, $tableFieldGroupKey) {
										// render three types of field-values:
										$showFields_values = [];                // a) shown as 'key: val' - if has value.
										$showFields_valuesAlsoNegative = [];    // b) shown as 'key: val' - always.
										$showFields_flagOnly = [];              // c) shown as label-flag, if has value > 0

										if ($itemType === 'content')	{
											$showFields_flagOnly = ['hidden', 'deleted'];
                                        }

										if ($itemType === 'page')	{
											$showFields_flagOnly = ['hidden', 'deleted'];
											if ($tableFieldGroupKey === $this->TV_PREFIX.'_ds') {
												$showFields_values[] = $this->TV_PREFIX.'_ds';
												$showFields_values[] = $this->TV_PREFIX.'_next_ds';
											}
											if ($tableFieldGroupKey === $this->TV_PREFIX.'_to') {
												$showFields_values[] = $this->TV_PREFIX.'_to';
												$showFields_values[] = $this->TV_PREFIX.'_next_to';
											}
										}


										$propTmpl = '<div class="@CLASS@">@VALUE@</div>';
										$class = 'smaller';
										$output = '';

                            			foreach ($showFields_flagOnly as $fieldName) {
                            				if ($pageRow[$fieldName])	{
												$output .= str_replace(['@CLASS@', '@VALUE@'], [$class, $fieldName], $propTmpl);
											}
										}
                            			foreach ($showFields_values as $fieldName) {
                            				if ($pageRow[$fieldName])	{
												$output .= str_replace(['@CLASS@', '@VALUE@'], [$class, $fieldName.': '.$pageRow[$fieldName]], $propTmpl);
                                            }
										}

										return $output;
								}) ($page)
							. '</span>'
                . '</div>';
                
                if ($page['hidden'] || $page['deleted'])    {
                    $typeIsAvailableThroughThisRootline = false;
                }
            }
            // collect this info 
            if (!$typeIsVisibleAtLeastOnce && $typeIsAvailableThroughThisRootline)    {
                $typeIsVisibleAtLeastOnce = true;
            }

            // mark inactive only for content analyse - it doesn't make much sense for pages, since we don't crawl subpages to check if anything ie. inherits, we only collect pages with values set
            $sectionContent .= '<div class="rootline_breadcrumb' .($typeIsAvailableThroughThisRootline  ||  $itemType === 'page'  ? '' : ' inactive'). '">';
            $sectionContent .= $rootlineBreadcrumb ? $rootlineBreadcrumb : '<div class="rootline_item warning">No pagetree rootline found for this path. No such pages in database.<br> Was removed manually? Looking for pid / page uid = ' . $item['pid'] . '</div>';
            $itemUids = [];
            foreach ($item['uids'] ? explode(',', $item['uids']) : [] as $ttcontentUid)   {
            	$ttcontentRow = ['uid' => $ttcontentUid];
            	if (1)	{
            		// get ttcontent essential details
            		$ttcontentRow = $this->getRecord('uid, hidden', 'tt_content', $ttcontentUid);
				}
            	
                $itemUids[] = '<a href="'.$this->urlAction('contentDetails', ['recordUid' => intval($ttcontentUid)]) . '" class="'.($ttcontentRow['hidden'] ? 'ce-hidden' : '').'">' . intval($ttcontentUid) . '</a>';
            }
            $sectionContent .= ($item['uids'] ? '<div class="these-items"><span class="records">tt_content uids: '.implode(', ', $itemUids).'</span></div>' : '');
            $sectionContent .= '<br class="clear"> </div>';
        }
        
	    
	    $this->setOutputContent('analyse', 'rootline', $sectionHeader
            . ($typeIsVisibleAtLeastOnce ? '' : '<p>- <b>If you don\'t see any errors above, it seems that this content type/page field value is not present in any visible pagetree. You can consider disabling/removing this functionality (if it\'s not just sql error)</b></p>')
            . $sectionContent);
    }


    
    protected function action_contentDetails()   {
	    // init args
        $uid = intval($_GET['recordUid']);

	    $sectionHeader = '<h4>Record view <i>(* some fields may be preparsed for readability)</i></h4>';
	    $sectionContent = '';

	    $row = $this->getRecord('*', 'tt_content', $uid);


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
                    $fieldMarked = (bool) $value;
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
	                	$fieldMarked = true;
                        $fieldProcessed = true;
                        $dom = new \DOMDocument;
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
        
	    
	    $this->outputContent['details__content'] = $sectionHeader
            . $sectionContent;
    }



    protected function action_pageDetails()   {
	    // init args
        $uid = intval($_GET['recordUid']);

	    $sectionHeader = '<h4>Record view <i>(* some fields may be preparsed for readability)</i></h4>';
	    $sectionContent = '<a href="' . $this->urlSite($uid) . '" target="_blank">' . $this->urlSite($uid) . '</a><br><br>';

	    $row = [];
        try {
			$query = $this->db->prepare("
				SELECT p.*
				FROM `pages` AS p
				WHERE p.uid = {$uid}
			");
			$query->execute();
			$query->setFetchMode(\PDO::FETCH_ASSOC);
			$row = $query->fetchAll()[0] ?: [];
		} catch (\PDOException $e) {
			$this->messages[] = "Error: " . $e->getMessage();
		}
		
        $sectionContent .= '<table class="mono">';
		foreach ($row as $fieldname => $value)    {
            $fieldMarked = false;
            $fieldProcessed = false;
            $finalValue = $value;

		    switch ($fieldname) {
                case 'uid':
                case 'pid':
                case 'title':
                case 'sys_language_uid':
                case 'doktype':
                case 'tx_wtv2flux_migrated':
                    $fieldMarked = true;
                    break;
                case $this->TV_PREFIX.'_ds':
                case $this->TV_PREFIX.'_next_ds':
                case $this->TV_PREFIX.'_to':
                case $this->TV_PREFIX.'_next_to':
                case 'tx_fed_page_controller_action':
                case 'tx_fed_page_controller_action_sub':
                    $fieldMarked = (bool) $value;
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
                case $this->TV_PREFIX.'_flex':
                case 'tx_fed_page_flexform':
                case 'tx_fed_page_flexform_sub':
                    if ($value) {
                        $fieldProcessed = true;
                        $dom = new \DOMDocument;
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
            $classesRow = [];
            if ($fieldMarked)
            	$classesRow[] = 'field_marked';
            if (preg_match('/^zzz/', $fieldname))
            	$classesRow[] = 'field_dim';
            	
		    $sectionContent .= "<tr" . (count($classesRow) ? ' class="'.implode(' ', $classesRow).'"' : '') . "><td>$finalFieldname</td><td>" . $finalValue . "</td>";
        }
        $sectionContent .= '</table>';
        
	    
	    $this->outputContent['details__page'] = $sectionHeader
            . $sectionContent;
    }
    
	/**
     * Build rootline array up to page top root
	 * @param array $rootline
	 * @param int $uid
	 * @return array
	 */
    protected function buildUpRootline(&$rootline, $uid, $additionalFields = [])    {
    	$uid = intval($uid);

	    try {
			$query = $this->db->prepare("
				SELECT uid, pid, deleted, hidden, title". ($additionalFields ? ', '.implode(', ', $additionalFields) : '' )."
				FROM `pages`
				WHERE uid = {$uid}
			    LIMIT 1
			");
			$query->execute();
			$query->setFetchMode(\PDO::FETCH_ASSOC);
			$pageRow = $query->fetch();
			
			// if valid row - collect
            if (is_array($pageRow)  &&  count($pageRow))    {
                $rootline[] = $pageRow;
            }

            // if has parent, go for him
            if ($pageRow['pid'])    {
                $this->buildUpRootline($rootline, $pageRow['pid'], $additionalFields);
            }

		} catch(\PDOException $e) {
			$this->messages[] = "Error: " . $e->getMessage();
		}
		return $rootline;
    }


    /**
     * @param string $fields
     * @param string string $table
     * @param int $uid
     * @return array|mixed|void
     */
    protected function getRecord($fields, $table, $uid)	{
		try {
			$query = $this->db->prepare("
				SELECT {$fields}
				FROM `{$table}`
				WHERE uid = {$uid}
			");
			$query->execute();
			$query->setFetchMode(\PDO::FETCH_ASSOC);
			return $query->fetchAll()[0] ?: [];
		} catch (\PDOException $e) {
			$this->messages[] = "Error: " . $e->getMessage();
		}
    }

	/**
	 * Build final page content from prepared parts
     * @return string
	 */
	public function handleRequestsAndCompileContent()   {
        
        $output = '';

        if ($_GET['action'])	{
			switch ($_GET['action'])    {

				case 'analyseRootline':
					$this->action_analyseRootline();
					$output .= '<h1 class="mono"><a href="'.$this->urlStartScreen().'">ContentSummary - Analyse rootline</a></h1>'.LF;
					$output .= $this->getOutputContent('analyse', 'rootline');
					break;
					
				case 'contentDetails':
					$this->action_contentDetails();
					$output .= '<h1 class="mono"><a href="'.$this->urlStartScreen().'">ContentSummary - Content record details</a></h1>'.LF;
					$output .= $this->getOutputContent('details', 'content');
					break;
				
				case 'pageDetails':
					$this->action_pageDetails();
					$output .= '<h1 class="mono"><a href="'.$this->urlStartScreen().'">ContentSummary - Page record details</a></h1>'.LF;
					$output .= $this->getOutputContent('details', 'page');
					break;

				case 'downloadCsv':
					$this->collectSummaryData();
					$this->action_downloadCsv();
					exit;

				default:
					return 'ACTION NOT SUPPORTED';
			}
        }
        else	{
			$this->collectSummaryData();
			
			$output .= '<h1 class="mono"><a href="'.$this->urlStartScreen().'">ContentSummary - Record types used</a></h1>'.LF; 
			$output .= '###PLACEHOLDER_MESSAGE###';
			$output .= '###PLACEHOLDER_DOWNLOAD_CSV###';

			if (in_array(self::CE_PLUGIN, $this->config['makeSummaryFor']))   {
				$output .= '<h2 class="mono">tt_content - Plugin types:</h2>'.LF;
				$output .= $this->getOutputContent('summary', self::CE_PLUGIN);
			}
			
			if (in_array(self::CE_CTYPE, $this->config['makeSummaryFor']))   {
				$output .= '<h2 class="mono">tt_content - CTypes:</h2>'.LF;
				$output .= $this->getOutputContent('summary', self::CE_CTYPE);
			}
			
			if (in_array(self::CE_FRAME, $this->config['makeSummaryFor']))   {
				$output .= '<h2 class="mono">tt_content - Frames:</h2>'.LF;
				$output .= $this->getOutputContent('summary', self::CE_FRAME);
			}
			
			if (in_array(self::CE_IMAGEORIENT, $this->config['makeSummaryFor']))   {
				$output .= '<h2 class="mono">tt_content - Image orient:</h2>'.LF;
				$output .= $this->getOutputContent('summary', self::CE_IMAGEORIENT);
			}
			
			if (in_array(self::CE_HEADERLAYOUT, $this->config['makeSummaryFor']))	{
				$output .= '<h2 class="mono">tt_content - Header layout:</h2>'.LF;
				$output .= $this->getOutputContent('summary', self::CE_HEADERLAYOUT);
			}
			
			if (in_array(self::CE_TEMPLAVOILA_DS, $this->config['makeSummaryFor']))   {
				$output .= '<h2 class="mono">tt_content - Templavoila FCE DS:</h2>'.LF;
				$output .= $this->getOutputContent('summary', self::CE_TEMPLAVOILA_DS);
			}
			
			if (in_array(self::CE_TEMPLAVOILA_TO, $this->config['makeSummaryFor']))   {
				$output .= '<h2 class="mono">tt_content - Templavoila FCE TO:</h2>'.LF;
				$output .= $this->getOutputContent('summary', self::CE_TEMPLAVOILA_TO);
			}
			
			if (in_array(self::PAGE_TEMPLAVOILA_DS, $this->config['makeSummaryFor']))   {
				$output .= '<h2 class="mono">pages - Templavoila DS:</h2>'.LF;
				$output .= '<p title="I really couldn\'t find better solution for that query"><i>(grouped by pairs _ds + _next_ds)</i></p>'.LF;
				$output .= $this->getOutputContent('summary', self::PAGE_TEMPLAVOILA_DS);
			}
			
			if (in_array(self::PAGE_TEMPLAVOILA_TO, $this->config['makeSummaryFor']))   {
				$output .= '<h2 class="mono">pages - Templavoila TO:</h2>'.LF;
				$output .= '<p><i>(grouped by pairs _to + _next_to)</i></p>'.LF;
				$output .= $this->getOutputContent('summary', self::PAGE_TEMPLAVOILA_TO);
			}
		}
		
        
        return $output;
    }


    /**
	 * Return previously generated output content for given item, optionally with debug info,
     * @param $section_prefix
     * @param $itemId
     * @return string
     */
	protected function getOutputContent($section_prefix, $itemId)  {

		return $this->outputContent[$section_prefix . '__' . $itemId]
			. ($this->config['debug']  ?  '<pre>'.$this->debug[$itemId]['sql'].'</pre>'  :  '');
	}

    /**
     * Store generated output content for item
     * @param $section_prefix
     * @param $itemId
     * @param $value
     * @return string
     */
	protected function setOutputContent($section_prefix, $itemId, $value)  {
		$this->outputContent[$section_prefix . '__' . $itemId] = $value;
	}

	
	protected function databaseConnect()    {
		try {
		    $this->db = new \PDO("mysql:host={$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host']};dbname={$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname']}",
			    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'],
			    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password']);
			// set the PDO error mode to exception
			$this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		} catch(\PDOException $e) {
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
	        '###PLACEHOLDER_DOWNLOAD_CSV###' => '<p>[ <a href="'. $this->urlAction('downloadCsv') .'" target="_blank">DOWNLOAD CSV</a> ]</p>',
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
            '###PLACEHOLDER_DOWNLOAD_CSV###',
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

	    	$csv = $this->buildCsv();
	    	
	    	$savePath = $this->config['fileDumpPath'] . 'content_summary-'.date('Ymd-His').'.csv';
			if (false === file_put_contents($savePath, $csv)) {
				$this->messages[] = 'Autosave CSV failed! Can\'t write file to path: ' . $savePath;
			}
		}
	}


	/**
	 * Build CSV from collected info
	 * @return string|mixed
	 */
	public function buildCsv() {

		// open buffer
		$csvHandle = fopen('php://temp/maxmemory:'. (5*1024*1024), 'r+');
		// prepare data array be in proper shape


		// list_type
		if (is_array($this->data[self::CE_PLUGIN])  &&  count($this->data[self::CE_PLUGIN]))    {
			// header line
			fputcsv($csvHandle, ['List types / plugins:', 'count_use:', 'pids:']);
			// must reparse the array, order of columns is mixed/ indexes alphabetically, so
			foreach ($this->data[self::CE_PLUGIN] as $row)  {
				fputcsv($csvHandle, [$row['list_type'], $row['count_use'], $row['pids']]);
			}
		}


		// CType
		if (is_array($this->data[self::CE_CTYPE])  &&  count($this->data[self::CE_CTYPE]))    {
			// leave 4 empty rows
			for ($i=0; $i<=3; $i++)		fputcsv($csvHandle, []);

			fputcsv($csvHandle, ['CTypes:', 'count_use:', 'pids:']);
			foreach ($this->data[self::CE_CTYPE] as $row)  {
				fputcsv($csvHandle, [$row['CType'], $row['count_use'], $row['pids']]);
			}
		}
		
		
		// Frames
		if (is_array($this->data[self::CE_FRAME])  &&  count($this->data[self::CE_FRAME]))    {
			// leave 4 empty rows
			for ($i=0; $i<=3; $i++)		fputcsv($csvHandle, []);

			fputcsv($csvHandle, ['CSC/FSC Frames', 'count_use:', 'pids:']);
			foreach ($this->data[self::CE_FRAME] as $row)  {
				fputcsv($csvHandle, [
						$row[$this->TT_FRAME],
						$row['count_use'],
						$row['pids']
				]);
			}
		}
		
		
		// Image orient
		if (is_array($this->data[self::CE_IMAGEORIENT])  &&  count($this->data[self::CE_IMAGEORIENT]))    {
			// leave 4 empty rows
			for ($i=0; $i<=3; $i++)		fputcsv($csvHandle, []);

			fputcsv($csvHandle, ['tt_content: Image orient', 'count_use:', 'pids:']);
			foreach ($this->data[self::CE_IMAGEORIENT] as $row)  {
				fputcsv($csvHandle, [
						$row['imageorient'],
						$row['count_use'],
						$row['pids']
				]);
			}
		}
		
		
		// Header layout
		if (is_array($this->data[self::CE_HEADERLAYOUT])  &&  count($this->data[self::CE_HEADERLAYOUT]))    {
			// leave 4 empty rows
			for ($i=0; $i<=3; $i++)		fputcsv($csvHandle, []);

			fputcsv($csvHandle, ['tt_content: Header layout', 'count_use:', 'pids:']);
			foreach ($this->data[self::CE_HEADERLAYOUT] as $row)  {
				fputcsv($csvHandle, [
						$row['header_layout'],
						$row['count_use'],
						$row['pids']
				]);
			}
		}
		
		
		// TV FCE DS
		if (is_array($this->data[self::CE_TEMPLAVOILA_DS])  &&  count($this->data[self::CE_TEMPLAVOILA_DS]))    {
			// leave 4 empty rows
			for ($i=0; $i<=3; $i++)		fputcsv($csvHandle, []);
			
			fputcsv($csvHandle, ['tt_content: TV FCE DSs:', 'count_use:', 'pids:']);
			foreach ($this->data[self::CE_TEMPLAVOILA_DS] as $row)  {
				fputcsv($csvHandle, [
						$row[$this->TV_PREFIX.'_ds'],
						$row['count_use'],
						$row['pids']
				]);
			}
		}
		
		
		// TV FCE TO
		if (is_array($this->data[self::CE_TEMPLAVOILA_TO])  &&  count($this->data[self::CE_TEMPLAVOILA_TO]))    {
			// leave 4 empty rows
			for ($i=0; $i<=3; $i++)		fputcsv($csvHandle, []);
			
			fputcsv($csvHandle, ['tt_content: TV FCE TOs:', 'count_use:', 'pids:']);
			foreach ($this->data[self::CE_TEMPLAVOILA_TO] as $row)  {
				fputcsv($csvHandle, [
						$row[$this->TV_PREFIX.'_to'] . ' / '. $row['to_title'],
						$row['count_use'],
						$row['pids']
				]);
			}
		}
		
		
		// PAGE - TV DS
		if (is_array($this->data[self::PAGE_TEMPLAVOILA_DS])  &&  count($this->data[self::PAGE_TEMPLAVOILA_DS]))    {
			// leave 4 empty rows
			for ($i=0; $i<=3; $i++)		fputcsv($csvHandle, []);
			
			fputcsv($csvHandle, ['pages: DS + next_DS', 'count_use:', 'pages:']);
			foreach ($this->data[self::PAGE_TEMPLAVOILA_DS] as $row)  {
				fputcsv($csvHandle, [
						'DS: '. $row[$this->TV_PREFIX.'_ds'] .LF
							.'next_DS: '. $row[$this->TV_PREFIX.'_next_ds'],
						$row['count_use'],
						$row['pids']
				]);
			}
		}
		
		
		// PAGE - TV TO
		if (is_array($this->data[self::PAGE_TEMPLAVOILA_TO])  &&  count($this->data[self::PAGE_TEMPLAVOILA_TO]))    {
			// leave 4 empty rows
			for ($i=0; $i<=3; $i++)		fputcsv($csvHandle, []);
			
			fputcsv($csvHandle, ['tt_content: TO + next_TO:', 'count_use:', 'pages:']);
			foreach ($this->data[self::PAGE_TEMPLAVOILA_TO] as $row)  {
				fputcsv($csvHandle, [
						'TO: '. $row[$this->TV_PREFIX.'_to'] .' / '. $row['to_title'] .LF
							.'next_TO: '. $row[$this->TV_PREFIX.'_next_to'],
						$row['count_use'],
						$row['pids']
				]);
			}
		}




		// catch streamed output from buffer 
		rewind($csvHandle);
		return stream_get_contents($csvHandle);
	}




	/**
	 * Content disposition / download start / send file to browser 
	 */
	public function action_downloadCsv() {

		// get data
	    $csv = $this->buildCsv();

	    $outputConfig = [
			'filename' => 'ContentSummary_'.time().'.csv',
		];

	    // send headers
	    $length = strlen($csv);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Content-Type: text/csv; charset=' . ($outputConfig['charset'] ?? 'utf-8'));
        header('Content-Disposition: attachment; filename=' . $outputConfig['filename']);
        header('Content-Length: ' . $length);
        // for ie problem:
        header('Pragma: private');
        header('Cache-Control: private, must-revalidate');


		// send body
	    print $csv;
	    exit;
	}



	public function renderView_getCss()	{
		// wrap in <style> tags is to make ide css formatting work
		$css = <<<EOD
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
    background: #ececec;
    color: #252525;
    cursor: auto;
    /*font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;*/
    font-family: Verdana, Arial, Helvetica, sans-serif;
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
    border: 1px solid #cdcdcd;
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
    /*font-family: Menlo, Monaco, Consolas, "Courier New", "Lucida Console", monospace, sans-serif;*/
    line-height: 1.2em;
}
tr.field_marked td {
    font-weight: bold;
}
tr.field_dim td {
    opacity: 0.4;
}
.rootline_breadcrumb    {
    border: 1px dotted #727272;
    padding: 4px 4px;
    margin-bottom: 10px;
    background: #d1d1d1;

	width: 100%;
    min-width: max-content;
	overflow-x: auto;
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
    margin: 2px 12px 2px 2px;
    padding: 0 14px 2px;
    border: 1px solid #8da6ce;
    background: #fff;
    position: relative;
	
	display: inline-block;
    word-break: keep-all;
    white-space: nowrap;
    overflow-wrap: break-word;
}
	.rootline_item:not(:first-child)  {
		margin-left: 26px;
    	margin-right: 12px;
	}
	.rootline_item:not(:first-child):before  {
		content: ' ➤ ';
		font-style: normal;
		color: #a1a0ab;
		position: absolute;
		top: 5px;
		left: -30px;
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
.rootline_item.repeats-previous-row-item {
    opacity: .1;
}
.rootline_item a    {
    color: inherit;
}
.rootline_item a:hover    {
    color: #000;
    text-decoration: underline;
}
/* linked page title. I used it for fix width, because overflow:hidden
	on rootline_item block hides my :before */
.rootline_item > a    {
	width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
	display: block;
}
.rootline_item .page-properties {
	display: inline-block;
	padding-left: 8px;
}
.these-items    {
    margin: 0 14px;
    padding: 0 20px;
    font-size: 0.9em;
	/* interesting property - makes the element be "ignored" in laying out its contents, they behave like they are direct children of their grandparent.
		all block settings from here are then ignored, but the content wraps properly now */ 
	/*display: contents;*/
	/*word-break: break-word;*/

	display: inline-block;
}
.these-items .records   {
	/*display: inline-block;*/
}
.these-items .ce-hidden	{
	text-decoration: line-through;
	opacity: .8;
}
	.these-items .ce-hidden:hover	{
		text-decoration: underline;
		opacity: 1;
	}
.page-uid   {
    font-size: 0.9em;
	vertical-align: top;
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
h1 a, h1 a:hover 	{
	color: #000;
}
</style>
EOD;

		return strip_tags($css);
	}
	
	public function renderView_getDocument()	{
		$css = $this->renderView_getCss();
		$pageContent = $this->handleRequestsAndCompileContent();
		$version = CONTENT_SUMMARY_VERSION;

		return <<<EOD
<html lang="en">
<head>
    <title>ContentSummary</title>
    <style>
$css
    </style>
</head>
<body>

    $pageContent

	<br>
	<p class="mono">ContentSummary v$version<br>
	<a href="https://wolo.pl/">wps</a> / Binary Owl Forever '.' 2021</p>
</body>
</html>
EOD;

	}
}



if (! $GLOBALS['ContentSummaryConfig']['mode_include'])	{

	// GO
	$WorkObject = new ContentSummary($GLOBALS['ContentSummaryConfig']);
	// catch output (with placeholders to insert ie. messages or errors)
	$html = $WorkObject->renderView_getDocument();
	
	// save that output to file, but with removed notifications placeholders (not used there, in static file) 
	$WorkObject->saveOutput($WorkObject->cleanupPlaceholders($html));
	$WorkObject->saveCsv();
	
	// but the original var still has them, so replace with values now, maybe we got some errors from saving these files 
	$html = $WorkObject->replacePlaceholders($html);
	
	// send final content to browser
	print $html;
}
