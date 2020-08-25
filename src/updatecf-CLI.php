<?php

/**
 * Woluweb - Update Custom Field project
 * A plugin allowing to populate Joomla Custom Fields from Webservices
 * php version 7.2
 *
 * @package   Updatecf
 * @author    Pascal Leconte <pascal.leconte@conseilgouz.com>
 * @author    Christophe Avonture <christophe@avonture.be>
 * @author    Marc Dechèvre <marc@woluweb.be>
 * @license   GNU GPL
 *
 * @link https://github.com/woluweb/updatecf
 * @wiki https://github.com/woluweb/updatecf/-/wikis/home
 */

// We are a valid entry point.
const _JEXEC = 1;
const JDEBUG = 1;

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php')) {
    include_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES')) {
    define('JPATH_BASE', dirname(__DIR__));
    include_once JPATH_BASE . '/includes/defines.php';
}

// Get the framework.
require_once JPATH_LIBRARIES . '/import.legacy.php';

// Bootstrap the CMS libraries.
require_once JPATH_LIBRARIES . '/cms.php';

// Load the configuration
require_once JPATH_CONFIGURATION . '/configuration.php';

/**
 * Update articles in a given category by querying the JSON provider
 * and updating custom fields in these articles.
 *
 * @since  1.0
 */
class UpdatecfCLI extends JApplicationCli
{
    /**
     * Parameters of the UpdateCF plugin.
     *
     * @var \stdClass
     */
    private $params;

    /**
     * Entry point for CLI script.
     *
     * @return void
     *
     * @since   3.0
     */
    public function doExecute()
    {
        $this->out(JText::_('UPDATE_CF_CLI'));
        $this->out('============================');

        // Configure error reporting to maximum for CLI output.
        error_reporting(E_ALL); // ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
        ini_set('display_errors', 1);

        // Load Library language
        $lang = JFactory::getLanguage();

        // Try the files_joomla file in the current language (without allowing the loading of the file in the default language)
        $lang->load('files_joomla.sys', JPATH_SITE, null, false, false)
        // Fallback to the files_joomla file in the default language
        || $lang->load('files_joomla.sys', JPATH_SITE, null, true);

        // Import the dependencies
        jimport('joomla.filesystem.file');
        jimport('joomla.filesystem.folder');

        $helper = JPATH_SITE . '/plugins/system/updatecf/helpers.php';

        if (!is_file($helper)) {
            $this->out('ERROR, the ' . $helper . ' file can\'t be retrieved', true);

            return;
        }

        JLoader::register('Woluweb\HelpersUpdateCf', $helper);

        $this->params = Woluweb\HelpersUpdateCf::getParams();

        if (null == $this->params) {
            $this->out('ERROR, the updatecf plugin is unpublished. Please first publish it.', true);

            return;
        }

        $articles = Woluweb\HelpersUpdateCf::getListArticles($this->params->get('categories') ?? []);

        // Process all articles
        foreach ($articles as $item) {
            JLog::add(sprintf('Process article %d - %s', $item->id, $item->title), JLog::INFO, 'curl');
            // CONTINUE
        }
    }
}

// Instantiate the application object, passing the class name to JCli::getInstance
// and use chaining to execute the application.
JApplicationCli::getInstance('UpdatecfCLI')->execute();
