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

// phpcs:disable PSR1.Files.SideEffects

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

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
     * Domain to query.
     *
     * @var string
     */
    private const DOMAIN = 'https://social.brussels/rest/organisation/';

    /**
     * The HTTP ressource has been successfuly retrieved.
     *
     * @var int
     */
    private const HTTP_OK = 200;

    /**
     * HTTP found.
     *
     * @var int
     */
    private const HTTP_FOUND = 302;

    /**
     * Used to detect if the Joomla major version is 4 or more.
     *
     * @var int
     */
    private const JOOMLA_4 = 4;

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
     * HTTP response.
     *
     * @var string
     */
    private $response;

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
            $this->killFile($fname);

            while ($backuptime < $time) {
                $backuptime += $interval;
            }

            $this->createLastRun(self::FILENAME . '.' . $backuptime, $interval);

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
     * Update of the Custom Fields values based on the external source (webservices).
     *
     * @param int   $articleId The ID of the article
     * @param array $fields    The list of custom fields
     *
     * @return void
     */
    public function updateCustomFields(int $articleId, array $fields): void
    {
        $jsonArray = $this->getJsonArray($this->response);

        $model = BaseDatabaseModel::getInstance('Field', 'FieldsModel', ['ignore_request' => true]);

        foreach ($fields as $field) {
            // we mention here all the Custom Fields which should be ignored by the plugin
            if (('id-external-source' === $field->name) || ('cf-update' === $field->name)) {
                continue;
            }

            // then, for every Custom Field where we want to fill in the value we
            // simply specify the value in the json provided by the external source
            switch ($field->name) {
                case 'labelfr':
                    $value= $jsonArray['legalStatus']['labelFr'] ?? '';

                    break;
                case 'labelnl':
                    $value= $jsonArray['legalStatus']['labelNl'] ?? '';

                    break;
                case 'nameofficialfr':
                    $value= $jsonArray['nameOfficialFr'] ?? '';

                    break;
                case 'nameofficialnl':
                    $value= $jsonArray['nameOfficialNl'] ?? '';

                    break;
                case 'descriptionfr':
                    $value= $jsonArray['descriptionFr'] ?? '';

                    break;
                case 'descriptionnl':
                    $value= $jsonArray['descriptionNl'] ?? '';

                    break;
                case 'permanencyfr':
                    $value= $jsonArray['permanencyFr'] ?? '';

                    break;
                case 'permanencynl':
                    $value= $jsonArray['permanencyNl'] ?? '';

                    break;
                case 'legalfr':
                    $value=  $jsonArray['legalStatus']['labelFr'] ?? '';

                    break;
                case 'legalnl':
                    $value=  $jsonArray['legalStatus']['labelNl'] ?? '';

                    break;
                case 'streetfr':
                    $value=  $jsonArray['address']['streetFr'] ?? '';

                    break;
                case 'streetnl':
                    $value=  $jsonArray['address']['streetNl'] ?? '';

                    break;
                case 'emailfr':
                    $value= $this->getRepeat($jsonArray['emailFr'] ?? '', 'emailfr', 'email');

                    break;
                case 'emailnl':
                    $value= $this->getRepeat($jsonArray['emailNl'] ?? '', 'emailnl', 'email');

                    break;
                default:
                    // Default value in case some Custom Field would not be found
                    // (also for example because its Name is misspelled in the backend)
                    $value = 'That Custom Field ' . $field->title . ' was not found';
            }

            $model->setFieldValue($field->id, $articleId, $value);
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

        JLog::add('OK', JLog::INFO, 'Plugin has been edited and saved');

        $interval = (int) $config['params']['freq'];

        if ([] !== $this->fnames) {
            $fname = array_pop($this->fnames);
            $this->killFile($fname);
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

        $this->createLastRun(self::FILENAME . '.' . $backuptime, 'w' . $interval);
    }

    /**
     * Safely kill a file.
     *
     * @param string $path Basename of the file (no path)
     *
     * @return void
     */
    private function killFile(string $path): void
    {
        if ('' === $path) {
            return;
        }

        if (is_file($path)) {
            try {
                unlink($path);
            } catch (\Exception $e) {
                JLog::add('ERROR', JLog::ERROR, $e->getMessage());
            }
        }
    }

    /**
     * Create the last run file.
     *
     * @param string $path    Basename of the file (no path)
     * @param string $content The content to write in the file
     *
     * @return void
     */
    private function createLastRun(string $path, string $content): void
    {
        if ('' === $path) {
            return;
        }

        $path = $this->folder . JFile::makeSafe($path);

        try {
            // Create the last run file
            $fileLastRun=fopen($path, 'w');
            fputs($fileLastRun, $content);
            fclose($fileLastRun);
        } catch (\Exception $e) {
            JLog::add('ERROR', JLog::ERROR, $e->getMessage());
        }
    }

    /**
     * Make the update, process articles.
     *
     * @return void
     */
    private function goUpdate(): void
    {
        $categories = $this->params->get('categories');

        if (null === $categories) {
            $res        = $this->getAllCategories();
            $categories = [];
            foreach ($res as $catid) {
                if ($catid->count > 0) {
                    $categories[] = $catid->id;
                }
            }
        }

        $joomlaVersion = new JVersion();
        $majorVersion  = (int) substr($joomlaVersion->getShortVersion(), 0, 1);

        if ($majorVersion >= self::JOOMLA_4) {
            $articles     = new ArticlesModel(['ignore_request' => true]);
        } else {
            JLoader::register('ContentModelArticles', JPATH_SITE . '/components/com_content/models/articles.php');
            $articles = JModelLegacy::getInstance('Articles', 'ContentModel', ['ignore_request' => true]);
        }

        if ($articles) {
            $params = new Registry();

            $articles->setState('params', $params);
            $articles->setState('list.limit', 0);
            $articles->setState('list.start', 0);
            $articles->setState('filter.tag', 0);
            $articles->setState('list.ordering', 'a.ordering');
            $articles->setState('list.direction', 'ASC');
            $articles->setState('filter.published', 1);

            $articles->setState('filter.category_id', $categories);

            $articles->setState('filter.featured', 'show');
            $articles->setState('filter.author_id', '');
            $articles->setState('filter.author_id.include', 1);
            $articles->setState('filter.access', false);

            $items = $articles->getItems();

            // Process all articles
            foreach ($items as $item) {
                $this->updateArticleCustomFields($item);
            }
        }
    }

    /**
     * For each Article, decides whether to trigger or not the update of the Custom Field values.
     *
     * @param stdClass $article Joomla article
     *
     * @return bool
     */
    private function updateArticleCustomFields(\stdClass $article): bool
    {
        JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');

        $item       = [];
        $item['id'] = $article->id;

        $fields = FieldsHelper::getFields('com_content.article', $item);

        $idExternalSource = '';

        $update = false;

        foreach ($fields as $field) {
            if (('cf-update' === $field->name) && ('yes' === $field->value)) {
                $update = true;
            }

            if ('id-external-source' === $field->name) {
                $idExternalSource = trim($field->value);
            }
        }

        // We update a Article only if its Custom Field is set on Yes and if the
        // ID of the External Source is filled in
        if ($update && ('' != $idExternalSource)) {
            // Query f.i. https://social.brussels/rest/organisation/13219
            $this->url = self::DOMAIN . urlencode($idExternalSource);

            $getContentCode = $this->getCurlContent($this->url);

            if (in_array($getContentCode, [self::HTTP_OK, self::HTTP_FOUND])) {
                // Updating custom fields in the article
                $this->updateCustomFields($article->id, $fields);
            } else {
                if (1 === (int) $this->params->get('log')) {
                    JLog::add(
                        'Error Custom field ' . $idExternalSource . ' not found',
                        JLog::INFO,
                        'Custom Fields Synchronisation'
                    );
                }

                return false;
            }
        }

        return true;
    }

    /**
     * Retrieving information thanks to curl.
     *
     * @param string $url URL to query
     *
     * @return int The HTTP code or 0 when curl isn't loaded
     */
    private function getCurlContent(string $url): int
    {
        if (!extension_loaded('curl')) {
            return 0;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');
        curl_setopt($curl, CURLOPT_REFERER, 'http://www.google.com');
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_URL, $url);

        $this->response = curl_exec($curl);
        $infos          = curl_getinfo($curl);

        curl_close($curl);

        return (int) $infos['http_code'];
    }

    /**
     * Get all categories.
     *
     * @return array
     */
    public static function getAllCategories(): array
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true);

        $query->select('distinct cat.id,count(cont.id) as count,cat.note')
            ->from('#__categories as cat ')
            ->join('left', '#__content cont on cat.id = cont.catid')
            ->where('(extension like "com_content") AND (cat.published = 1) AND (cat.access = 1) AND (cont.state = 1)')
            ->group('cont.catid');

        $db->setQuery($query);

        return $db->loadObjectList();
    }
}
