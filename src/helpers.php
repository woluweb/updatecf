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

namespace Woluweb;

use Joomla\Registry\Registry;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Helper for the Update Custom Fields system plugin.
 */
class HelpersUpdateCf
{
    /**
     * Used to detect if the Joomla major version is 4 or more.
     *
     * @var int
     */
    private const JOOMLA_4 = 4;

    /**
     * Retrieve the parameters of the UpdateCf system plugin.
     *
     * @return \JRegistry|null
     */

    public static function getParams(): \JRegistry
    {
        $plugin = \JPluginHelper::getPlugin('system', 'updatecf');

        // return null when the plugin is disabled
        return $plugin ? new \JRegistry($plugin->params) : null;
    }

    /**
     * Get the list of articles of a given category/ies Id).
     *
     * @param array $categories List of catogories to process. Retrieve all articles
     *                          in these categories and process article by article
     *
     * @return array|null
     */
    public static function getListArticles(array $categories = []): ?array
    {
        if ([] === $categories) {
            $tmp = self::getAllCategories();
            foreach ($tmp as $catid) {
                if ($catid->count > 0) {
                    $categories[] = $catid->id;
                }
            }
        }

        $joomlaVersion = new \JVersion();
        $majorVersion  = (int) substr($joomlaVersion->getShortVersion(), 0, 1);

        if ($majorVersion >= self::JOOMLA_4) {
            $model     = new \ArticlesModel(['ignore_request' => true]);
        } else {
            \JLoader::register('ContentModelArticles', JPATH_SITE . '/components/com_content/models/articles.php');
            $model = \JModelLegacy::getInstance('Articles', 'ContentModel', ['ignore_request' => true]);
        }

        if (!$model) {
            return [];
        }

        $params = new Registry();

        $model->setState('params', $params);
        $model->setState('list.limit', 0);
        $model->setState('list.start', 0);
        $model->setState('filter.tag', 0);
        $model->setState('list.ordering', 'a.ordering');
        $model->setState('list.direction', 'ASC');
        $model->setState('filter.published', 1);

        $model->setState('filter.category_id', $categories);

        $model->setState('filter.featured', 'show');
        $model->setState('filter.author_id', '');
        $model->setState('filter.author_id.include', 1);
        $model->setState('filter.access', false);

        if (null === $model) {
            return [];
        }

        $items =  $model->getItems();

        if ($error = $model->getError()) {
            JLog::add('ERROR', JLog::ERROR, $error);

            return [];
        }

        return $items;
    }

    /**
     * Retrieving information thanks to curl.
     *
     * @param string $url URL to query
     *
     * @return array<int, string>
     */
    public static function getJsonContent(string $url): array
    {
        if (!function_exists('curl_init')) {
            // cURL isn't enabled, try with file_get_content
            return ['message'=>@file_get_contents($url, 'r')];
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_REFERER, 'http://www.google.com');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');

        $response = (string) curl_exec($ch);

        curl_close($ch);

        if ('' === $response) {
            return [];
        }

        return json_decode($response, true);
    }

    /**
     * Safely kill a file.
     *
     * @param string $path Basename of the file (no path)
     *
     * @return void
     */
    public static function killFile(string $path): void
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
     * Get all Joomla categories but only those with at least one linked article.
     *
     * @return array<mixed>
     */
    public static function getAllCategories(): array
    {
        $db = \JFactory::getDbo();

        $query = $db->getQuery(true);

        $query->select('distinct cat.id')
            ->from('#__categories as cat')
            ->join('left', '#__content cont on cat.id = cont.catid ')
            ->where('(extension like "com_content") AND (cat.published = 1) ' .
                'AND (cat.access = 1) AND (cont.state = 1)')
            ->having('count(cont.id) > 0')
            ->group('cont.catid');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Update custom fields in the article.
     *
     * @param stdClass $article Joomla article
     *
     * @return bool
     */
    public static function updateArticleCustomFields(\stdClass $article): array
    {
        $config  = self::getParams();
        $urlJson = $config['url_json_provider'];

        if ('' === $urlJson) {
            return ['success' => false, 'message' => JText::_('PLG_CONTENT_UPDATECF_URL_JSON_PROVIDER_MISSING')];
        }

        $item       = [];
        $item['id'] = $article->id;

        \JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');
        $fields = \FieldsHelper::getFields('com_content.article', $item);

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

        if (!$update) {
            return [];
        }

        if ('' === $idExternalSource) {
            return [];
        }

        // Query f.i. https://social.brussels/rest/organisation/13219
        $url = $urlJson . urlencode($idExternalSource);

        $response = self::getJsonContent($url);

        if ([] === $response) {
            $info = sprintf(
                \JText::_('PLG_CONTENT_UPDATECF_UPDATING_ARTICLE_ERROR'),
                $article->title,
                $article->id,
                $url
            );

            return ['success' => false, 'message' => $info];
        }

        // Updating custom fields in the article
        self::updateCustomFields($article->id, $fields, $response);

        $info = sprintf(\JText::_('PLG_CONTENT_UPDATECF_UPDATING_ARTICLE'), $article->title, $article->id, $url);

        return ['success' => true, 'message' => $info];
    }

    /**
     * Create the last run file.
     *
     * @param string $path    Basename of the file (no path)
     * @param string $content The content to write in the file
     *
     * @return void
     */
    public static function createLastRun(string $path, string $content): void
    {
        if ('' === $path) {
            return;
        }

        $path = dirname(__FILE__) . '/' . \JFile::makeSafe($path);

        try {
            // Create the last run file
            $fileLastRun = fopen($path, 'w');
            fputs($fileLastRun, $content);
            fclose($fileLastRun);
        } catch (\Exception $e) {
            \JLog::add('ERROR', JLog::ERROR, $e->getMessage());
        }
    }

    /**
     * Update of the Custom Fields values based on the external source (webservices).
     *
     * @param int   $articleId The ID of the article
     * @param array $fields    The list of custom fields
     * @param array $json      JSON returned by the JSON provider
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     *
     * @return void
     */
    private static function updateCustomFields(int $articleId, array $fields, array $json): void
    {
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
                    $value= $json['legalStatus']['labelFr'] ?? '';

                    break;
                case 'labelnl':
                    $value= $json['legalStatus']['labelNl'] ?? '';

                    break;
                case 'nameofficialfr':
                    $value= $json['nameOfficialFr'] ?? '';

                    break;
                case 'nameofficialnl':
                    $value= $json['nameOfficialNl'] ?? '';

                    break;
                case 'descriptionfr':
                    $value= $json['descriptionFr'] ?? '';

                    break;
                case 'descriptionnl':
                    $value= $json['descriptionNl'] ?? '';

                    break;
                case 'permanencyfr':
                    $value= $json['permanencyFr'] ?? '';

                    break;
                case 'permanencynl':
                    $value= $json['permanencyNl'] ?? '';

                    break;
                case 'legalfr':
                    $value=  $json['legalStatus']['labelFr'] ?? '';

                    break;
                case 'legalnl':
                    $value=  $json['legalStatus']['labelNl'] ?? '';

                    break;
                case 'streetfr':
                    $value=  $json['address']['streetFr'] ?? '';

                    break;
                case 'streetnl':
                    $value=  $json['address']['streetNl'] ?? '';

                    break;
                case 'emailfr':
                    $value= self::getRepeat($json['emailFr'] ?? '', 'emailfr', 'email');

                    break;
                case 'emailnl':
                    $value= self::getRepeat($json['emailNl'] ?? '', 'emailnl', 'email');

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
     * Returns the elements of an array in a repeatable field.
     *
     * @param array  $array An array
     * @param string $field A field
     * @param string $name  A name
     *
     * @return string
     */
    private static function getRepeat(array $array, string $field, string $name): string
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
}
