<?php

namespace DNADesign\Elemental\Models;

use DNADesign\Elemental\Controllers\ElementController;
use DNADesign\Elemental\Forms\TextCheckboxGroupField;
use DNADesign\Elemental\ORM\FieldType\DBObjectType;
use Exception;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\SchemaBuilder;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\VersionedAdmin\Forms\HistoryViewerField;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\View\Requirements;
use RuntimeException;

/**
 * Class BaseElement
 * @package DNADesign\Elemental\Models
 *
 * @property string $Title
 * @property bool $ShowTitle
 * @property int $Sort
 * @property string $ExtraClass
 * @property string $Style
 * @property int $ParentID
 *
 * @method ElementalArea Parent()
 */
class BaseElement extends DataObject
{
    /**
     * Override this on your custom elements to specify a CSS icon class
     *
     * @var string
     */
    private static $icon = 'font-icon-block-layout';

    /**
     * Describe the purpose of this element
     *
     * @config
     * @var string
     */
    private static $description = 'Base element class';

    private static $db = [
        'Title' => 'Varchar(255)',
        'ShowTitle' => 'Boolean',
        'Sort' => 'Int',
        'ExtraClass' => 'Varchar(255)',
        'Style' => 'Varchar(255)'
    ];

    private static $has_one = [
        'Parent' => ElementalArea::class
    ];

    private static $extensions = [
        Versioned::class
    ];

    private static $casting = [
        'BlockSchema' => DBObjectType::class,
        'IsLiveVersion' => DBBoolean::class,
        'IsPublished' => DBBoolean::class,
        'canCreate' => DBBoolean::class,
        'canPublish' => DBBoolean::class,
        'canUnpublish' => DBBoolean::class,
        'canDelete' => DBBoolean::class,
    ];

    private static $versioned_gridfield_extensions = true;

    private static $table_name = 'Element';

    /**
     * @var string
     */
    private static $controller_class = ElementController::class;

    /**
     * @var string
     */
    private static $controller_template = 'ElementHolder';

    /**
     * @var ElementController
     */
    protected $controller;

    /**
     * Cache various data to improve CMS load time
     *
     * @internal
     * @var array
     */
    protected $cacheData;

    private static $default_sort = 'Sort';

    private static $singular_name = 'block';

    private static $plural_name = 'blocks';

    private static $summary_fields = [
        'EditorPreview' => 'Summary'
    ];

    /**
     * @config
     * @var array
     */
    private static $styles = [];

    private static $searchable_fields = [
        'ID' => [
            'field' => NumericField::class,
        ],
        'Title',
        'LastEdited'
    ];

    /**
     * Enable for backwards compatibility
     *
     * @var boolean
     */
    private static $disable_pretty_anchor_name = false;

    /**
     * Set to false to prevent an in-line edit form from showing in an elemental area. Instead the element will be
     * clickable and a GridFieldDetailForm will be used.
     *
     * @config
     * @var bool
     */
    private static $inline_editable = true;

    /**
     * Display a show title button
     *
     * @config
     * @var boolean
     */
    private static $displays_title_in_template = true;

    /**
     * Store used anchor names, this is to avoid title clashes
     * when calling 'getAnchor'
     *
     * @var array
     */
    protected static $used_anchors = [];

    /**
     * For caching 'getAnchor'
     *
     * @var string
     */
    protected $anchor = null;

    /**
     * Basic permissions, defaults to page perms where possible.
     *
     * @param Member $member
     * @return boolean
     */
    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        if ($this->hasMethod('getPage')) {
            if ($page = $this->getPage()) {
                return $page->canView($member);
            }
        }

        return (Permission::check('CMS_ACCESS', 'any', $member)) ? true : null;
    }

    /**
     * Basic permissions, defaults to page perms where possible.
     *
     * @param Member $member
     *
     * @return boolean
     */
    public function canEdit($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        if ($this->hasMethod('getPage')) {
            if ($page = $this->getPage()) {
                return $page->canEdit($member);
            }
        }

        return (Permission::check('CMS_ACCESS', 'any', $member)) ? true : null;
    }

    /**
     * Basic permissions, defaults to page perms where possible.
     *
     * Uses archive not delete so that current stage is respected i.e if a
     * element is not published, then it can be deleted by someone who doesn't
     * have publishing permissions.
     *
     * @param Member $member
     *
     * @return boolean
     */
    public function canDelete($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        if ($this->hasMethod('getPage')) {
            if ($page = $this->getPage()) {
                return $page->canArchive($member);
            }
        }

        return (Permission::check('CMS_ACCESS', 'any', $member)) ? true : null;
    }

    /**
     * Basic permissions, defaults to page perms where possible.
     *
     * @param Member $member
     * @param array $context
     *
     * @return boolean
     */
    public function canCreate($member = null, $context = array())
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return (Permission::check('CMS_ACCESS', 'any', $member)) ? true : null;
    }

    /**
     * Increment the sort order if one hasn't been already defined. This
     * ensures that new elements are created at the end of the list by default.
     *
     * {@inheritDoc}
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->Sort) {
            if ($this->hasExtension(Versioned::class)) {
                $records = Versioned::get_by_stage(BaseElement::class, Versioned::DRAFT);
            } else {
                $records = BaseElement::get();
            }

            $records = $records->filter('ParentID', $this->ParentID);

            $this->Sort = $records->max('Sort') + 1;
        }
    }

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            // Remove relationship fields
            $fields->removeByName('ParentID');
            $fields->removeByName('Sort');

            // Remove link and file tracking tabs
            $fields->removeByName(['LinkTracking', 'FileTracking']);

            $fields->addFieldToTab(
                'Root.Settings',
                TextField::create('ExtraClass', _t(__CLASS__ . '.ExtraCssClassesLabel', 'Custom CSS classes'))
                    ->setAttribute(
                        'placeholder',
                        _t(__CLASS__ . '.ExtraCssClassesPlaceholder', 'my_class another_class')
                    )
            );

            // Rename the "Settings" tab
            $fields->fieldByName('Root.Settings')
                ->setTitle(_t(__CLASS__ . '.SettingsTabLabel', 'Settings'));

            // Add a combined field for "Title" and "Displayed" checkbox in a Bootstrap input group
            $fields->removeByName('ShowTitle');

            if ($this->config()->get('displays_title_in_template')) {
                $fields->replaceField(
                    'Title',
                    TextCheckboxGroupField::create()
                        ->setName('Title')
                );
            }

            // Rename the "Main" tab
            $fields->fieldByName('Root.Main')
                ->setTitle(_t(__CLASS__ . '.MainTabLabel', 'Content'));

            $fields->addFieldsToTab('Root.Main', [
                HiddenField::create('AbsoluteLink', false, Director::absoluteURL($this->PreviewLink())),
                HiddenField::create('LiveLink', false, Director::absoluteURL($this->Link())),
                HiddenField::create('StageLink', false, Director::absoluteURL($this->PreviewLink())),
            ]);

            $styles = $this->config()->get('styles');

            if ($styles && count($styles) > 0) {
                $styleDropdown = DropdownField::create('Style', _t(__CLASS__.'.STYLE', 'Style variation'), $styles);

                $fields->insertBefore($styleDropdown, 'ExtraClass');

                $styleDropdown->setEmptyString(_t(__CLASS__.'.CUSTOM_STYLES', 'Select a style..'));
            } else {
                $fields->removeByName('Style');
            }

            // Hide the navigation section of the tabs in the React component {@see silverstripe/admin Tabs}
            $rootTabset = $fields->fieldByName('Root');
            $rootTabset->setSchemaState(['hideNav' => true]);

            if ($this->isInDB()) {
                $fields->addFieldsToTab('Root.History', [
                    HistoryViewerField::create('ElementHistory'),
                ]);
                // Add class to containing tab
                $fields->fieldByName('Root.History')
                    ->addExtraClass('elemental-block__history-tab tab--history-viewer');

                // Hack: automatically navigate to the History tab with `#Root_History` is in the URL
                // To unhack, fix this: https://github.com/silverstripe/silverstripe-admin/issues/911
                Requirements::customScript(<<<JS
    document.addEventListener('DOMContentLoaded', () => {
        var hash = window.location.hash.substr(1);
        if (hash !== 'Root_History') {
            return null;
        }
        jQuery('.cms-tabset-nav-primary li[aria-controls="Root_History"] a').trigger('click')
    });
JS
                );
            }
        });

        return parent::getCMSFields();
    }

    /**
     * Get the type of the current block, for use in GridField summaries, block
     * type dropdowns etc. Examples are "Content", "File", "Media", etc.
     *
     * @return string
     */
    public function getType()
    {
        $default = $this->i18n_singular_name() ?: 'Block';
        return _t(__CLASS__ . '.BlockType' , $default);
    }

    /**
     * Proxy through to configuration setting 'inline_editable'
     *
     * @return bool
     */
    public function inlineEditable()
    {
        return static::config()->get('inline_editable');
    }

    /**
     * @param ElementController $controller
     *
     * @return $this
     */
    public function setController($controller)
    {
        $this->controller = $controller;

        return $this;
    }

    /**
     * @throws Exception If the specified controller class doesn't exist
     *
     * @return ElementController
     */
    public function getController()
    {
        if ($this->controller) {
            return $this->controller;
        }

        $controllerClass = self::config()->controller_class;

        if (!class_exists($controllerClass)) {
            throw new Exception(
                'Could not find controller class ' . $controllerClass . ' as defined in ' . static::class
            );
        }

        $this->controller = Injector::inst()->create($controllerClass, $this);
        $this->controller->doInit();

        return $this->controller;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setAreaRelationNameCache($name)
    {
        $this->cacheData['area_relation_name'] = $name;

        return $this;
    }

    /**
     * @return Controller
     */
    public function Top()
    {
        return (Controller::has_curr()) ? Controller::curr() : null;
    }

    /**
     * Default way to render element in templates. Note that all blocks should
     * be rendered through their {@link ElementController} class as this
     * contains the holder styles.
     *
     * @return string|null HTML
     */
    public function forTemplate($holder = true)
    {
        $templates = $this->getRenderTemplates();

        if ($templates) {
            return $this->renderWith($templates);
        }

        return null;
    }

    /**
     * @param string $suffix
     *
     * @return array
     */
    public function getRenderTemplates($suffix = '')
    {
        $classes = ClassInfo::ancestry($this->ClassName);
        $classes[static::class] = static::class;
        $classes = array_reverse($classes);
        $templates = [];

        foreach ($classes as $key => $class) {
            if ($class == BaseElement::class) {
                continue;
            }

            if ($class == DataObject::class) {
                break;
            }

            if ($style = $this->Style) {
                $templates[$class][] = $class . $suffix . '_'. $this->getAreaRelationName() . '_' . $style;
                $templates[$class][] = $class . $suffix . '_' . $style;
            }
            $templates[$class][] = $class . $suffix . '_'. $this->getAreaRelationName();
            $templates[$class][] = $class . $suffix;
        }

        $this->extend('updateRenderTemplates', $templates, $suffix);

        $templateFlat = [];
        foreach ($templates as $class => $variations) {
            $templateFlat = array_merge($templateFlat, $variations);
        }

        return $templateFlat;
    }

    /**
     * Given form data (wit
     *
     * @param $data
     */
    public function updateFromFormData($data)
    {
        $cmsFields = $this->getCMSFields();

        foreach ($data as $field => $datum) {
            $field = $cmsFields->dataFieldByName($field);

            if (!$field) {
                continue;
            }

            $field->setSubmittedValue($datum);
            $field->saveInto($this);
        }
    }

    /**
     * Strip all namespaces from class namespace.
     *
     * @param string $classname e.g. "\Fully\Namespaced\Class"
     *
     * @return string following the param example, "Class"
     */
    protected function stripNamespacing($classname)
    {
        $classParts = explode('\\', $classname);
        return array_pop($classParts);
    }

    /**
     * @return string
     */
    public function getSimpleClassName()
    {
        return strtolower($this->sanitiseClassName($this->ClassName, '__'));
    }

    /**
     * @return null|SiteTree
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function getPage()
    {
        // Allow for repeated calls to be cached
        if (isset($this->cacheData['page'])) {
            return $this->cacheData['page'];
        }


        $class = DataObject::getSchema()->hasOneComponent($this, 'Parent');
        $area = ($this->ParentID) ? DataObject::get_by_id($class, $this->ParentID) : null;
        if ($area instanceof ElementalArea && $area->exists()) {
            $this->cacheData['page'] = $area->getOwnerPage();
            return $this->cacheData['page'];
        }

        return null;
    }

    /**
     * Get a unique anchor name
     *
     * @return string
     */
    public function getAnchor()
    {
        if ($this->anchor !== null) {
            return $this->anchor;
        }

        $anchorTitle = '';

        if (!$this->config()->disable_pretty_anchor_name) {
            if ($this->hasMethod('getAnchorTitle')) {
                $anchorTitle = $this->getAnchorTitle();
            } elseif ($this->config()->enable_title_in_template) {
                $anchorTitle = $this->getField('Title');
            }
        }

        if (!$anchorTitle) {
            $anchorTitle = 'e'.$this->ID;
        }

        $filter = URLSegmentFilter::create();
        $titleAsURL = $filter->filter($anchorTitle);

        // Ensure that this anchor name isn't already in use
        // ie. If two elemental blocks have the same title, it'll append '-2', '-3'
        $result = $titleAsURL;
        $count = 1;
        while (isset(self::$used_anchors[$result]) && self::$used_anchors[$result] !== $this->ID) {
            ++$count;
            $result = $titleAsURL . '-' . $count;
        }
        self::$used_anchors[$result] = $this->ID;
        return $this->anchor = $result;
    }

    /**
     * @param string|null $action
     * @return string|null
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function AbsoluteLink($action = null)
    {
        if ($page = $this->getPage()) {
            $link = $page->AbsoluteLink($action) . '#' . $this->getAnchor();

            $this->extend('updateAbsoluteLink', $link);

            return $link;
        }

        return null;
    }

    /**
     * @param string|null $action
     * @return string
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function Link($action = null)
    {
        if ($page = $this->getPage()) {
            $link = $page->Link($action) . '#' . $this->getAnchor();

            $this->extend('updateLink', $link);

            return $link;
        }

        return null;
    }

    /**
     * @param string|null $action
     * @return string
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function PreviewLink($action = null)
    {
        $action = $action . '?ElementalPreview=' . mt_rand();
        $link = $this->Link($action);
        $this->extend('updatePreviewLink', $link);

        return $link;
    }

    /**
     * @return boolean
     */
    public function isCMSPreview()
    {
        if (Controller::has_curr()) {
            $controller = Controller::curr();

            if ($controller->getRequest()->requestVar('CMSPreview')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param bool $directLink Indicates that the GridFieldDetailEdit form link should be given even if the block can be
     *                         edited in-line.
     * @return null|string
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function CMSEditLink($directLink = false)
    {
        // Allow for repeated calls to be returned from cache
        if (isset($this->cacheData['cms_edit_link'])) {
            return $this->cacheData['cms_edit_link'];
        }

        $relationName = $this->getAreaRelationName();
        $page = $this->getPage();

        if (!$page) {
            $link = null;
            $this->extend('updateCMSEditLink', $link);
            return $link;
        }

        if (!$page instanceof SiteTree && method_exists($page, 'CMSEditLink')) {
            $link = Controller::join_links($page->CMSEditLink(), 'ItemEditForm');
        } else {
            $link = $page->CMSEditLink();
        }

        // In-line editable blocks should just take you to the page. Editable ones should add the suffix for detail form
        if (!$this->inlineEditable() || $directLink) {
            $link = Controller::join_links(
                singleton(CMSPageEditController::class)->Link('EditForm'),
                $page->ID,
                'field/' . $relationName . '/item/',
                $this->ID,
                'edit'
            );
        }

        $this->extend('updateCMSEditLink', $link);

        $this->cacheData['cms_edit_link'] = $link;
        return $link;
    }

    /**
     * Retrieve a elemental area relation for creating cms links
     *
     * @return int|string The name of a valid elemental area relation
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function getAreaRelationName()
    {
        // Allow repeated calls to return from internal cache
        if (isset($this->cacheData['area_relation_name'])) {
            return $this->cacheData['area_relation_name'];
        }

        $page = $this->getPage();

        $result = 'ElementalArea';

        if ($page) {
            $has_one = $page->config()->get('has_one');
            $area = $this->Parent();

            foreach ($has_one as $relationName => $relationClass) {
                if ($page instanceof BaseElement && $relationName === 'Parent') {
                    continue;
                }
                if ($relationClass === $area->ClassName && $page->{$relationName}()->ID === $area->ID) {
                    $result = $relationName;
                    break;
                }
            }
        }

        $this->setAreaRelationNameCache($result);

        return $result;
    }

    /**
     * Sanitise a model class' name for inclusion in a link.
     *
     * @return string
     */
    public function sanitiseClassName($class, $delimiter = '-')
    {
        return str_replace('\\', $delimiter, $class);
    }

    public function unsanitiseClassName($class, $delimiter = '-')
    {
        return str_replace($delimiter, '\\', $class);
    }

    /**
     * @return null|string
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function getEditLink()
    {
        return Director::absoluteURL($this->CMSEditLink());
    }

    /**
     * @return DBField|null
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function PageCMSEditLink()
    {
        if ($page = $this->getPage()) {
            return DBField::create_field('HTMLText', sprintf(
                '<a href="%s">%s</a>',
                $page->CMSEditLink(),
                $page->Title
            ));
        }

        return null;
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        return 'text/html';
    }

    /**
     * This can be overridden on child elements to create a summary for display
     * in GridFields.
     *
     * @return string
     */
    public function getSummary()
    {
        return '';
    }

    /**
     * The block config defines a set of data (usually set through config on the element) that will be made available in
     * client side config. Individual element types may choose to add config variable for use in React code
     *
     * @return array
     */
    public static function getBlockConfig()
    {
        return [];
    }

    /**
     * The block actions is an associative array available for providing data to the client side to be used to describe
     * actions that may be performed. This is available as a plain "ObjectType" in the GraphQL schema.
     *
     * By default the only action is "edit" which is simply the URL where the block may be edited.
     *
     * To modify the actions, either use the extension point or overload the `provideBlockSchema` method.
     *
     * @internal This API may change in future. Treat this as a `final` method.
     * @return array
     */
    public function getBlockSchema()
    {
        $blockSchema = $this->provideBlockSchema();

        $this->extend('updateBlockSchema', $blockSchema);

        return $blockSchema;
    }

    /**
     * Provide block schema data, which will be serialised and sent via GraphQL to the editor client.
     *
     * Overload this method in child element classes to augment, or use the extension point on `getBlockSchema`
     * to update it from an `Extension`.
     *
     * @return array
     * @throws SchemaBuilderException
     * @throws ValidationException
     */
    protected function provideBlockSchema()
    {
        return [
            'typeName' => static::getGraphQLTypeName(),
            'actions' => [
                'edit' => $this->getEditLink(),
            ],
        ];
    }

    /**
     * Generate markup for element type icons suitable for use in GridFields.
     *
     * @return null|DBHTMLText
     */
    public function getIcon()
    {
        $data = ArrayData::create([]);

        $iconClass = $this->config()->get('icon');
        if ($iconClass) {
            $data->IconClass = $iconClass;

            // Add versioned states (rendered as a circle over the icon)
            if ($this->hasExtension(Versioned::class)) {
                $data->IsVersioned = true;
                if ($this->isOnDraftOnly()) {
                    $data->VersionState = 'draft';
                    $data->VersionStateTitle = _t(
                        'SilverStripe\\Versioned\\VersionedGridFieldState\\VersionedGridFieldState.ADDEDTODRAFTHELP',
                        'Item has not been published yet'
                    );
                } elseif ($this->isModifiedOnDraft()) {
                    $data->VersionState = 'modified';
                    $data->VersionStateTitle = $data->VersionStateTitle = _t(
                        'SilverStripe\\Versioned\\VersionedGridFieldState\\VersionedGridFieldState.MODIFIEDONDRAFTHELP',
                        'Item has unpublished changes'
                    );
                }
            }

            return $data->renderWith(__CLASS__ . '/PreviewIcon');
        }

        return null;
    }

    /**
     * Get a description for this content element, if available
     *
     * @return string
     */
    public function getDescription()
    {
        $description = $this->config()->uninherited('description');
        if ($description) {
            return _t(__CLASS__ . '.Description', $description);
        }
        return '';
    }

    /**
     * Generate markup for element type, with description suitable for use in
     * GridFields.
     *
     * @return DBField
     */
    public function getTypeNice()
    {
        $description = $this->getDescription();
        $desc = ($description) ? ' <span class="element__note"> &mdash; ' . $description . '</span>' : '';

        return DBField::create_field(
            'HTMLVarchar',
            $this->getType() . $desc
        );
    }

    /**
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function getEditorPreview()
    {
        $templates = $this->getRenderTemplates('_EditorPreview');
        $templates[] = BaseElement::class . '_EditorPreview';

        return $this->renderWith($templates);
    }

    /**
     * @return Member
     */
    public function getAuthor()
    {
        if ($this->AuthorID) {
            return Member::get()->byId($this->AuthorID);
        }

        return null;
    }

    /**
     * Get a user defined style variant for this element, if available
     *
     * @return string
     */
    public function getStyleVariant()
    {
        $style = $this->Style;
        $styles = $this->config()->get('styles');

        if (isset($styles[$style])) {
            $style = strtolower($style);
        } else {
            $style = '';
        }

        $this->extend('updateStyleVariant', $style);

        return $style;
    }

    /**
     * @return mixed|null
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function getPageTitle()
    {
        $page = $this->getPage();

        if ($page) {
            return $page->Title;
        }

        return null;
    }

    /**
     * @return boolean
     */
    public function First()
    {
        return ($this->Parent()->Elements()->first()->ID === $this->ID);
    }

    /**
     * @return boolean
     */
    public function Last()
    {
        return ($this->Parent()->Elements()->last()->ID === $this->ID);
    }

    /**
     * @return int
     */
    public function TotalItems()
    {
        return $this->Parent()->Elements()->count();
    }

    /**
     * Returns the position of the current element.
     *
     * @return int
     */
    public function Pos()
    {
        return ($this->Parent()->Elements()->filter('Sort:LessThan', $this->Sort)->count() + 1);
    }

    /**
     * @return string
     */
    public function EvenOdd()
    {
        $odd = (bool) ($this->Pos() % 2);

        return  ($odd) ? 'odd' : 'even';
    }

    /**
     * @return string
     */
    public static function getGraphQLTypeName(): string
    {
        return class_exists(StaticSchema::class)
            ? StaticSchema::inst()->typeNameForDataObject(static::class)
            : str_replace('\\', '_', static::class);
    }
}
