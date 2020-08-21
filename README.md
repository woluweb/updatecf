# Update Custom Fields

![Banner](./banner.svg)

A plugin allowing to populate Joomla Custom Fields from Web Services

## Preamble

In this plugin, we retrieve information from <https://social.brussels>, which is a Directory of social organizations in Brussels.

Example of page for a given organization:
<https://social.brussels/organisation/470>
Corresponding page in json format (which will then be used to synchronize our Custom Field values):
<https://social.brussels/rest/organisation/470>

Therefore, some things (like the Fields we want to retrieve and synchronize) are _hardcoded_ in the plugin.
But you can easily adapt the code to your needs according to your Source

## Setup of the website for this plugin

### Category and articles

We create a category (or several) to which we will assign a series of Custom Fields.

### Custom Fields to be created

The first two Custom Fields are
- a Custom Field 
  - of Type 'Radio'
  - with Name 'cf-update'
  - having two Values ('yes' and 'no')
- a Custom Field
  - of Type 'Text'
  - with Name 'id-external-source'

The plugin will indeed trigger for a given article _only if_ 'cf-update' is set on 'yes' and if the 'id-external-site' is filled in

Then we also create a number of other Custom Fields, based on the Fields we want to retrieve from the json of the External Source.
See the code of the plugin to see the Name of the chosen Fields, namely:
- nameofficialfr
- nameofficialnl
- labelfr
- labelnl
- streetfr
- streetnl
- permanencyfr
- permanencynl
- emailfr
- emailnl
- fake-field

The last field is created just to show that we have a default value (for example in case we make a spelling mistake in the Name of some Custom Field).

## Options

The plugin has several Options. You can indeed:
- select the Categories for which the synchronisation will take place
- select the frequency of the synchronization
- enable/disable the Action Log (you can access the log file on /administrator/logs/updatecf.trace.log)
