
# ContentSummary - Record types use cases tracking tool for TYPO3
### v0.13
#### WTP / Binary Owl Forever / wolo.pl '.' studio 2021


## It helps you to:

- Prepare a clear table of found content types, plugins, FCEs, frames.
- What is used where and how many of them... (...you have to repair, if they're broken.)
- Get direct links to each type examples - find them all quickly to control if they still work after update.
- Whether that cave-era-accordion, which uses that old problematic js lib, is really still needed.
- Analyze chart of rootline parents of all pages found containing selected contenttype/plugin to check if
  maybe all instances on pages that are already deleted or unavailable publicly for years  


## What does it do exactly:

It connects to TYPO3's database, iterates all tt_contents in groups by:  
CType, list_type, frame_class, imageorient, header_layout, tx_templavoilaplus_ds.
Then summarizes each part, grouped by values with use count and pids where are found /direct links to each.
Going into details of each type value (ie. details of list_type=news_pi1) shows the rootline paths for each
occurrence (marking hidden pages), so you have clearly visualized whether any instances of that plugin are
used/visible anywhere.
Also, it dumps main summary into csv and/or html.


## FAQ:

  Q: **How to use?**  
  A: It's typo3-independent, so run from anywhere. Temporary include somewhere in project's global scope php, ie.
     in AdditionalConfiguration_host.php. That way you have database configuration. Or put the db credentials 
     right into ContentSummary.php (in 8.x format) and call file directly.

  Q: **Which TYPO3 versions/branches does it support?**  
  A: These which have fields list_type and Ctype in tt_content table, so basically all since 4.x to 10.x should work.
	 For some fields to work (tt_content frames and templavoila stuff) you need to set the compatVersion option to
	 an int of major version. You only must set the database credentials in the format like below / 8.x form.  

  Q: **Why it keeps making .csv/.html files on every run?** 
  A: Usually I need it in csv to analyse, so I decided to save it always by default. You can disable this behaviour
     in config.


## Config:

Just set the options inside the main script if need to:

    baseDomain: (string)
        default = https:// + $_SERVER['HTTP_HOST']
        Full base url, to make nice direct links to pages with found occurrences. Usually works OK by itself.

    autosaveCSV: (int/bool)
        default = 0
        Save summary in CSV on every run. Disable, if it bothers you. If not, just keep deleting them. 

    autosaveHTML: (int/bool)
        default = 0
        Dump main summary HTML output on every run. (You will come back here anyway to check something you forgot, so maybe just keep this on) 

    fileDumpPath: (string)
        default = ''
        Where to save them, if not to current dir

    debug: (int/bool)
        default = 0
        Display dev info, like SQL queries

	versionCompat: (int)
		default = 10
		Handle db structure differences between major branches, currently afair only setting "6" sets tt_content frame field to 
		section_frame and old templavoila tables / fields (without +)

	makeSummaryFor: (array[string])
		default = [... all available]
		Selection which summary tables to build. Array of keynames for each analyse. For details check code/default config.

	mode_include: (int/bool)
		default: null
		Deactivate standalone functionality and act like a normal class for inclusion. Then you can get the analysis in your code and make a good use from.
		IMPORTANT - This setting works slightly different, you don't put it in config, but set the array $GLOBALS['ContentSummaryConfig']['mode_include']
		to any positive value, BEFORE including the class.



