
# WTP TYPO3 Content Summary  
### v0.7c
#### WTP / wolo.pl '.' studio 2021


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
     Only the Frames section will not show where the field had its previous name before change. I think it was 
     in 7.x maybe, but I didn't try. You only must set the database credentials in the format like below / 8.x form.  

  Q: **Why it keeps making .csv/.html files on every run?** 
  A: Usually I need it in csv to analyse, so I decided to save it always by default. You can disable this behaviour
     in config.


## Config:

Just set the options inside the main script if need to:

    baseDomain : (string)
        default = https:// + $_SERVER['HTTP_HOST']
        Full base url, to make nice direct links to pages with found occurrences. Usually works OK by itself.

    autosaveCSV : (int/bool)
        default = 1
        Save summary in CSV on every run. Disable, if it bothers you. If not, just keep deleting them. 

    autosaveHTML : (int/bool)
        default = 0
        Dump main summary HTML output on every run. (You will come back here anyway to check something you forgot, so maybe just keep this on) 

    fileDumpPath : (string)
        default = ''
        Where to save them, if not to current dir

