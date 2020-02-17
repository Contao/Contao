# Change log

## 4.4.47 (2020-02-17)

 * Replace document.write() in the back end templates (see #1329).
 * Fix the initial table sorting in the "table" content element (see #1315).
 * Improve the Contao\Database\Result class (see #1287).
 * Fix the top navigation overflow (see #1302).
 * Fix checking for binary strings in the version comparison (see #1294).
 * Correctly find unactivated members (see #1258).
 * Urlencode the path returned by the "file::uuid" insert tag (see #1268).
 * Fix the custom response status code in Symfony 3.4 (see #1204).

## 4.4.46 (2019-12-17)

 * Prevent information disclosure in the back end (see CVE-2019-19712).
 * Prevent unrestricted file uploads (see CVE-2019-19745).
 * Set img width/height attributes as an aspect ratio (see #940).
 * Always warm the English language cache (see #1040).
 * Check if a template exists when inheriting templates (see #1016).
 * Use the status code 307 to redirect on the logout page (see #1041).
 * Sort the custom layout sections by their position (see #1042).
 * Correctly trigger kernel response events when handling exceptions (see #1020).
 * Do not catch Swift exceptions when submitting forms (see #1017).
 * Correctly compare order fields in the diff view (see #1002).

## 4.4.45 (2019-11-04)

 * Reduce the number of DB queries in the picture factory (see #921).
 * Hide the breadcrumb menu if the node is not within the given path (see #888).
 * Also export the media type(s) when exporting style sheets (see #905).
 * Only hide newsletter channels without redirect page in the web modules (see #907).
 * Quote the identifiers in the back end filter menu (see #906).
 * Use a monospace font in the diff view (see #904).
 * If there are no unsynchronized folders, do not show an info message (see #897).
 * Distinguish between XML and HTML sitemap (see #879).
 * Re-index array of modules after unset keys to prevent inconsistency (see #834).

## 4.4.44 (2019-10-01)

 * Prevent regular users from enabling the template editor for themselves (see #749).
 * Use the robots meta data to determine whether to add a page to the XML sitemap (see #501).
 * Do not versionize the file name and path (see #694).
 * Update the comments notification URL if it has changed (see #373).
 * Hide the "generate aliases" button if the alias field has not been enabled (see #771).
 * Show only the active columns in the module wizard (see #765).
 * Fix the pagination menu in the versions overview (see #752).
 * Reset unique fields when restoring a version (see #698).

## 4.4.43 (2019-09-05)

 * Handle renamed files in the version overview (see #671).
 * Hide the username if the initial version is auto-generated (see #664).
 * Set the e-mail priority if it has been given (see #608).
 * Also show the breadcrumb menu if there are no results (see #660).
 * Correctly replace literal insert tags (see #670).
 * Increase the alias field lengths (see #678).
 * Retain origId in chained alias elements (see #635).
 * Check if the theme preview image exists (see #636).

## 4.4.42 (2019-08-15)

 * Fix the shift key checkbox selection in the picker (see #578).

## 4.4.41 (2019-07-16)

 * Correctly hide running events in the event list.
 * Correctly apply the sorting flags in the list and parent view (see contao/core-bundle#1536).
 * Purge the search index when a page alias changes (see #472).
 * Show only newsletter channels with redirect page in the newsletter list module (see #494).
 * Use `scssphp/scssphp` instead of `leafo/scssphp` (see #506).
 * Hide empty legends in the `member_grouped.html5` template (see #514).

## 4.4.40 (2019-05-21)

 * Ignore the query string when marking pages as "active" (see #480).
 * Do not cache file downloads in the HTTP cache (see #460).
 * Fix the "Recreate the symlinks" maintenance task (see #462).
 * Do not inherit cache timeouts on error pages (see #231).

## 4.4.39 (2019-04-30)

 * Prevent SQL injections in the file manager search (see CVE-2019-11512).
 * Correctly handle dates in the news bundle (see #436).
 * Also show future news items if the "show all news items" option is selected (see #419).

## 4.4.38 (2019-04-10)

 * Correctly copy multiple events into an empty calendar (see #427).
 * Correctly check the permissions to create form fields (see #414).
 * Fix the save callback in the back end password module (see #429).
 * Correctly handle dates in the calendar bundle (see #428).

## 4.4.37 (2019-04-09)

 * Invalidate the user sessions if a password changes (see CVE-2019-10641).

## 4.4.36 (2019-03-25)

 * Make custom layout section titles and IDs mandatory (see #341).
 * Prevent using reserved layout section IDs in custom layout sections (see #301).
 * Show the video elements headline in the back end preview (see #382).

## 4.4.35 (2019-02-21)

 * Fix the format selection in the image size widget (see #315).
 * Ignore a `.public` file in the root files directory (see #286).
 * Correctly load MooTools via CDN (see #318).
 * Do not double decode URL fragments (see #321).
 * Correctly replace insert tags if the page contains invalid characters (see #349).

## 4.4.34 (2019-01-24)

 * Validate the primary key when registering or saving a model (see #230).
 * Exempt the "page" insert tag from caching (see #284).
 * Correctly sort the tree view records if there is an active filter (see #269).
 * Fix two routing issues (see #263, #264).

## 4.4.33 (2019-01-16)

 * Support comma separated values in `Model::getRelated()` (see #257).
 * Do not check the user's file permissions in the template editor (see #224).
 * Do not show pretty errors if "text/html" is not accepted (see #249).
 * Return `null` in `Model::findMultipleByIds()` if there are no models (see #266).
 * Restore compatibility with Doctrine DBAL 2.9 (see #256).

## 4.4.32 (2018-12-19)

 * Correctly check the permission to move child records as non-admin user (see #247).
 * Do not parse form templates twice (see #214).

## 4.4.31 (2018-12-13)

 * Prevent information disclosure through incorrect access control in the back end (see CVE-2018-20028).

## 4.4.30 (2018-12-04)

 * Fix a compatibility issue with Doctrine DBAL 2.9 (see #212).

## 4.4.29 (2018-11-22)

 * Do not convert line breaks in table cells if there are HTML block elements (see #159).
 * Automatically enable image sizes created by regular users (see contao/core#8836).
 * Handle unknown languages in the meta editor (see #127).

## 4.4.28 (2018-10-31)

 * Correctly rebuild the symlinks in the maintenance module (see #150).

## 4.4.27 (2018-10-31)

 * Check the member status when sending newsletters (see contao/core#8812).
 * Fix the schema.org markup of the breadcrumb menu (see contao/core-bundle#1561).
 * Allow to set the target directory when installing the web directory (see #142).
 * Correctly render the back end forms in Firefox (see #79).
 * Show the info messages in the DropZone uploader (see #83).

## 4.4.26 (2018-09-20)

 * Fix an error when creating new pages (see #63).

## 4.4.25 (2018-09-18)

 * Correctly detect Chrome on iOS in the environment class (see #61).
 * Optimize generating sitemaps (see contao/core#6830).
 * Use min-height for .w50 widgets in the back end (see contao/core#8864).
 * Prevent arbitrary code execution through .phar files (see CVE-2018-17057).

## 4.4.24 (2018-09-05)

 * Ignore the "uncached" insert tag flag in the unknown insert tags (see #48).
 * Make the ID of the subscription modules unique (see #40).
 * Use the correct table when handling root nodes in the picker (see #44).

## 4.4.23 (2018-08-28)

 * Replace the `Set-Cookie` header when merging HTTP headers (see #35).

## 4.4.22 (2018-08-27)

 * Do not merge the session cookie header (see #11, #29).
 * Update the list of countries (see #12).

## 4.4.21 (2018-08-13)

 * Correctly set the back end headline for custom actions (see contao/newsletter-bundle#23).
 * Remove support for deprecated user password hashes (see contao/core-bundle#1608).
 * Fix the MySQL 8 compatibility (see contao/installation-bundle#93).
 * Revert the intermediate maintenance mode fix (see contao/manager-bundle#78).

## 4.4.20 (2018-06-26)

 * Make the session listener compatible with Symfony 3.4.12.

## 4.4.19 (2018-06-18)

 * Show the 404 page if there is no match in the set of potential pages (see contao/core-bundle#1522).
 * Correctly calculate the nodes when initializing the picker (see contao/core-bundle#1535).
 * Do not re-send the activation mail if auto-registration is disabled (see contao/core-bundle#1526).
 * Remove the app_dev.php/ fragment when generating sitemaps (see contao/core-bundle#1520).
 * Show the 404 page if a numeric aliases has no url suffix (see contao/core-bundle#1508).
 * Fix the schema.org markup so the breadcrumb is linked to the webpage (see contao/core-bundle#1527).
 * Reduce memory consumption during sitemap generation (see contao/core-bundle#1549).
 * Correctly handle spaces when opening nodes in the file picker (see contao/core-bundle#1449).
 * Disable the maintenance mode for local requests (see contao/core-bundle#1492).
 * Correctly blacklist unsubscribed recipients (see contao/newsletter-bundle#21).
 * Use a given e-mail address in the unsubscribe module (see contao/newsletter-bundle#12).
 * Delete old subscriptions if the new e-mail address exists (see contao/newsletter-bundle#19).

## 4.4.18 (2018-04-18)

 * Fix an XSS vulnerability in the system log (see CVE-2018-10125).
 * Correctly highlight all keywords in the search results (see contao/core-bundle#1461).
 * Log unknown insert tag (flags) in the system log (see contao/core-bundle#1182).

## 4.4.17 (2018-04-04)

 * Correctly hide empty custom layout sections (see contao/core-bundle#1115).
 * Preserve the container directory when purging the cache.
 * Suppress error messages in production (see contao/core-bundle#1422).
 * Correctly duplicate recipients if a channel is duplicated (see contao/newsletter-bundle#15).

## 4.4.16 (2018-03-08)

 * Correctly link to the picker from the TinyMCE link menu (see contao/core-bundle#1415).

## 4.4.15 (2018-03-06)

 * Do not make the response private when saving the session (see contao/core-bundle#1388).
 * Improve the folder hashing performance (see contao/core#8856).

## 4.4.14 (2018-02-14)

 * Correctly render the article list if there are no articles (see contao/core-bundle#1351).
 * Do not log custom insert tags as "unknown" if they have been replaced (see contao/core-bundle#1295).
 * Show redirect pages with unpublished targets in the front end preview.
 * Log database connection errors (see contao/core-bundle#1324).
 * Remove "allow_reload" in favor of the "expect" header (see terminal42/header-replay-bundle#11).

## 4.4.13 (2018-01-23)

 * Quote reserved words in database queries (see contao/core-bundle#1262).
 * Correctly render external links in the `event_teaser` template (see contao/calendar-bundle#21).
 * Do not remove old subscriptions not related to the selected channels (see contao/core#8824).

## 4.4.12 (2018-01-03)

 * Do not resend activation mails for active members (see contao/core-bundle#1234).
 * Order the files by name when selecting folders in the file picker (see contao/core-bundle#1270).
 * Optimize inserting keywords into tl_search_index (see contao/core-bundle#1277).
 * The assets:install command requires the application to be set in Symfony 3.4 (see contao/installation-bundle#81).

## 4.4.11 (2017-12-28)

 * Revert 'Quote reserved words in database queries (see contao/core-bundle#1262)'.

## 4.4.10 (2017-12-27)

 * Quote reserved words in database queries (see contao/core-bundle#1262).
 * Only add _locale if prepend_locale is enabled (see contao/core-bundle#1257).
 * Add the missing options array in the CommentsModel class (see contao/comments-bundle#9).
 * Use the schema filter in the install tool (see contao/installation-bundle#78).

## 4.4.9 (2017-12-14)

 * Show the "invisible" field when editing a form field (see contao/core-bundle#1199).
 * Only add pages requested via GET to the search index (see contao/core-bundle#1194).
 * Fix the Encrption class not supporting PHP 7.2 (see contao/core#8820).
 * Handle single file uploads in FileUpload::getFilesFromGlobal() (see contao/core-bundle#1192).
 * Do not always create a new version when an event is saved (see contao/news-bundle#26).
 * Use a simpler lock mechanism in the install tool (see contao/installation-bundle#73).

## 4.4.8 (2017-11-15)

 * Prevent SQL injections in the back end search panel (see CVE-2017-16558).
 * Prevent SQL injections in the listing module (see CVE-2017-16558).
 * Support class named services in System::import() and System::importStatic() (see contao/core-bundle#1176).
 * Only show pretty error screens on Contao routes (see contao/core-bundle#1149).

## 4.4.7 (2017-10-12)

 * Show broken images in the file manager (see contao/core-bundle#1116).
 * Copy the existing referers if a new referer ID is initialized (see contao/core-bundle#1117).
 * Stop using the TinyMCE gzip compressor (deprecated since 2014).
 * Prevent the User::authenticate() method from running twice (see contao/core-bundle#1067).
 * Filter multi-day events outside the scope in the event list (see contao/core#8792).

## 4.4.6 (2017-09-28)

 * Bind the lock file path to the installation root directory (see contao/core-bundle#1107).
 * Correctly select the important part in the modal dialog (see contao/core-bundle#1093).
 * Correctly handle unencoded data images in the Combiner (see contao/core#8788).
 * Do not add a suffix when copying if the "doNotCopy" flag is set (see contao/core#8610).
 * Use the module type as group header if sorted by type (see contao/core#8402).
 * Fix the setEmptyEndTime() save_callback (see contao/calendar-bundle#10).
 * Correctly show multi-day events if the shortened view is disabled (see contao/core#8782).

## 4.4.5 (2017-09-18)

 * Fall back to the URL if there is no link title (see contao/core-bundle#1081).
 * Correctly calculate the intersection of the root nodes with the mounted nodes (see contao/core-bundle#1001).
 * Catch the DriverException if the database connection fails (see contao/managed-edition#27).
 * Fix the back end theme.
 * Check if the session has been started before using the flash bag.
 * Catch the DriverException if the database connection fails (see contao/managed-edition#27).

## 4.4.4 (2017-09-05)

 * Show the form submit buttons at the end of the form instead of at the end of the page.
 * Do not add the referer ID in the Template::route() method (see contao/core-bundle#1033).
 * Correctly read the newsletter channel target page in the newsletter list (see contao/newsletter-bundle#7).

## 4.4.3 (2017-08-16)

 * Correctly assign the form CSS ID (see contao/core-bundle#956).
 * Fix the referer management in the back end (see contao/core#6127).
 * Also check for a front end user during header replay (see contao/core-bundle#1008).
 * Encode the username when opening the front end preview as a member (see contao/core#8762).
 * Correctly assign the CSS media type in the combiner.
 * Warm up the Symfony cache after the database credentials have been set (see contao/installation-bundle#63).
 * Check if the Contao framework has been initialized when adding the UA string (see contao/standard-edition#64).

## 4.4.2 (2017-07-25)

 * Adjust the command scheduler listener so it does not rely on request parameters (see contao/core-bundle#955).
 * Rewrite the DCA picker (see contao/core-bundle#950).

## 4.4.1 (2017-07-12)

 * Prevent arbitrary PHP file inclusions in the back end (see CVE-2017-10993).
 * Correctly handle subpalettes in "edit multiple" mode (see contao/core-bundle#946).
 * Correctly show the DCA picker in the site structure (see contao/core-bundle#906).
 * Correctly update the style sheets if a format definition is enabled/disabled (see contao/core-bundle#893).
 * Always show the "show from" and "show until" fields (see contao/core-bundle#908).
 * Correctly set the "overwriteMeta" field during the database update (see contao/core-bundle#888).

## 4.4.0 (2017-06-15)

 * Fix the "save and go back" function (see contao/core-bundle#870).

## 4.4.0-RC2 (2017-06-12)

 * Update all Contao components to their latest version.
 * Regenerate the symlinks after importing a theme (see contao/core-bundle#867).
 * Only check "gdMaxImgWidth" and "gdMaxImgHeight" if the GDlib is used to resize images (see contao/core-bundle#826).
 * Improve the accessibility of the CAPTCHA widget (see contao/core#8709).
 * Re-add the "reset selection" buttons to the picker (see contao/core-bundle#856).
 * Trigger all the callbacks in the toggleVisibility() methods (see contao/core-bundle#756).
 * Only execute the command scheduler upon the "contao_backend" and "contao_frontend" routes (see contao/core-bundle#736).
 * Correctly set the important part in "edit multiple" mode (see contao/core-bundle#839).
 * Remove the broken alias transliteration (see contao/core-bundle#848).

## 4.4.0-RC1 (2017-05-23)

 * Tweak the back end template.
 * Add the "allowed member groups" setting (see contao/core#8528).
 * Hide the CAPTCHA field by default by adding a honeypot field (see contao/core-bundle#832).
 * Add the "href" parameter to the article list (see contao/core-bundle#694).
 * Show both paths and UUIDs in the "show" view (see contao/core-bundle#793).
 * Do not romanize file names anymore.
 * Improve the back end breadcrumb menu (see contao/core-bundle#623).
 * Correctly render the image thumbnails (see contao/core-bundle#817).
 * Use the "file" insert tag in the file picker where applicable (see contao/core#8578).
 * Update the Punycode library to version 2 (see contao/core-bundle#748).
 * Always add custom meta fields to the templates (see contao/core-bundle#717).
 * Notify subscribers of replies (see contao/core#8565).
 * Ignore tables not starting with "tl_" in the install tool (see contao/installation-bundle#51).
 * Re-add the "sqlCompileCommand" hook (see contao/installation-bundle#51).
 * Purge the opcode caches after deleting the Symfony cache (see contao/contao-manager#80).
 * Show a "no results" notice if a search does not return any results (see contao/core#7705).

## 4.4.0-beta1 (2017-05-05)

 * Warn if another user has edited a record when saving it (see contao/core-bundle#809).
 * Optimize the element preview height (see contao/core-bundle#810).
 * Use the file meta data by default when adding an image (see contao/core-bundle#807).
 * Use the kernel.project_dir parameter (see contao/core-bundle#758).
 * Disable the "publish" checkbox if a parent folder is public (see contao/core-bundle#712).
 * Improve the findByIdOrAlias() method (see contao/core-bundle#729).
 * Make sure that all modules can have a custom template (see contao/core-bundle#704).
 * Remove the popupWidth and popupHeight parameters in the file manager (see contao/core-bundle#727).
 * Remove the cron.txt file (see contao/core-bundle#753).
 * Allow to disable input encoding for a whole DCA (see contao/core-bundle#708).
 * Add the DCA picker (see contao/core-bundle#755).
 * Allow to manually pass a value to any widget (see contao/core-bundle#674).
 * Only prefix an all numeric alias when standardizing (see contao/core-bundle#707).
 * Support using objects in callback arrays (see contao/core-bundle#699).
 * Support importing form field options from a CSV file (see contao/core-bundle#444).
 * Add a Doctrine DBAL field type for UUIDs (see contao/core-bundle#415).
 * Support custom back end routes (see contao/core-bundle#512).
 * Add the contao.image.target_dir parameter (see contao/core-bundle#684).
 * Match the security firewall based on the request scope (see contao/core-bundle#677).
 * Add the contao.web_dir parameter (see contao/installation-bundle#40).
 * Look up the form class and allow to choose the type (see contao/core#8527).
 * Auto-select the active page in the quick navigation/link module (see contao/core#8587).
 * Extended reporting if the script handler fails (see contao/manager-bundle#27).
 * Set prepend_locale to false (see contao/core-bundle#785).
 * Change the maintenance lock file path (see contao/core-bundle#728).
 * Add basic security (see contao/standard-edition#54).
