<?php

namespace DNADesign\Elemental\Models;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\UnsavedRelationList;
use SilverStripe\Versioned\Versioned;

/**
 * Class ElementalArea
 * @package DNADesign\Elemental\Models
 *
 * @property string $OwnerClassName
 */
class ElementalArea extends DataObject
{
    private static $db = [
        'OwnerClassName' => 'DBClassName("SilverStripe\\\\ORM\\\\DataObject")',
    ];

    private static $has_many = [
        'Elements' => BaseElement::class,
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $owns = [
        'Elements',
    ];

    private static $cascade_deletes = [
        'Elements',
    ];

    private static $cascade_duplicates = [
        'Elements',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
    ];

    private static $table_name = 'ElementalArea';

    /**
     * Don't show this model in campaign admin as part of implicit change sets
     *
     * @config
     * @var bool
     */
    private static $hide_in_campaigns = true;

    /**
     * Cache various data to improve CMS load time
     *
     * @internal
     * @var array
     */
    protected $cacheData = [];

    /**
     * @return array
     */
    public function supportedPageTypes()
    {
        $elementalClasses = [];

        foreach (ClassInfo::getValidSubClasses(DataObject::class) as $class) {
            if (Extensible::has_extension($class, ElementalAreasExtension::class)) {
                $elementalClasses[] = $class;
            }
        }

        return $elementalClasses;
    }

    /**
     * @return DBHTMLText
     */
    public function forTemplate()
    {
        return $this->renderWith(static::class);
    }

    /**
     * @param ArrayList $elements
     * @return $this
     */
    public function setElementsCached(ArrayList $elements)
    {
        $this->cacheData['elements'] = $elements;

        return $this;
    }

    /**
     * @param DataObject $page
     * @return $this
     */
    public function setOwnerPageCached(DataObject $page)
    {
        $this->cacheData['owner_page'] = $page;

        return $this;
    }

    /**
     * A cache-aware accessor for the elements
     * @return ArrayList|HasManyList|BaseElement[]
     */
    public function Elements()
    {
        if (isset($this->cacheData['elements'])) {
            return $this->cacheData['elements'];
        }

        return parent::Elements();
    }

    /**
     * Necessary to display results in CMS site search.
     *
     * @return DBField
     */
    public function Breadcrumbs()
    {
        $ownerClassName = $this->OwnerClassName;

        if ($owner = $ownerClassName::get()->filter('ElementalAreaID', $this->ID)->first()) {
            return DBField::create_field('HTMLText', sprintf(
                '<a href="%s">%s</a>',
                $owner->CMSEditLink(),
                $owner->Title
            ));
        }

        return null;
    }

    /**
     * Used in template instead of {@link Elements()} to wrap each element in
     * its' controller, making it easier to access and process form logic and
     * actions stored in {@link ElementController}.
     *
     * @return ArrayList
     * @throws \Exception
     */
    public function ElementControllers()
    {
        // Don't try and process unsaved lists
        if ($this->Elements() instanceof UnsavedRelationList) {
            return ArrayList::create();
        }

        $controllers = ArrayList::create();
        $items = $this->Elements()->filterByCallback(function (BaseElement $item) {
            return $item->canView();
        });

        if (!is_null($items)) {
            foreach ($items as $element) {
                $controller = $element->getController();
                $controllers->push($controller);
            }
        }

        return $controllers;
    }

    /**
     * @return null|DataObject
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function getOwnerPage()
    {
        // You can't find the owner page of a area that hasn't been save yet
        if (!$this->isInDB()) {
            return null;
        }

        // Allow for repeated calls to read from cache
        if (isset($this->cacheData['owner_page'])) {
            return $this->cacheData['owner_page'];
        }

        if ($this->OwnerClassName && ClassInfo::exists($this->OwnerClassName)) {
            $class = $this->OwnerClassName;
            $instance = Injector::inst()->get($class);
            if (!ClassInfo::hasMethod($instance, 'getElementalRelations')) {
                return null;
            }
            $elementalAreaRelations = $instance->getElementalRelations();

            foreach ($elementalAreaRelations as $eaRelationship) {
                $areaID = $eaRelationship . 'ID';

                $table = DataObject::getSchema()->tableForField($class, $areaID);
                $baseTable = DataObject::getSchema()->baseDataTable($class);
                $page = DataObject::get_one($class, [
                    "\"{$table}\".\"{$areaID}\" = ?"     => $this->ID,
                    "\"{$baseTable}\".\"ClassName\" = ?" => $class
                ]);

                if ($page) {
                    $this->setOwnerPageCached($page);
                    return $page;
                }
            }
        }

        foreach ($this->supportedPageTypes() as $class) {
            $instance = Injector::inst()->get($class);
            if (!ClassInfo::hasMethod($instance, 'getElementalRelations')) {
                return null;
            }

            $areaIDFilters = [];
            foreach ($instance->getElementalRelations() as $eaRelationship) {
                $areaIDFilters[$eaRelationship . 'ID'] = $this->ID;
            }

            try {
                $page = Versioned::get_by_stage($class, Versioned::DRAFT)->filterAny($areaIDFilters)->first();
            } catch (\Exception $ex) {
                // Usually this is catching cases where test stubs from other modules are trying to be loaded
                // and failing in unit tests.
                if (in_array(TestOnly::class, class_implements($class))) {
                    continue;
                }
                // Continue as normal...
                throw $ex;
            }

            if ($page) {
                if ($this->OwnerClassName !== $class) {
                    $this->OwnerClassName = $class;

                    // Avoid recursion: only write if it's already in the database
                    if ($this->isInDB()) {
                        $this->write();
                    }
                }

                $this->cacheData['area_relation_name'] = $page;
                return $page;
            }
        }

        return null;
    }

    /**
     * @param null $member
     * @return bool
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function canEdit($member = null)
    {
        if (parent::canEdit($member)) {
            return true;
        }

        $ownerPage = $this->getOwnerPage();
        if ($ownerPage !== null) {
            return $this->getOwnerPage()->canEdit($member);
        }

        return false;
    }

    /**
     * @param null $member
     * @return bool
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function canView($member = null)
    {
        if (parent::canEdit($member)) {
            return true;
        }

        $ownerPage = $this->getOwnerPage();
        if ($ownerPage !== null) {
            return $this->getOwnerPage()->canView($member);
        }

        return false;
    }
}
