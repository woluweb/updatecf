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

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;

/**
 * The Update Custom Fields system plugin.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PlgSystemupdatecf extends JPlugin
{
    /**
     * Name of last run file; stored in the parent folder of the plugin.
     *
     * @var string
     */
    private const FILENAME = 'updatecf_checkfile';

    /**
     * Remember the folder of this plugin
     * For instance "/home/user/site/plugins/system/updatecf/".
     *
     * @var string
     */
    private $folder = '';

    /**
     * List of "updatecf_checkfile" files found in the current folder.
     *
     * @var array<string>
     */
    private $fnames = [];

    /**
     * Plugin constructor.
     *
     * @param [type] $subject
     * @param array  $config  Configuration items of the plugin
     */
    public function __construct(&$subject, $config = [])
    {
        // 4.0. compatibility
        jimport('joomla.filesystem.folder');
        jimport('joomla.filesystem.file');

        parent::__construct($subject, $config);

        $this->loadLanguage();

        if ('' === trim($config['params']['url_json_provider'])) {
            JFactory::getApplication()->enqueueMessage(
                JText::_('PLG_CONTENT_UPDATECF_URL_JSON_PROVIDER_MISSING'),
                'error'
            );

            return;
        }

        $helper = JPATH_SITE . '/plugins/system/updatecf/helpers.php';
        JLoader::register('Woluweb\HelpersUpdateCf', $helper);

        // Folder of this plugin
        $this->folder  = dirname(__FILE__) . DIRECTORY_SEPARATOR;

        // Get the list of existing "updatecf_checkfile" files in the current folder
        $this->fnames  = (array) JFolder::files($this->folder, self::FILENAME . '.*');
    }

    /**
     * Joomla onAfterInitialise.
     *
     * @return void
     */
    public function onAfterInitialise(): void
    {
        $input = Factory::getApplication()->input;

        // Get an empty string when the plugin wasn't called from the Edit plugin backend page
        $pJform = $input->get('jform', '', 'array');

        if ('' != $pJform) {
            // Plugin called from the Edit plugin page, run the "manual" synchronization
            if ('updatecf' === $pJform['element']) {
                $this->manualPluginSaving($pJform);
            }
        }

        if ([] === $this->fnames) {
            return;
        }

        $fname      = (string) array_pop($this->fnames);
        $backuptime = substr($fname, -10, 10);
        $interval   = file_get_contents($this->folder . $fname);

        $create  = false;

        if ('w' == $interval[0]) {
            $interval = (int) substr($interval, 1);
            $create   = true;
        }

        $time = time();

        // Update
        if (($time > $backuptime) || $create) {
            Woluweb\HelpersUpdateCf::killFile($fname);

            while ($backuptime < $time) {
                $backuptime += $interval;
            }

            \Woluweb\HelpersUpdateCf::createLastRun(self::FILENAME . '.' . $backuptime, $interval);

            // there is an Option allowing to have a Log everytime the Plugin is triggered
            if (1 === (int) $this->params->get('log')) {
                JLog::addLogger(['text_file' => 'updatecf.trace.log.php'], JLog::INFO);
            }

            $this->goUpdate();

            if (1 === (int) $this->params->get('log')) {
                JLog::add('OK', JLog::INFO, 'Custom Fields Synchronisation');
            }
        }
    }

    /**
     * Formatting the received message in JSon mode.
     *
     * @param string $json The JSON message
     *
     * @return array
     */
    public function getJsonArray(string $json): array
    {
        return (array) json_decode($json, true);
    }

    /**
     * Return a field from an array in a text zone.
     *
     * @param array  $array An array
     * @param string $field A field name
     *
     * @return string The value of the field
     */
    public function getOneField(array $array, string $field): string
    {
        return $array[$field] ?? '';
    }

    /**
     * Returns the elements of an array in a repeatable field.
     *
     * @param array  $array An array
     * @param string $field A field
     * @param string $name  A name
     *
     * @return string
     */
    public function getRepeat(array $array, string $field, string $name): string
    {
        $ix      = 0;
        $results = [];

        foreach ($array as $elem) {
            $item                                  = [];
            $item[$name]                           = $elem;
            $results[$field . '-repeatable' . $ix] = $item;
            ++$ix;
        }

        return (string) json_encode($results);
    }

    /**
     * The present plugin will trigger automatically at the frequency configured
     * in the Plugin Options.
     *
     * To do so it creates a file with the timestamp of the last execution
     * Note: the manual way to trigger the Plugin is simply to (Open and) Save it
     *
     * @param array $config Configuration items of the plugin
     *
     * @return void
     */
    private function manualPluginSaving(array $config): void
    {
        if (!(isset($config['params']['freq']))) {
            return;
        }

        JLog::add('OK', JLog::INFO, JText::_('PLG_CONTENT_UPDATECF_SAVED'));

        $interval = (int) $config['params']['freq'];

        if ([] !== $this->fnames) {
            $fname = array_pop($this->fnames);
            Woluweb\HelpersUpdateCf::killFile($fname);
        }

        $dayssecs = $config['params']['time'];
        $dayssecs = strtotime(date('Y-m-d') . ' ' . $dayssecs);

        if (!$dayssecs) {
            $dayssecs=0;
        } else {
            $dayssecs -= strtotime(date('Y-m-d'));
        }

        $time       = time();
        $round      = strtotime(date('Y-m-d', $time));
        $backuptime = $round + $dayssecs;
        $xdays      = (int)$config['params']['xdays'];

        if ($xdays < 0) {
            $xdays = 1;
        }

        if (1 == $xdays) {
            $interval= (0 == $interval) ? 86400 : (86400 / $interval);

            while ($backuptime < $time) {
                $backuptime += $interval;
            }
        } else {
            $interval = $xdays * 86400;
            if ($backuptime < $time) {
                $backuptime += 86400;
            }
        }

        \Woluweb\HelpersUpdateCf::createLastRun(self::FILENAME . '.' . $backuptime, 'w' . $interval);
    }

    /**
     * Make the update, process articles.
     *
     * @return void
     */
    private function goUpdate(): void
    {
        $categories = $this->params->get('categories');

        $articles = Woluweb\HelpersUpdateCf::getListArticles($categories);

        // Process all articles
        foreach ($articles as $item) {
            $return = \Woluweb\HelpersUpdateCf::updateArticleCustomFields($item);

            if ([] !== $return) {
                $type = $return['success'] ? 'info' : 'error';
                \JFactory::getApplication()->enqueueMessage($return['message'], $type);
                \JLog::add($return['message'], ('info' === $type ? \JLog::INFO : \JLog::ERROR), 'plugin');
            }
        }
    }
}
