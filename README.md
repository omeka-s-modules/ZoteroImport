# ZoteroImport

Import items from Zotero to Omeka S


## Installation

See general end user documentation for [Installing a module](https://github.com/omeka/omeka-s-enduser/blob/master/modules/modules.md#installing-modules)

## Configuration

Optionally import the Fedora Vocabulary and Linked Data Platform Vocabulary. If you do so, data in these vocabularies will also be imported into Omeka S.

## Usage

### Importing

From the main navigation on the left of the admin screen, click Zotero Import

1. Choose an Item Set for imported items.

1. Choose collection type, "User" or "Group"
  
1. Required. The user ID can be found on the "Feeds/API" section of the Zotero settings page. The group ID can be found on the Zotero group library page by looking at the URL of "Subscribe to this feed".
  
1. Not required. The collection key can be found on the Zotero library page by looking at the URL when looking at the collection. 

1. Optional. Enter your API key to import private data.

1. Choose whether to import files. The API key is required to import files.

1. Choose "Added After". Only import items that have been added to Zotero after this datetime.

1. Hit Submit.

