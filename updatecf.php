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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;

// 4.0. compatibility

jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

$input  = Factory::getApplication()->input;
$pJform = $input->get('jform', '', 'array');

/*
 * The present plugin will trigger automatically at the frequency configured in the Plugin Options
 * To do so it creates a file with the timestamp of the last execution
 * Note : the manual way to trigger the Plugin is simply to (Open and) Save it
 */
if (isset($pJform['params']['freq'])) {
    $folder  = JPATH_SITE . '/plugins/system/updatecf';
    $chkfile = 'majcf_checkfile';
    $fnames  =(JFolder::files($folder, $chkfile . '.*'));
    $fname   =array_pop($fnames);
    if ($fname) {
        unlink($folder . '/' . $fname);
    }

    $dayssecs=$pJform['params']['time'];
    $dayssecs=strtotime(date('Y-m-d') . ' ' . $dayssecs);
    if (!$dayssecs) {
        $dayssecs=0;
    } else {
        $dayssecs -= strtotime(date('Y-m-d'));
    }

    $time      =time();
    $round     =strtotime(date('Y-m-d', $time));
    $backuptime=$round + $dayssecs;
    $xdays     =(int)$pJform['params']['xdays'];
    if ($xdays < 0) {
        $xdays=1;
    }

    if (1 == $xdays) {
        $interval=(int)$pJform['params']['freq'];
        $interval= (0 == $interval) ? 86400 : (86400 / $interval);

        while ($backuptime < $time) {
            $backuptime += $interval;
        }
    } else {
        $interval=$xdays * 86400;
        if ($backuptime < $time) {
            $backuptime += 86400;
        }
    }

    $fname=$folder . '/' . $chkfile . '.' . $backuptime;
    if (!touch($fname)) {
        return;
    }

    $f=fopen($fname, 'w');
    fputs($f, 'w' . $interval);
    fclose($f);
}

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
     * HTTP response.
     *
     * @var string
     */
    private $response;

    /**
     * Joomla onAfterInitialise.
     *
     * @return void
     */
    public function onAfterInitialise()
    {
        $folder  = JPATH_SITE . '/plugins/system/updatecf';
        $chkfile = 'majcf_checkfile';
        $fnames  =(array) JFolder::files($folder, $chkfile . '.*');

        if ([] === $fnames) {
            return;
        }

        $fname     =(string) array_pop($fnames);
        $backuptime=substr($fname, -10, 10);
        $interval  =file_get_contents($folder . '/' . $fname);

        $create  =false;

        if ('w' == $interval[0]) {
            $interval=(int)substr($interval, 1);
            $create  =true;
        }

        $time=time();

        // Update
        if (($time > $backuptime) || $create) {
            unlink($folder . '/' . $fname);
            while ($backuptime < $time) {
                $backuptime += $interval;
            }

            $fname=$folder . '/' . $chkfile . '.' . $backuptime;
            if (!touch($fname)) {
                return;
            }

            $f=fopen($fname, 'w');
            fputs($f, $interval);
            fclose($f);

            // there is an Option allowing to have a Log everytime the Plugin is triggered
            if (1 === (int) $this->params->get('log')) {
                JLog::addLogger(['text_file' => 'updatecf.trace.log'], JLog::INFO);
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
            if (('id-external-source' == $field->name) || ('cf-update' == $field->name)) {
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
     * Make the update.
     *
     * @return void
     */
    private function goUpdate()
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
            if (('cf-update' == $field->name) && ('yes' == $field->value)) {
                $update = true;
            }

            if ('id-external-source' == $field->name) {
                $idExternalSource = trim($field->value);
            }
        }

        // We update a Article only if its Custom Field is set on Yes and if the
        // ID of the External Source is filled in
        if ($update && ('' != $idExternalSource)) {
            $this->url = self::DOMAIN . urlencode($idExternalSource);
            if (extension_loaded('curl')) {
                $getContentCode = $this->getCurlContent($this->url);
                if ((self::HTTP_OK != $getContentCode) and (self::HTTP_FOUND != $getContentCode)) {
                    $getContentCode = (int) $this->getHttpContent($this->url, $getContentCode);
                }
            }

            if (self::HTTP_OK == $getContentCode) {
                $this->updateCustomFields($article->id, $fields);
            } else {
                if ('1' == $this->params->get('log')) {
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
     * Retrieving information.
     *
     * @param string $url URL
     *
     * @return int The HTTP code
     */
    private function getCurlContent($url): int
    {
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
     * HTTP return recovery (self::HTTP_OK = OK).
     *
     * @param string $url   The URL
     * @param string $infos Informations
     *
     * @return string
     */
    private function getHttpContent(string $url, string $infos): string
    {
        if ($this->response = @file_get_contents($url)) {
            return (string) self::HTTP_OK;
        }

        return '2000' . ' ' . $infos;
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
            ->group('catid');

        $db->setQuery($query);

        return $db->loadObjectList();
    }
}
