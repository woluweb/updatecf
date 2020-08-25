# Update Custom Fields

![Joomla 3](https://img.shields.io/static/v1?label=Joomla&message=3.X&style=flat&logo=joomla&logoColor=orange&color=blue)

![Banner](./banner.svg)

A plugin allowing to populate Joomla Custom Fields from Web Services

## Preamble

Please go [https://sync.joomlacustomfields.org/fr/](https://sync.joomlacustomfields.org/fr/) for more explanations and for a demo.

In this plugin, we retrieve information from [https://social.brussels](https://social.brussels), which is a Directory of social organizations in Brussels.

Example of page for a given organization: [https://social.brussels/organisation/470](https://social.brussels/organisation/470).

Corresponding page in JSON-format (which will then be used to synchronize our Custom Field values): [https://social.brussels/rest/organisation/470](https://social.brussels/rest/organisation/470).

Therefore, some things (like the Fields we want to retrieve and synchronize) are *hardcoded* in the plugin.

But you can easily adapt the code to your needs according to your Source

## Setup of the website for this plugin

### Category and articles

We create a category (or several) to which we will assign a series of Custom Fields.

### Custom Fields to be created

The first two Custom Fields are:

* a Custom Field 
  * of Type 'Radio'
  * with Name 'cf-update'
  * having two Values ('yes' and 'no')
* a Custom Field
  * of Type 'Text'
  * with Name 'id-external-source'

The plugin will indeed trigger for a given article _only if_ 'cf-update' is set on 'yes' and if the 'id-external-site' is filled in.

Then we also create a number of other Custom Fields, based on the Fields we want to retrieve from the json of the External Source.

See the code of the plugin to see the Name of the chosen Fields, namely:

* nameofficialfr
* nameofficialnl
* labelfr
* labelnl
* streetfr
* streetnl
* permanencyfr
* permanencynl
* emailfr
* emailnl
* fake-field

The last field is created just to show that we have a default value (for example in case we make a spelling mistake in the Name of some Custom Field).

Joomla natively supports multilangual websites. So we assign the corresponding language (FR / NL) to each Custom Field, meaning that they will appear in the front-end in function of the selected language on the website.

## Options

The plugin has several Options. You can indeed:

* select the Categories for which the synchronization will take place
* select the frequency of the synchronization
* select the time at which the synchronization should trigger (in the TimeZone of the site. Note that the Log file is expressed in UTC)
* enable/disable the Action Log (you can access the Log file on `/administrator/logs/updatecf.trace.log.php`)
