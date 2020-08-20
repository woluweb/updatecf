# Update Custom Fields

![Banner](./banner.svg)

A plugin allowing to populate Joomla Custom Fields from Web Services

## Preamble

In this plugin, we retrieve information from <https://social.brussels>

Example of page for a given organisation :
<https://social.brussels/organisation/470>
Corresponding page in json format (which will be used to synchronize our Custom Field values):
<https://social.brussels/rest/organisation/470>

Therefore, some things (like the Fields we want to retrieve and synchronize) are hardcoded in the plugin.
But you can easily adapt the code to your needs according to your source
