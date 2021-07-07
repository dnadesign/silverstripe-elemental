<?php

namespace DNADesign\Elemental\Extensions;

use DNADesign\Elemental\Forms\ElementalAreaField;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extensible;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * This extension handles most of the relationships between pages and element
 * area, it doesn't add an ElementArea to the page however. Because of this,
 * developers can add multiple {@link ElementArea} areas to to a page.
 *
 * If you want multiple ElementalAreas add them as has_ones, add this extensions
 * and MAKE SURE you don't forget to add ElementAreas to $owns, otherwise they
 * will never publish
 *
 * private static $has_one = array(
 *     'ElementalArea1' => ElementalArea::class,
 *     'ElementalArea2' => ElementalArea::class
 * );
 *
 * private static $owns = array(
 *     'ElementalArea1',
 *     'ElementalArea2'
 * );
 *
 * private static $cascade_duplicates = array(
 *     'ElementalArea1',
 *     'ElementalArea2'
 * );
 *
 * @package elemental
 */
class ElementalAreasExtension extends DataExtension
{
    use Extensible;

    /**
     * Classes to ignore adding elements to
     * @config
     * @var array $ignored_classes
     */
    private static $ignored_classes = [];

    /**
     * On saving the element area, should Elemental reset the main website
     * `$Content` field.
     *
     * @config
     * @var boolean
     */
    private static $clear_contentfield = false;

    /**
     * Whether to sort the elements alphabetically by their title
     *
     * @config
     * @var boolean
     */
    private static $sort_types_alphabetically = true;

    /**
     * Whether or not to replace the default SiteTree content field
     * Applies globally, across all page types; unless a page type overrides this with its own config setting of
     * `elemental_keep_content_field`
     *
     * @var boolean
     * @config
     */
    private static $keep_content_fields = false;

    /**
     * Get the available element types for this page type,
     *
     * Uses allowed_elements, stop_element_inheritance, disallowed_elements in
     * order to get to correct list.
     *
     * @return array
     */
    public function getElementalTypes()
    {
        $config = $this->owner->config();

        if (is_array($config->get('allowed_elements'))) {
            if ($config->get('stop_element_inheritance')) {
                $availableClasses = $config->get('allowed_elements', Config::UNINHERITED);
            } else {
                $availableClasses = $config->get('allowed_elements');
            }
        } else {
            $availableClasses = ClassInfo::subclassesFor(BaseElement::class);
        }

        if ($config->get('stop_element_inheritance')) {
            $disallowedElements = (array) $config->get('disallowed_elements', Config::UNINHERITED);
        } else {
            $disallowedElements = (array) $config->get('disallowed_elements');
        }
        $list = [];

        foreach ($availableClasses as $availableClass) {
            /** @var BaseElement $inst */
            $inst = singleton($availableClass);

            if (!in_array($availableClass, $disallowedElements) && $inst->canCreate()) {
                if ($inst->hasMethod('canCreateElement') && !$inst->canCreateElement()) {
                    continue;
                }

                $list[$availableClass] = $inst->getType();
            }
        }

        if ($config->get('sort_types_alphabetically') !== false) {
            asort($list);
        }

        if (isset($list[BaseElement::class])) {
            unset($list[BaseElement::class]);
        }

        $class = get_class($this->owner);
        $this->owner->invokeWithExtensions('updateAvailableTypesForClass', $class, $list);

        return $list;
    }

    /**
     * Returns an array of the relation names to ElementAreas. Ignores any
     * has_one fields named `Parent` as that would indicate that this is child
     * of an existing area
     *
     * @return array
     */
    public function getElementalRelations()
    {
        $hasOnes = $this->owner->hasOne();

        if (!$hasOnes) {
            return false;
        }

        $elementalAreaRelations = [];

        foreach ($hasOnes as $hasOneName => $hasOneClass) {
            if ($hasOneName === 'Parent' || $hasOneName === 'ParentID') {
                continue;
            }

            if ($hasOneClass == ElementalArea::class || is_subclass_of($hasOneClass, ElementalArea::class)) {
                $elementalAreaRelations[] = $hasOneName;
            }
        }

        return $elementalAreaRelations;
    }

    /**
     * Setup the CMS Fields
     *
     * @param FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->supportsElemental()) {
            return;
        }

        // add an empty holder for content as some module explicitly use insert after content
        $globalReplace = !Config::inst()->get(self::class, 'keep_content_fields');
        $classOverride = Config::inst()->get(get_class($this->owner), 'elemental_keep_content_field');
        if ($globalReplace && !$classOverride || $classOverride === false) {
            $fields->replaceField('Content', new LiteralField('Content', ''));
        }
        $elementalAreaRelations = $this->owner->getElementalRelations();

        foreach ($elementalAreaRelations as $eaRelationship) {
            $key = $eaRelationship . 'ID';

            // remove the scaffold dropdown
            $fields->removeByName($key);

            // remove the field, but don't add anything.
            if (!$this->owner->isInDb()) {
                continue;
            }

            // Example: $eaRelationship = 'ElementalArea';
            $area = $this->owner->$eaRelationship();

            $editor = ElementalAreaField::create($eaRelationship, $area, $this->getElementalTypes());

            $insertBefore = Config::inst()->get(get_class($this->owner), 'insert_before_field_name');
            
            if ($insertBefore && $fields->dataFieldByName($insertBefore)) {
                $fields->insertBefore($insertBefore, $editor);
            } else if ($this->owner instanceof SiteTree && $fields->findOrMakeTab('Root.Main')->fieldByName('Metadata')) {
                $fields->addFieldToTab('Root.Main', $editor, 'Metadata');
            } else {
                $fields->addFieldToTab('Root.Main', $editor);
            }
        }

        return $fields;
    }

    /**
     * Make sure there is always an ElementalArea for adding Elements
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->supportsElemental()) {
            return;
        }

        $elementalAreaRelations = $this->owner->getElementalRelations();

        $this->ensureElementalAreasExist($elementalAreaRelations);

        $ownerClassName = get_class($this->owner);

        // Update the OwnerClassName on EA if the class has changed
        foreach ($elementalAreaRelations as $eaRelation) {
            $ea = $this->owner->$eaRelation();
            if ($ea->OwnerClassName !== $ownerClassName) {
                $ea->OwnerClassName = $ownerClassName;
                $ea->write();
            }
        }

        if (Config::inst()->get(self::class, 'clear_contentfield')) {
            $this->owner->Content = '';
        }
    }

    /**
     * @return boolean
     */
    public function supportsElemental()
    {
        if ($this->owner->hasMethod('includeElemental')) {
            $res = $this->owner->includeElemental();

            if ($res !== null) {
                return $res;
            }
        }

        if (is_a($this->owner, RedirectorPage::class) || is_a($this->owner, VirtualPage::class)) {
            return false;
        } elseif ($ignored = Config::inst()->get(ElementalPageExtension::class, 'ignored_classes')) {
            foreach ($ignored as $check) {
                if (is_a($this->owner, $check)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Set all has_one relationships to an ElementalArea to a valid ID if they're unset
     *
     * @param array $elementalAreaRelations indexed array of relationship names that are to ElementalAreas
     * @return DataObject
     */
    public function ensureElementalAreasExist($elementalAreaRelations)
    {
        foreach ($elementalAreaRelations as $eaRelationship) {
            $areaID = $eaRelationship . 'ID';

            if (!$this->owner->$areaID) {
                $area = ElementalArea::create();
                $area->OwnerClassName = get_class($this->owner);
                $area->write();
                $this->owner->$areaID = $area->ID;
            }
        }
        return $this->owner;
    }

    /**
     * Extension hook {@see DataObject::requireDefaultRecords}
     *
     * @return void
     */
    public function requireDefaultRecords()
    {
        if (!$this->supportsElemental()) {
            return;
        }

        $this->owner->extend('onBeforeRequireDefaultElementalRecords');

        $ownerClass = get_class($this->owner);
        $elementalAreas = $this->owner->getElementalRelations();
        $schema = $this->owner->getSchema();

        // There is no inbuilt filter for null values
        $where = [];
        foreach ($elementalAreas as $areaName) {
            $queryDetails = $schema->sqlColumnForField($ownerClass, $areaName . 'ID');
            $where[] = $queryDetails . ' IS NULL OR ' . $queryDetails . ' = 0' ;
        }

        $records = $ownerClass::get()->where(implode(' OR ', $where));
        if ($ignored_classes = Config::inst()->get(ElementalPageExtension::class, 'ignored_classes')) {
            $records = $records->exclude('ClassName', $ignored_classes);
        }

        foreach ($records as $elementalObject) {
            if ($elementalObject->hasMethod('includeElemental')) {
                $res = $elementalObject->includeElemental();
                if ($res === false) {
                    continue;
                }
            }

            $needsPublishing = Extensible::has_extension($elementalObject, Versioned::class)
                && $elementalObject->isPublished();

            /** @var ElementalAreasExtension $elementalObject */
            $elementalObject->ensureElementalAreasExist($elementalAreas);
            $elementalObject->write();
            if ($needsPublishing) {
                $elementalObject->publishRecursive();
            }
        }

        $this->owner->extend('onAfterRequireDefaultElementalRecords');
    }
}
