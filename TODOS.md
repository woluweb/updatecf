# Things still to do

* [ ] Check if Joomla can install the ZIP from github
* [ ] JInstaller integration, make updating the plugin easy
* [ ] Copy the CLI script to the `/cli` folder during the installation of the plugin
* [ ] Make the CLI script working (I was unable to get the list of articles; getting a Fatal error in Joomla himself (line `$model = \JModelLegacy::getInstance('Articles', 'ContentModel', ['ignore_request' => true]);` in the helper; impossible then to get the list of `getItems();`)
* [ ] Improve JLog statements
* [ ] Reduce the number of hard-coding (actually custom fields name are hardcoded, name and location in the received JSON response)
* [ ] Test, test, test
