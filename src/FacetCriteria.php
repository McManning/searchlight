<?php namespace Searchlight;

/**
 * Input criteria provided by an API request for a specific facet
 */
class FacetCriteria
{
    protected string $identifier;
    protected ?string $query;
    protected ?float $size;

    /** @var FilterCriteria[] */
    protected array $filterCriteria;

    public function __construct(string $identifier, ?string $query = null, ?float $size = null)
    {
        $this->identifier = $identifier;
        $this->query = $query;
        $this->size = $size;
        $this->filterCriteria = [];
    }

    /**
     * Convert query text into a regex string for Elastic
     *
     * Examples:
     *
     * - `"Al"` to `"([a-zA-Z]+ )+?[aA][lL].*"`
     * - `"foo/FIZz + buZZ?"` to `"([a-zA-Z]+ )+?[fF][oO][oO]\/FIZ[zZ] \+ [bB][uU]ZZ\?.*"`
     */
    public function getQueryAsRegex(): ?string
    {
        if (!$this->query) {
            return null;
        }

        $re = '';

        foreach (mb_str_split($this->query) as $char) {
            // Escape possible regex tokens
            $char = preg_quote($char, '/');

            // Unicode-safe type check for a lowercase letter
            if (preg_match('/\p{Ll}/u', $char)) {
                // Transform each lowercase character into [aA]
                $re .= '[' . $char . mb_strtoupper($char) . ']';
            } else {
                $re .= $char;
            }
        }

        $re .= '.*';
        if (strlen($re) > 2) {
            // TODO: Prefix unicode? I presume this exists to match "McManning"
            // in the string "Chase McManning" but is this the best way of doing that?
            $re = '([a-zA-Z]+ )+?' . $re;
        }

        return $re;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * Retrieve filtering criteria that has been applied
     * alongside this faceting criteria for the same identifier
     *
     * @return FilterCriteria[]
     */
    public function getFilterCriteria(): array
    {
        return $this->filterCriteria;
    }

    /**
     * @param FilterCriteria[]
     */
    public function setFilterCriteria(array $criteria)
    {
        $this->filterCriteria = $criteria;
    }
}

