<?php namespace Searchlight\Filters;

use Searchlight\Interfaces\IFilter;
use Searchlight\FilterCriteria;
use Searchlight\Exceptions\ConfigurationException;

/**
 * Apply a geo bounding box filter to a specific field when searching
 */
class GeoBoundingBoxFilter implements IFilter
{
    const DEFAULT_DISPLAY = 'GeoBoundingBoxFilter';

    protected string $identifier;
    protected string $label;
    protected string $display;
    protected string $field;

    public function __construct(array $args)
    {
        $this->identifier = data_get($args, 'identifier');
        $this->label = data_get($args, 'label');
        $this->display = data_get($args, 'display', self::DEFAULT_DISPLAY);
        $this->field = data_get($args, 'field');

        if (!$this->identifier) {
            throw new ConfigurationException(
                "GeoBoundingBoxFilter requires an 'identifier' argument"
            );
        }

        if (!$this->field) {
            throw new ConfigurationException(
                "GeoBoundingBoxFilter '{$this->identifier}' requires a 'field' argument"
            );
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * {@inheritDoc}
     */
    public function getFilters(array $criteria): array
    {
        $field = $this->field;

        // TODO: The reference implementation only supports a single
        // filter criteria. Should this be rewritten to support multiple?
        // https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/filters/GeoBoundingBoxFilter.ts#L23

        $corners = [];
        $bb = $criteria[0]->geoBoundingBox;
        if ($bb->topLeft) {
            $corners['top_left'] = $bb->topLeft;
        }
        if ($bb->bottomRight) {
            $corners['bottom_right'] = $bb->bottomRight;
        }
        if ($bb->bottomLeft) {
            $corners['bottom_left'] = $bb->bottomLeft;
        }
        if ($bb->topRight) {
            $corners['top_right'] = $bb->topRight;
        }

        return [
            'geo_bounding_box' => [
                [$field] => $corners
            ]
        ];
    }

    /**
     * Return a concrete `GeoBoundingBoxSelectedFilter` GraphQL type from response data
     *
     * @return array GraphQL type `GeoBoundingBoxSelectedFilter`
     */
    public function toSKSelectedFilter(FilterCriteria $criteria): array
    {
        $bb = $criteria->geoBoundingBox;
        return [
            'type' => 'GeoBoundingBoxSelectedFilter',

            'id' => $this->identifier.'_'.json_encode($bb),
            'identifier' => $this->identifier,
            'display' => $this->display,
            'label' => $this->label,

            'topLeft' => $bb->topLeft,
            'bottomRight' => $bb->bottomRight,
            'bottomLeft' => $bb->bottomLeft,
            'topRight' => $bb->topRight,
        ];
    }
}
