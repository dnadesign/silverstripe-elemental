# Silverstripe CMS Elemental

[![Build Status](http://img.shields.io/travis/silverstripe/silverstripe-elemental.svg?style=flat)](https://travis-ci.com/silverstripe/silverstripe-elemental)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe/silverstripe-elemental/badges/quality-score.png?b=4)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-elemental/?branch=4)
[![codecov](https://codecov.io/gh/silverstripe/silverstripe-elemental/branch/4/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-elemental)
[![Silverstripe CMS supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)
[![Version](http://img.shields.io/packagist/v/dnadesign/silverstripe-elemental.svg?style=flat)](https://packagist.org/packages/dnadesign/silverstripe-elemental)
[![License](https://poser.pugx.org/dnadesign/silverstripe-elemental/license.svg)](LICENSE.md)

## Introduction

This module extends a page type to swap the content area for a list of manageable elements to compose a page out
of rather than a single text field. Features supported:

* Versioning of elements
* Ability to add, remove supported elements per _elemental area_

The module provides basic markup for each of the elements but you will likely need to provide your own styles. Replace
the `$Content` variable with `$ElementalArea` in your page templates, and rely on the markup of the individual elements.

For a more detailed overview of using this module, please see [the User help guides](docs/en/userguide/index.md).

## Requirements

* Silverstripe CMS ^4.3
* Versioned Admin ^1.0
* GridFieldExtensions ^3.1

For a Silverstripe CMS 4.1 or 4.2 compatible version of this module, please see the [2.x or 3.x release line](https://github.com/silverstripe/silverstripe-elemental/tree/3#readme).

For a Silverstripe CMS 3.x compatible version of this module, please see the [1 branch, or 1.x release line](https://github.com/silverstripe/silverstripe-elemental/tree/1#readme).

## Installation

```
composer require dnadesign/silverstripe-elemental ^4
```

The following YAML config will enable elements on every `Page` object,
replacing the standard `Content` rich text field.

**mysite/\_config/elements.yml**

```yaml
Page:
  extensions:
    - DNADesign\Elemental\Extensions\ElementalPageExtension
```

In your page type layout template use `$ElementalArea` to render the elements to the page (in place of `$Content`).

## Getting more elements

Note that this module comes by default with the base element and a "Content" element. If you need more, take
a look at some other modules:

## Silverstripe CMS supported content block modules

To learn more about [Silverstripe CMS supported](https://www.silverstripe.org/software/addons/supported-modules-definition/) content block types see, [Creating new blocks](/docs/en/userguide/edit_content.md).

* [dnadesign/silverstripe-elemental](https://github.com/silverstripe/silverstripe-elemental): Text content (built-in)
* [silverstripe/silverstripe-elemental-fileblock](https://github.com/silverstripe/silverstripe-elemental-fileblock): File and image block
* [silverstripe/silverstripe-elemental-bannerblock](https://github.com/silverstripe/silverstripe-elemental-bannerblock): Banner with call-to-action and content
* [dnadesign/silverstripe-elemental-userforms](https://github.com/dnadesign/silverstripe-elemental-userforms): Embed a [user defined form](https://github.com/silverstripe/silverstripe-userforms)

## Examples of community built content block modules (not a comprehensive list - may not be updated for Elemental 4 yet)

* [dnadesign/silverstripe-elemental-list](https://github.com/dnadesign/silverstripe-elemental-list): Container for elements (allows layouts)
* [dnadesign/silverstripe-elemental-virtual](https://github.com/dnadesign/silverstripe-elemental-virtual): Reuse elements across pages
* [dynamic/silverstripe-elemental-blocks](https://github.com/dynamic/silverstripe-elemental-blocks#included-blocks): Elemental Blocks will add the following Elements to your site
  * Accordion: content in collapsable panels
  * Countdown: time left until a set date and time
  * Customer Service: map, directions, and contact info for your location
  * Embeded Code: embed code like iframes, javascript
  * Featured Content: large image, headline, description, link. one per row
  * File List: A list of files for download
  * Flexslider: Flexslider slideshow
  * Gallery: display a collection of images
  * Image: single image
  * oEmbed: embed content from YouTube, SoundCloud, etc
  * Promos: small image, headline, description, link. 3 to 4 per row
  * Recent Blog Posts: list of the most recent posts of a specific blog
  * Section Navigation: list of child pages or pages in current level
  * Sponsors: sponsor logos in a row
  * Tab Set: Create a tabbed interface that uses elements
  * Testimonials: list of customer testimonials, filter by category

## Helpful modules

These modules can extend functionality, and make elemental
more compatible with other approaches in Silverstripe CMS:

* [dnadesign/silverstripe-elemental-subsites](https://github.com/dnadesign/silverstripe-elemental-subsites): Compatibility with the [silverstripe/subsites](https://github.com/silverstripe/silverstripe-subsites) module
* [dnadesign/silverstripe-elemental-skeletons](https://github.com/dnadesign/silverstripe-elemental-skeletons): Creates a template of elements which can be created for a page in the CMS

## Configuration

### Limit to specific page type

If you want to use elements alongside traditional page types,
you can define an "empty" page type and assign the extension only to this type.

**mysite/src/MyElementPage.php**

```php
<?php
class MyElementPage extends Page
{
}
```

**mysite/\_config/elements.yml**

```yaml
MyElementPage:
  extensions:
    - DNADesign\Elemental\Extensions\ElementalPageExtension
```

### Migrating existing page content

You can use the `MigrateContentToElement` BuildTask that is provided to assist with migrating content from pages to elements.
For more information on using this task refer to the [content migration documentation](docs/en/content_migration.md).

### Customize HTML and markup

The basic element area is rendered into the `DNADesign/Elemental/Models/ElementalArea.ss` template. This loops over
each of the element controller instances. Each controller instance will render `$ElementHolder` which represents
the element contained within a holder `div`. The wrapper div is the `ElementHolder.ss` template.

To customise the ElementEditor in the CMS you will need to use the Silverstripe CMS JS Injector to apply transformations
to the necessary React components. [See here](https://docs.silverstripe.org/en/4/developer_guides/customising_the_admin_interface/how_tos/customise_react_components/)
for more information.

### Limit allowed elements

You may wish to only enable certain elements for the CMS authors to choose from rather than the full set. This can be
done according to various page types:

```yaml
Page:
  allowed_elements:
    - DNADesign\Elemental\Models\ElementContent
```

Likewise, you can exclude certain elements from being used.

```yaml
Page:
  disallowed_elements:
    - YourCompany\YourModule\Elements\ElementContact
```

### Sharing elements between pages

By default the page to element relationship is a "has one", meaning you cannot share elements between pages. If this
functionality is desired, you could take a look at the [silverstripe-elemental-virtual](https://github.com/silverstripe/silverstripe-elemental-virtual)
module which helps to achieve this.

### Defining your own elements

An element is as simple as a PHP class which extends `DNADesign\Elemental\Models\BaseElement`, and a template to go
with it (unless you want it to use the default template). After you add the class, ensure you have rebuilt your
database and reload the CMS.

```php
<?php

use DNADesign\Elemental\Models\BaseElement;

class MyElement extends BaseElement
{
    private static $singular_name = 'my element';

    private static $plural_name = 'my elements';

    private static $description = 'What my custom element does';

	public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // ...

        return $fields;
    }

    public function getType()
    {
        return 'My Element';
    }
}
```

#### In-line Editing

Elements can be edited in the CMS using an inline form where all your elements appear together. For elements
that are more complex (e.g. use custom `FormField` classes) you can disable the in-line edit form by setting `private static $inline_editable = false` in your
element class. A `GridFieldDetailForm` will be used to edit blocks that are not in-line editable. Alternatively as the CMS element editor is now React driven, in-line editing functionality can be added to by defining your own React components.

**Note: The default is that all elements are in-line editable.**

If in-line editing is not disabled, whilst not having a custom component defined, custom fields will not be rendered unless the field's `schemaDataType` is set See [Framework's FormField definition](https://github.com/silverstripe/silverstripe-framework/blob/4/src/Forms/FormField.php).

After building your own React components and including them into the CMS, altering the applicable Element's PHP definition to use the new React component can be achieved by setting some `protected` properties of that class.

```php
    protected $schemaDataType = FormField::SCHEMA_DATA_TYPE_CUSTOM;
    protected $schemaComponent = 'BlockLinkField';
```

- The `$schemaDataType` does not need to be CUSTOM, but should not be STRUCTURAL as structural types are not submitted as form data.
- The `$schemaComponent` is the name of the React component you have created to be used.

The above example was taken from [`silverstripe/elemental-bannerblock`](https://github.com/silverstripe/silverstripe-elemental-bannerblock/blob/master/src/Block/BannerBlock.php)

### Defining your own HTML

`MyElement` will be rendered into a `MyElement.ss` template with the `ElementHolder.ss` wrapper. Changing the holder
template can be done via YAML, or by using a `$controller_template` on your subclass.

```php
private static $controller_template = 'MyElementHolder';
```

To customise existing block templates such as `Content` and `Form` templates, copy the relevant files from
`vendor/dnadesign/silverstripe-elemental/templates` to your theme. When doing this, ensure you match the folder
structure (PHP class namespace) to ensure that your new template version takes priority.

**Note:** The default set of elements follow the [BEM (Block Element Modifier])(http://getbem.com/) class naming
convention, which allows developers to style individual parts of the DOM without unnecessarily nested CSS. Where
possible, we encourage you to follow this naming system.


#### Position Helpers

In your `BaseElement` template you can use the following variables for additional
logic or styling helpers. They behave in the same way traditional `SS_Viewer`
methods work either returning a `Boolean`, `String` or a `Int`

  1. `$First` (boolean)
  1. `$Last` (boolean)
  1. `$Pos` (int)
  1. `$TotalItems` (int)
  1. `$EvenOdd` (string - 'even' or 'odd')


```
<div class="element element--{$EvenOdd} <% if First %>element--first<% end_if %> <% if Last %>element--last<% end_if %>">
    // ...
</div>
```

### Style variants

Via YAML you can configure a whitelist of style variants for each `BaseElement`
subclass. For instance, if you have `dark` and `light` variations of your
content block you would enter the following in YAML in the format
(class: 'Description'). The class will be added to the `ElementHolder`.

```yml
DNADesign\Elemental\Models\ElementContent:
  styles:
    light: 'Light Background'
    dark: 'Dark Background'
```


### Disabling the default stylesheets

When installing this module, there may be a default set of CSS stylesheets that come to provide some examples for the
various default element types for the front-end website.

You can disable this with YAML configuration:

```yaml
# File: mysite/_config/elements.yml
DNADesign\Elemental\Controllers\ElementController:
  include_default_styles: false
```

### Implementing search

The Elemental module comes with an indexer for Solr (via the
[silverstripe-fulltextsearch module](https://github.com/silverstripe/silverstripe-fulltextsearch)). You can enable
this index in your search engine to ensure that a page's elemental area content is indexed under the page's data.

For information on configuring Solr please see [the fulltextsearch documentation](https://github.com/silverstripe/silverstripe-fulltextsearch).

**Note:** If using this indexer, be aware that HTML tags will be stripped from the content before it is indexed.
The Solr search results may add in emphasis tags or other formatting around matched key words, so you may need
to allow unescaped HTML in your search results template. You should use the `$Excerpt` property (see
`SolrIndex::search` for more) to display the relevant search matches.

### Disabling CMS content search

When installing this module, the page model admin search will look for a term in the entire content of each elemental pages.
This is done by rendering each page, which can be resource hungry and make the search timeout.

You can disable this with YAML configuration:

```yaml
# File: mysite/_config/elements.yml
DNADesign\Elemental\Controllers\ElementSiteTreeFilterSearch:
  search_for_term_in_content: false
```

### Usage of GridField

This module used to use GridField to create and update Elements in the CMS. This has now been largely succeeded by a JavaScript interface via React. However elements that are marked as being incompatible with in-line editing will still use the GridField method.

## Building the elemental frontend assets

This module uses the [Silverstripe CMS Webpack module](https://github.com/silverstripe/webpack-config), and inherits
things from the core Silverstripe CMS 4 modules, such as a core variable sheet and Javascript components.

When making changes to either the SASS or Javascript files, ensure you change the source files in `client/src/`.

You can have [yarn](https://yarnpkg.com/en/) watch and rebuild delta changes as you make them (for development only):

```
yarn watch
```

When you're ready to make a pull request you can rebuild them, which will also
minify everything. Note that `watch` will generate source map files which you
shouldn't commit in for your final pull request. To minify and package:

```
yarn build
```

You'll need to have [yarn installed](https://yarnpkg.com/en/docs/install)
globally in your command line.

**Note:** If adding or modifying colours, spacing, font sizes etc. please try
and use an appropriate variable from the silverstripe/admin module if available.

## Integration with other modules

* [Multiple languages with tractorcow/silverstripe-fluent](docs/en/advanced_setup.md)
* [Search through silverstripe/fulltextsearch](docs/en/searching-blocks.md)

## Upgrading

For developers upgrading from Elemental 3 to 4, [see the upgrade guide](docs/en/upgrading_to_4.md).

## Screenshots

![Elemental content block overview](docs/en/userguide/_images/content-block-overview.png)

## Versioning

This library follows [Semver](http://semver.org). According to Semver, you will
be able to upgrade to any minor or patch version of this library without any
breaking changes to the public API. Semver also requires that we clearly define
the public API for this library.

All methods, with `public` visibility, are part of the public API. All other
methods are not part of the public API. Where possible, we'll try to keep
`protected` methods backwards-compatible in minor/patch versions, but if you're
overriding methods then please test your work before upgrading.

## Reporting Issues

Please [create an issue](https://github.com/silverstripe/silverstripe-elemental/issues) for any bugs you've found, or features you're missing.

## Credits

Silverstripe Elemental was created by [DNA Design](https://www.dna.co.nz/).

CMS Icon blocks by Creative Stall from the Noun Project.
